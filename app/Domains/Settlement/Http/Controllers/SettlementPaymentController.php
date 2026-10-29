<?php

namespace App\Domains\Settlement\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Actions\PaymentConfirmations;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Settlement\Actions\SettlementPayments;
use App\Domains\Settlement\Actions\SettlementService;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Settlement\Models\SettlementPayment;
use App\Domains\Verification\Support\SecureFiles;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Balance payments to Thabekhulu, buyer uploads for settlement, and two-person payment confirmations. */
class SettlementPaymentController extends Controller
{
    private const FILE = ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];

    // --- buyer -----------------------------------------------------------------------------

    public function balanceProof(Request $request, SettlementCase $case, #[CurrentUser] User $user, SettlementPayments $payments): RedirectResponse
    {
        $this->ownOpenCase($case, $user);
        $request->validate(['proof' => self::FILE]);
        $p = $payments->forCase($case);
        abort_if($p->status === 'received', 422, 'This balance is already paid.');
        /** @var UploadedFile $file */
        $file = $request->file('proof');
        SecureFiles::delete($p->proof_path);
        $p->forceFill(['proof_path' => SecureFiles::put($file, 'balance-proofs'), 'proof_mime' => (string) $file->getMimeType(), 'proof_uploaded_at' => now()])->save();
        Audit::record('settlement', 'balance_proof_uploaded', "Proof of balance payment uploaded for {$p->reference}", $case, $user);

        return $this->toast(__('Thank you. Your balance is confirmed once the payment reflects in our account.'));
    }

    public function agreement(Request $request, SettlementCase $case, #[CurrentUser] User $user): RedirectResponse
    {
        $this->ownOpenCase($case, $user);
        $request->validate(['document' => self::FILE]);
        /** @var UploadedFile $file */
        $file = $request->file('document');
        DB::table('settlement_uploads')->where('settlement_case_id', $case->id)->where('step', 'agreement_signed')->where('status', 'pending')->update(['status' => 'rejected', 'reason' => 'Replaced by a newer upload', 'updated_at' => now()]);
        DB::table('settlement_uploads')->insert(['settlement_case_id' => $case->id, 'step' => 'agreement_signed', 'path' => SecureFiles::put($file, 'settlement-uploads'),
            'mime' => (string) $file->getMimeType(), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('settlement', 'agreement_uploaded', 'Buyer uploaded the signed sale agreement', $case, $user);

        return $this->toast(__('Uploaded. We’ll check it and confirm.'));
    }

    // --- settlement staff --------------------------------------------------------------------

    public function file(int $upload, #[CurrentUser] User $actor): Response
    {
        $u = DB::table('settlement_uploads')->where('id', $upload)->first() ?? abort(404);
        Audit::record('settlement', 'upload_viewed', "Opened buyer upload #{$upload}", null, $actor);

        return $this->stream((string) $u->path, (string) $u->mime);
    }

    public function acceptUpload(int $upload, SettlementService $service, #[CurrentUser] User $actor): RedirectResponse
    {
        $u = DB::table('settlement_uploads')->where('id', $upload)->where('status', 'pending')->first() ?? abort(404);
        $case = SettlementCase::findOrFail((int) $u->settlement_case_id);
        $tmp = tempnam(sys_get_temp_dir(), 'set');
        file_put_contents($tmp, SecureFiles::read((string) $u->path));
        $document = new UploadedFile($tmp, 'buyer-upload.'.(str_contains((string) $u->mime, 'pdf') ? 'pdf' : 'jpg'), (string) $u->mime, null, true);
        $service->recordStep($case, (string) $u->step, now()->toDateString(), $document, 'Uploaded by the buyer, accepted', $actor);
        DB::table('settlement_uploads')->where('id', $upload)->update(['status' => 'accepted', 'reviewed_by' => $actor->id, 'updated_at' => now()]);

        return $this->toast(__('Accepted, and the step is recorded.'));
    }

    public function rejectUpload(Request $request, int $upload, #[CurrentUser] User $actor): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $n = DB::table('settlement_uploads')->where('id', $upload)->where('status', 'pending')->update(['status' => 'rejected', 'reason' => $reason, 'reviewed_by' => $actor->id, 'updated_at' => now()]);
        abort_if($n === 0, 404);
        Audit::record('settlement', 'upload_rejected', "Rejected buyer upload #{$upload}: {$reason}", null, $actor);

        return $this->toast(__('Rejected. The buyer sees the reason and can upload again.'));
    }

    // --- finance ---------------------------------------------------------------------------

    public function balanceProofFile(SettlementPayment $payment, #[CurrentUser] User $actor): Response
    {
        abort_if($payment->proof_path === null, 404);
        Audit::record('settlement', 'balance_proof_viewed', "Opened proof of balance payment for {$payment->reference}", $payment->case, $actor);

        return $this->stream((string) $payment->proof_path, (string) $payment->proof_mime);
    }

    public function requestConfirmation(Request $request, PaymentConfirmations $confirmations, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['deposit', 'balance'])], 'target' => ['required', 'string', 'max:26'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000000'], 'bank_reference' => ['required', 'string', 'max:120'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:500'],
        ]);
        $target = $data['kind'] === 'deposit' ? Deposit::where('ulid', $data['target'])->firstOrFail() : SettlementPayment::where('ulid', $data['target'])->firstOrFail();
        $confirmations->request($data['kind'], $target->id, (int) round($data['amount'] * 100), $data['bank_reference'], $data['paid_on'], $actor, $data['note'] ?? null);

        return $this->toast(__('Recorded. A second finance user must approve it before it counts.'));
    }

    public function approveConfirmation(int $confirmation, PaymentConfirmations $confirmations, #[CurrentUser] User $actor): RedirectResponse
    {
        $confirmations->approve($confirmation, $actor);

        return $this->toast(__('Approved. The payment is now confirmed.'));
    }

    public function rejectConfirmation(Request $request, int $confirmation, PaymentConfirmations $confirmations, #[CurrentUser] User $actor): RedirectResponse
    {
        $confirmations->reject($confirmation, $actor, $request->validate(['note' => ['required', 'string', 'max:500']])['note']);

        return $this->toast(__('Rejected.'));
    }

    // --- helpers -----------------------------------------------------------------------------

    private function ownOpenCase(SettlementCase $case, User $user): void
    {
        abort_unless($case->approval->user_id === $user->id && $case->status === 'open', 403);
    }

    private function stream(string $path, string $mime): Response
    {
        return response(SecureFiles::read($path), 200, ['Content-Type' => $mime, 'Content-Disposition' => 'inline', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
