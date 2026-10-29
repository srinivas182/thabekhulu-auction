import { useEffect, useRef } from 'react';
import { rand } from '@/lib/money';
import type { ListingCardData } from './types';
import 'leaflet/dist/leaflet.css';

type Props = {
    listings: ListingCardData[];
    className?: string;
    zoom?: number;
    label: string;
};

const shortRand = (v: number | null) => {
    if (v === null) return '';
    if (v >= 1_000_000)
        return `R ${(v / 1_000_000).toFixed(v >= 10_000_000 ? 0 : 1)}m`;

    return rand(v);
};

/**
 * OpenStreetMap via Leaflet (spec D2). Loaded in the browser only.
 * Positions are already rounded server-side to about 1 km.
 */
export default function ListingMap({
    listings,
    className,
    zoom,
    label,
}: Props) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let map: import('leaflet').Map | undefined;
        let cancelled = false;

        void import('leaflet').then((L) => {
            if (cancelled || !ref.current) return;
            const placed = listings.filter((l) => l.position !== null);
            map = L.map(ref.current, { scrollWheelZoom: false }).setView(
                [-28.5, 25],
                zoom ?? 5,
            );
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            }).addTo(map);

            placed.forEach((l) => {
                const icon = L.divIcon({
                    className: '',
                    html: `<span class="inline-block whitespace-nowrap rounded-full bg-primary px-2.5 py-1 text-[13px] font-bold text-primary-foreground shadow">${shortRand(l.minimum)}</span>`,
                });
                const marker = L.marker([l.position!.lat, l.position!.lng], {
                    icon,
                    title: l.title,
                    keyboard: true,
                }).addTo(map!);
                // A preview, then "View listing" (built as elements, never raw HTML, so titles can't inject markup).
                marker.bindPopup(() => popupFor(l), {
                    maxWidth: 260,
                    minWidth: 220,
                    className: 'listing-popup',
                });
            });

            if (placed.length > 1) {
                map.fitBounds(
                    L.latLngBounds(
                        placed.map((l) => [l.position!.lat, l.position!.lng]),
                    ),
                    { padding: [40, 40], maxZoom: 11 },
                );
            } else if (placed.length === 1) {
                map.setView(
                    [placed[0].position!.lat, placed[0].position!.lng],
                    zoom ?? 11,
                );
            }
        });

        return () => {
            cancelled = true;
            map?.remove();
        };
    }, [listings, zoom]);

    return (
        <div ref={ref} role="region" aria-label={label} className={className} />
    );
}

/** The marker popup: photo, title, place, price, timing and a link to the listing. */
function popupFor(l: ListingCardData): HTMLElement {
    const el = (tag: string, cls: string, text?: string) => {
        const n = document.createElement(tag);
        n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    };
    const box = el('div', 'grid gap-1.5 text-sm');
    if (l.image) {
        const img = document.createElement('img');
        img.src = l.image;
        img.alt = l.imageAlt;
        img.loading = 'lazy';
        img.className = 'h-28 w-full rounded-md object-cover';
        box.append(img);
    }
    box.append(
        el(
            'span',
            'text-xs font-semibold uppercase tracking-wide text-muted-foreground',
            l.category,
        ),
    );
    box.append(el('strong', 'text-base leading-snug', l.title));
    const place = [l.suburb, l.city].filter(Boolean).join(', ');
    if (place)
        box.append(
            el(
                'span',
                'text-muted-foreground',
                l.position?.approximate
                    ? `${place} (approximate location)`
                    : place,
            ),
        );
    if (l.minimum !== null)
        box.append(el('span', '', `Minimum bid ${rand(l.minimum)}`));
    const when = l.status.key === 'soon' ? l.opensLabel : l.closesLabel;
    if (when) box.append(el('span', 'text-muted-foreground', when));
    const link = el(
        'a',
        'mt-1 inline-flex justify-center rounded-md bg-primary px-3 py-2 font-semibold !text-primary-foreground no-underline',
        'View listing',
    ) as HTMLAnchorElement;
    link.href = l.url;
    box.append(link);

    return box;
}
