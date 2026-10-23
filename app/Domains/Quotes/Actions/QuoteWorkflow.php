<?php

namespace App\Domains\Quotes\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Quotes\Models\Quote;
use App\Domains\Quotes\Notifications\QuoteActivity;
use App\Domains\Quotes\Notifications\QuoteMail;
use App\Domains\Quotes\Settings\QuotationSettings;
use App\Domains\Quotes\Support\QuoteFingerprint;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Quotation status changes (sprint 3Q spec 5). Each change is stored as a
 * quote event and in the audit log.
 */
class QuoteWorkflow
{
    public function __construct(private readonly QuotationSettings $settings) {}

    public function needsApproval(Quote $quote): bool
    {
        return $quote->once_total > $this->settings->approval_threshold * 100;
    }

    public function submitForApproval(User $actor, Quote $quote): void
    {
        $this->expect($quote, ['draft']);
        $this->ensureHasLines($quote);
        $this->move($quote, 'awaiting_approval', 'submitted', $actor);
    }

    public function approve(User $approver, Quote $quote): void
    {
        $this->expect($quote, ['awaiting_approval']);
        if ($quote->created_by === $approver->id) {
            throw ValidationException::withMessages(['quote' => __('You can’t approve a quotation you prepared. Ask another approver.')]);
        }

        $quote->approved_by = $approver->id;
        $this->move($quote, 'approved', 'approved', $approver);
    }

    public function returnToDraft(User $approver, Quote $quote, string $reason): void
    {
        $this->expect($quote, ['awaiting_approval']);
        $this->move($quote, 'draft', 'returned', $approver, $reason);
    }

    public function send(User $actor, Quote $quote, ?string $message, ?string $cc): void
    {
        $this->expect($quote, ['draft', 'approved']);
        $this->ensureHasLines($quote);
        if ($quote->status === 'draft' && $this->needsApproval($quote)) {
            throw ValidationException::withMessages(['quote' => __('This quotation is above R :amount and needs approval before it is sent.', ['amount' => number_format($this->settings->approval_threshold, 0, '.', ' ')])]);
        }

        $quote->load(['lines', 'milestones', 'payments', 'client']);
        $quote->fingerprint = QuoteFingerprint::of($quote);
        $quote->sent_at = Carbon::now();
        $this->move($quote, 'sent', 'sent', $actor, $cc ? "Copied to {$cc}" : null);

        $route = Notification::route('mail', $quote->client->email);
        $route->notify(new QuoteMail($quote, 'sent', $this->link($quote), $message));
        if ($cc) {
            Notification::route('mail', $cc)->notify(new QuoteMail($quote, 'sent', $this->link($quote), $message));
        }
    }

    public function markViewed(Quote $quote, ?string $ip): void
    {
        $quote->events()->create(['type' => 'viewed', 'ip_address' => $ip, 'created_at' => now()]);

        if ($quote->status === 'sent') {
            $quote->forceFill(['status' => 'viewed'])->save();
            $this->notifyCreator($quote, 'viewed');
        }
    }

    public function accept(Quote $quote, string $name, ?string $ip, ?string $agent): void
    {
        DB::transaction(function () use ($quote, $name, $ip, $agent): void {
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $this->ensureOpen($locked);

            if (! hash_equals((string) $locked->fingerprint, QuoteFingerprint::of($locked))) {
                throw ValidationException::withMessages(['quote' => __('This quotation has changed since it was sent. Please ask Thabekhulu for the latest version.')]);
            }

            $locked->forceFill([
                'status' => 'accepted', 'accepted_name' => $name, 'accepted_at' => now(),
                'accepted_ip' => $ip, 'accepted_agent' => mb_substr((string) $agent, 0, 255),
            ])->save();
            $locked->events()->create(['type' => 'accepted', 'ip_address' => $ip, 'note' => $name, 'created_at' => now()]);
            $quote->setRawAttributes($locked->getAttributes());
        });

        Audit::record('quotes', 'accepted', "{$name} accepted quotation {$quote->reference()}", $quote, null, ['fingerprint' => $quote->fingerprint]);
        $quote->load(['lines', 'milestones', 'payments', 'client']);
        Notification::route('mail', $quote->client->email)->notify(new QuoteMail($quote, 'accepted', $this->link($quote)));
        $this->notifyCreator($quote, 'accepted');
    }

    public function decline(Quote $quote, ?string $reason, ?string $ip): void
    {
        $this->ensureOpen($quote);
        $quote->decline_reason = $reason !== null ? mb_substr(strip_tags($reason), 0, 500) : null;
        $this->move($quote, 'declined', 'declined', null, $reason, $ip);
        $this->notifyCreator($quote, 'declined');
    }

    /** Copies a sent, declined or expired quotation into a new draft version; the old one is superseded. */
    public function newVersion(User $actor, Quote $quote): Quote
    {
        $this->expect($quote, ['sent', 'viewed', 'declined', 'expired']);
        $quote->load(['lines', 'milestones', 'payments']);

        return DB::transaction(function () use ($actor, $quote): Quote {
            $next = $quote->replicate(['ulid', 'status', 'fingerprint', 'accepted_name', 'accepted_at', 'accepted_ip', 'accepted_agent', 'decline_reason', 'sent_at', 'approved_by']);
            $next->fill([
                'version' => (int) Quote::where('number', $quote->number)->max('version') + 1, 'status' => 'draft',
                'created_by' => $actor->id, 'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays($this->settings->validity_days)->toDateString(),
            ])->save();

            foreach (['lines', 'milestones', 'payments'] as $relation) {
                foreach ($quote->{$relation} as $row) {
                    $next->{$relation}()->create(collect($row->getAttributes())->except(['id', 'quote_id'])->all());
                }
            }

            if ($quote->status !== 'expired') {
                $this->move($quote, 'superseded', 'superseded', $actor, "Replaced by {$next->reference()}");
            }
            $next->events()->create(['type' => 'created', 'user_id' => $actor->id, 'note' => "New version of {$quote->reference()}", 'created_at' => now()]);
            Audit::record('quotes', 'new_version', "Created {$next->reference()} from {$quote->reference()}", $next, $actor);

            return $next;
        });
    }

    /**
     * Daily: expire open quotations past their date and send one reminder before expiry.
     *
     * @return array{expired: int, reminded: int}
     */
    public function daily(): array
    {
        $expired = 0;
        $reminded = 0;
        $today = now((string) config('platform.display_timezone'))->toDateString();

        Quote::whereIn('status', ['sent', 'viewed'])->whereDate('valid_until', '<', $today)->each(function (Quote $q) use (&$expired): void {
            $this->move($q, 'expired', 'expired', null);
            $expired++;
        });

        $remindOn = now((string) config('platform.display_timezone'))->addDays($this->settings->reminder_days)->toDateString();
        Quote::with('client')->whereIn('status', ['sent', 'viewed'])->whereDate('valid_until', $remindOn)
            ->whereDoesntHave('events', fn ($e) => $e->where('type', 'reminded'))
            ->each(function (Quote $q) use (&$reminded): void {
                Notification::route('mail', $q->client->email)->notify(new QuoteMail($q, 'reminder', $this->link($q)));
                $q->events()->create(['type' => 'reminded', 'created_at' => now()]);
                $reminded++;
            });

        return ['expired' => $expired, 'reminded' => $reminded];
    }

    /** Signed link valid until the end of the quotation's last valid day (decision Q2). */
    public function link(Quote $quote): string
    {
        return URL::temporarySignedRoute('quotes.public.show', $quote->valid_until->copy()->endOfDay()->addDay(), ['quote' => $quote->ulid]);
    }

    private function ensureOpen(Quote $quote): void
    {
        if (! in_array($quote->status, ['sent', 'viewed'], true)) {
            throw ValidationException::withMessages(['quote' => __('This quotation is no longer open (:status).', ['status' => strtolower(Quote::STATUSES[$quote->status] ?? $quote->status)])]);
        }
        if ($quote->valid_until->endOfDay()->isPast()) {
            throw ValidationException::withMessages(['quote' => __('This quotation has expired. Please ask Thabekhulu for an updated quotation.')]);
        }
    }

    private function ensureHasLines(Quote $quote): void
    {
        if (! $quote->lines()->where('kind', 'item')->exists()) {
            throw ValidationException::withMessages(['quote' => __('Add at least one line item first.')]);
        }
    }

    /** @param list<string> $allowed */
    private function expect(Quote $quote, array $allowed): void
    {
        if (! in_array($quote->status, $allowed, true)) {
            throw ValidationException::withMessages(['quote' => __('This can’t be done while the quotation is :status.', ['status' => strtolower(Quote::STATUSES[$quote->status] ?? $quote->status)])]);
        }
    }

    private function move(Quote $quote, string $status, string $event, ?User $actor, ?string $note = null, ?string $ip = null): void
    {
        $from = $quote->status;
        $quote->status = $status;
        $quote->save();
        $quote->events()->create(['type' => $event, 'user_id' => $actor?->id, 'ip_address' => $ip, 'note' => $note, 'created_at' => now()]);

        Audit::record('quotes', $event, "Quotation {$quote->reference()}: ".(Quote::STATUSES[$from] ?? $from).' → '.Quote::STATUSES[$status], $quote, $actor, [
            'old' => ['status' => $from], 'attributes' => ['status' => $status], 'note' => $note,
        ]);
    }

    private function notifyCreator(Quote $quote, string $event): void
    {
        $quote->loadMissing('creator', 'client');
        if ($quote->creator !== null) {
            $quote->creator->notify(new QuoteActivity($quote, $event));
        }
    }
}
