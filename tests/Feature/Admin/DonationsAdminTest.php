<?php

use App\Enums\DonationStatus;
use App\Jobs\SendDonationReceipt;
use App\Mail\DonationReceipt;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function adminUser(): User
{
    return User::factory()->admin()->create();
}

describe('access', function () {
    test('editors cannot see donors or donations', function () {
        $editor = User::factory()->create();
        $donation = Donation::factory()->succeeded()->create();

        $this->actingAs($editor)->get(route('donations.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('donations.show', $donation))->assertForbidden();
        $this->actingAs($editor)->get(route('donations.receipt', $donation))->assertForbidden();
        $this->actingAs($editor)->get(route('donations.export'))->assertForbidden();
        $this->actingAs($editor)->post(route('donations.resend', $donation))->assertForbidden();
    });

    test('guests are sent to the login page', function () {
        $this->get(route('donations.index'))->assertRedirect();
    });
});

describe('list', function () {
    test('filters by status, search, dates and real/test, and totals what was received', function () {
        Donation::factory()->succeeded()->live()->create(['donor_first_name' => 'Real', 'amount_cents' => 10000, 'created_at' => '2026-10-05 12:00:00']);
        Donation::factory()->succeeded()->live()->create(['donor_first_name' => 'Refunded', 'amount_cents' => 5000, 'refunded_cents' => 2000, 'status' => DonationStatus::PartiallyRefunded, 'created_at' => '2026-10-06 12:00:00']);
        Donation::factory()->succeeded()->create(['donor_first_name' => 'Tester', 'amount_cents' => 2500]);
        Donation::factory()->create(['donor_first_name' => 'Waiting']);
        Donation::factory()->create(['donor_first_name' => 'Declined', 'status' => DonationStatus::Failed]);

        $admin = adminUser();

        $this->actingAs($admin)->get(route('donations.index', ['mode' => 'real']))
            ->assertInertia(fn (Assert $page) => $page->component('donations/index')
                ->has('donations.data', 2)
                ->where('totals.cents', 13000)   // 10,000 + (5,000 - 2,000 refunded)
                ->where('totals.gifts', 2));

        $this->actingAs($admin)->get(route('donations.index', ['view' => 'pending']))
            ->assertInertia(fn (Assert $page) => $page->has('donations.data', 1)->where('donations.data.0.donor', fn (string $d) => str_starts_with($d, 'Waiting')));

        $this->actingAs($admin)->get(route('donations.index', ['search' => 'Declined']))->assertInertia(fn (Assert $page) => $page->has('donations.data', 1));
        $this->actingAs($admin)->get(route('donations.index', ['from' => '2026-10-06', 'to' => '2026-10-06', 'mode' => 'real']))->assertInertia(fn (Assert $page) => $page->has('donations.data', 1));
        $this->actingAs($admin)->get(route('donations.index', ['view' => 'refunded']))->assertInertia(fn (Assert $page) => $page->has('donations.data', 1));
    });

    test('an invalid date or view is ignored rather than breaking the page', function () {
        $this->actingAs(adminUser())->get(route('donations.index', ['from' => 'yesterday', 'view' => 'nonsense']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.view', 'all')->where('filters.from', ''));
    });
});

describe('detail, receipt and resend', function () {
    test('shows the donor, payment and audit trail', function () {
        $donation = Donation::factory()->succeeded()->create(['donor_first_name' => 'Amrit', 'event_title' => 'Punjab Flood Relief 2025']);
        $donation->trail()->create(['type' => 'charge_succeeded']);

        $this->actingAs(adminUser())->get(route('donations.show', $donation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('donations/show')
                ->where('donation.event', 'Punjab Flood Relief 2025')
                ->where('donation.card', 'Visa •••• 4242')
                ->where('donation.can_resend', true)
                ->has('donation.trail', 1));
    });

    test('previews the receipt as the donor sees it', function () {
        $donation = Donation::factory()->succeeded()->live()->create([
            'donor_first_name' => 'Amrit', 'donor_last_name' => 'Singh', 'event_title' => 'Punjab Flood Relief 2025',
            'amount_cents' => 5000, 'address_line1' => '123 Main Street', 'city' => 'Kelowna', 'province' => 'BC', 'postal_code' => 'V1X 2A1',
            'paid_at' => '2025-10-05 20:00:00', 'receipt_number' => '2025-000042',
        ]);

        $html = $this->actingAs(adminUser())->get(route('donations.receipt', $donation))->assertOk()->getContent();

        expect($html)
            ->toContain('Official receipt for income tax purposes')
            ->toContain('Canco Cares Foundation')
            ->toContain('125 Hwy 33 E, Kelowna, BC V1X 2A1')
            ->toContain('79021 4621 RR0001')
            ->toContain('Punjab Flood Relief 2025')
            ->toContain('2025-000042')
            ->toContain('Sunday, October 5, 2025')
            ->toContain('Amrit Singh')
            ->toContain('123 Main Street')
            ->toContain('Donation $ (Amount: 50.00 CAD)')
            ->toContain('Simran Kaur')
            ->toContain('Authorized Signature')
            ->not->toContain('not valid for tax purposes');
    });

    test('a test donation\'s receipt says it is not valid for tax purposes', function () {
        $donation = Donation::factory()->succeeded()->create(['is_test' => true]);

        $this->actingAs(adminUser())->get(route('donations.receipt', $donation))->assertSee('not valid for tax purposes');
    });

    test('escapes anything a donor typed', function () {
        $donation = Donation::factory()->succeeded()->create(['donor_first_name' => '<script>alert(1)</script>']);

        $html = $this->actingAs(adminUser())->get(route('donations.receipt', $donation))->getContent();

        expect($html)->not->toContain('<script>alert(1)')->toContain('&lt;script&gt;');
    });

    test('there is no receipt before payment', function () {
        $this->actingAs(adminUser())->get(route('donations.receipt', Donation::factory()->create()))->assertNotFound();
    });

    test('a receipt can be sent again, but only for a paid donation', function () {
        Queue::fake();
        $paid = Donation::factory()->succeeded()->create();
        $pending = Donation::factory()->create();

        $this->actingAs(adminUser())->post(route('donations.resend', $paid))->assertRedirect();
        $this->actingAs(adminUser())->post(route('donations.resend', $pending))->assertNotFound();

        Queue::assertPushed(SendDonationReceipt::class, 1);
    });

    test('the receipt email carries the receipt and goes to the donor', function () {
        Mail::fake();
        $donation = Donation::factory()->succeeded()->create(['donor_email' => 'amrit@example.com']);

        (new SendDonationReceipt($donation->id))->handle();

        Mail::assertSent(DonationReceipt::class, function (DonationReceipt $mail) use ($donation) {
            $mail->assertSeeInHtml('Official receipt for income tax purposes');
            $mail->assertSeeInText($donation->receipt_number);

            return $mail->hasTo('amrit@example.com') && str_contains((string) $mail->envelope()->subject, $donation->receipt_number);
        });
        expect($donation->fresh()->receipt_sent_at)->not->toBeNull();
    });

    test('no receipt is ever sent for a pending or failed donation', function () {
        Mail::fake();
        $pending = Donation::factory()->create();
        $failed = Donation::factory()->create(['status' => DonationStatus::Failed]);

        (new SendDonationReceipt($pending->id))->handle();
        (new SendDonationReceipt($failed->id))->handle();

        Mail::assertNothingSent();
    });
});

describe('CSV export', function () {
    test('exports the filtered donations with their receipt numbers', function () {
        Donation::factory()->succeeded()->create(['donor_first_name' => 'Amrit', 'receipt_number' => '2026-000007']);
        Donation::factory()->create(['donor_first_name' => 'Pending']);

        $csv = $this->actingAs(adminUser())->get(route('donations.export', ['view' => 'succeeded']))->assertOk()->streamedContent();

        expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('Reference,Date,Status')->toContain('2026-000007')->toContain('Amrit')->not->toContain('Pending');
    });
});
