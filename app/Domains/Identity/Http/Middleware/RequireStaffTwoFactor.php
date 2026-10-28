<?php

namespace App\Domains\Identity\Http\Middleware;

use App\Domains\Settings\Settings\SecuritySettings;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff must have confirmed two-factor authentication before they can use
 * any back-office page. They are sent to the security settings to enrol.
 */
class RequireStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Settings » Security » "Staff two-factor required" (on by default; may be off only for testing).
        $required = app(SecuritySettings::class)->staff_two_factor_required;

        if ($required && $user instanceof User && $user->isStaff() && ! $user->hasConfirmedTwoFactor()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('Set up two-factor authentication to continue to the back office.'),
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
