<?php

namespace App\Domains\Listings\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Catalogue\Models\DocumentType;
use App\Domains\Listings\Enums\ComplianceItem;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Models\ListingDocument;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Notifications\DepositsOpen;
use App\Domains\Verification\Models\BidderApproval;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photos, floor plans, documents and the compliance checklist of a listing.
 */
class ListingAssetController extends Controller
{
    public function uploadMedia(Request $request, Listing $listing, string $collection, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless(in_array($collection, ['photos', 'floor_plans'], true), 404);
        $this->ensureEditable($listing);

        $mimes = $collection === 'photos' ? 'jpg,jpeg,png,webp' : 'jpg,jpeg,png,webp,pdf';
        $request->validate([
            'files' => ['required', 'array', 'max:30'],
            'files.*' => ['file', "mimes:{$mimes}", 'max:15360'],
        ]);

        /** @var list<UploadedFile> $files */
        $files = $request->file('files');
        foreach ($files as $file) {
            $listing->addMedia($file)
                ->usingFileName(Str::random(24).'.'.strtolower($file->getClientOriginalExtension()))
                ->withCustomProperties(['alt' => ''])
                ->toMediaCollection($collection);
        }

        Audit::record('listings', 'media_added', count($files)." file(s) added to {$listing->title}", $listing, $actor, ['collection' => $collection]);
        $this->toast(count($files) === 1 ? 'File uploaded.' : count($files).' files uploaded.');

        return back();
    }

    public function updateMedia(Request $request, Listing $listing, Media $media): RedirectResponse
    {
        $this->ensureOwns($listing, $media);
        $this->ensureEditable($listing);

        $alt = $request->validate(['alt' => ['required', 'string', 'max:200']])['alt'];
        $media->setCustomProperty('alt', $alt)->save();

        return back();
    }

    public function reorderMedia(Request $request, Listing $listing, string $collection): RedirectResponse
    {
        abort_unless(in_array($collection, ['photos', 'floor_plans'], true), 404);
        $this->ensureEditable($listing);

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $owned = $listing->getMedia($collection)->pluck('id')->all();

        abort_if(array_diff($ids, $owned) !== [] || count($ids) !== count($owned), 422);
        Media::setNewOrder($ids);

        return back();
    }

    public function destroyMedia(Listing $listing, Media $media, #[CurrentUser] User $actor): RedirectResponse
    {
        $this->ensureOwns($listing, $media);
        $this->ensureEditable($listing);

        $media->delete();
        Audit::record('listings', 'media_removed', "Removed {$media->file_name} from {$listing->title}", $listing, $actor);

        return back();
    }

    public function uploadDocument(Request $request, Listing $listing, DocumentType $type, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless($type->category_id === $listing->category_id, 404);
        $this->ensureEditable($listing);

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $type->accepted_formats), 'max:'.($type->max_size_mb * 1024)],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $path = $file->storeAs("listings/{$listing->ulid}/documents", Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');

        $document = $listing->documents()->create([
            'document_type_id' => $type->id,
            'path' => (string) $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);

        Audit::record('listings', 'document_uploaded', "Uploaded {$type->name} for {$listing->title}", $listing, $actor, ['document' => $document->original_name]);
        $this->toast('Document uploaded.');

        return back();
    }

    /** Staff only in Sprint 3; verified-bidder access and watermarking arrive in Sprint 4. */
    public function downloadDocument(Listing $listing, ListingDocument $document, #[CurrentUser] User $actor): StreamedResponse
    {
        abort_unless($document->listing_id === $listing->id, 404);

        Audit::record('listings', 'document_downloaded', "Downloaded {$document->original_name}", $listing, $actor);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroyDocument(Listing $listing, ListingDocument $document, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless($document->listing_id === $listing->id, 404);
        $this->ensureEditable($listing);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        Audit::record('listings', 'document_removed', "Removed {$document->original_name} from {$listing->title}", $listing, $actor);

        return back();
    }

    public function signOff(Request $request, Listing $listing, string $item, #[CurrentUser] User $actor): RedirectResponse
    {
        $item = ComplianceItem::tryFrom($item) ?? abort(404);
        $note = $request->validate(['note' => ['required', 'string', 'max:1000']])['note'];
        $wasComplete = DepositService::complianceComplete($listing);

        $listing->complianceItems()->updateOrCreate(['item' => $item->value], [
            'signed_off_by' => $actor->id, 'signed_off_at' => now(), 'note' => $note,
        ]);

        // The last legal check just completed: tell approved bidders who haven't paid that deposits are open.
        if (! $wasComplete && DepositService::complianceComplete($listing->fresh() ?? $listing)) {
            BidderApproval::with('user')->where('listing_id', $listing->id)->where('status', 'approved')
                ->whereDoesntHave('deposits', fn ($q) => $q->whereIn('status', ['received', 'applied']))->get()
                ->each(fn ($a) => $a->user->notify(new DepositsOpen($listing->title, route('deposits.show', $listing))));
        }

        Audit::record('compliance', 'signed_off', "Signed off “{$item->label()}” for {$listing->title}", $listing, $actor, ['item' => $item->value, 'note' => $note]);
        $this->toast('Signed off.');

        return back();
    }

    public function withdrawSignOff(Request $request, Listing $listing, string $item, #[CurrentUser] User $actor): RedirectResponse
    {
        $item = ComplianceItem::tryFrom($item) ?? abort(404);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];

        $listing->complianceItems()->where('item', $item->value)->delete();

        Audit::record('compliance', 'sign_off_withdrawn', "Withdrew sign-off of “{$item->label()}” for {$listing->title}", $listing, $actor, ['item' => $item->value, 'reason' => $reason]);

        return back();
    }

    private function ensureEditable(Listing $listing): void
    {
        abort_unless($listing->status->isEditable() || Gate::allows('listings.publish'), 403, __('This listing is locked while it is :status.', ['status' => strtolower($listing->status->label())]));
    }

    private function ensureOwns(Listing $listing, Media $media): void
    {
        abort_unless($media->model_type === $listing->getMorphClass() && (int) $media->model_id === $listing->id, 404);
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);
    }
}
