<?php

namespace App\Domains\Compliance\Console;

use App\Domains\Bidding\Support\ShillReport;
use App\Domains\DataRoom\Settings\DataRoomSettings;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hourly: gathers signals already recorded elsewhere into one review queue
 * (T7). Each signal is recorded once (fingerprint). Nothing is decided or
 * reported automatically.
 */
class ComplianceScan extends Command
{
    protected $signature = 'compliance:scan';

    protected $description = 'Gather suspicious-activity signals for review';

    private int $added = 0;

    public function handle(): int
    {
        // 1. Integrity flags (Sprint 6c) on live auctions and those closed in the last 30 days.
        $listings = Listing::whereIn('status', [ListingStatus::Live, ListingStatus::Closed, ListingStatus::Sold, ListingStatus::NoBids])->where('ends_at', '>=', now()->subDays(30))->get();
        foreach ($listings as $l) {
            foreach (ShillReport::for($l) as $f) {
                $this->add('integrity', 'integrity:'.$l->id.':'.md5($f['kind'].implode('|', $f['bidders'])), $l->id, null,
                    "{$f['kind']} on {$l->title}: {$f['detail']}", ['bidders' => $f['bidders']]);
            }
        }

        // 2. Screening results that are not clear (Sprint 4b).
        foreach (DB::table('screenings')->where('result', '!=', 'clear')->get() as $s) {
            $this->add('screening', 'screening:'.$s->id, null, (int) $s->user_id, 'Sanctions/PEP screening: '.str_replace('_', ' ', (string) $s->result), ['lists' => $s->lists ?? null, 'screened_at' => $s->screened_at]);
        }

        // 3. Heavy data-room downloading: the Sprint 4c daily alert threshold, per user per listing per day.
        $limit = app(DataRoomSettings::class)->daily_download_alert;
        $rows = DB::table('document_downloads')->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('user_id, listing_id, DATE(created_at) as day, count(*) as n')->groupBy('user_id', 'listing_id', DB::raw('DATE(created_at)'))->havingRaw('count(*) >= ?', [$limit])->get();
        foreach ($rows as $r) {
            $this->add('downloads', "downloads:{$r->user_id}:{$r->listing_id}:{$r->day}", (int) $r->listing_id, (int) $r->user_id, "{$r->n} data-room downloads in one day ({$r->day})", ['threshold' => $limit]);
        }

        // 4. Bank lines still needing attention after 3 days (Sprint 5a).
        foreach (DB::table('bank_lines')->whereIn('status', ['unmatched', 'short', 'over'])->where('created_at', '<=', now()->subDays(3))->get() as $b) {
            $this->add('bank_line', 'bank_line:'.$b->id, null, null, 'Bank line unresolved for 3+ days: R '.number_format(((int) $b->amount) / 100, 2, '.', ' ').' ('.$b->status.')', ['description' => $b->description, 'reference_found' => $b->reference_found, 'posted_on' => $b->posted_on]);
        }

        // 5. Confirmed settlement defaults (Sprint 7a).
        foreach (DB::table('settlement_defaults as d')->join('settlement_cases as c', 'c.id', '=', 'd.settlement_case_id')->join('bidder_approvals as a', 'a.id', '=', 'c.bidder_approval_id')
            ->where('d.status', 'confirmed')->get(['d.id', 'd.reason', 'c.listing_id', 'a.user_id']) as $d) {
            $this->add('default', 'default:'.$d->id, (int) $d->listing_id, (int) $d->user_id, 'Settlement default confirmed', ['reason' => $d->reason]);
        }

        // 6. Repeated sign-in lockouts for one email (audit log), last 7 days.
        $lockouts = DB::table('activity_log')->where('event', 'lockout')->where('created_at', '>=', now()->subDays(7))->get()
            ->groupBy(fn ($e) => strtolower((string) (json_decode((string) $e->properties, true)['email'] ?? '')))->filter(fn ($g, $email) => $email !== '' && $g->count() >= 3);
        foreach ($lockouts as $email => $group) {
            $this->add('lockout', 'lockout:'.md5($email).':'.now()->format('oW'), null, DB::table('users')->where('email', $email)->value('id'), "{$group->count()} sign-in lockouts for {$email} this week", ['email' => $email]);
        }

        // 7. Bid confirmation codes guessed wrongly 5 times (Sprint 6a).
        foreach (DB::table('bid_otps')->where('attempts', '>=', 5)->get() as $o) {
            $this->add('bid_code', 'bid_code:'.$o->id, (int) $o->listing_id, (int) $o->user_id, 'Bid confirmation code entered wrongly 5 times', []);
        }

        $this->info("{$this->added} new items for review.");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $details */
    private function add(string $source, string $fingerprint, ?int $listingId, ?int $userId, string $summary, array $details): void
    {
        $this->added += DB::table('suspicious_items')->insertOrIgnore([
            'ulid' => (string) Str::ulid(), 'source' => $source, 'fingerprint' => mb_substr($fingerprint, 0, 190), 'listing_id' => $listingId, 'user_id' => $userId,
            'summary' => mb_substr($summary, 0, 500), 'details' => json_encode($details), 'status' => 'open', 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
