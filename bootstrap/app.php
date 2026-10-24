<?php

use App\Domains\Growth\Http\Middleware\CaptureAttribution;
use App\Domains\Identity\Http\Middleware\EnsureAccountActive;
use App\Domains\Identity\Http\Middleware\EnsureStaff;
use App\Domains\Identity\Http\Middleware\RequireStaffTwoFactor;
use App\Domains\Platform\Http\Middleware\EnsureFeature;
use App\Domains\Platform\Http\Middleware\SecurityHeaders;
use App\Domains\Settings\Http\Middleware\ApplyRuntimeSettings;
use App\Domains\Settings\Http\Middleware\RestrictAdminIp;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Domains/Listings/Console', __DIR__.'/../app/Domains/Quotes/Console', __DIR__.'/../app/Domains/Bidding/Console', __DIR__.'/../app/Domains/Settlement/Console', __DIR__.'/../app/Domains/Growth/Console', __DIR__.'/../app/Domains/Compliance/Console', __DIR__.'/../app/Domains/Platform/Console'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        // Provider callbacks are authenticated by their signatures, not CSRF tokens.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->alias([
            'feature' => EnsureFeature::class,
            'staff' => EnsureStaff::class,
            'staff.2fa' => RequireStaffTwoFactor::class,
            'admin.ip' => RestrictAdminIp::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Session lifetime comes from security settings, so set it before the session starts.
        $middleware->web(prepend: [ApplyRuntimeSettings::class]);

        $middleware->web(append: [
            CaptureAttribution::class,
            SecurityHeaders::class,
            EnsureAccountActive::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Branded error pages outside local development.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if (app()->isLocal() || $request->expectsJson()
                || ! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            if ($status === 419) {
                return back()->with('error', 'Your session expired. Please try again.');
            }

            return Inertia::render('error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
