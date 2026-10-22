<?php

namespace App\Domains\Listings\Http\Controllers;

use App\Domains\Listings\Actions\ListingWorkflow;
use App\Domains\Listings\Models\Listing;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ListingWorkflowController extends Controller
{
    public function __construct(private readonly ListingWorkflow $workflow) {}

    public function submit(Listing $listing, #[CurrentUser] User $actor): RedirectResponse
    {
        $this->workflow->submit($actor, $listing);

        return $this->done('Submitted for review.');
    }

    public function approve(Listing $listing, #[CurrentUser] User $actor): RedirectResponse
    {
        $this->workflow->approve($actor, $listing);

        return $this->done('Approved and published.');
    }

    public function requestChanges(Request $request, Listing $listing, #[CurrentUser] User $actor): RedirectResponse
    {
        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];
        $this->workflow->requestChanges($actor, $listing, $note);

        return $this->done('Sent back with your comments.');
    }

    public function withdraw(Request $request, Listing $listing, #[CurrentUser] User $actor): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $this->workflow->withdraw($actor, $listing, $reason);

        return $this->done('Listing withdrawn.');
    }

    public function reopen(Listing $listing, #[CurrentUser] User $actor): RedirectResponse
    {
        $this->workflow->reopen($actor, $listing);

        return $this->done('Listing reopened as a draft.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return back();
    }
}
