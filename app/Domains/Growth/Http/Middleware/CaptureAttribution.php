<?php

namespace App\Domains\Growth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** First-touch campaign source (utm_*), kept in the session until the visitor registers. */
class CaptureAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->filled('utm_source') && ! $request->session()->has('attribution')) {
            $request->session()->put('attribution', array_filter([
                'source' => mb_substr((string) $request->query('utm_source'), 0, 60),
                'medium' => mb_substr((string) $request->query('utm_medium', ''), 0, 60),
                'campaign' => mb_substr((string) $request->query('utm_campaign', ''), 0, 80),
                'landing' => mb_substr($request->path(), 0, 200),
                'at' => now()->toIso8601String(),
            ]));
        }

        return $next($request);
    }
}
