<?php

namespace App\Providers;

use App\Http\Middleware\EnsureFrontendApiKey;
use App\Models\Story;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureApi();
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
        Relation::morphMap(['story' => Story::class]);

        RateLimiter::for('public-api', fn (Request $request): Limit => EnsureFrontendApiKey::isValid($request)
            ? Limit::perMinute(3000)->by('frontend')
            : Limit::perMinute(120)->by((string) $request->ip()));
    }

    /**
     * Gate for everything only administrators may touch (users, settings, pages, donations).
     * Use it as `can:admin` middleware or `Gate::authorize('admin')`.
     */
    protected function configureAuthorization(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
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
