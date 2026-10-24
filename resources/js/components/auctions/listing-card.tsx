import { Link } from '@inertiajs/react';
import { rand } from '@/lib/money';
import StatusBadge from './status-badge';
import type { ListingCardData } from './types';

/** Design system ListingCard. */
export default function ListingCard({ listing }: { listing: ListingCardData }) {
    const place = [listing.city, listing.province].filter(Boolean).join(', ');

    return (
        <article className="relative overflow-hidden rounded-[18px] border border-border bg-card focus-within:ring-2 focus-within:ring-ring">
            <div className="relative aspect-[3/2] bg-muted">
                {listing.image ? (
                    <img
                        src={listing.image}
                        alt={listing.imageAlt}
                        loading="lazy"
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center text-sm text-muted-foreground">
                        Photos coming soon
                    </div>
                )}
                <StatusBadge
                    status={listing.status.key}
                    label={listing.status.label}
                    className="absolute top-3 left-3"
                />
                {(() => {
                    const badges = [
                        ...new Set([
                            ...(listing.featured ? ['premium'] : []),
                            ...(listing.badges ?? []),
                        ]),
                    ];
                    const label: Record<string, string> = {
                        premium: 'Premium',
                        new: 'New',
                        hot: 'Hot',
                        renovated: 'Renovated',
                    };

                    return badges.length > 0 ? (
                        <ul
                            aria-label="Badges"
                            className="absolute top-3 right-3 flex flex-wrap justify-end gap-1.5"
                        >
                            {badges.map((b) => (
                                <li
                                    key={b}
                                    className="rounded-full bg-brand-accent-soft px-2.5 py-1 text-[13px] font-semibold text-brand-accent"
                                >
                                    {label[b] ?? b}
                                </li>
                            ))}
                        </ul>
                    ) : null;
                })()}
            </div>
            <div className="p-5">
                <p className="text-sm text-muted-foreground">
                    {listing.category}
                    {place ? ` · ${place}` : ''}
                </p>
                <h3 className="mt-1 mb-3 font-display text-2xl leading-tight">
                    <Link
                        href={listing.url}
                        className="text-foreground after:absolute after:inset-0 focus-visible:outline-none"
                    >
                        {listing.title}
                    </Link>
                </h3>
                {listing.facts.length > 0 && (
                    <ul className="mb-3 flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted-foreground">
                        {listing.facts.slice(0, 3).map((f) => (
                            <li key={f.label}>
                                <span className="sr-only">{f.label}: </span>
                                {f.value}
                            </li>
                        ))}
                    </ul>
                )}
                <div className="flex items-baseline justify-between border-t border-border pt-3">
                    <div>
                        <p className="text-[13px] text-muted-foreground">
                            {listing.auctionType === 'sealed'
                                ? 'Sealed tender · minimum'
                                : 'Minimum bid'}
                        </p>
                        <p className="tabular text-[22px] font-bold">
                            {rand(listing.minimum)}
                        </p>
                    </div>
                    {listing.renovated && (
                        <span className="rounded-full bg-brand-accent-soft px-2.5 py-1 text-[13px] font-semibold text-brand-accent">
                            Renovated
                        </span>
                    )}
                </div>
            </div>
        </article>
    );
}
