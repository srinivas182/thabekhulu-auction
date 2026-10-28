<?php

namespace App\Domains\Platform\Email;

use App\Domains\Audit\Support\Audit;
use App\Domains\Platform\Mail\TestEmail;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Back office » Email provider (settings.manage). */
class EmailProviderController extends Controller
{
    public function show(): Response
    {
        $s = app(EmailSettings::class);

        return Inertia::render('admin/settings/email', [
            'settings' => ['enabled' => $s->enabled, 'provider' => $s->provider, 'host' => $s->host, 'port' => $s->port, 'username' => $s->username,
                'password_hint' => $s->password !== '' ? '•••• '.mb_substr($s->password, -4) : null, 'from_address' => $s->from_address, 'from_name' => $s->from_name],
            'sending' => $s->ready(),
            'providers' => EmailProviders::all(),
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'provider' => ['required', Rule::in(array_keys(EmailProviders::all()))],
            'host' => ['nullable', 'required_if:enabled,true', 'regex:/^[A-Za-z0-9.-]+$/', 'max:190'],
            'port' => ['required', 'integer', Rule::in([25, 465, 587, 2525])],
            'username' => ['nullable', 'required_if:enabled,true', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['nullable', 'required_if:enabled,true', 'email:rfc', 'max:190'],
            'from_name' => ['required', 'string', 'max:120'],
        ], ['host.regex' => __('Enter the server name only, for example smtp-relay.brevo.com.')]);

        $s = app(EmailSettings::class);
        if (($data['enabled'] ?? false) && ! filled($data['password'] ?? null) && $s->password === '') {
            return back()->withErrors(['password' => __('Enter the password or API key from your provider.')]);
        }
        $s->enabled = (bool) ($data['enabled'] ?? false);
        $s->provider = $data['provider'];
        $s->host = (string) ($data['host'] ?? '');
        $s->port = (int) $data['port'];
        $s->username = (string) ($data['username'] ?? '');
        if (filled($data['password'] ?? null)) {
            $s->password = (string) $data['password']; // write-only: blank keeps the saved key
        }
        $s->from_address = (string) ($data['from_address'] ?? '');
        $s->from_name = $data['from_name'];
        $s->save();

        Audit::record('settings', 'email_provider', 'Updated the email provider', null, $actor, ['enabled' => $s->enabled, 'provider' => $s->provider]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $s->ready() ? __('Saved. Emails are sent through :p.', ['p' => EmailProviders::all()[$s->provider]['label']]) : __('Saved. Email sending is off, so no emails are sent.')]);

        return back();
    }

    /** Sends one email straight away and reports the provider's answer. */
    public function test(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email:rfc', 'max:190']]);
        if (! EmailConfig::sending()) {
            return back()->withErrors(['to' => __('Enable and save a provider first.')]);
        }

        try {
            Mail::to($data['to'])->send(new TestEmail);
        } catch (\Throwable $e) {
            Audit::record('settings', 'email_test_failed', 'Test email failed', null, $actor);

            return back()->withErrors(['to' => __('The provider refused the email: :m', ['m' => mb_substr($e->getMessage(), 0, 300)])]);
        }
        Audit::record('settings', 'email_test_sent', "Test email sent to {$data['to']}", null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent to :to. Check the inbox (and spam folder).', ['to' => $data['to']])]);

        return back();
    }
}
