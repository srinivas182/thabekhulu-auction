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
use App\Http\Middleware\RequireFreshPassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
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
    ->withCommands([__DIR__.'/../app/Domains/Listings/Console', __DIR__.'/../app/Domains/Quotes/Console', __DIR__.'/../app/Domains/Bidding/Console', __DIR__.'/../app/Domains/Settlement/Console', __DIR__.'/../app/Domains/Growth/Console', __DIR__.'/../app/Domains/Compliance/Console', __DIR__.'/../app/Domains/Platform/Console', __DIR__.'/../app/Domains/Privacy/Console', __DIR__.'/../app/Domains/Demo/Console', __DIR__.'/../app/Domains/Cms/Console'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        // Provider callbacks are authenticated by their signatures, not CSRF tokens.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->alias([
            'feature' => EnsureFeature::class,
            'staff' => EnsureStaff::class,
            'staff.2fa' => RequireStaffTwoFactor::class,
            'password.fresh' => RequireFreshPassword::class,
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
            // Capped: Apache rejects a single header over 8 KB with an empty 500 (seen on /login and /register).
            // The full preload list is still in each page's HTML, so nothing is lost.
            AddLinkHeadersForPreloadedAssets::using(12),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Branded error pages outside local development.
        // An upload bigger than PHP allows: tell the person plainly instead of showing an error page.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                Inertia::flash('toast', ['type' => 'error', 'message' => __('That file is too large to upload (the limit is :size). Please choose a smaller file, or a PDF instead of a photo.', ['size' => ini_get('upload_max_filesize')])]);

                return back(303);
            }

            return null;
        });

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
