<?php

namespace App\Domains\Quotes\Http\Controllers;

use App\Domains\Quotes\Actions\QuoteWorkflow;
use App\Domains\Quotes\Models\Quote;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class QuoteWorkflowController extends Controller
{
    public function __construct(private readonly QuoteWorkflow $workflow) {}

    public function submit(Quote $quote, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeView($quote, $user);
        $this->workflow->submitForApproval($user, $quote);

        return $this->done('Sent for approval.');
    }

    public function approve(Quote $quote, #[CurrentUser] User $user): RedirectResponse
    {
        $this->workflow->approve($user, $quote);

        return $this->done('Quotation approved.');
    }

    public function returnToDraft(Request $request, Quote $quote, #[CurrentUser] User $user): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $this->workflow->returnToDraft($user, $quote, $reason);

        return $this->done('Returned to the preparer.');
    }

    public function send(Request $request, Quote $quote, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeView($quote, $user);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:2000'], 'cc' => ['nullable', 'email', 'max:255']]);
        $this->workflow->send($user, $quote, isset($data['message']) ? strip_tags($data['message']) : null, $data['cc'] ?? null);

        return $this->done('Quotation sent to the client.');
    }

    public function newVersion(Quote $quote, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeView($quote, $user);
        $next = $this->workflow->newVersion($user, $quote);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Created :ref.', ['ref' => $next->reference()])]);

        return redirect()->route('admin.quotes.edit', $next);
    }

    private function authorizeView(Quote $quote, User $user): void
    {
        abort_unless(Gate::allows('quotes.view_all') || $quote->created_by === $user->id, 403);
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return back();
    }
}
