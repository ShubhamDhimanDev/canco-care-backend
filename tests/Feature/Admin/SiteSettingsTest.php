<?php

use App\Jobs\RevalidateFrontend;
use App\Models\Donation;
use App\Models\User;
use App\Services\Settings;
use App\Support\ImpactFigure;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function siteSettingsInput(array $overrides = []): array
{
    return array_replace_recursive([
        'impact' => ['mode' => 'custom', 'amount_text' => '$95,000', 'caption' => 'AND COUNTING!', 'blurb' => 'We help.', 'starting_amount' => '80000'],
        'social' => ['facebook' => 'https://facebook.com/cancocares', 'instagram' => '', 'linkedin' => ''],
        'contact' => ['inquiry_email' => 'hello@cancocares.ca'],
        'donations' => ['enabled' => '0', 'presets' => ['25', '50', '100', '250', '', ''], 'min' => '5'],
        'receipts' => [
            'charity_name' => 'Canco Cares Foundation', 'address' => '125 Hwy 33 E, Kelowna, BC V1X 2A1',
            'registration_number' => '79021 4621 RR0001', 'signatory_name' => 'Simran Kaur', 'signatory_label' => 'Authorized Signature',
            'reply_to' => 'inquiry@cancocares.ca',
        ],
    ], $overrides);
}

beforeEach(function () {
    Queue::fake();
    config(['services.donations.gateway' => 'fake']);
});

describe('access', function () {
    test('only administrators can open or save them', function () {
        $editor = User::factory()->create();

        $this->actingAs($editor)->get(route('site-settings.edit'))->assertForbidden();
        $this->actingAs($editor)->put(route('site-settings.update'), siteSettingsInput())->assertForbidden();
    });

    test('guests are sent to the login page', function () {
        $this->get(route('site-settings.edit'))->assertRedirect();
    });
});

describe('saving', function () {
    test('shows the current values', function () {
        $this->actingAs(User::factory()->admin()->create())->get(route('site-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('site-settings/edit')
                ->where('values.impact.mode', 'custom')
                ->where('values.impact.amount_text', '$80,000')
                ->where('values.impact.starting_amount', '80000')
                ->where('values.donations.presets', [25, 50, 100, 250])
                ->where('values.receipts.registration_number', '79021 4621 RR0001')
                ->where('gateway.available', true));
    });

    test('saves every group, turns dollars into cents and refreshes the website', function () {
        $this->actingAs(User::factory()->admin()->create())->put(route('site-settings.update'), siteSettingsInput([
            'donations' => ['presets' => ['100', '25', '50', '', '', ''], 'min' => '7.50'],
            'impact' => ['starting_amount' => '1234.56'],
        ]))->assertRedirect(route('site-settings.edit'))->assertSessionHasNoErrors();

        $settings = app(Settings::class);

        expect($settings->string('impact.amount_text'))->toBe('$95,000')
            ->and($settings->int('impact.actual_offset_cents'))->toBe(123456)
            ->and($settings->get('donations.presets_cents'))->toBe([2500, 5000, 10000])   // sorted, blanks dropped
            ->and($settings->int('donations.min_cents'))->toBe(750)
            ->and($settings->string('social.facebook'))->toBe('https://facebook.com/cancocares')
            ->and($settings->string('contact.inquiry_email'))->toBe('hello@cancocares.ca');

        Queue::assertPushed(RevalidateFrontend::class, fn (RevalidateFrontend $job) => $job->tags === ['settings']);
    });

    test('validation', function (string $path, mixed $value) {
        $input = siteSettingsInput();
        data_set($input, str_replace('/', '.', $path), $value);

        $this->actingAs(User::factory()->admin()->create())->put(route('site-settings.update'), $input)
            ->assertSessionHasErrors(str_replace('/', '.', $path));
    })->with([
        'unknown impact mode' => ['impact/mode', 'magic'],
        'negative starting amount' => ['impact/starting_amount', '-5'],
        'starting amount with three decimals' => ['impact/starting_amount', '1.234'],
        'bad facebook link' => ['social/facebook', 'javascript:alert(1)'],
        'bad contact email' => ['contact/inquiry_email', 'nope'],
        'minimum gift of zero' => ['donations/min', '0'],
        'no charity name' => ['receipts/charity_name', ''],
        'no registration number' => ['receipts/registration_number', ''],
    ]);

    test('custom mode needs a number to show', function () {
        $this->actingAs(User::factory()->admin()->create())->put(route('site-settings.update'), siteSettingsInput(['impact' => ['mode' => 'custom', 'amount_text' => '']]))
            ->assertSessionHasErrors('impact.amount_text');
    });

    test('switching to actual keeps the typed number for switching back', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('site-settings.update'), siteSettingsInput(['impact' => ['mode' => 'actual', 'amount_text' => '']]))->assertSessionHasNoErrors();

        expect(app(Settings::class)->string('impact.mode'))->toBe('actual')->and(app(Settings::class)->string('impact.amount_text'))->toBe('$80,000');
    });

    test('suggested amounts must be whole, different dollars', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('site-settings.update'), siteSettingsInput(['donations' => ['presets' => ['25', '25', '', '', '', '']]]))->assertSessionHasErrors('donations.presets.0');
        $this->actingAs($admin)->put(route('site-settings.update'), siteSettingsInput(['donations' => ['presets' => ['12.5', '', '', '', '', '']]]))->assertSessionHasErrors('donations.presets.0');
        $this->actingAs($admin)->put(route('site-settings.update'), siteSettingsInput(['donations' => ['presets' => ['', '', '', '', '', '']]]))->assertSessionHasErrors('donations.presets');
    });
});

describe('switching donations on', function () {
    test('works when a gateway is usable', function () {
        $this->actingAs(User::factory()->admin()->create())->put(route('site-settings.update'), siteSettingsInput(['donations' => ['enabled' => '1']]))->assertSessionHasNoErrors();

        expect(app(Settings::class)->bool('donations.enabled'))->toBeTrue();
    });

    test('is refused while no gateway is configured, and says why', function () {
        config(['services.donations.gateway' => null]);

        $this->actingAs(User::factory()->admin()->create())->put(route('site-settings.update'), siteSettingsInput(['donations' => ['enabled' => '1']]))
            ->assertSessionHasErrors('donations.enabled');

        expect(app(Settings::class)->bool('donations.enabled'))->toBeFalse();
    });

    test('is refused for the test gateway on a production server', function () {
        // Outside "testing" Laravel enforces CSRF; this test is about the gateway rule, not the token.
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->actingAs(User::factory()->admin()->create())->put(route('site-settings.update'), siteSettingsInput(['donations' => ['enabled' => '1']]))
            ->assertSessionHasErrors('donations.enabled');
    });

    test('Global Payments is not selectable until it is built', function () {
        config(['services.donations.gateway' => 'global_payments']);

        $this->actingAs(User::factory()->admin()->create())->put(route('site-settings.update'), siteSettingsInput(['donations' => ['enabled' => '1']]))
            ->assertSessionHasErrors('donations.enabled');
    });
});

describe('impact figure', function () {
    test('custom mode shows exactly what was typed', function () {
        app(Settings::class)->setMany(['impact.mode' => 'custom', 'impact.amount_text' => 'Over $1 million']);

        $this->getJson('/api/v1/settings')->assertOk()->assertJsonPath('data.impact.amount_text', 'Over $1 million')->assertJsonPath('data.impact.caption', 'AND COUNTING!');
    });

    test('actual mode is the starting amount plus real donations after refunds, and ignores tests, pending and failed', function () {
        app(Settings::class)->setMany(['impact.mode' => 'actual', 'impact.actual_offset_cents' => 8_000_000]);

        Donation::factory()->succeeded()->live()->create(['amount_cents' => 10000]);                                   // +100
        Donation::factory()->succeeded()->live()->create(['amount_cents' => 5099, 'refunded_cents' => 1000, 'status' => 'partially_refunded']);   // +40.99
        Donation::factory()->succeeded()->live()->create(['amount_cents' => 2000, 'refunded_cents' => 2000, 'status' => 'refunded']);   // refunded: 0
        Donation::factory()->succeeded()->create(['amount_cents' => 99999999]);                                         // test: ignored
        Donation::factory()->live()->create(['amount_cents' => 99999999]);                                              // pending: ignored
        Donation::factory()->live()->create(['amount_cents' => 99999999, 'status' => 'failed']);                        // failed: ignored

        // 80,000.00 + 100.00 + 40.99 = 80,140.99 → whole dollars, rounded down.
        $this->getJson('/api/v1/settings')->assertJsonPath('data.impact.amount_text', '$80,140');
    });

    test('actual mode with no donations shows the starting amount', function () {
        app(Settings::class)->setMany(['impact.mode' => 'actual', 'impact.actual_offset_cents' => 8_000_000]);

        $this->getJson('/api/v1/settings')->assertJsonPath('data.impact.amount_text', '$80,000');
    });

    test('actual mode with a zero start counts only donations made here', function () {
        app(Settings::class)->setMany(['impact.mode' => 'actual', 'impact.actual_offset_cents' => 0]);
        Donation::factory()->succeeded()->live()->create(['amount_cents' => 123456]);

        $this->getJson('/api/v1/settings')->assertJsonPath('data.impact.amount_text', '$1,234');
    });

    test('the public settings expose nothing private', function () {
        app(Settings::class)->setMany(['inquiries.fallback_emails' => ['secret@cancocares.ca'], 'receipts.registration_number' => '999']);

        $body = $this->getJson('/api/v1/settings')->assertOk()->getContent();

        expect($body)->not->toContain('secret@cancocares.ca')->not->toContain('999')->not->toContain('fallback');
        expect(array_keys($this->getJson('/api/v1/settings')->json('data')))->toBe(['impact', 'social', 'contact', 'donations']);
    });

    test('formatting rounds down and never goes negative', function () {
        expect(ImpactFigure::format(8_014_099))->toBe('$80,140')
            ->and(ImpactFigure::format(99))->toBe('$0')
            ->and(ImpactFigure::format(-500))->toBe('$0')
            ->and(ImpactFigure::format(123_456_789_00))->toBe('$123,456,789');
    });
});
