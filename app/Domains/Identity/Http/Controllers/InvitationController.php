<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\AcceptInvitation;
use App\Domains\Identity\Http\Requests\AcceptInvitationRequest;
use App\Domains\Identity\Models\StaffInvitation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = $this->find($token);

        return Inertia::render('auth/accept-invitation', [
            'token' => $token,
            'valid' => $invitation?->isPending() ?? false,
            'email' => $invitation?->isPending() ? $invitation->email : null,
            'role' => $invitation?->isPending() ? ($invitation->role->getAttribute('label') ?? $invitation->role->name) : null,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        $invitation = $this->find($token);

        abort_if($invitation === null, 404);

        $user = $accept->handle($invitation, $request->details());

        Auth::login($user);
        $request->session()->regenerate();
        // They just chose a password, so skip the confirm-password step for 2FA set-up.
        $request->session()->passwordConfirmed();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Welcome. Set up two-factor authentication to finish activating your account.'),
        ]);

        return redirect()->route('security.edit');
    }

    private function find(string $token): ?StaffInvitation
    {
        return StaffInvitation::with('role')
            ->where('token_hash', StaffInvitation::hashToken($token))
            ->first();
    }
}
