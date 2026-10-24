<?php

namespace App\Domains\Events\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR content for a ticket: "TDGT1.<ticket ulid>.<signature>". The signature
 * is an HMAC of the ulid with the application key, so tickets can't be
 * forged and can be shown again at any time without storing a secret.
 */
final class TicketToken
{
    public static function signature(string $ulid): string
    {
        return substr(hash_hmac('sha256', 'ticket|'.$ulid, (string) config('app.key')), 0, 32);
    }

    public static function payload(string $ulid): string
    {
        return "TDGT1.{$ulid}.".self::signature($ulid);
    }

    /** The ticket ulid if the payload is genuine, else null (constant-time comparison). */
    public static function verify(string $payload): ?string
    {
        // Case-insensitive: keyboard-wedge scanners and typed entry may change letter case;
        // hex carries the same value either way (found in Sprint 7c testing).
        if (! preg_match('/^TDGT1\.([0-9A-HJKMNP-TV-Z]{26})\.([0-9a-f]{32})$/i', trim($payload), $m)) {
            return null;
        }
        $ulid = strtoupper($m[1]);

        return hash_equals(self::signature($ulid), strtolower($m[2])) ? $ulid : null;
    }

    /** Door code: 8 characters without look-alikes, shown as K7Q3-9XWM. */
    public static function code(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $c = '';
        for ($i = 0; $i < 8; $i++) {
            $c .= $alphabet[random_int(0, 31)];
        }

        return substr($c, 0, 4).'-'.substr($c, 4);
    }

    /** PNG data URI of the QR code. */
    public static function qr(string $ulid): string
    {
        return (new QRCode(new QROptions(['outputInterface' => QRGdImagePNG::class, 'scale' => 8, 'outputBase64' => true, 'eccLevel' => EccLevel::M])))->render(self::payload($ulid));
    }
}
