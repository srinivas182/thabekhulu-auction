<?php

namespace App\Domains\Notifications\Http;

use App\Domains\Audit\Support\Audit;
use App\Domains\Notifications\Settings\WhatsAppSettings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Buyer alert preferences (WhatsApp opt-in, POPIA consent) and staff WhatsApp settings. */
class NotificationPreferenceController extends Controller
{
    public function show(#[CurrentUser] User $user): Response
    {
        return Inertia::render('account/notifications', [
            'whatsapp' => ['optedIn' => $user->whatsapp_opt_in_at !== null, 'number' => $user->whatsapp_number ?? $user->mobile, 'available' => app(WhatsAppSettings::class)->ready()],
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        // People type numbers with spaces, brackets and dashes; validate the digits.
        if ($request->filled('number')) {
            $request->merge(['number' => preg_replace('/[\s().-]/', '', (string) $request->input('number'))]);
        }
        $data = $request->validate(['whatsapp' => ['boolean'], 'number' => ['nullable', 'required_if:whatsapp,true', 'regex:/^\+?27\d{9}$|^\+\d{8,15}$/']], [
            'number.regex' => __('Enter the number in international format, e.g. +27 82 555 0000.'),
        ]);
        $on = (bool) ($data['whatsapp'] ?? false);
        $user->forceFill(['whatsapp_number' => $on ? preg_replace('/[^\d+]/', '', (string) $data['number']) : $user->whatsapp_number, 'whatsapp_opt_in_at' => $on ? ($user->whatsapp_opt_in_at ?? now()) : null])->save();

        Audit::record('notifications', $on ? 'whatsapp_opt_in' : 'whatsapp_opt_out', $on ? 'Opted in to WhatsApp auction alerts' : 'Opted out of WhatsApp auction alerts', null, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => $on ? __('WhatsApp alerts are on.') : __('WhatsApp alerts are off.')]);

        return back();
    }

    public function settings(): Response
    {
        $s = app(WhatsAppSettings::class);

        return Inertia::render('admin/notifications/whatsapp', ['settings' => [
            'enabled' => $s->enabled, 'phone_number_id' => $s->phone_number_id, 'token_hint' => $s->access_token !== '' ? '•••• '.substr($s->access_token, -4) : null,
            'api_version' => $s->api_version, 'language' => $s->language, 'template_outbid' => $s->template_outbid, 'template_closing' => $s->template_closing,
        ]]);
    }

    public function updateSettings(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'], 'phone_number_id' => ['nullable', 'regex:/^\d{6,20}$/'], 'access_token' => ['nullable', 'string', 'max:1000'],
            'api_version' => ['required', 'regex:/^v\d+\.\d+$/'], 'language' => ['required', 'string', 'max:10'],
            'template_outbid' => ['required', 'regex:/^[a-z0-9_]+$/'], 'template_closing' => ['required', 'regex:/^[a-z0-9_]+$/'],
        ]);
        $s = app(WhatsAppSettings::class);
        $s->enabled = (bool) ($data['enabled'] ?? false);
        $s->phone_number_id = (string) ($data['phone_number_id'] ?? '');
        if (filled($data['access_token'] ?? null)) {
            $s->access_token = (string) $data['access_token']; // write-only: blank keeps the saved token
        }
        foreach (['api_version', 'language', 'template_outbid', 'template_closing'] as $k) {
            $s->{$k} = (string) $data[$k];
        }
        $s->save();

        Audit::record('notifications', 'whatsapp_settings', 'Updated WhatsApp settings', null, $actor, ['enabled' => $s->enabled]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp settings saved.')]);

        return back();
    }
}
