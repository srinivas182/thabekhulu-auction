<?php

namespace App\Domains\Audit\Http\Controllers;

use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Audit\Support\Audit;
use App\Domains\Audit\Support\AuditQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = AuditQuery::filters($request);

        $entries = AuditQuery::build($filters)
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditEntry $entry): array => AuditQuery::present($entry));

        return Inertia::render('admin/audit/index', [
            'entries' => $entries,
            'filters' => $filters,
            'modules' => AuditEntry::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
            'canExport' => Gate::allows('audit.export'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = AuditQuery::filters($request);
        $tz = (string) config('platform.display_timezone');

        // Exporting the audit log is itself recorded.
        Audit::record('audit', 'exported', 'Exported the audit log', null, null, ['filters' => array_filter($filters)]);

        return response()->streamDownload(function () use ($filters, $tz): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Time (SAST)', 'Module', 'Event', 'Description', 'Actor', 'Actor email', 'IP address', 'Changes']);

            AuditQuery::build($filters)->chunkById(500, function ($chunk) use ($out, $tz): void {
                foreach ($chunk as $entry) {
                    /** @var AuditEntry $entry */
                    $row = AuditQuery::present($entry);
                    fputcsv($out, [
                        $entry->created_at?->setTimezone($tz)->format('Y-m-d H:i:s'),
                        $row['module'],
                        $row['event'],
                        $row['description'],
                        $row['actor'],
                        $row['actorEmail'],
                        $row['ip'],
                        json_encode($row['changes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            }, 'id');

            fclose($out);
        }, 'audit-log-'.now($tz)->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
