<?php

namespace App\Providers;

use App\Domains\Access\Enums\SystemRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

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
        $this->guardProductionConfiguration();
        $this->configureDefaults();
        $this->configureAccess();
    }

    /**
     * Super admins pass every permission check. Deactivated accounts pass none.
     * Successful logins are stamped for the staff list and audit trail.
     */
    protected function configureAccess(): void
    {
        Gate::before(function (User $user): ?bool {
            if (! $user->isActive()) {
                return false;
            }

            return $user->hasRole(SystemRole::SuperAdmin->value) ? true : null;
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ])->saveQuietly();
            }
        });
    }

    /**
     * Refuse to boot in production with unsafe settings.
     */
    protected function guardProductionConfiguration(): void
    {
        if (app()->isProduction() && config('app.debug')) {
            throw new RuntimeException('APP_DEBUG must be false in production.');
        }
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

        // Spec 5.2: minimum 12 characters, checked against breached passwords.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)->letters()->numbers()->uncompromised()
            : Password::min(12)->letters()->numbers(),
        );
    }
}
