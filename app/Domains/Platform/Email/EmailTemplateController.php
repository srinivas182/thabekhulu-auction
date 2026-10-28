<?php

namespace App\Domains\Platform\Email;

use App\Domains\Audit\Support\Audit;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as Notify;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Back office » Email templates (settings.manage): change the wording, never the facts. */
class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        $edited = DB::table('email_templates')->pluck('updated_at', 'key');

        return Inertia::render('admin/email-templates/index', [
            'templates' => collect(EmailTemplates::all())->map(fn ($t, $key) => ['key' => $key, 'group' => $t['group'], 'name' => $t['name'], 'when' => $t['when'], 'edited' => isset($edited[$key])])->values(),
            'sending' => EmailConfig::sending(),
        ]);
    }

    public function edit(string $key): Response
    {
        $d = EmailTemplates::all()[$key] ?? abort(404);

        return Inertia::render('admin/email-templates/edit', [
            'key' => $key, 'name' => $d['name'], 'when' => $d['when'], 'hasButton' => $d['button'] !== null,
            'template' => TemplateMail::current($key), 'variables' => $d['variables'], 'required' => $d['required'], 'sending' => EmailConfig::sending(),
        ]);
    }

    public function update(Request $request, string $key, #[CurrentUser] User $actor): RedirectResponse
    {
        $fields = $this->validated($request, $key);
        DB::table('email_templates')->updateOrInsert(['key' => $key], $fields + ['updated_by' => $actor->id, 'updated_at' => now(), 'created_at' => now()]);
        Audit::record('settings', 'email_template_updated', "Updated the “{$key}” email", null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved. This email now uses your wording.')]);

        return back();
    }

    public function reset(string $key, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless(isset(EmailTemplates::all()[$key]), 404);
        DB::table('email_templates')->where('key', $key)->delete();
        Audit::record('settings', 'email_template_reset', "Reset the “{$key}” email to the default wording", null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Back to the default wording.')]);

        return back();
    }

    /** The real email, rendered with example details, for the unsaved wording on screen. */
    public function preview(Request $request, string $key): JsonResponse
    {
        $d = EmailTemplates::all()[$key] ?? abort(404);
        $fields = $request->only(['subject', 'greeting', 'body', 'after', 'button']);
        $mail = TemplateMail::make($key, $d['example'], $d['button'] !== null ? url('/') : null, null, array_map(fn ($v) => (string) $v, $fields));

        return response()->json(['subject' => $mail->subject, 'html' => (string) $mail->render()]);
    }

    public function test(Request $request, string $key, #[CurrentUser] User $actor): RedirectResponse
    {
        $d = EmailTemplates::all()[$key] ?? abort(404);
        if (! EmailConfig::sending()) {
            return back()->withErrors(['test' => __('Email sending is off. Set up a provider in Email provider first.')]);
        }
        $mail = TemplateMail::make($key, $d['example'], $d['button'] !== null ? url('/') : null);
        try {
            Notify::route('mail', $actor->email)->notifyNow(new class($mail) extends Notification
            {
                public function __construct(private MailMessage $mail) {}

                /** @return list<string> */
                public function via(object $notifiable): array
                {
                    return ['mail'];
                }

                public function toMail(object $notifiable): MailMessage
                {
                    return $this->mail;
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['test' => __('The provider refused the email: :m', ['m' => mb_substr($e->getMessage(), 0, 300)])]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test sent to :email, with example details.', ['email' => $actor->email])]);

        return back();
    }

    /** @return array{subject: string, greeting: string, body: string, after: string, button: string|null} */
    private function validated(Request $request, string $key): array
    {
        $d = EmailTemplates::all()[$key] ?? abort(404);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'], 'greeting' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'], 'after' => ['nullable', 'string', 'max:3000'],
            'button' => [$d['button'] !== null ? 'required' : 'nullable', 'string', 'max:60'],
        ]);
        $fields = ['subject' => $data['subject'], 'greeting' => (string) ($data['greeting'] ?? ''), 'body' => $data['body'], 'after' => (string) ($data['after'] ?? ''),
            'button' => $d['button'] !== null ? (string) $data['button'] : null];

        $used = TemplateMail::placeholders(implode("\n", array_filter($fields, 'is_string')));
        $unknown = array_diff($used, array_keys($d['variables']));
        $missing = array_diff($d['required'], $used);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['body' => __('Unknown detail: :list. Use only the details listed beside the form.', ['list' => '{{'.implode('}}, {{', $unknown).'}}'])]);
        }
        if ($missing !== []) {
            throw ValidationException::withMessages(['body' => __('This email must include :list.', ['list' => '{{'.implode('}}, {{', $missing).'}}'])]);
        }

        return $fields;
    }
}
