<?php

namespace App\Domains\Platform\Support;

/**
 * Page meta for search engines and link previews (Sprint 8b). URLs are made
 * absolute: WhatsApp, Facebook and others can't fetch relative images.
 */
final class Seo
{
    /** @return array{title: string, description: string, image: string|null, canonical: string, type: string} */
    public static function make(string $title, ?string $description, ?string $image, string $url, string $type = 'website'): array
    {
        $abs = fn (?string $u) => $u === null || $u === '' ? null : (str_starts_with($u, 'http') ? $u : url($u));

        return [
            'title' => $title,
            'description' => mb_substr(trim(strip_tags((string) $description)), 0, 300),
            'image' => $abs($image),
            'canonical' => (string) $abs($url),
            'type' => $type,
        ];
    }
}
