<?php

namespace App\Domains\Compliance\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Models\Listing;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Suspicious-activity queue (compliance.review). People decide; the platform
 * gathers evidence. Reports to the FIC are filed by Thabekhulu's compliance
 * officer outside the platform (T7).
 */
class ComplianceController extends Controller
{
    public const STATUSES = ['open' => 'Open', 'reviewing' => 'Under review', 'dismissed' => 'Dismissed', 'escalated' => 'Escalated to the compliance officer'];

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'open';
        $tz = (string) config('platform.display_timezone');

        return Inertia::render('admin/compliance/index', [
            'status' => $status,
            'counts' => DB::table('suspicious_items')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'items' => DB::table('suspicious_items as i')->leftJoin('listings as l', 'l.id', '=', 'i.listing_id')->leftJoin('users as u', 'u.id', '=', 'i.user_id')
                ->where('i.status', $status)->orderByDesc('i.detected_at')->limit(200)
                ->get(['i.ulid', 'i.source', 'i.summary', 'i.detected_at', 'l.title as listing', 'u.first_name', 'u.last_name'])
                ->map(fn ($i) => ['ulid' => $i->ulid, 'source' => $i->source, 'summary' => $i->summary, 'listing' => $i->listing,
                    'person' => trim(($i->first_name ?? '').' '.($i->last_name ?? '')) ?: null,
                    'detected' => CarbonImmutable::parse((string) $i->detected_at)->setTimezone($tz)->format('j M Y, H:i')]),
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(string $ulid): Response
    {
        $i = DB::table('suspicious_items')->where('ulid', $ulid)->first() ?? abort(404);
        $tz = (string) config('platform.display_timezone');

        return Inertia::render('admin/compliance/show', [
            'item' => ['ulid' => $i->ulid, 'source' => $i->source, 'summary' => $i->summary, 'status' => $i->status, 'details' => json_decode((string) $i->details, true),
                'listing' => $i->listing_id ? DB::table('listings')->where('id', $i->listing_id)->value('title') : null,
                'person' => $i->user_id ? User::find((int) $i->user_id)?->name : null,
                'detected' => CarbonImmutable::parse((string) $i->detected_at)->setTimezone($tz)->format('j M Y, H:i')],
            'notes' => DB::table('suspicious_notes as n')->join('users as u', 'u.id', '=', 'n.user_id')->where('suspicious_item_id', $i->id)->orderBy('n.id')
                ->get(['n.note', 'n.created_at', 'u.first_name', 'u.last_name'])->map(fn ($n) => ['note' => $n->note, 'by' => "{$n->first_name} {$n->last_name}",
                    'at' => CarbonImmutable::parse((string) $n->created_at)->setTimezone($tz)->format('j M Y, H:i')]),
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, string $ulid, #[CurrentUser] User $actor): RedirectResponse
    {
        $i = DB::table('suspicious_items')->where('ulid', $ulid)->first() ?? abort(404);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(self::STATUSES))], 'note' => ['nullable', 'required_if:status,dismissed,escalated', 'string', 'max:2000']],
            ['note.required_if' => __('Record the reason for this decision.')]);

        DB::transaction(function () use ($i, $data, $actor) {
            DB::table('suspicious_items')->where('id', $i->id)->update(['status' => $data['status'], 'updated_at' => now()] + (in_array($data['status'], ['dismissed', 'escalated'], true) ? ['decided_by' => $actor->id, 'decided_at' => now()] : []));
            if (filled($data['note'] ?? null)) {
                DB::table('suspicious_notes')->insert(['suspicious_item_id' => $i->id, 'user_id' => $actor->id, 'note' => $data['note'], 'created_at' => now()]);
            }
        });
        Audit::record('compliance', 'item_'.$data['status'], "Suspicious-activity item {$i->ulid}: ".self::STATUSES[$data['status']], null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    /** Evidence pack: the item, its notes, and the audit trail for the person and listing around the detection. */
    public function evidence(string $ulid, #[CurrentUser] User $actor): SymfonyResponse
    {
        $i = DB::table('suspicious_items')->where('ulid', $ulid)->first() ?? abort(404);
        $from = CarbonImmutable::parse((string) $i->detected_at)->subDays(30);
        $to = CarbonImmutable::parse((string) $i->detected_at)->addDays(30);
        $trail = DB::table('activity_log')->whereBetween('created_at', [$from, $to])
            ->where(function ($q) use ($i) {
                if ($i->user_id) {
                    $q->orWhere(fn ($w) => $w->where('causer_type', User::class)->where('causer_id', $i->user_id))->orWhere(fn ($w) => $w->where('subject_type', User::class)->where('subject_id', $i->user_id));
                }
                if ($i->listing_id) {
                    $q->orWhere(fn ($w) => $w->where('subject_type', Listing::class)->where('subject_id', $i->listing_id));
                }
                if (! $i->user_id && ! $i->listing_id) {
                    $q->whereRaw('1 = 0');
                }
            })->orderBy('id')->get(['created_at', 'log_name', 'event', 'description', 'properties']);

        Audit::record('compliance', 'evidence_exported', "Exported evidence for item {$i->ulid}", null, $actor);
        $pack = [
            'generated_at' => now()->toIso8601String(), 'generated_by' => $actor->name,
            'note' => 'Evidence gathered by the platform for review. Any report to the Financial Intelligence Centre is made by the compliance officer.',
            'item' => ['source' => $i->source, 'summary' => $i->summary, 'status' => $i->status, 'detected_at' => $i->detected_at, 'details' => json_decode((string) $i->details, true)],
            'notes' => DB::table('suspicious_notes')->where('suspicious_item_id', $i->id)->orderBy('id')->get(['note', 'user_id', 'created_at']),
            'audit_trail' => $trail,
        ];

        return response(json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 200, [
            'Content-Type' => 'application/json', 'Content-Disposition' => "attachment; filename=\"evidence-{$i->ulid}.json\"", 'Cache-Control' => 'no-store, private',
        ]);
    }
}
