<?php

use App\Contracts\DonationGateway;
use App\Enums\DonationStatus;
use App\Jobs\RevalidateFrontend;
use App\Mail\DonationReceipt;
use App\Models\Donation;
use App\Models\Event;
use App\Services\Donations\FakeGateway;
use App\Services\Donations\GatewayException;
use App\Services\Donations\GatewayResult;
use App\Services\Settings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const DONATION_KEY = ['X-Api-Key' => 'test-key-123'];

beforeEach(function () {
    config(['services.frontend.api_key' => 'test-key-123', 'services.donations.gateway' => 'fake']);
    app(Settings::class)->set('donations.enabled', true);
    Mail::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function donationPayload(array $overrides = []): array
{
    return [
        'amount_cents' => 5000,
        'currency' => 'CAD',
        'first_name' => 'Amrit',
        'last_name' => 'Singh',
        'email' => 'Amrit@Example.com',
        'phone' => '250-555-0100',
        'address_line1' => '123 Main Street',
        'city' => 'Kelowna',
        'province' => 'bc',
        'postal_code' => 'v1x2a1',
        'consent' => true,
        'idempotency_key' => (string) Str::uuid(),
        ...$overrides,
    ];
}

function startDonation(array $overrides = []): array
{
    return test()->postJson('/api/v1/donations', donationPayload($overrides), DONATION_KEY)->json('data');
}

describe('GET /donation-options', function () {
    test('lists open giving, amounts and only the events switched on for the form', function () {
        Event::factory()->create(['slug' => 'flood-relief', 'title' => 'Punjab Flood Relief 2025', 'show_in_donation_form' => true]);
        Event::factory()->create(['slug' => 'switched-off', 'show_in_donation_form' => false]);
        Event::factory()->draft()->create(['slug' => 'draft', 'show_in_donation_form' => true]);
        Event::factory()->cancelled()->create(['slug' => 'cancelled', 'show_in_donation_form' => true]);
        Event::factory()->create(['slug' => 'trashed', 'show_in_donation_form' => true])->delete();

        $this->getJson('/api/v1/donation-options')->assertOk()->assertJson(['data' => [
            'enabled' => true,
            'currency' => 'CAD',
            'presets_cents' => [2500, 5000, 10000, 25000],
            'min_cents' => 500,
            'test_mode' => true,
            'events' => [['slug' => 'flood-relief', 'title' => 'Punjab Flood Relief 2025']],
        ]]);
    });

    test('a past event can still be offered (the administrator decides)', function () {
        Event::factory()->past()->create(['slug' => 'old-campaign', 'show_in_donation_form' => true]);

        $this->getJson('/api/v1/donation-options')->assertJsonPath('data.events.0.slug', 'old-campaign');
    });

    test('is closed when the setting is off or no usable gateway exists', function () {
        app(Settings::class)->set('donations.enabled', false);
        $this->getJson('/api/v1/donation-options')->assertJsonPath('data.enabled', false);

        app(Settings::class)->set('donations.enabled', true);
        config(['services.donations.gateway' => null]);
        $this->getJson('/api/v1/donation-options')->assertJsonPath('data.enabled', false);
    });

    test('the fake gateway never counts as available in production', function () {
        $this->app->detectEnvironment(fn () => 'production');

        $this->getJson('/api/v1/donation-options')->assertJsonPath('data.enabled', false);

        expect(fn () => new FakeGateway)->toThrow(LogicException::class);
    });
});

describe('POST /donations', function () {
    test('needs the frontend key', function () {
        $this->postJson('/api/v1/donations', donationPayload())->assertUnauthorized();
        expect(Donation::count())->toBe(0);
    });

    test('is refused while donations are closed', function () {
        app(Settings::class)->set('donations.enabled', false);

        $this->postJson('/api/v1/donations', donationPayload(), DONATION_KEY)->assertForbidden();
        expect(Donation::count())->toBe(0);
    });

    test('creates a pending donation with the receipt address and a payment session', function () {
        $response = $this->postJson('/api/v1/donations', donationPayload(), DONATION_KEY)->assertCreated();

        $donation = Donation::firstOrFail();

        expect($response->json('data'))->toMatchArray([
            'reference' => $donation->reference,
            'status' => 'pending',
            'amount_cents' => 5000,
            'currency' => 'CAD',
            'gateway' => ['provider' => 'fake', 'test_mode' => true],
        ])->and($donation->status)->toBe(DonationStatus::Pending)
            ->and($donation->donor_email)->toBe('amrit@example.com')
            ->and($donation->province)->toBe('BC')
            ->and($donation->postal_code)->toBe('V1X 2A1')
            ->and($donation->is_test)->toBeTrue()
            ->and($donation->receipt_number)->toBeNull()
            ->and($donation->trail->pluck('type')->all())->toBe(['created', 'session_created']);
    });

    test('records the chosen event and keeps its title for the receipt', function () {
        $event = Event::factory()->create(['slug' => 'flood-relief', 'title' => 'Punjab Flood Relief 2025', 'show_in_donation_form' => true]);

        $this->postJson('/api/v1/donations', donationPayload(['event' => 'flood-relief']), DONATION_KEY)->assertCreated();

        $event->update(['title' => 'Renamed later']);

        expect(Donation::firstOrFail())->event_id->toBe($event->id)->event_title->toBe('Punjab Flood Relief 2025');
    });

    test('an event that is not offered in the form is refused', function () {
        Event::factory()->create(['slug' => 'hidden', 'show_in_donation_form' => false]);

        $this->postJson('/api/v1/donations', donationPayload(['event' => 'hidden']), DONATION_KEY)
            ->assertUnprocessable()->assertJsonValidationErrors('event');
        $this->postJson('/api/v1/donations', donationPayload(['event' => 'does-not-exist']), DONATION_KEY)
            ->assertUnprocessable()->assertJsonValidationErrors('event');
    });

    test('the same idempotency key returns the same donation, once', function () {
        $payload = donationPayload();

        $first = $this->postJson('/api/v1/donations', $payload, DONATION_KEY)->assertCreated();
        $second = $this->postJson('/api/v1/donations', $payload, DONATION_KEY)->assertOk();

        expect($second->json('data.reference'))->toBe($first->json('data.reference'))->and(Donation::count())->toBe(1);
    });

    test('validation', function (string $field, mixed $value) {
        $this->postJson('/api/v1/donations', donationPayload([$field => $value]), DONATION_KEY)
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(Donation::count())->toBe(0);
    })->with([
        'below the minimum' => ['amount_cents', 499],
        'zero' => ['amount_cents', 0],
        'negative' => ['amount_cents', -5000],
        'over the maximum' => ['amount_cents', 1_000_001],
        'not a whole number of cents' => ['amount_cents', 50.5],
        'a word for an amount' => ['amount_cents', 'fifty'],
        'another currency' => ['currency', 'USD'],
        'no first name' => ['first_name', ''],
        'no last name' => ['last_name', ''],
        'bad email' => ['email', 'nope'],
        'no street address' => ['address_line1', ''],
        'no city' => ['city', ''],
        'bad province' => ['province', 'British Columbia'],
        'bad postal code' => ['postal_code', '90210'],
        'no consent' => ['consent', false],
        'no idempotency key' => ['idempotency_key', ''],
        'idempotency key not a uuid' => ['idempotency_key', 'abc'],
        'honeypot filled' => ['website', 'http://spam.example'],
    ]);

    test('the minimum follows the administrator setting', function () {
        app(Settings::class)->set('donations.min_cents', 2500);

        $this->postJson('/api/v1/donations', donationPayload(['amount_cents' => 2000]), DONATION_KEY)->assertUnprocessable()->assertJsonValidationErrors('amount_cents');
        $this->postJson('/api/v1/donations', donationPayload(['amount_cents' => 2500]), DONATION_KEY)->assertCreated();
    });

    test('is rate limited per visitor', function () {
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/v1/donations', donationPayload(), [...DONATION_KEY, 'X-Forwarded-For' => '203.0.113.7'])->assertSuccessful();
        }

        $this->postJson('/api/v1/donations', donationPayload(), [...DONATION_KEY, 'X-Forwarded-For' => '203.0.113.7'])->assertStatus(429);
    });
});

describe('POST /donations/{reference}/confirm', function () {
    test('an approved payment succeeds, issues a receipt and emails it', function () {
        Queue::fake([RevalidateFrontend::class]);
        $reference = startDonation()['reference'];

        $response = $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::APPROVE], DONATION_KEY)->assertOk();

        $donation = Donation::firstOrFail();

        expect($response->json('data.status'))->toBe('succeeded')
            ->and($donation->status)->toBe(DonationStatus::Succeeded)
            ->and($donation->paid_at)->not->toBeNull()
            ->and($donation->receipt_number)->toBe(now('America/Vancouver')->format('Y').'-'.str_pad((string) $donation->id, 6, '0', STR_PAD_LEFT))
            ->and($donation->card_last4)->toBe('4242')
            ->and($donation->receipt_sent_at)->not->toBeNull()
            ->and($donation->trail->pluck('type')->all())->toContain('charge_attempted', 'charge_succeeded');

        Mail::assertSent(DonationReceipt::class, fn (DonationReceipt $mail) => $mail->hasTo('amrit@example.com'));
        Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job) => $job->tags === ['settings']);
    });

    test('a declined card fails with a friendly reason and no receipt', function () {
        $reference = startDonation()['reference'];

        $response = $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::DECLINE], DONATION_KEY)->assertOk();

        $donation = Donation::firstOrFail();

        expect($response->json('data.status'))->toBe('failed')
            ->and($response->json('data.failure_reason'))->toContain('declined')
            ->and($donation->receipt_number)->toBeNull();
        Mail::assertNothingSent();
    });

    test('an unrecognised token fails rather than succeeding', function () {
        $reference = startDonation()['reference'];

        $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => 'anything'], DONATION_KEY)
            ->assertOk()->assertJsonPath('data.status', 'failed');
    });

    test('a client-sent amount is ignored: the stored amount is what counts', function () {
        $reference = startDonation(['amount_cents' => 5000])['reference'];

        $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::APPROVE, 'amount_cents' => 1, 'currency' => 'USD'], DONATION_KEY)->assertOk();

        expect(Donation::firstOrFail()->amount_cents)->toBe(5000);
    });

    test('confirming twice never charges twice', function () {
        $charges = new ArrayObject(['n' => 0]);
        $this->app->bind(DonationGateway::class, fn () => new class($charges) extends FakeGateway
        {
            public function __construct(private ArrayObject $charges)
            {
                parent::__construct();
            }

            public function charge(Donation $donation, string $paymentToken): GatewayResult
            {
                $this->charges['n']++;

                return parent::charge($donation, $paymentToken);
            }
        });
        $reference = startDonation()['reference'];

        $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::APPROVE], DONATION_KEY)->assertOk();
        $again = $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::APPROVE], DONATION_KEY)->assertStatus(409);

        expect($charges['n'])->toBe(1)->and($again->json('data.status'))->toBe('succeeded')->and(Donation::count())->toBe(1);
    });

    test('a failed donation cannot be revived: the donor starts a new attempt', function () {
        $reference = startDonation()['reference'];
        $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::DECLINE], DONATION_KEY)->assertOk();

        $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::APPROVE], DONATION_KEY)
            ->assertStatus(409)->assertJsonPath('data.status', 'failed');
    });

    test('an unreachable provider leaves the donation pending and says so', function () {
        $this->app->bind(DonationGateway::class, fn () => new class extends FakeGateway
        {
            public function charge(Donation $donation, string $paymentToken): GatewayResult
            {
                throw new GatewayException('timeout');
            }
        });
        $reference = startDonation()['reference'];

        $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::APPROVE], DONATION_KEY)->assertStatus(502);

        expect(Donation::firstOrFail()->status)->toBe(DonationStatus::Pending)
            ->and(Donation::firstOrFail()->trail->pluck('type')->all())->toContain('charge_error');
    });

    test('needs a token, the key and a real reference', function () {
        $reference = startDonation()['reference'];

        $this->postJson("/api/v1/donations/{$reference}/confirm", [], DONATION_KEY)->assertUnprocessable();
        $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::APPROVE])->assertUnauthorized();
        $this->postJson('/api/v1/donations/'.Str::uuid().'/confirm', ['payment_token' => FakeGateway::APPROVE], DONATION_KEY)->assertNotFound();
        $this->postJson('/api/v1/donations/not-a-uuid/confirm', ['payment_token' => FakeGateway::APPROVE], DONATION_KEY)->assertNotFound();
    });

    test('is limited to five attempts a minute per donation', function () {
        $reference = startDonation()['reference'];

        foreach (range(1, 5) as $i) {
            $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => 'x'], DONATION_KEY);
        }

        $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => 'x'], DONATION_KEY)->assertStatus(429);
    });

    test('no card data, token or secret is written to the audit trail', function () {
        $reference = startDonation()['reference'];
        $this->postJson("/api/v1/donations/{$reference}/confirm", ['payment_token' => FakeGateway::APPROVE], DONATION_KEY);

        $dump = json_encode(Donation::firstOrFail()->trail->map->payload->all());

        expect($dump)->not->toContain(FakeGateway::APPROVE)->not->toContain('4242');
    });
});

describe('GET /donations/{reference}', function () {
    test('shows a minimal status with no personal or gateway details', function () {
        $donation = Donation::factory()->succeeded()->create();

        $response = $this->getJson("/api/v1/donations/{$donation->reference}")->assertOk();

        expect(array_keys($response->json('data')))->toBe(['reference', 'status', 'amount_cents', 'currency', 'receipt_number', 'failure_reason', 'paid_at', 'donor_first_name']);
        expect($response->getContent())->not->toContain($donation->donor_email)->not->toContain('123 Main')->not->toContain('4242');
        $response->assertHeader('Cache-Control');
    });

    test('is never cached', function () {
        $donation = Donation::factory()->create();

        expect($this->getJson("/api/v1/donations/{$donation->reference}")->headers->get('Cache-Control'))->toContain('no-store');
    });

    test('404s for an unknown reference', function () {
        $this->getJson('/api/v1/donations/'.Str::uuid())->assertNotFound();
    });
});

describe('state machine', function () {
    test('only legal moves are allowed', function () {
        expect(DonationStatus::Pending->canBecome(DonationStatus::Succeeded))->toBeTrue()
            ->and(DonationStatus::Succeeded->canBecome(DonationStatus::Pending))->toBeFalse()
            ->and(DonationStatus::Succeeded->canBecome(DonationStatus::Failed))->toBeFalse()
            ->and(DonationStatus::Failed->canBecome(DonationStatus::Succeeded))->toBeFalse()
            ->and(DonationStatus::Succeeded->canBecome(DonationStatus::Refunded))->toBeTrue()
            ->and(DonationStatus::Refunded->next())->toBe([]);
    });
});
