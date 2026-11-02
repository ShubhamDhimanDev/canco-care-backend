<?php

use App\Contracts\BotVerifier;
use App\Enums\InquiryStatus;
use App\Mail\InquiryAutoReply;
use App\Mail\InquiryReceived;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Services\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

const KEY = ['X-Api-Key' => 'test-key-123'];

beforeEach(function () {
    config(['services.frontend.api_key' => 'test-key-123']);
    Mail::fake();
    RateLimiter::clear('x');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inquiryPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'Ada@Example.com',
        'phone' => '(403) 555-1234',
        'message' => 'I would like to learn more about partnering with Canco Cares.',
        'consent' => true,
        'source_url' => 'https://cancocares.ca/contact-us',
        ...$overrides,
    ];
}

describe('GET /api/v1/inquiry-types', function () {
    test('lists active types in the admin order, without their email addresses', function () {
        InquiryType::factory()->create(['name' => 'Media', 'slug' => 'media', 'sort_order' => 2, 'email' => 'media@x.ca']);
        InquiryType::factory()->create(['name' => 'Partnerships', 'slug' => 'partnerships', 'sort_order' => 1, 'email' => 'p@x.ca']);
        InquiryType::factory()->inactive()->create(['name' => 'Old', 'slug' => 'old']);

        $response = $this->getJson('/api/v1/inquiry-types')->assertOk();

        expect($response->json('data'))->toBe([
            ['slug' => 'partnerships', 'name' => 'Partnerships'],
            ['slug' => 'media', 'name' => 'Media'],
        ]);
        expect($response->getContent())->not->toContain('@x.ca');
    });
});

describe('POST /api/v1/inquiries', function () {
    test('needs the frontend key', function () {
        $this->postJson('/api/v1/inquiries', inquiryPayload())->assertUnauthorized();
        $this->postJson('/api/v1/inquiries', inquiryPayload(), ['X-Api-Key' => 'wrong'])->assertUnauthorized();
        expect(Inquiry::count())->toBe(0);
    });

    test('stores the inquiry and returns its reference', function () {
        $response = $this->postJson('/api/v1/inquiries', inquiryPayload(), KEY)->assertCreated();

        $inquiry = Inquiry::firstOrFail();

        expect($response->json('data.reference'))->toBe($inquiry->reference)->toHaveLength(26)
            ->and($inquiry->email)->toBe('ada@example.com')
            ->and($inquiry->phone)->toBe('+14035551234')
            ->and($inquiry->status)->toBe(InquiryStatus::New)
            ->and($inquiry->consent_at)->not->toBeNull()
            ->and($inquiry->ip_hash)->toHaveLength(64);
    });

    test('emails the address of the chosen type and sends the sender an auto-reply', function () {
        InquiryType::factory()->create(['name' => 'Partnerships', 'slug' => 'partnerships', 'email' => 'partners@cancocares.ca']);
        InquiryType::factory()->create(['name' => 'Media', 'slug' => 'media', 'email' => 'media@cancocares.ca']);

        $this->postJson('/api/v1/inquiries', inquiryPayload(['inquiry_type' => 'partnerships']), KEY)->assertCreated();

        Mail::assertQueued(InquiryReceived::class, fn (InquiryReceived $mail) => $mail->hasTo('partners@cancocares.ca') && ! $mail->hasTo('media@cancocares.ca') && $mail->hasReplyTo('ada@example.com'));
        Mail::assertQueued(InquiryAutoReply::class, fn (InquiryAutoReply $mail) => $mail->hasTo('ada@example.com'));

        $inquiry = Inquiry::firstOrFail();
        expect($inquiry->type_name)->toBe('Partnerships')->and($inquiry->notified_to)->toBe('partners@cancocares.ca');
    });

    test('falls back to the configured addresses when the topic has none', function () {
        app(Settings::class)->set('inquiries.fallback_emails', ['hello@cancocares.ca', 'board@cancocares.ca']);

        $this->postJson('/api/v1/inquiries', inquiryPayload(), KEY)->assertCreated();

        Mail::assertQueued(InquiryReceived::class, fn (InquiryReceived $mail) => $mail->hasTo('hello@cancocares.ca') && $mail->hasTo('board@cancocares.ca'));
    });

    test('is still stored when nobody is configured to receive it', function () {
        $this->postJson('/api/v1/inquiries', inquiryPayload(), KEY)->assertCreated();

        expect(Inquiry::count())->toBe(1);
        Mail::assertNotQueued(InquiryReceived::class);
        Mail::assertQueued(InquiryAutoReply::class);
    });

    test('a type is required once any are offered, and must be an active one', function () {
        InquiryType::factory()->create(['slug' => 'media']);
        InquiryType::factory()->inactive()->create(['slug' => 'retired']);

        $this->postJson('/api/v1/inquiries', inquiryPayload(), KEY)->assertUnprocessable()->assertJsonValidationErrors('inquiry_type');
        $this->postJson('/api/v1/inquiries', inquiryPayload(['inquiry_type' => 'retired']), KEY)->assertUnprocessable()->assertJsonValidationErrors('inquiry_type');
        $this->postJson('/api/v1/inquiries', inquiryPayload(['inquiry_type' => 'media']), KEY)->assertCreated();
    });

    test('validation: every rule has a test', function (string $field, mixed $value) {
        $this->postJson('/api/v1/inquiries', inquiryPayload([$field => $value]), KEY)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(Inquiry::count())->toBe(0);
    })->with([
        'missing first name' => ['first_name', ''],
        'first name too long' => ['first_name', str_repeat('a', 81)],
        'missing last name' => ['last_name', ''],
        'bad email' => ['email', 'not-an-email'],
        'email too long' => ['email', str_repeat('a', 186).'@x.ca'],
        'short phone' => ['phone', '555-1234'],
        'letters in phone' => ['phone', 'call me maybe'],
        'message too short' => ['message', 'Hi'],
        'message too long' => ['message', str_repeat('a', 5001)],
        'no consent' => ['consent', false],
        'bad source url' => ['source_url', 'javascript:alert(1)'],
    ]);

    test('accepts the usual ways of writing a phone number', function (string $phone) {
        $this->postJson('/api/v1/inquiries', inquiryPayload(['phone' => $phone]), KEY)->assertCreated();

        expect(Inquiry::firstOrFail()->phone)->toBe('+14035551234');
    })->with(['(403) 555-1234', '403-555-1234', '+1 403 555 1234', '14035551234', '403.555.1234']);

    test('a filled honeypot looks successful but is stored as spam and tells nobody', function () {
        InquiryType::factory()->create(['slug' => 'media', 'email' => 'media@cancocares.ca']);

        $this->postJson('/api/v1/inquiries', inquiryPayload(['website' => 'http://spam.example', 'inquiry_type' => 'media']), KEY)->assertCreated();

        expect(Inquiry::firstOrFail()->status)->toBe(InquiryStatus::Spam);
        Mail::assertNothingQueued();
    });

    test('control characters are stripped and text is stored as text', function () {
        $this->postJson('/api/v1/inquiries', inquiryPayload([
            'first_name' => "Ada\x00",
            'message' => "Hello <script>alert(1)</script>\x07 there, I have a question.",
        ]), KEY)->assertCreated();

        $inquiry = Inquiry::firstOrFail();

        expect($inquiry->first_name)->toBe('Ada')->and($inquiry->message)->not->toContain("\x07");
    });

    test('the bot check is enforced on the server once Turnstile is configured', function () {
        config(['services.turnstile.secret' => 'secret']);
        app()->forgetInstance(BotVerifier::class);

        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

        $this->postJson('/api/v1/inquiries', inquiryPayload(), KEY)->assertUnprocessable()->assertJsonValidationErrors('bot_token');
        $this->postJson('/api/v1/inquiries', inquiryPayload(['bot_token' => 'bad']), KEY)->assertUnprocessable()->assertJsonValidationErrors('bot_token');
        $this->postJson('/api/v1/inquiries', inquiryPayload(['bot_token' => 'good']), KEY)->assertCreated();
    });

    test('is rate limited per visitor address, using the address the website forwards', function () {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/inquiries', inquiryPayload(['email' => "p{$i}@example.com"]), [...KEY, 'X-Forwarded-For' => '203.0.113.9'])->assertCreated();
        }

        $this->postJson('/api/v1/inquiries', inquiryPayload(['email' => 'p6@example.com']), [...KEY, 'X-Forwarded-For' => '203.0.113.9'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // A different visitor behind the same website server is not affected.
        $this->postJson('/api/v1/inquiries', inquiryPayload(['email' => 'other@example.com']), [...KEY, 'X-Forwarded-For' => '198.51.100.4'])->assertCreated();
    });

    test('is rate limited per email address across the day', function () {
        foreach (range(1, 20) as $i) {
            RateLimiter::clear('inquiry-ip:203.0.113.'.$i);
            $this->postJson('/api/v1/inquiries', inquiryPayload(), [...KEY, 'X-Forwarded-For' => '203.0.113.'.$i])->assertCreated();
        }

        $this->postJson('/api/v1/inquiries', inquiryPayload(), [...KEY, 'X-Forwarded-For' => '203.0.113.99'])->assertStatus(429);
    });
});
