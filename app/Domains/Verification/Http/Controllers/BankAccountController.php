<?php

namespace App\Domains\Verification\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BankMessage;
use App\Domains\Verification\Models\Verification;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The buyer's refund bank account (step 3 of "Register to bid").
 * Refunds only go to an account in the bidder's own name, and the account
 * can't be changed while a refund or deposit is pending.
 */
class BankAccountController extends Controller
{
    public function show(#[CurrentUser] User $user): Response
    {
        $bank = BankAccount::where('user_id', $user->id)->latest('id')->first();

        return Inertia::render('account/bank', [
            'account' => $bank === null ? null : [
                'bank' => $bank->bank, 'branchCode' => $bank->branch_code, 'last4' => $bank->account_last4, 'type' => $bank->account_type,
                'holder' => $bank->holder_name, 'status' => $bank->status, 'rejectionReason' => $bank->rejection_reason, 'locked' => $bank->locked,
                'infoRequested' => $bank->status === 'info_requested',
            ],
            'verified' => Verification::where('user_id', $user->id)->where('status', 'verified')->exists(),
            'messages' => BankMessage::thread($user->id),
            'banks' => BankAccount::BANKS,
            'types' => BankAccount::TYPES,
            'name' => $user->name,
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($user->isBuyer(), 403);

        $current = BankAccount::where('user_id', $user->id)->latest('id')->first();
        if ($current !== null && $current->locked) {
            throw ValidationException::withMessages(['account_number' => __('Your refund account can’t be changed while a deposit or refund is being processed.')]);
        }

        $data = $request->validate([
            'bank' => ['required', Rule::in(array_keys(BankAccount::BANKS))],
            'branch_code' => ['required', 'digits_between:6,6'],
            'account_number' => ['required', 'regex:/^[0-9 ]{6,16}$/'],
            'account_type' => ['required', Rule::in(array_keys(BankAccount::TYPES))],
            'holder_name' => ['required', 'string', 'max:160'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'branch_code.digits_between' => __('The branch code has 6 digits.'),
            'account_number.regex' => __('Enter the account number using digits only.'),
            'proof.required' => __('Upload a bank letter or recent statement showing the account holder’s name.'),
        ]);

        $number = preg_replace('/\D/', '', $data['account_number']) ?? '';
        /** @var UploadedFile $proof */
        $proof = $request->file('proof');
        $path = "bank-proof/{$user->id}/".Str::uuid().'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(base64_encode((string) file_get_contents($proof->getRealPath()))));

        // Each submission is its own record. Earlier ones (and their letters) are kept for the KYC record,
        // marked superseded so refunds can only ever use the current, verified account.
        BankAccount::where('user_id', $user->id)->where('status', '!=', 'superseded')->update(['status' => 'superseded']);
        $account = BankAccount::create([
            'user_id' => $user->id, 'bank' => $data['bank'], 'branch_code' => $data['branch_code'], 'account_number' => $number, 'account_last4' => substr($number, -4),
            'account_type' => $data['account_type'], 'holder_name' => $data['holder_name'], 'status' => 'pending', 'proof_path' => $path,
        ]);

        Audit::record('kyc', 'bank_submitted', "{$user->name} submitted a refund bank account ending {$account->account_last4}", $account, $user, [
            'name_matches' => $this->namesMatch($data['holder_name'], $user->name),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. We’ll confirm the account is in your name and email you.')]);

        BankMessage::note($user->id, $account->id, 'buyer', 'submitted', $current !== null && in_array($current->status, ['info_requested', 'rejected'], true) ? 'Submitted updated bank details and letter' : 'Submitted bank details', $user->id);

        return back();
    }

    /** Loose comparison used only as a hint for the reviewer. */
    private function namesMatch(string $holder, string $name): bool
    {
        $norm = fn (string $s): string => Str::of($s)->lower()->replaceMatches('/^(mr|mrs|ms|dr|prof)\.?\s+/', '')->replaceMatches('/[^a-z ]/', '')->squish()->toString();

        return $norm($holder) === $norm($name) || str_contains($norm($holder), $norm($name));
    }

    /** The buyer answers a question about their bank account (alongside any corrected letter). */
    public function reply(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $bank = BankAccount::where('user_id', $user->id)->latest('id')->first();
        abort_unless($bank !== null && $bank->status === 'info_requested', 422, 'There is no open request to reply to.');
        $body = $request->validate(['reply' => ['required', 'string', 'max:2000']])['reply'];
        BankMessage::note($user->id, $bank->id, 'buyer', 'reply', $body, $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply sent. If we asked for a new letter, submit it below as well.')]);

        return back();
    }
}
