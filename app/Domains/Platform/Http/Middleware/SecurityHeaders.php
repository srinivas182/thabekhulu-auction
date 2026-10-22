<?php

namespace App\Domains\Platform\Http\Middleware;

use App\Domains\Platform\Features\Flags;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the platform's baseline security headers to every web response:
 * a nonce-based Content Security Policy, HSTS (HTTPS only), clickjacking,
 * MIME-sniffing, referrer and permissions policies, and noindex while the
 * public site is not yet open to search engines.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;

        if (config('platform.csp.enabled')) {
            $header = config('platform.csp.report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $headers->set($header, $this->contentSecurityPolicy(Vite::cspNonce()));
        }

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=(self)');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! Flags::active('public_site_indexing')) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    private function contentSecurityPolicy(?string $nonce): string
    {
        $devServer = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => array_filter(["'self'", $nonce ? "'nonce-{$nonce}'" : null, $devServer]),
            // Radix UI and Inertia set inline style attributes at runtime.
            'style-src' => array_filter(["'self'", "'unsafe-inline'", $devServer]),
            // OpenStreetMap tiles for listing maps (spec D2).
            'img-src' => ["'self'", 'data:', 'blob:', 'https://tile.openstreetmap.org'],
            // Privacy-enhanced video tours only (spec D3).
            'frame-src' => ['https://www.youtube-nocookie.com', 'https://player.vimeo.com'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => array_filter(["'self'", $devServer, $devServer ? str_replace('http', 'ws', $devServer) : null]),
            'media-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $values, string $directive): string => $directive.' '.implode(' ', $values))
            ->implode('; ');
    }

    /**
     * Origin of the Vite dev server when running `npm run dev`, so hot
     * reloading works locally. Never present in production builds.
     */
    private function viteDevServer(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        $url = trim((string) file_get_contents(public_path('hot')));
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
