<?php

namespace App\Domains\Verification\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Settings\Settings\AuctionSettings;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\BiddingEntity;
use App\Domains\Verification\Models\ProofOfFunds;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Models\VerificationDocument;
use App\Domains\Verification\Notifications\KycUpdate;
use App\Domains\Verification\Support\Countries;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * KYC review queue (kyc.view to see, kyc.approve / kyc.reject to decide).
 * Documents are decrypted only here and every opening is audit-logged.
 */
class KycReviewController extends Controller
{
    public const SCREENING_DAYS = 30;

    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() ?: 'review';

        $verifications = Verification::with('user:id,first_name,last_name,email')
            ->when($tab === 'review', fn ($q) => $q->whereIn('status', ['review', 'submitted']))
            ->when($tab === 'verified', fn ($q) => $q->where('status', 'verified'))
            ->when($tab === 'rejected', fn ($q) => $q->where('status', 'rejected'))
            ->oldest('submitted_at')->paginate(25)->withQueryString()
            ->through(fn (Verification $v): array => [
                'ulid' => $v->ulid, 'name' => $v->user->name, 'email' => $v->user->email, 'status' => $v->status,
                'statusLabel' => Verification::STATUSES[$v->status] ?? $v->status, 'documentType' => $v->document_type,
                'submittedAt' => $v->submitted_at?->toIso8601String(),
                'identity' => $v->checks['identity']['outcome'] ?? null,
            ]);

        return Inertia::render('admin/kyc/index', [
            'tab' => $tab,
            'verifications' => $verifications,
            'counts' => [
                'review' => Verification::whereIn('status', ['review', 'submitted'])->count(),
                'banks' => BankAccount::where('status', 'pending')->count(),
                'screening' => $this->screeningDue()->count(),
                'entities' => BiddingEntity::where('status', 'pending')->count(),
                'funds' => ProofOfFunds::where('status', 'pending')->count(),
            ],
            'entities' => $tab === 'entities' ? BiddingEntity::with('user:id,first_name,last_name')->where('status', 'pending')->oldest()->get()
                ->map(fn ($e) => ['ulid' => $e->ulid, 'name' => $e->name, 'type' => $e->type, 'person' => $e->user->name, 'createdAt' => $e->created_at?->toIso8601String()]) : [],
            'funds' => $tab === 'funds' ? ProofOfFunds::with('user:id,first_name,last_name')->where('status', 'pending')->oldest()->get()
                ->map(fn ($f) => ['id' => $f->id, 'person' => $f->user->name, 'amount' => $f->amount, 'url' => route('admin.kyc.funds-document', $f), 'createdAt' => $f->created_at?->toIso8601String()]) : [],
            'banks' => $tab === 'banks' ? BankAccount::with('user:id,first_name,last_name')->where('status', 'pending')->oldest()->get()->map(fn (BankAccount $b) => [
                'id' => $b->id, 'name' => $b->user->name, 'userId' => $b->user_id, 'bank' => $b->bank, 'holder' => $b->holder_name, 'last4' => $b->account_last4, 'createdAt' => $b->created_at?->toIso8601String(),
                'verification' => Verification::where('user_id', $b->user_id)->latest('id')->value('ulid'),
            ]) : [],
            'screeningDue' => $tab === 'screening' ? $this->screeningDue()->with('user:id,first_name,last_name')->get()->map(fn (Verification $v) => [
                'ulid' => $v->ulid, 'name' => $v->user->name,
                'lastScreened' => Screening::where('user_id', $v->user_id)->latest('screened_at')->value('screened_at'),
            ]) : [],
        ]);
    }

    public function show(Verification $verification): Response
    {
        $verification->load(['user', 'documents']);
        $user = $verification->user;
        $bank = BankAccount::where('user_id', $user->id)->latest('id')->first();

        return Inertia::render('admin/kyc/show', [
            'verification' => [
                'ulid' => $verification->ulid, 'status' => $verification->status, 'statusLabel' => Verification::STATUSES[$verification->status] ?? $verification->status,
                'name' => $user->name, 'email' => $user->email, 'mobile' => $user->mobile,
                'documentType' => $verification->document_type, 'idNumber' => $verification->id_number, 'passportCountry' => $verification->passport_country,
                'dateOfBirth' => $verification->date_of_birth?->format('j F Y'),
                'age' => $verification->date_of_birth?->age,
                'passportCountryName' => $verification->passport_country ? (Countries::forPassports()[$verification->passport_country] ?? $verification->passport_country) : null,
                'passportExpiresOn' => $verification->passport_expires_on?->format('j F Y'),
                'passportDaysLeft' => $verification->passport_expires_on ? (int) now()->startOfDay()->diffInDays($verification->passport_expires_on, false) : null,
                'settlementConflicts' => self::settlementConflicts($verification), 'maritalStatus' => $verification->marital_status !== null ? (BuyerVerificationController::MARITAL[$verification->marital_status] ?? $verification->marital_status) : null,
                'spouse' => $verification->spouse === null ? null : ['name' => $verification->spouse['name'] ?? null, 'idNumber' => $verification->spouse['id_number'] ?? null],
                'checks' => $verification->checks ?? [], 'rejectionReason' => $verification->rejection_reason, 'infoRequested' => $verification->info_requested,
                'submittedAt' => $verification->submitted_at?->toIso8601String(),
                'documents' => $verification->documents->map(fn (VerificationDocument $d): array => [
                    'id' => $d->id, 'kind' => $d->kind, 'mime' => $d->mime, 'url' => route('admin.kyc.document', [$verification, $d]),
                ]),
            ],
            'screenings' => Screening::with('screener:id,first_name,last_name')->where('user_id', $user->id)->latest('screened_at')->limit(10)->get()->map(fn (Screening $s): array => [
                'result' => $s->result, 'resultLabel' => Screening::RESULTS[$s->result], 'lists' => $s->lists, 'provider' => $s->provider,
                'note' => $s->note, 'by' => $s->screener?->name, 'at' => $s->screened_at->toIso8601String(),
            ]),
            'bank' => $bank === null ? null : [
                'id' => $bank->id, 'bank' => $bank->bank, 'branchCode' => $bank->branch_code, 'accountNumber' => $bank->account_number,
                'type' => BankAccount::TYPES[$bank->account_type] ?? $bank->account_type, 'holder' => $bank->holder_name, 'status' => $bank->status,
                'locked' => $bank->locked, 'proofUrl' => $bank->proof_path ? route('admin.kyc.bank-proof', $bank) : null,
            ],
            'screeningResults' => Screening::RESULTS,
            'can' => ['approve' => Gate::allows('kyc.approve'), 'reject' => Gate::allows('kyc.reject')],
        ]);
    }

    public function document(Verification $verification, VerificationDocument $document, #[CurrentUser] User $actor): HttpResponse
    {
        abort_unless($document->verification_id === $verification->id, 404);
        Audit::record('kyc', 'document_viewed', "Opened {$document->kind} of verification {$verification->ulid}", $verification, $actor);

        return response(BuyerVerificationController::read($document), 200, [
            'Content-Type' => $document->mime, 'Content-Disposition' => 'inline', 'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Verification $verification, #[CurrentUser] User $actor): RedirectResponse
    {
        $this->expectOpen($verification);

        $latest = Screening::where('user_id', $verification->user_id)->latest('screened_at')->first();
        if ($latest === null || $latest->screened_at->lt(now()->subDays(self::SCREENING_DAYS))) {
            throw ValidationException::withMessages(['decision' => __('Record a sanctions and PEP screening (within the last :days days) before approving.', ['days' => self::SCREENING_DAYS])]);
        }
        if ($latest->result !== 'clear') {
            throw ValidationException::withMessages(['decision' => __('The latest screening is not clear. Resolve it before approving.')]);
        }

        $verification->forceFill([
            'status' => 'verified', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'verified_at' => now(),
            'expires_at' => Carbon::now()->addMonths(24), 'rejection_reason' => null, 'info_requested' => null,
        ])->save();

        Audit::record('kyc', 'approved', "Approved verification of {$verification->user->name}", $verification, $actor);
        $verification->user->notify(new KycUpdate('verified'));

        return $this->done('Buyer verified.');
    }

    public function reject(Request $request, Verification $verification, #[CurrentUser] User $actor): RedirectResponse
    {
        $this->expectOpen($verification);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $verification->forceFill(['status' => 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'rejection_reason' => $reason])->save();

        Audit::record('kyc', 'rejected', "Rejected verification of {$verification->user->name}", $verification, $actor, ['reason' => $reason]);
        $verification->user->notify(new KycUpdate('rejected', $reason));

        return $this->done('Verification rejected; the buyer has been emailed.');
    }

    public function requestInfo(Request $request, Verification $verification, #[CurrentUser] User $actor): RedirectResponse
    {
        $this->expectOpen($verification);
        $message = $request->validate(['message' => ['required', 'string', 'max:1000']])['message'];

        // Reopen for the buyer to edit.
        $verification->forceFill(['status' => 'in_progress', 'info_requested' => $message])->save();

        Audit::record('kyc', 'info_requested', "Asked {$verification->user->name} for more information", $verification, $actor, ['message' => $message]);
        $verification->user->notify(new KycUpdate('info', $message));

        return $this->done('Request sent to the buyer.');
    }

    public function screen(Request $request, Verification $verification, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'result' => ['required', Rule::in(array_keys(Screening::RESULTS))],
            'lists' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'required_unless:result,clear', 'string', 'max:2000'],
        ], ['note.required_unless' => __('Explain the possible or confirmed match.')]);

        Screening::create($data + ['user_id' => $verification->user_id, 'provider' => 'manual', 'screened_by' => $actor->id, 'screened_at' => now()]);

        Audit::record('kyc', 'screened', "Screening of {$verification->user->name}: ".Screening::RESULTS[$data['result']], $verification, $actor, ['lists' => $data['lists']]);

        return $this->done('Screening recorded.');
    }

    public function bankProof(BankAccount $bank, #[CurrentUser] User $actor): HttpResponse
    {
        $path = $bank->proof_path ?? abort(404);
        Audit::record('kyc', 'bank_proof_viewed', "Opened bank proof of {$bank->user->name}", $bank, $actor);
        $raw = (string) base64_decode(Crypt::decryptString((string) Storage::disk('local')->get($path)), true);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw) ?: 'application/octet-stream';

        return response($raw, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'inline', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function decideBank(Request $request, BankAccount $bank, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['verify', 'reject'])], 'reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:500']]);
        abort_unless(Gate::allows($data['decision'] === 'verify' ? 'kyc.approve' : 'kyc.reject'), 403);

        if ($data['decision'] === 'verify') {
            $bank->forceFill(['status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now(), 'rejection_reason' => null])->save();
            $bank->user->notify(new KycUpdate('bank_verified'));
        } else {
            $bank->forceFill(['status' => 'rejected', 'rejection_reason' => $data['reason']])->save();
            $bank->user->notify(new KycUpdate('bank_rejected', $data['reason']));
        }

        Audit::record('kyc', "bank_{$data['decision']}", ($data['decision'] === 'verify' ? 'Verified' : 'Rejected')." bank account of {$bank->user->name}", $bank, $actor, ['reason' => $data['reason'] ?? null]);

        return $this->done($data['decision'] === 'verify' ? 'Bank account verified.' : 'Bank account rejected; the buyer has been emailed.');
    }

    /** @return Builder<Verification> */
    private function screeningDue(): Builder
    {
        $recent = Screening::where('screened_at', '>=', now()->subDays(self::SCREENING_DAYS))->select('user_id');

        return Verification::where('status', 'verified')->whereNotIn('user_id', $recent);
    }

    private function expectOpen(Verification $verification): void
    {
        if (! in_array($verification->status, ['submitted', 'review'], true)) {
            throw ValidationException::withMessages(['decision' => __('This verification is :status.', ['status' => strtolower(Verification::STATUSES[$verification->status] ?? $verification->status)])]);
        }
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return back();
    }

    /**
     * Auctions this buyer applied for whose settlement balance falls due after the passport expires.
     *
     * @return list<array{listing: string, settles: string}>
     */
    private static function settlementConflicts(Verification $verification): array
    {
        if ($verification->passport_expires_on === null) {
            return [];
        }
        $days = app(AuctionSettings::class)->settlement_balance_days;
        $out = [];
        foreach (BidderApproval::with('listing')->where('user_id', $verification->user_id)->whereIn('status', ['pending', 'approved'])->get() as $a) {
            $settles = $a->listing->ends_at?->copy()->addDays($days);
            if ($settles !== null && $settles->greaterThan($verification->passport_expires_on)) {
                $out[] = ['listing' => (string) $a->listing->title, 'settles' => $settles->format('j F Y')];
            }
        }

        return $out;
    }
}
