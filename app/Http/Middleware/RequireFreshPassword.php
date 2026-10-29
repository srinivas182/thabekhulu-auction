<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff created with a temporary password must choose their own before using the back office.
 */
class RequireFreshPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Please choose your own password before continuing.')]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
