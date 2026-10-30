<?php

namespace App\Domains\Security;

use App\Domains\Audit\Support\Audit;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Security\Notifications\ApprovalCode;
use App\Domains\Settings\Settings\SecuritySettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Security approval codes. A sensitive back-office action (any delete, a change to a public listing,
 * a finance approval, a change to security settings) goes through only with a 6-digit code emailed to
 * the security approvers. The email names who asked, what, when and from where. Codes last 10 minutes,
 * work once, allow 5 tries and are tied to the exact action and data.
 */
final class ApprovalGate
{
    /** Changes to a public listing (Coming soon or Live). */
    private const LISTING_CHANGES = [
        'admin.listings.update' => 'Change listing details', 'admin.listings.media.store' => 'Add listing photos', 'admin.listings.media.update' => 'Change a listing photo',
        'admin.listings.media.order' => 'Reorder listing photos', 'admin.listings.documents.store' => 'Add a listing document',
        'admin.listings.compliance.signoff' => 'Sign off a legal check', 'admin.listings.withdraw' => 'Withdraw the listing',
    ];

    /** Finance approvals. */
    private const FINANCE = [
        'admin.confirmations.store' => 'Confirm a payment received (first step)', 'admin.confirmations.approve' => 'Approve a payment confirmation (second step)',
        'admin.finance.refunds.approve' => 'Approve refunds', 'admin.finance.refunds.paid' => 'Mark refunds paid',
        'admin.settlement.payout.approve' => 'Approve a seller payout', 'admin.settlement.payout.paid' => 'Mark a seller payout paid',
        'admin.finance.resolve' => 'Match a bank statement line to a payment',
        // Settings that could redirect money.
        'admin.finance.settings.update' => 'Change the trust account or refund settings', 'admin.finance.providers.update' => 'Change payment provider settings',
        'admin.finance.providers.deposit' => 'Change which payment provider takes deposits',
        // Removals that aren't DELETE requests.
        'admin.bids.void' => 'Void a bid', 'admin.staff.deactivate' => 'Deactivate a staff member',
    ];

    public const MINUTES = 10;

    public const TRIES = 5;

    public static function enabled(): bool
    {
        return app(SecuritySettings::class)->approvals_enabled && self::approvers() !== [];
    }

    /** @return list<string> */
    public static function approvers(): array
    {
        return array_values(array_filter(array_map(fn ($e) => mb_strtolower(trim((string) $e)), app(SecuritySettings::class)->approver_emails)));
    }

    /** What this request is, if it needs a code; null when it doesn't. */
    public static function label(Request $request): ?string
    {
        $name = (string) $request->route()?->getName();
        if (! str_starts_with($name, 'admin.')) {
            return null;
        }
        if ($request->isMethod('DELETE')) {
            return 'Delete: '.str_replace(['admin.', '.destroy', '.', '-'], ['', '', ' ', ' '], $name);
        }
        if (isset(self::FINANCE[$name])) {
            return self::FINANCE[$name];
        }
        if ($name === 'admin.settings.update' && $request->route('group') === 'security') {
            return 'Change security settings';
        }
        if (isset(self::LISTING_CHANGES[$name])) {
            $listing = $request->route('listing');

            return $listing instanceof Listing && in_array($listing->status, [ListingStatus::Scheduled, ListingStatus::Live], true) ? self::LISTING_CHANGES[$name] : null;
        }

        return null;
    }

    /** Throws (asking for a code, or refusing a wrong one) unless the request carries the right code. */
    public static function check(Request $request, User $user, string $label): void
    {
        $action = (string) $request->route()?->getName();
        $hash = self::payloadHash($request, $action);
        $pending = DB::table('security_approvals')->where('user_id', $user->id)->where('action', $action)->where('payload_hash', $hash)
            ->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        $code = trim((string) $request->input('approval_code', ''));

        if ($code !== '' && $pending !== null) {
            if ($pending->attempts >= self::TRIES) {
                DB::table('security_approvals')->where('id', $pending->id)->update(['expires_at' => now()]);
                Audit::record('security', 'approval_locked', "Too many wrong approval codes: {$label}", null, $user);
                throw ValidationException::withMessages(['approval_code' => __('Too many wrong codes. Start again to request a new code.')]);
            }
            if (! Hash::check($code, (string) $pending->code_hash)) {
                DB::table('security_approvals')->where('id', $pending->id)->increment('attempts');
                Audit::record('security', 'approval_failed', "Wrong approval code: {$label}", null, $user);
                throw ValidationException::withMessages(['approval_code' => __('That code isn’t right. :n tries left.', ['n' => max(0, self::TRIES - 1 - (int) $pending->attempts)])]);
            }
            DB::table('security_approvals')->where('id', $pending->id)->update(['used_at' => now()]);
            Audit::record('security', 'approval_used', "Approved with a code: {$label}", null, $user, ['summary' => $pending->summary]);

            return;
        }
        if ($code !== '') { // a code, but no matching pending request
            throw ValidationException::withMessages(['approval_code' => __('That code has expired, or was for a different action. Close this and try again to get a new code.')]);
        }
        if ($pending !== null && now()->diffInSeconds($pending->created_at, true) < 60) {
            throw ValidationException::withMessages(['approval_code' => __('A code was sent to the security approvers a moment ago. Enter it to continue.')]);
        }
        if (DB::table('security_approvals')->where('user_id', $user->id)->where('created_at', '>', now()->subMinutes(10))->count() >= 10) {
            throw ValidationException::withMessages(['approval_code' => __('Too many approval requests. Please wait a few minutes.')]);
        }

        $plain = (string) random_int(100000, 999999);
        $summary = self::summary($request, $label);
        DB::table('security_approvals')->insert([
            'user_id' => $user->id, 'action' => $action, 'summary' => mb_substr($summary, 0, 2000), 'payload_hash' => $hash, 'code_hash' => Hash::make($plain),
            'ip' => $request->ip(), 'expires_at' => now()->addMinutes(self::MINUTES), 'created_at' => now(),
        ]);
        Notification::route('mail', self::approvers())->notify(new ApprovalCode($plain, $user, $summary, (string) $request->ip()));
        Audit::record('security', 'approval_requested', "Approval code requested: {$label}", null, $user, ['summary' => $summary]);

        throw ValidationException::withMessages(['approval_code' => __('This needs approval. A 6-digit code was emailed to the security approvers; enter it to continue.')]);
    }

    /** The action, its subject and (for edits) what changes, in plain words for the approvers. */
    public static function summary(Request $request, string $label): string
    {
        $lines = [$label];
        foreach ((array) $request->route()?->parameters() as $value) {
            if ($value instanceof Model) {
                $name = $value->getAttribute('title') ?? $value->getAttribute('name') ?? $value->getAttribute('label') ?? $value->getAttribute('reference') ?? $value->getAttribute('email');
                $lines[] = class_basename($value).': '.($name !== null ? (string) $name : '#'.$value->getKey());
            }
        }
        $listing = $request->route('listing');
        if ($request->route()?->getName() === 'admin.listings.update' && $listing instanceof Listing) {
            foreach ($request->except(['approval_code', '_token', '_method']) as $key => $new) {
                $old = $listing->getAttribute((string) $key);
                if (is_scalar($new) && $old !== null && is_scalar($old) && (string) $old !== (string) $new) {
                    $lines[] = "{$key}: ".mb_substr((string) $old, 0, 80).' → '.mb_substr((string) $new, 0, 80);
                }
            }
        }
        foreach (['amount', 'reference', 'received_on', 'reason', 'note'] as $key) {
            if (is_scalar($request->input($key)) && $request->input($key) !== '') {
                $lines[] = ucfirst(str_replace('_', ' ', $key)).': '.mb_substr((string) $request->input($key), 0, 200);
            }
        }

        return implode("\n", array_slice($lines, 0, 20));
    }

    /** Ties a code to this action, its route subjects, its input and any files (by content). */
    private static function payloadHash(Request $request, string $action): string
    {
        $params = array_map(fn ($v) => $v instanceof Model ? class_basename($v).':'.$v->getKey() : $v, (array) $request->route()?->parameters());
        $files_in = $request->allFiles();
        $all = [];
        array_walk_recursive($files_in, function ($f) use (&$all): void {
            if ($f instanceof UploadedFile) {
                $all[] = sha1_file((string) $f->getRealPath());
            }
        });
        $files = $all;

        return hash('sha256', json_encode([$action, $params, $request->except(['approval_code', '_token', '_method']), $files]) ?: '');
    }
}
