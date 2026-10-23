<?php

namespace App\Domains\Quotes\Http\Controllers\Public;

use App\Domains\Quotes\Actions\QuoteWorkflow;
use App\Domains\Quotes\Models\Quote;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/** "My account → Quotes": quotations sent to a registered buyer. */
class AccountQuotesController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, QuoteWorkflow $workflow): Response
    {
        $quotes = Quote::whereIn('status', ['sent', 'viewed', 'accepted', 'declined', 'expired'])
            ->whereHas('client', fn (Builder $c) => $c->where('user_id', $user->id)->orWhere('email', strtolower($user->email)))
            ->latest('sent_at')->get()
            ->map(fn (Quote $q): array => [
                'reference' => $q->reference(), 'title' => $q->title, 'status' => Quote::STATUSES[$q->status] ?? $q->status,
                'total' => $q->once_total, 'validUntil' => $q->valid_until->format('j F Y'), 'url' => $workflow->link($q),
            ]);

        return Inertia::render('account/quotes', ['quotes' => $quotes]);
    }
}
