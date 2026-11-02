<?php

use App\Enums\DonationStatus;
use App\Models\Donation;
use App\Models\Inquiry;
use App\Models\Story;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

describe('dashboard figures', function () {
    test('an editor sees content and inbox figures but never any money', function () {
        Story::factory()->count(2)->create();
        Story::factory()->draft()->create();
        Story::factory()->scheduled()->create();
        Inquiry::factory()->count(3)->create();
        Donation::factory()->succeeded()->live()->create(['amount_cents' => 12345]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('stories.published', 2)
                ->where('stories.drafts', 1)
                ->where('stories.scheduled', 1)
                ->has('stories.recent', 4)
                ->where('inquiries.new', 3)
                ->where('donations', null)
                ->where('system', null)
            );
    });

    test('an administrator sees what has been raised, net of refunds, and never counts test gifts', function () {
        Donation::factory()->succeeded()->live()->create(['amount_cents' => 10000, 'refunded_cents' => 2500, 'paid_at' => now()]);
        Donation::factory()->succeeded()->live()->create(['amount_cents' => 5000, 'paid_at' => now()->subDays(2)]);
        Donation::factory()->succeeded()->create(['amount_cents' => 99900, 'paid_at' => now()]);
        Donation::factory()->create(['status' => DonationStatus::Failed]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('donations.total_cents', 12500)
                ->where('donations.gifts', 2)
                ->where('donations.average_cents', 6250)
                ->where('donations.test_count', 2)
                ->where('donations.failed_week', 1)
                ->has('donations.chart.series', 30)
                ->has('donations.recent', 4)
                ->has('system.queue_failed')
            );
    });

    test('the recent gifts list shows a first name and an initial, not the donor’s full name', function () {
        Donation::factory()->succeeded()->create(['donor_first_name' => 'Amrit', 'donor_last_name' => 'Sandhu']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('donations.recent.0.donor', 'Amrit S.'));
    });
});
