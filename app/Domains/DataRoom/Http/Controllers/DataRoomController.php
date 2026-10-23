<?php

namespace App\Domains\DataRoom\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Catalogue\Enums\DocumentVisibility;
use App\Domains\DataRoom\Settings\DataRoomSettings;
use App\Domains\DataRoom\Support\DataRoomAccess;
use App\Domains\DataRoom\Support\Watermarker;
use App\Domains\DataRoom\Support\WatermarkFailed;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Models\ListingDocument;
use App\Domains\Listings\Public\PublicListing;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** The buyer's data room for one listing: NDA, document list and watermarked downloads. */
class DataRoomController extends Controller
{
    public function __construct(private readonly DataRoomAccess $access) {}

    public function show(Listing $listing, #[CurrentUser] User $user): Response
    {
        abort_unless($user->isBuyer() && $listing->status->isPublic(), 404);
        $listing->load(['category', 'city', 'documents.documentType']);
        $approval = $this->access->approval($user, $listing);

        $documents = $listing->documents
            ->filter(fn (ListingDocument $d) => $d->documentType->visibility !== DocumentVisibility::Internal)
            ->map(fn (ListingDocument $d): array => [
                'id' => $d->id, 'type' => $d->documentType->name, 'name' => $d->original_name, 'size' => $d->size,
                'level' => $d->documentType->visibility === DocumentVisibility::ApprovedNda ? 'Data room' : 'Verified bidders',
                'open' => $this->access->canOpen($user, $d),
            ])->values();

        return Inertia::render('account/data-room', [
            'listing' => ['ulid' => $listing->ulid, 'title' => $listing->title, 'url' => PublicListing::url($listing)],
            'documents' => $documents,
            'state' => [
                'verified' => $this->access->isVerified($user),
                'approval' => $approval?->status,
                'bidderNumber' => $approval?->status === 'approved' ? $approval->bidder_number : null,
                'ndaAccepted' => $this->access->ndaAccepted($user, $listing),
                'canSignNda' => $this->access->canSignNda($user, $listing),
                'hasDataRoom' => $documents->contains('level', 'Data room') || $listing->category->documentTypes()->where('visibility', 'approved_nda')->exists(),
            ],
            'nda' => ['text' => app(DataRoomSettings::class)->nda_text, 'version' => app(DataRoomSettings::class)->nda_version],
            'name' => $user->name,
        ]);
    }

    public function acceptNda(Request $request, Listing $listing, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($this->access->canSignNda($user, $listing), 403, __('Apply to bid on this listing before signing its NDA.'));
        $data = $request->validate(['typed_name' => ['required', 'string', 'min:3', 'max:160'], 'agree' => ['accepted']], [
            'agree.accepted' => __('Tick the box to confirm you accept the NDA.'),
        ]);

        DB::table('nda_acceptances')->insertOrIgnore([
            'listing_id' => $listing->id, 'user_id' => $user->id, 'nda_version' => app(DataRoomSettings::class)->nda_version,
            'typed_name' => strip_tags($data['typed_name']), 'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''), 'accepted_at' => now(),
        ]);

        Audit::record('dataroom', 'nda_accepted', "{$user->name} accepted NDA v{app(DataRoomSettings::class)->nda_version} for {$listing->title}", $listing, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('NDA accepted.')]);

        return back();
    }

    public function download(Request $request, Listing $listing, ListingDocument $document, #[CurrentUser] User $user, Watermarker $watermarker): HttpResponse
    {
        abort_unless($document->listing_id === $listing->id, 404);
        abort_unless($this->access->canOpen($user, $document), 403);

        $approval = $this->access->approval($user, $listing);
        $number = $approval?->status === 'approved' ? $approval->bidder_number : null;
        $line = sprintf('Confidential · %s · %s · %s SAST · Thabekhulu data room',
            $user->name, $number ? "Bidder #{$number}" : 'Verified bidder', now((string) config('platform.display_timezone'))->format('j M Y H:i'));

        try {
            $stamped = $watermarker->stamp((string) Storage::disk('local')->get($document->path), $document->mime, $line);
        } catch (WatermarkFailed $e) {
            Log::warning('Data room document could not be watermarked; download refused', ['document' => $document->id, 'error' => $e->getMessage()]);
            Audit::record('dataroom', 'watermark_failed', "Could not watermark {$document->original_name} for {$listing->title}; please re-upload it as a standard PDF", $listing, $user);
            abort(503, __('This document is temporarily unavailable. Thabekhulu has been notified.'));
        }

        DB::table('document_downloads')->insert([
            'listing_document_id' => $document->id, 'listing_id' => $listing->id, 'user_id' => $user->id,
            'bidder_number' => $number, 'ip_address' => $request->ip(), 'created_at' => now(),
        ]);
        Audit::record('dataroom', 'downloaded', "{$user->name} downloaded {$document->original_name} ({$listing->title})", $listing, $user);

        return response($stamped, 200, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => 'attachment; filename="'.Str::slug(pathinfo($document->original_name, PATHINFO_FILENAME)).'-confidential.'.pathinfo($document->original_name, PATHINFO_EXTENSION).'"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
