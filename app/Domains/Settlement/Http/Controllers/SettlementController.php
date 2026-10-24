<?php

namespace App\Domains\Settlement\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Settlement\Actions\PayoutService;
use App\Domains\Settlement\Actions\SettlementService;
use App\Domains\Settlement\Models\Invoice;
use App\Domains\Settlement\Models\Payout;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Settlement\Models\SettlementDefault;
use App\Domains\Settlement\Models\SettlementStep;
use App\Domains\Verification\Support\SecureFiles;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Staff settlement (settlement.manage; auctions.settle declares defaults; settlement.confirm_default confirms). */
class SettlementController extends Controller
{
    private function mayView(): void
    {
        abort_unless(Gate::any(['settlement.manage', 'auctions.settle', 'settlement.confirm_default']), 403);
    }

    public function index(): Response
    {
        $this->mayView();
        $cases = SettlementCase::with(['listing:id,title', 'approval.user:id,first_name,last_name'])->latest('id')->limit(200)->get()
            ->map(fn (SettlementCase $c) => [
                'ulid' => $c->ulid, 'listing' => $c->listing->title, 'buyer' => '#'.$c->approval->bidder_number.' '.$c->approval->user->name,
                'kind' => $c->kind, 'price' => $c->price, 'status' => $c->status, 'statusLabel' => SettlementCase::STATUSES[$c->status],
                'missed' => $c->status === 'open' ? $c->missedDeadline() : null,
                'defaultPending' => SettlementDefault::where('settlement_case_id', $c->id)->where('status', 'proposed')->exists(),
            ])->sortByDesc(fn ($c) => ($c['missed'] ? 2 : 0) + ($c['defaultPending'] ? 1 : 0))->values();

        return Inertia::render('admin/settlement/index', ['cases' => $cases]);
    }

    public function show(SettlementCase $case): Response
    {
        $this->mayView();
        $case->load(['listing.category', 'approval.user', 'steps', 'default']);
        $deposit = Deposit::where('bidder_approval_id', $case->bidder_approval_id)->first();
        $d = $case->default;
        $tz = (string) config('platform.display_timezone');

        return Inertia::render('admin/settlement/show', [
            'case' => [
                'ulid' => $case->ulid, 'listing' => $case->listing->title, 'mining' => $case->listing->category->slug === 'mining', 'kind' => $case->kind,
                'buyer' => '#'.$case->approval->bidder_number.' '.$case->approval->user->name, 'email' => $case->approval->user->email,
                'price' => $case->price, 'status' => $case->status, 'statusLabel' => SettlementCase::STATUSES[$case->status],
                'agreementDue' => $case->agreement_due?->format('j F Y'), 'balanceDue' => $case->balance_due?->format('j F Y'),
                'offerExpires' => $case->offer_expires_at?->setTimezone($tz)->format('j F Y, H:i'), 'missed' => $case->status === 'open' ? $case->missedDeadline() : null,
                'depositTerms' => $case->listing->deposit_terms->value, 'held' => $deposit?->balance(), 'depositRef' => $deposit?->reference,
                'tenPercent' => intdiv($case->price * 100, 10),
            ],
            'steps' => collect(SettlementCase::STEPS)->map(function (string $label, string $key) use ($case) {
                $s = $case->steps->firstWhere('step', $key);

                return ['key' => $key, 'label' => $label, 'doneOn' => $s?->done_on->format('j F Y'), 'note' => $s?->note,
                    'document' => $s?->document_path ? route('admin.settlement.document', [$case, $s]) : null];
            })->values(),
            'default' => $d === null ? null : ['status' => $d->status, 'reason' => $d->reason, 'auctionCost' => $d->auction_cost, 'forfeit' => $d->proposed_forfeit,
                'securitySignoff' => $d->security_signoff, 'declaredBy' => User::find($d->declared_by)?->name, 'declaredByMe' => $d->declared_by === request()->user()?->id,
                'confirmedBy' => $d->confirmed_by ? User::find($d->confirmed_by)?->name : null],
            'can' => ['manage' => Gate::allows('settlement.manage'), 'declare' => Gate::allows('auctions.settle'), 'confirm' => Gate::allows('settlement.confirm_default'),
                'payout' => Gate::allows('payouts.record'), 'approvePayout' => Gate::allows('payouts.approve')],
            'money' => $case->status === 'completed' ? $this->money($case) : null,
            'transfer' => $case->status === 'completed' ? $this->transfer($case) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function money(SettlementCase $case): array
    {
        $p = Payout::where('settlement_case_id', $case->id)->first();
        $i = Invoice::where('settlement_case_id', $case->id)->first();

        return [
            'figures' => PayoutService::figures($case),
            'payout' => $p ? ['id' => $p->id, 'status' => $p->status, 'payee' => $p->payee, 'reference' => $p->payee_reference, 'amount' => $p->amount,
                'preparedByMe' => $p->prepared_by === request()->user()?->id, 'preparedBy' => User::find($p->prepared_by)?->name, 'approvedBy' => $p->approved_by ? User::find($p->approved_by)?->name : null] : null,
            'invoice' => $i ? ['number' => $i->number, 'total' => $i->total, 'url' => route('admin.settlement.invoice.pdf', $i)] : null,
        ];
    }

    /** @return array{stages: list<array{key: string, label: string, doneOn: string|null}>} */
    public static function transfer(SettlementCase $case): array
    {
        $list = $case->listing->category->slug === 'mining' ? SettlementCase::MINING_STAGES : SettlementCase::TRANSFER_STAGES;
        $done = DB::table('transfer_stages')->where('settlement_case_id', $case->id)->pluck('done_on', 'stage');

        $stages = [];
        foreach ($list as $key => $label) {
            $stages[] = ['key' => (string) $key, 'label' => (string) $label, 'doneOn' => isset($done[$key]) ? CarbonImmutable::parse((string) $done[$key])->format('j F Y') : null];
        }

        return ['stages' => $stages];
    }

    public function recordStep(Request $request, SettlementCase $case, SettlementService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'step' => ['required', Rule::in(array_keys(SettlementCase::STEPS))], 'done_on' => ['required', 'date', 'before_or_equal:today'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], 'note' => ['nullable', 'string', 'max:500'],
        ]);
        $service->recordStep($case, $data['step'], $data['done_on'], $request->file('document'), $data['note'] ?? null, $actor);

        return $this->done('Step recorded.');
    }

    public function complete(SettlementCase $case, SettlementService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $service->complete($case, $actor);

        return $this->done('Settlement completed.');
    }

    public function declareDefault(Request $request, SettlementCase $case, SettlementService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:800'], 'auction_cost' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'security_signoff' => ['boolean'],
        ], ['auction_cost.required' => __('Enter the actual auction cost (R); the forfeiture is capped by it.')]);
        $service->declareDefault($case, $data['reason'], (int) round(((float) $data['auction_cost']) * 100), (bool) ($data['security_signoff'] ?? false), $actor);

        return $this->done('Default declared. A second person must confirm it.');
    }

    public function confirmDefault(SettlementCase $case, SettlementService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $service->confirmDefault($case, $actor);

        return $this->done('Default confirmed: forfeiture recorded, any excess scheduled for refund, the runner-up offered the property.');
    }

    public function document(SettlementCase $case, SettlementStep $step, #[CurrentUser] User $actor): HttpResponse
    {
        $this->mayView();
        abort_unless($step->settlement_case_id === $case->id && $step->document_path !== null, 404);
        Audit::record('settlement', 'document_viewed', "Opened {$step->step} document", $case, $actor);

        return response(SecureFiles::read((string) $step->document_path), 200, ['Content-Type' => (string) $step->document_mime, 'Content-Disposition' => 'inline', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Buyer: their settlement cases and any runner-up offer. */
    public function mine(#[CurrentUser] User $user): Response
    {
        $tz = (string) config('platform.display_timezone');
        $cases = SettlementCase::with(['listing.category', 'steps', 'approval'])->whereHas('approval', fn ($q) => $q->where('user_id', $user->id))->latest('id')->get()
            ->map(fn (SettlementCase $c) => [
                'ulid' => $c->ulid, 'listing' => $c->listing->title, 'kind' => $c->kind, 'price' => $c->price, 'status' => $c->status, 'statusLabel' => SettlementCase::STATUSES[$c->status],
                'agreementDue' => $c->agreement_due?->format('j F Y'), 'balanceDue' => $c->balance_due?->format('j F Y'),
                'offerExpires' => $c->offer_expires_at?->setTimezone($tz)->format('j F Y, H:i'),
                'steps' => collect(SettlementCase::STEPS)->map(fn ($label, $key) => ['label' => $label, 'done' => $c->steps->contains('step', $key)])->values(),
                'transfer' => $c->status === 'completed' ? self::transfer($c)['stages'] : [],
            ]);

        return Inertia::render('account/settlements', ['cases' => $cases]);
    }

    public function accept(SettlementCase $case, SettlementService $service, #[CurrentUser] User $user): RedirectResponse
    {
        $service->acceptOffer($case, $user);

        return $this->done('Offer accepted. Our team will contact you about signing the sale agreement.');
    }

    public function decline(SettlementCase $case, SettlementService $service, #[CurrentUser] User $user): RedirectResponse
    {
        $service->declineOffer($case, $user);

        return $this->done('Offer declined. Your deposit will be refunded.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return back();
    }
}
