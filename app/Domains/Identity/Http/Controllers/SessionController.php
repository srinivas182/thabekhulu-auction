<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists the account's signed-in devices and signs out the others.
 * Requires the database session driver.
 */
class SessionController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
                ->map(fn (object $session): array => [
                    'device' => $this->describe((string) ($session->user_agent ?? '')),
                    'ip' => $session->ip_address,
                    'lastActive' => now()->setTimestamp((int) $session->last_activity)->diffForHumans(),
                    'current' => $session->id === $request->session()->getId(),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('settings/sessions', ['sessions' => $sessions]);
    }

    public function destroy(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices((string) $request->input('password'));

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signed out of all other devices.')]);

        return back();
    }

    /**
     * Short, readable description such as "Chrome on Windows".
     */
    private function describe(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$platform}";
    }
}
