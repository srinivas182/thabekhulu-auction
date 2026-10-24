<?php

namespace App\Domains\Leads\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Leads\Models\Enquiry;
use App\Domains\Leads\Models\EnquiryReply;
use App\Domains\Leads\Models\Lead;
use App\Domains\Leads\Models\LeadNote;
use App\Domains\Leads\Notifications\EnquiryReplyMail;
use App\Domains\Listings\Models\Listing;
use App\Domains\Sellers\Http\Controllers\SellerController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Sell with us" leads (leads.view / leads.manage) and contact enquiries
 * (enquiries.view / enquiries.respond).
 */
class InboxController extends Controller
{
    public function leads(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;

        return Inertia::render('admin/inbox/leads', [
            'leads' => Lead::when($status, fn ($q, $s) => $q->where('status', $s))->latest()->paginate(25)->withQueryString()
                ->through(fn (Lead $l): array => [
                    'ulid' => $l->ulid, 'name' => $l->name, 'location' => $l->location, 'type' => $l->property_type,
                    'askingPrice' => $l->asking_price, 'status' => $l->status, 'statusLabel' => Lead::STATUSES[$l->status] ?? $l->status,
                    'createdAt' => $l->created_at?->toIso8601String(),
                ]),
            'statuses' => Lead::STATUSES,
            'filter' => $status,
            'counts' => Lead::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function showLead(Lead $lead): Response
    {
        $lead->load(['notes.user:id,first_name,last_name', 'assignee:id,first_name,last_name']);

        return Inertia::render('admin/inbox/lead', [
            'lead' => [
                'ulid' => $lead->ulid, 'name' => $lead->name, 'email' => $lead->email, 'mobile' => $lead->mobile, 'type' => $lead->property_type,
                'location' => $lead->location, 'askingPrice' => $lead->asking_price, 'description' => $lead->description,
                'status' => $lead->status, 'assignedTo' => $lead->assigned_to, 'createdAt' => $lead->created_at?->toIso8601String(),
                'photos' => $lead->getMedia('photos')->map(fn (Media $m): array => ['id' => $m->id, 'name' => $m->file_name])->values(),
                'notes' => $lead->notes->map(fn (LeadNote $n): array => ['note' => $n->note, 'by' => $n->user !== null ? $n->user->name : 'System', 'at' => $n->created_at->toIso8601String()]),
            ],
            'statuses' => Lead::STATUSES,
            'staff' => User::staff()->whereNull('deactivated_at')->orderBy('first_name')->get(['id', 'first_name', 'last_name'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
            'canManage' => Gate::allows('leads.manage'),
            // Sprint 8a: seller documents and turning the lead into a draft listing.
            'documents' => DB::table('lead_documents')->where('lead_id', $lead->id)->get(['id', 'kind'])
                ->map(fn ($d) => ['kind' => SellerController::DOC_KINDS[$d->kind] ?? $d->kind, 'url' => route('admin.leads.document', [$lead, $d->id])])->values(),
            'fromSeller' => $lead->user_id !== null,
            'listing' => $lead->listing_id ? ['url' => route('admin.listings.edit', Listing::whereKey($lead->listing_id)->value('ulid'))] : null,
            'categories' => Gate::allows('listings.create') && $lead->listing_id === null ? Category::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function updateLead(Request $request, Lead $lead, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Lead::STATUSES))],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('type', 'staff')],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $from = $lead->status;
        $lead->fill(['status' => $data['status'], 'assigned_to' => $data['assigned_to'] ?? null])->save();
        if (filled($data['note'] ?? null)) {
            $lead->notes()->create(['user_id' => $actor->id, 'note' => strip_tags((string) $data['note']), 'created_at' => now()]);
        }

        Audit::record('leads', 'updated', "Updated lead from {$lead->name}", $lead, $actor, ['old' => ['status' => $from], 'attributes' => ['status' => $lead->status]]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead updated.')]);

        return back();
    }

    public function leadPhoto(Lead $lead, Media $media, #[CurrentUser] User $actor): StreamedResponse
    {
        abort_unless($media->model_type === $lead->getMorphClass() && (int) $media->model_id === $lead->id, 404);
        Audit::record('leads', 'photo_viewed', "Opened a photo from lead {$lead->name}", $lead, $actor);

        return response()->streamDownload(function () use ($media): void {
            $stream = $media->stream();
            fpassthru($stream);
            fclose($stream);
        }, $media->file_name, ['Content-Type' => $media->mime_type], 'inline');
    }

    public function enquiries(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;

        return Inertia::render('admin/inbox/enquiries', [
            'enquiries' => Enquiry::when($status, fn ($q, $s) => $q->where('status', $s))->latest()->paginate(25)->withQueryString()
                ->through(fn (Enquiry $e): array => ['id' => $e->id, 'name' => $e->name, 'subject' => $e->subject, 'status' => $e->status, 'createdAt' => $e->created_at?->toIso8601String()]),
            'filter' => $status,
        ]);
    }

    public function showEnquiry(Enquiry $enquiry): Response
    {
        $enquiry->load(['replies.user:id,first_name,last_name', 'listing:id,title,ulid']);

        return Inertia::render('admin/inbox/enquiry', [
            'enquiry' => [
                'id' => $enquiry->id, 'name' => $enquiry->name, 'email' => $enquiry->email, 'phone' => $enquiry->phone,
                'subject' => $enquiry->subject, 'message' => $enquiry->message, 'status' => $enquiry->status,
                'listing' => $enquiry->listing?->title, 'createdAt' => $enquiry->created_at?->toIso8601String(),
                'replies' => $enquiry->replies->map(fn (EnquiryReply $r): array => ['body' => $r->body, 'by' => $r->user !== null ? $r->user->name : 'Staff', 'at' => $r->created_at->toIso8601String()]),
            ],
            'canRespond' => Gate::allows('enquiries.respond'),
        ]);
    }

    public function reply(Request $request, Enquiry $enquiry, #[CurrentUser] User $actor): RedirectResponse
    {
        $body = strip_tags($request->validate(['body' => ['required', 'string', 'max:5000']])['body']);

        $enquiry->replies()->create(['user_id' => $actor->id, 'body' => $body, 'created_at' => now()]);
        $enquiry->forceFill(['status' => 'answered'])->save();
        Notification::route('mail', $enquiry->email)->notify(new EnquiryReplyMail($enquiry->subject, $body, $actor->name));

        Audit::record('enquiries', 'replied', "Replied to {$enquiry->name}: {$enquiry->subject}", $enquiry, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply sent by email.')]);

        return back();
    }

    public function close(Enquiry $enquiry, #[CurrentUser] User $actor): RedirectResponse
    {
        $enquiry->forceFill(['status' => 'closed'])->save();
        Audit::record('enquiries', 'closed', "Closed enquiry from {$enquiry->name}", $enquiry, $actor);

        return back();
    }
}
