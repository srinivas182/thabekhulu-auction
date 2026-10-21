<?php

namespace App\Domains\Platform\Features;

use App\Domains\Audit\Support\Audit;
use App\Models\User;
use Laravel\Pennant\Feature;

/**
 * Platform-wide feature flags (spec section 9). Flags apply to the whole
 * platform, not per user, so they are stored against Pennant's null scope.
 */
final class Flags
{
    /**
     * @var array<string, array{label: string, description: string, phase: string}>
     */
    public const CATALOGUE = [
        'sellers' => [
            'label' => 'Seller accounts',
            'description' => 'Sellers can register, buy packages and submit their own listings.',
            'phase' => 'Phase 2',
        ],
        'consortium_bidding' => [
            'label' => 'Consortium bidding',
            'description' => 'Groups of buyers can bid together on high-value listings.',
            'phase' => 'Phase 2',
        ],
        'investor_club' => [
            'label' => 'Investor Club',
            'description' => 'Early access and invitation-only auctions for verified high-value buyers.',
            'phase' => 'Phase 2',
        ],
        'whatsapp_notifications' => [
            'label' => 'WhatsApp notifications',
            'description' => 'Outbid, closing-soon and payment alerts by WhatsApp.',
            'phase' => 'Sprint 6',
        ],
        'public_site_indexing' => [
            'label' => 'Search engine indexing',
            'description' => 'Allow Google and AI search tools to index the public website. Turn on at launch.',
            'phase' => 'Launch',
        ],
    ];

    /**
     * Registers each flag with its default. Defaults are all off, except
     * indexing which follows PUBLIC_SITE_INDEXING until first changed.
     */
    public static function define(): void
    {
        foreach (array_keys(self::CATALOGUE) as $key) {
            Feature::define($key, fn (): bool => $key === 'public_site_indexing'
                ? (bool) config('platform.indexing')
                : false);
        }
    }

    public static function active(string $key): bool
    {
        return Feature::for(null)->active($key);
    }

    public static function set(string $key, bool $on, ?User $actor = null): void
    {
        abort_unless(array_key_exists($key, self::CATALOGUE), 404);

        $was = self::active($key);

        if ($was === $on) {
            return;
        }

        $on ? Feature::for(null)->activate($key) : Feature::for(null)->deactivate($key);

        Audit::record('features', $on ? 'enabled' : 'disabled',
            ($on ? 'Turned on ' : 'Turned off ').self::CATALOGUE[$key]['label'], null, $actor, [
                'flag' => $key,
                'old' => ['active' => $was],
                'attributes' => ['active' => $on],
            ]);
    }

    /**
     * @return list<array{key: string, label: string, description: string, phase: string, active: bool}>
     */
    public static function all(): array
    {
        $flags = [];

        foreach (self::CATALOGUE as $key => $meta) {
            $flags[] = ['key' => $key, ...$meta, 'active' => self::active($key)];
        }

        return $flags;
    }
}
