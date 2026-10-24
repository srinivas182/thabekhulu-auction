<?php

namespace App\Domains\Privacy\Support;

use App\Domains\Verification\Support\SecureFiles;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data retention (Sprint 9a, decision H6). Each category says what is kept,
 * for how long and on what basis, finds what is due, and can purge it.
 * The schedule is a proposal until Thabekhulu's attorney approves it; until
 * then the job only reports. Financial records (ledger, receipts, invoices,
 * payouts, refunds) are never touched here: tax law requires keeping them.
 */
final class RetentionSchedule
{
    public const KYC_MONTHS = 60;

    /** Deposits and settlements that keep a relationship open. */
    private const OPEN_DEPOSITS = ['awaiting', 'part_paid', 'received', 'refund_scheduled'];

    /** @return array<string, array{label: string, kept: string, basis: string}> */
    public static function categories(): array
    {
        return [
            'kyc' => ['label' => 'KYC records and their files', 'kept' => '5 years after the relationship ends', 'basis' => 'FIC Act record-keeping (to confirm)'],
            'downloads' => ['label' => 'Data-room download logs', 'kept' => '5 years', 'basis' => 'Evidence kept with KYC records (to confirm)'],
            'leads' => ['label' => 'Leads never converted', 'kept' => '2 years after last activity', 'basis' => 'POPIA: no longer than necessary (to confirm)'],
            'newsletter_pending' => ['label' => 'Unconfirmed newsletter sign-ups', 'kept' => '30 days', 'basis' => 'POPIA (to confirm)'],
            'newsletter_unsubscribed' => ['label' => 'Unsubscribed newsletter records', 'kept' => '2 years', 'basis' => 'POPIA (to confirm)'],
            'ticket_holders' => ['label' => 'Ticket holders’ details (anonymised; amounts kept)', 'kept' => '2 years after the event', 'basis' => 'POPIA; payment records kept for tax (to confirm)'],
        ];
    }

    /**
     * Buyers and sellers whose relationship ended more than 5 years ago:
     * deactivated, or not signed in since; never anyone with an open deposit or settlement.
     *
     * @return Collection<int, int>
     */
    public static function endedRelationships(): Collection
    {
        $cutoff = CarbonImmutable::now()->subMonths(self::KYC_MONTHS);

        return DB::table('users')->whereIn('type', ['buyer', 'seller'])
            ->where(fn ($q) => $q->where('deactivated_at', '<=', $cutoff)
                ->orWhere(fn ($w) => $w->whereNull('deactivated_at')->whereRaw('COALESCE(last_login_at, created_at) <= ?', [$cutoff])))
            ->whereNotExists(fn ($q) => $q->from('deposits')->whereColumn('deposits.user_id', 'users.id')->whereIn('status', self::OPEN_DEPOSITS))
            ->whereNotExists(fn ($q) => $q->from('settlement_cases')->join('bidder_approvals', 'bidder_approvals.id', '=', 'settlement_cases.bidder_approval_id')
                ->whereColumn('bidder_approvals.user_id', 'users.id')->whereIn('settlement_cases.status', ['open', 'offered']))
            ->pluck('id');
    }

    /** @return array<string, int> category => records due now */
    public static function due(): array
    {
        $users = self::endedRelationships();
        $now = CarbonImmutable::now();

        return [
            'kyc' => DB::table('verifications')->whereIn('user_id', $users)->count() + DB::table('bank_accounts')->whereIn('user_id', $users)->count()
                + DB::table('screenings')->whereIn('user_id', $users)->count() + DB::table('bidding_entities')->whereIn('user_id', $users)->count()
                + DB::table('proofs_of_funds')->whereIn('user_id', $users)->count(),
            'downloads' => DB::table('document_downloads')->where('created_at', '<=', $now->subMonths(self::KYC_MONTHS))->count(),
            'leads' => DB::table('leads')->whereNull('listing_id')->where('status', '!=', 'converted')->where('updated_at', '<=', $now->subMonths(24))->count(),
            'newsletter_pending' => DB::table('newsletter_subscribers')->where('status', 'pending')->where('created_at', '<=', $now->subDays(30))->count(),
            'newsletter_unsubscribed' => DB::table('newsletter_subscribers')->where('status', 'unsubscribed')->where('unsubscribed_at', '<=', $now->subMonths(24))->count(),
            'ticket_holders' => DB::table('ticket_orders')->join('events', 'events.id', '=', 'ticket_orders.event_id')->where('events.ends_at', '<=', $now->subMonths(24))
                ->where('ticket_orders.email', 'not like', 'removed+%')->count(),
        ];
    }

    /**
     * Deletes or anonymises what is due. Called only when purging has been approved.
     *
     * @return array<string, int>
     */
    public static function purge(): array
    {
        $users = self::endedRelationships();
        $now = CarbonImmutable::now();
        $done = [];

        // KYC: files first, then rows. Users and their financial records remain.
        $files = DB::table('verification_documents')->whereIn('verification_id', DB::table('verifications')->whereIn('user_id', $users)->pluck('id'))->pluck('path')
            ->merge(DB::table('entity_documents')->whereIn('bidding_entity_id', DB::table('bidding_entities')->whereIn('user_id', $users)->pluck('id'))->pluck('path'))
            ->merge(DB::table('proofs_of_funds')->whereIn('user_id', $users)->pluck('path'))
            ->merge(DB::table('bank_accounts')->whereIn('user_id', $users)->whereNotNull('proof_path')->pluck('proof_path'));
        foreach ($files as $path) {
            SecureFiles::delete((string) $path);
        }
        $done['kyc'] = DB::transaction(function () use ($users) {
            $n = 0;
            foreach (['verifications', 'screenings', 'bank_accounts', 'bidding_entities', 'proofs_of_funds'] as $table) {
                $n += DB::table($table)->whereIn('user_id', $users)->delete(); // child document rows cascade
            }

            return $n;
        });

        $done['downloads'] = DB::table('document_downloads')->where('created_at', '<=', $now->subMonths(self::KYC_MONTHS))->delete();
        $done['leads'] = DB::table('leads')->whereNull('listing_id')->where('status', '!=', 'converted')->where('updated_at', '<=', $now->subMonths(24))->delete();
        $done['newsletter_pending'] = DB::table('newsletter_subscribers')->where('status', 'pending')->where('created_at', '<=', $now->subDays(30))->delete();
        $done['newsletter_unsubscribed'] = DB::table('newsletter_subscribers')->where('status', 'unsubscribed')->where('unsubscribed_at', '<=', $now->subMonths(24))->delete();
        // Row by row (portable across databases): amounts and references stay for the accounts.
        $done['ticket_holders'] = 0;
        foreach (DB::table('ticket_orders')->whereIn('event_id', DB::table('events')->where('ends_at', '<=', $now->subMonths(24))->pluck('id'))
            ->where('email', 'not like', 'removed+%')->get(['id', 'ulid']) as $o) {
            $done['ticket_holders'] += DB::table('ticket_orders')->where('id', $o->id)->update(['name' => 'Removed', 'mobile' => null, 'email' => "removed+{$o->ulid}@invalid"]);
        }

        return $done;
    }
}
