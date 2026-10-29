<?php

namespace App\Domains\Settlement\Console;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Settlement\Notifications\SettlementNotice;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Daily: expire runner-up offers; remind buyers 7 and 2 days before each settlement deadline. */
class SettlementDaily extends Command
{
    protected $signature = 'settlement:daily';

    protected $description = 'Expire runner-up offers and send settlement deadline reminders';

    public function handle(): int
    {
        $expired = 0;
        foreach (SettlementCase::with('listing')->where('status', 'offered')->where('offer_expires_at', '<=', now())->get() as $case) {
            $case->forceFill(['status' => 'expired'])->save();
            $case->listing->forceFill(['status' => ListingStatus::NotSold])->save(); // runner-up offer lapsed
            Audit::record('settlement', 'offer_expired', "Runner-up offer for {$case->listing->title} expired; their refund proceeds", $case);
            $expired++;
        }

        $today = CarbonImmutable::today((string) config('platform.display_timezone'));
        $reminded = 0;
        foreach (SettlementCase::with(['listing', 'approval.user'])->where('status', 'open')->get() as $case) {
            $done = $case->steps()->pluck('step')->all();
            foreach (['agreement_due' => 'agreement_signed', 'balance_due' => 'balance_paid'] as $field => $step) {
                $due = $case->{$field};
                if ($due === null || in_array($step, $done, true)) {
                    continue;
                }
                $days = (int) $today->diffInDays($due, false);
                if (in_array($days, [7, 2], true)) {
                    $case->approval->user->notify(new SettlementNotice('reminder', __('Reminder: :step by :date', ['step' => SettlementCase::STEPS[$step], 'date' => $due->format('j F Y')]), [
                        __('For :title: :step is due by :date.', ['title' => $case->listing->title, 'step' => strtolower(SettlementCase::STEPS[$step]), 'date' => $due->format('j F Y')]),
                        __('Missing a settlement deadline can lead to a default under the Rules of Auction.'),
                    ], route('account')));
                    $reminded++;
                }
            }
        }
        $this->info("{$expired} offers expired, {$reminded} reminders sent.");

        return self::SUCCESS;
    }
}
