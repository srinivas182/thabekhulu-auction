<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
