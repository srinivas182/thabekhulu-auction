<?php

namespace App\Domains\Verification\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Verification\Actions\ApplyCheckResult;
use App\Domains\Verification\Data\CheckResult;
use App\Domains\Verification\Data\IdentityRequest;
use App\Domains\Verification\Enums\CheckType;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Models\VerificationDocument;
use App\Domains\Verification\Support\Countries;
use App\Domains\Verification\Support\ProviderRegistry;
use App\Domains\Verification\Support\SaIdNumber;
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
 * The buyer's identity verification (approved design "Register to bid",
 * step 2). Documents are stored encrypted on the private disk.
 */
class BuyerVerificationController extends Controller
{
    public const MARITAL = ['single' => 'Single', 'married_icop' => 'Married in community of property', 'married_ocop' => 'Married out of community of property', 'divorced' => 'Divorced', 'widowed' => 'Widowed'];

    private const DOC_RULE = ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'];

    public function show(#[CurrentUser] User $user): Response
    {
        $v = $this->current($user);
        $kinds = $v ? $v->documents()->pluck('kind')->all() : [];

        return Inertia::render('account/verification', [
            'countries' => Countries::forPassports(),
            'verification' => $v === null ? null : [
                'status' => $v->status, 'statusLabel' => Verification::STATUSES[$v->status] ?? $v->status,
                'documentType' => $v->document_type, 'idNumberLast4' => $v->id_number ? substr($v->id_number, -4) : null,
                'passportCountry' => $v->passport_country, 'passportExpiresOn' => $v->passport_expires_on?->toDateString(), 'dateOfBirthInput' => $v->date_of_birth?->toDateString(), 'maritalStatus' => $v->marital_status,
                'spouseName' => $v->spouse['name'] ?? null, 'documents' => $kinds, 'rejectionReason' => $v->rejection_reason, 'infoRequested' => $v->info_requested,
                'verifiedAt' => $v->verified_at?->format('j F Y'), 'expiresAt' => $v->expires_at?->format('j F Y'),
            ],
            'maritalOptions' => self::MARITAL,
            'user' => ['firstName' => $user->first_name, 'lastName' => $user->last_name],
        ]);
    }

    public function identity(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $v = $this->editable($user);
        $data = $request->validate([
            'document_type' => ['required', Rule::in(['sa_id', 'passport'])],
            'id_number' => ['required', 'string', 'max:20'],
            'passport_country' => ['nullable', 'required_if:document_type,passport', Rule::in(array_keys(Countries::forPassports()))],
            'date_of_birth' => ['nullable', 'required_if:document_type,passport', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString(), 'after:1900-01-01'],
            'passport_expires_on' => ['nullable', 'required_if:document_type,passport', 'date', 'after:today'],
            'front' => [Rule::requiredIf(! $v->documents()->whereIn('kind', ['id_front', 'passport'])->exists()), ...self::DOC_RULE],
            'back' => ['nullable', ...self::DOC_RULE],
        ]);

        $number = $data['document_type'] === 'sa_id' ? (preg_replace('/\D/', '', $data['id_number']) ?? '') : strtoupper((string) preg_replace('/[\s-]/', '', $data['id_number']));
        if ($data['document_type'] === 'sa_id' && ! SaIdNumber::isValid($number)) {
            throw ValidationException::withMessages(['id_number' => __('That isn’t a valid South African ID number. Check all 13 digits.')]);
        }
        if ($data['document_type'] === 'passport' && ! preg_match('/^[A-Z0-9]{6,20}$/', $number)) {
            throw ValidationException::withMessages(['id_number' => __('A passport number has 6 to 20 letters and numbers. Check it against your passport.')]);
        }
        if ($data['document_type'] === 'sa_id' && SaIdNumber::dateOfBirth($number)?->gt(now()->subYears(18))) {
            throw ValidationException::withMessages(['id_number' => __('You must be 18 or older to bid.')]);
        }

        $hash = Verification::hashIdNumber($data['document_type'].':'.$number);
        if (Verification::where('id_number_hash', $hash)->where('user_id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['id_number' => __('This ID is already linked to another account. Each person may have one account; contact us if this is a mistake.')]);
        }

        $v->fill([
            'document_type' => $data['document_type'], 'id_number' => $number, 'id_number_hash' => $hash,
            'passport_country' => $data['document_type'] === 'passport' ? strtoupper((string) $data['passport_country']) : null,
            'date_of_birth' => $data['document_type'] === 'sa_id' ? SaIdNumber::dateOfBirth($number) : $data['date_of_birth'],
            'passport_expires_on' => $data['document_type'] === 'passport' ? $data['passport_expires_on'] : null,
        ])->save();

        $this->store($v, $request->file('front'), $data['document_type'] === 'passport' ? 'passport' : 'id_front');
        $this->store($v, $request->file('back'), 'id_back');

        return $this->next('Identity document saved.');
    }

    public function selfie(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $v = $this->editable($user);
        $request->validate(['selfie' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);
        $this->store($v, $request->file('selfie'), 'selfie');

        return $this->next('Selfie saved.');
    }

    public function address(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $v = $this->editable($user);
        $data = $request->validate([
            'proof_of_address' => [Rule::requiredIf(! $v->documents()->where('kind', 'proof_of_address')->exists()), ...self::DOC_RULE],
            'marital_status' => ['required', Rule::in(array_keys(self::MARITAL))],
            'spouse_name' => ['nullable', 'required_if:marital_status,married_icop', 'string', 'max:160'],
            'spouse_id_number' => ['nullable', 'required_if:marital_status,married_icop', 'string', 'max:20'],
            'spouse_consent' => [Rule::requiredIf($request->input('marital_status') === 'married_icop' && ! $v->documents()->where('kind', 'spouse_consent')->exists()), ...self::DOC_RULE],
        ], [
            'spouse_name.required_if' => __('Your spouse’s name is needed because you’re married in community of property.'),
            'spouse_id_number.required_if' => __('Your spouse’s ID number is needed because you’re married in community of property.'),
            'spouse_consent.required' => __('Upload your spouse’s signed consent to the purchase.'),
        ]);

        if ($data['marital_status'] === 'married_icop' && ! SaIdNumber::isValid((string) $data['spouse_id_number'])) {
            throw ValidationException::withMessages(['spouse_id_number' => __('That isn’t a valid South African ID number.')]);
        }

        $v->fill([
            'marital_status' => $data['marital_status'],
            'spouse' => $data['marital_status'] === 'married_icop' ? ['name' => $data['spouse_name'], 'id_number' => preg_replace('/\D/', '', (string) $data['spouse_id_number'])] : null,
        ])->save();

        $this->store($v, $request->file('proof_of_address'), 'proof_of_address');
        $this->store($v, $request->file('spouse_consent'), 'spouse_consent');

        return $this->next('Address and marital status saved.');
    }

    public function submit(#[CurrentUser] User $user, ProviderRegistry $registry, ApplyCheckResult $apply): RedirectResponse
    {
        $v = $this->editable($user);
        $kinds = $v->documents()->pluck('kind')->all();
        $missing = array_filter([
            $v->document_type === null ? 'your identity document' : null,
            ! in_array('selfie', $kinds, true) ? 'a selfie' : null,
            ! in_array('proof_of_address', $kinds, true) || $v->marital_status === null ? 'proof of address and marital status' : null,
        ]);
        if ($missing !== []) {
            throw ValidationException::withMessages(['verification' => __('Please add :items first.', ['items' => implode(', ', $missing)])]);
        }

        $v->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'rejection_reason' => null])->save();
        Audit::record('kyc', 'submitted', "{$user->name} submitted identity verification", $v, $user);

        // Identity data check through the routed provider; selfie and documents are reviewed by a person (4b queue).
        $check = $v->document_type === 'passport' ? CheckType::PassportIdentity : CheckType::SaIdentity;
        $provider = $registry->forCheck($check);
        $result = $provider === null
            ? CheckResult::review('Routed to manual review.')
            : $provider->checkIdentity(new IdentityRequest($v->ulid, $user->first_name, $user->last_name, (string) $v->id_number,
                $v->date_of_birth?->toDateString(), $v->document_type === 'passport' ? (string) $v->passport_country : 'ZA', (string) $v->document_type),
                $registry->credentials($provider->key()), $registry->setting($provider->key())->mode);

        $apply->handle($v, $provider?->key() ?? ProviderRegistry::MANUAL, $result, 'identity');
        $apply->handle($v->fresh() ?? $v, ProviderRegistry::MANUAL, CheckResult::review('Selfie and documents to be checked by our team.'), 'documents');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. We’re checking your details and will email you when you’re verified.')]);

        return redirect()->route('account.verification');
    }

    private function current(User $user): ?Verification
    {
        return Verification::where('user_id', $user->id)->latest('id')->first();
    }

    /** The verification the buyer may still change; starts one if none is open. */
    private function editable(User $user): Verification
    {
        abort_unless($user->isBuyer() || $user->isSeller(), 403); // sellers verify too (Sprint 8a)
        $v = $this->current($user);

        if ($v !== null && in_array($v->status, ['submitted', 'review', 'verified'], true)) {
            throw ValidationException::withMessages(['verification' => __('Your verification is :status and can’t be changed now.', ['status' => strtolower(Verification::STATUSES[$v->status])])]);
        }

        return $v !== null && $v->status === 'in_progress' ? $v : Verification::create(['user_id' => $user->id, 'status' => 'in_progress']);
    }

    /** Encrypts the file contents and stores them privately; replaces an earlier file of the same kind. */
    private function store(Verification $v, ?UploadedFile $file, string $kind): void
    {
        if ($file === null) {
            return;
        }

        foreach ($v->documents()->where('kind', $kind)->get() as $old) {
            Storage::disk('local')->delete($old->path);
            $old->delete();
        }

        $path = "verifications/{$v->ulid}/".Str::uuid().'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(base64_encode((string) file_get_contents($file->getRealPath()))));

        $v->documents()->create(['kind' => $kind, 'path' => $path, 'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize()]);
    }

    /** Reads a stored document back (staff review in 4b). */
    public static function read(VerificationDocument $document): string
    {
        return (string) base64_decode(Crypt::decryptString((string) Storage::disk('local')->get($document->path)), true);
    }

    private function next(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return back();
    }
}
