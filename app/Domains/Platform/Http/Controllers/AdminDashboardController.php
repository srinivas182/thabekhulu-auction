<?php

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Listings\Enums\ComplianceItem;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Platform\Health\HealthCheck;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Verification;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The back office home: what needs attention now (with links), then the key figures.
 * Every figure is shown only to staff whose role allows the screen it comes from.
 */
class AdminDashboardController extends Controller
{
    public function __invoke(HealthCheck $health): Response
    {
        $attention = [];
        $groups = [];
        $add = function (string $label, int $count, string $href, bool $urgent = false) use (&$attention): void {
            if ($count > 0) {
                $attention[] = ['label' => $label, 'count' => $count, 'href' => $href, 'urgent' => $urgent];
            }
        };
        $monthStart = now()->startOfMonth();

        if (Gate::allows('bidders.approve')) {
            $pending = BidderApproval::where('status', 'pending');
            $old = (clone $pending)->where('created_at', '<=', now()->subDays(2))->count();
            $add('Applications to bid waiting for approval', (clone $pending)->count(), route('admin.bidders.queue'), $old > 0);
            if ($old > 0) {
                $add('of them waiting more than 2 days', $old, route('admin.bidders.queue'), true);
            }
        }
        if (Gate::allows('kyc.view')) {
            $add('Identity verifications to review', Verification::whereIn('status', ['submitted', 'review'])->count(), route('admin.kyc.index'));
            $add('Refund bank accounts to verify', BankAccount::where('status', 'pending')->count(), route('admin.kyc.index', ['tab' => 'banks']));
        }
        if (Gate::allows('deposits.reconcile')) {
            $add('Payments waiting for a second approval', DB::table('payment_confirmations')->where('status', 'pending')->count(), route('admin.finance.index'));
        }
        if (Gate::allows('settlement.manage')) {
            $add('Buyer settlement uploads to review', DB::table('settlement_uploads')->where('status', 'pending')->count(), route('admin.settlement.index'));
        }
        if (Gate::allows('compliance.review') || Gate::allows('compliance.signoff')) {
            $total = count(ComplianceItem::cases());
            $incomplete = Listing::whereIn('status', [ListingStatus::Scheduled, ListingStatus::Live])->withCount(['complianceItems'])->get()
                ->filter(fn (Listing $l) => $l->getAttribute('compliance_items_count') < $total)->count();
            $add('Public listings with legal checks incomplete (deposits closed)', $incomplete, route('admin.listings.index'), true);
        }
        if (Gate::allows('compliance.review')) {
            $add('Suspicious-activity alerts open', DB::table('suspicious_items')->where('status', 'open')->count(), route('admin.compliance.index'), true);
        }
        if (Gate::allows('enquiries.view')) {
            $add('New enquiries', DB::table('enquiries')->where('status', 'new')->count(), route('admin.enquiries.index'));
        }
        if (Gate::allows('leads.view')) {
            $add('New seller leads', DB::table('leads')->where('status', 'new')->count(), route('admin.leads.index'));
        }

        if (Gate::allows('listings.view')) {
            $groups[] = ['title' => 'Auctions', 'href' => route('admin.listings.index'), 'items' => [
                ['label' => 'Live now', 'value' => Listing::where('status', ListingStatus::Live)->count()],
                ['label' => 'Closing within 24 hours', 'value' => Listing::where('status', ListingStatus::Live)->whereBetween('ends_at', [now(), now()->addDay()])->count()],
                ['label' => 'Coming soon', 'value' => Listing::where('status', ListingStatus::Scheduled)->count()],
                ['label' => 'Under offer', 'value' => Listing::where('status', ListingStatus::UnderOffer)->count()],
                ['label' => 'Sold this month', 'value' => DB::table('settlement_cases')->where('status', 'completed')->where('completed_at', '>=', $monthStart)->count()],
            ]];
        }
        if (Gate::allows('buyers.view') || Gate::allows('kyc.view')) {
            $buyers = User::where('type', 'buyer');
            $groups[] = ['title' => 'Buyers', 'href' => route('admin.kyc.index'), 'items' => [
                ['label' => 'Registered', 'value' => (clone $buyers)->count()],
                ['label' => 'New this month', 'value' => (clone $buyers)->where('created_at', '>=', $monthStart)->count()],
                ['label' => 'Identity verified', 'value' => Verification::where('status', 'verified')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->distinct()->count('user_id')],
                ['label' => 'Approved bidders (all auctions)', 'value' => BidderApproval::where('status', 'approved')->count()],
            ]];
        }
        if (Gate::allows('deposits.view')) {
            $groups[] = ['title' => 'Money', 'href' => route('admin.finance.index'), 'items' => [
                ['label' => 'Deposits held', 'value' => (int) Deposit::where('status', 'received')->sum('amount'), 'cents' => true],
                ['label' => 'Deposits awaiting payment', 'value' => Deposit::whereIn('status', ['awaiting', 'part_paid'])->count()],
                ['label' => 'Refunds scheduled', 'value' => Deposit::where('status', 'refund_scheduled')->count()],
            ]];
        }
        if (Gate::allows('settlement.manage') || Gate::allows('auctions.settle')) {
            $groups[] = ['title' => 'Settlement', 'href' => route('admin.settlement.index'), 'items' => [
                ['label' => 'Sales in progress', 'value' => DB::table('settlement_cases')->where('status', 'open')->count()],
                ['label' => 'Runner-up offers open', 'value' => DB::table('settlement_cases')->where('status', 'offered')->count()],
                ['label' => 'Completed this month', 'value' => DB::table('settlement_cases')->where('status', 'completed')->where('completed_at', '>=', $monthStart)->count()],
            ]];
        }

        return Inertia::render('admin/dashboard', [
            'attention' => $attention,
            'groups' => $groups,
            'health' => $health->run(),
        ]);
    }
}
