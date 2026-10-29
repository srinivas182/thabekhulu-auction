import { cn } from '@/lib/utils';

const tone = {
    live: 'text-status-live',
    closing: 'text-status-closing',
    soon: 'text-status-soon',
    processing: 'text-status-processing',
    under_offer: 'text-status-processing',
    sold: 'text-status-sold',
} as const;

/** Auction status: colour always paired with a word (design system StatusBadge). */
export default function StatusBadge({
    status,
    label,
    className,
}: {
    status: keyof typeof tone;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border border-current bg-card px-2.5 py-1 text-[13px] leading-none font-semibold',
                tone[status],
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'size-1.5 rounded-full bg-current',
                    status === 'live' && 'motion-safe:animate-pulse',
                )}
            />
            {label}
        </span>
    );
}
