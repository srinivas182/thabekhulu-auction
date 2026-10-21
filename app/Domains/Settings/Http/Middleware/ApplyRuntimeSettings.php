<?php

namespace App\Domains\Settings\Http\Middleware;

use App\Domains\Settings\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Applies settings that must take effect before the session starts, such
 * as the inactivity timeout. Runs before StartSession.
 */
class ApplyRuntimeSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            config(['session.lifetime' => app(SecuritySettings::class)->session_lifetime_minutes]);
        } catch (Throwable) {
            // Settings not migrated yet (fresh install); keep the config default.
        }

        return $next($request);
    }
}
