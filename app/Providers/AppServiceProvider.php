<?php

namespace App\Providers;

use App\Contracts\BotVerifier;
use App\Contracts\DonationGateway;
use App\Http\Middleware\EnsureFrontendApiKey;
use App\Models\Event;
use App\Models\Story;
use App\Models\User;
use App\Services\Audit;
use App\Services\Bots\NullBotVerifier;
use App\Services\Bots\TurnstileVerifier;
use App\Services\Donations\DonationGateways;
use App\Services\Settings;
use App\Support\ClientIp;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);

        // The payment provider comes from DONATIONS_GATEWAY; resolving it fails when none is usable.
        $this->app->bind(DonationGateway::class, fn (): DonationGateway => DonationGateways::make());

        // Turnstile checks the bot token once its secret is in .env; until then the form relies on
        // the honeypot and the rate limits (open question Q-S2).
        $this->app->bind(BotVerifier::class, fn (): BotVerifier => filled(config('services.turnstile.secret'))
            ? new TurnstileVerifier((string) config('services.turnstile.secret'))
            : new NullBotVerifier);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureApi();
        $this->configureAudit();
    }

    /**
     * Sign-ins and sign-outs go in the audit trail too (model changes are logged by the `Audited` trait).
     */
    protected function configureAudit(): void
    {
        $this->app->singleton(Audit::class);

        Events::listen(Login::class, fn (Login $event) => $event->user instanceof User
            ? app(Audit::class)->record('login', 'auth', $event->user->id, $event->user->auditLabel(), null, null, $event->user)
            : null);

        Events::listen(Logout::class, fn (Logout $event) => $event->user instanceof User
            ? app(Audit::class)->record('logout', 'auth', $event->user->id, $event->user->auditLabel(), null, null, $event->user)
            : null);

        // The address that was tried (never the password), so repeated guessing is visible.
        Events::listen(Failed::class, fn (Failed $event) => app(Audit::class)->record(
            'login_failed',
            'auth',
            null,
            is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : null,
            null,
            ['email' => $event->credentials['email'] ?? null],
        ));
    }

    /**
     * Stable aliases for polymorphic relations, and the API rate limiter.
     *
     * The Next.js server makes nearly all API calls from one IP address, so a plain
     * per-IP limit would throttle the website itself. Requests that present the
     * valid frontend key get a generous allowance; everyone else is limited per IP.
     */
    protected function configureApi(): void
    {
        Relation::morphMap(['story' => Story::class, 'event' => Event::class]);

        // The contact form: 5 a minute per visitor address, and 20 a day per email address.
        RateLimiter::for('inquiries', function (Request $request): array {
            $limits = [Limit::perMinute(5)->by('inquiry-ip:'.ClientIp::for($request))];
            $email = $request->input('email');

            if (is_string($email) && $email !== '') {
                $limits[] = Limit::perDay(20)->by('inquiry-email:'.mb_strtolower($email));
            }

            return $limits;
        });

        // Donations: 10 attempts a minute per visitor, and 5 payment confirmations a minute per donation.
        RateLimiter::for('donations', fn (Request $request): Limit => Limit::perMinute(10)->by('donation-ip:'.ClientIp::for($request)));
        RateLimiter::for('donation-confirm', fn (Request $request): Limit => Limit::perMinute(5)->by('donation-confirm:'.$request->route('reference')));

        RateLimiter::for('public-api', fn (Request $request): Limit => EnsureFrontendApiKey::isValid($request)
            ? Limit::perMinute(3000)->by('frontend')
            : Limit::perMinute(120)->by((string) $request->ip()));
    }

    /**
     * The `admin` gate is for what only a super administrator may touch (users and roles). Everything else is
     * a named permission (PermissionCatalog). Use `can:admin` / `can:stories.view` as route middleware.
     */
    protected function configureAuthorization(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        // A super administrator passes every check (permissions, policies, abilities). For everyone
        // else `null` defers to the normal checks: Spatie's permission hook, then the policies.
        Gate::before(fn (User $user): ?bool => $user->isAdmin() ? true : null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
