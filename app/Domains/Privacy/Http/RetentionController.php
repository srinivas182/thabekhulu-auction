<?php

namespace App\Domains\Privacy\Http;

use App\Domains\Audit\Support\Audit;
use App\Domains\Privacy\Settings\PrivacySettings;
use App\Domains\Privacy\Support\RetentionSchedule;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Settings → Data retention (super admin only). */
class RetentionController extends Controller
{
    public function show(): Response
    {
        abort_unless(request()->user()?->isSuperAdmin() === true, 403);
        $s = app(PrivacySettings::class);
        $due = RetentionSchedule::due();

        return Inertia::render('admin/settings/retention', [
            'categories' => collect(RetentionSchedule::categories())->map(fn ($c, $k) => $c + ['key' => $k, 'due' => $due[$k]])->values(),
            'approval' => ['enabled' => $s->purge_enabled, 'reference' => $s->approval_reference, 'by' => $s->approved_by, 'at' => $s->approved_at],
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless($actor->isSuperAdmin(), 403);
        $data = $request->validate(['enabled' => ['boolean'], 'reference' => ['nullable', 'required_if:enabled,true', 'string', 'max:200']],
            ['reference.required_if' => __('Record the attorney’s approval (for example, the letter reference and date).')]);
        $s = app(PrivacySettings::class);
        $on = (bool) ($data['enabled'] ?? false);
        $s->purge_enabled = $on;
        $s->approval_reference = $on ? (string) $data['reference'] : '';
        $s->approved_by = $on ? $actor->name : '';
        $s->approved_at = $on ? now()->toIso8601String() : '';
        $s->save();

        Audit::record('privacy', $on ? 'retention_purge_enabled' : 'retention_purge_disabled', $on ? "Retention purge enabled under approval {$s->approval_reference}" : 'Retention purge disabled', null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => $on ? __('Purging enabled. The weekly job will now apply the schedule.') : __('Report only.')]);

        return back();
    }
}
