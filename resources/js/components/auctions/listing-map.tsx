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
                marker.on('click', () => {
                    window.location.href = l.url;
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
