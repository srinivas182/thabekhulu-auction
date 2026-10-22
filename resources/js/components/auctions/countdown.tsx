import { useEffect, useState } from 'react';

const parts = (ms: number) => {
    const s = Math.max(0, Math.floor(ms / 1000));

    return [
        ['days', Math.floor(s / 86400)],
        ['hours', Math.floor((s % 86400) / 3600)],
        ['min', Math.floor((s % 3600) / 60)],
        ['sec', s % 60],
    ] as const;
};

/**
 * Live countdown. Shows dashes until the page has loaded, then ticks every
 * second in the browser. The closing time itself is always also shown as text.
 */
export default function Countdown({
    to,
    label,
}: {
    to: string;
    label: string;
}) {
    const target = new Date(to).getTime();
    // Null until mounted, so the server and first client render match.
    const [now, setNow] = useState<number | null>(null);

    useEffect(() => {
        setNow(Date.now());
        const id = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(id);
    }, []);

    return (
        <div role="timer" aria-label={label}>
            <dl className="grid grid-cols-4 gap-1.5 text-center">
                {parts(now === null ? 0 : target - now).map(([unit, value]) => (
                    <div
                        key={unit}
                        className="flex flex-col rounded-[10px] bg-background px-0.5 py-2"
                    >
                        <dd className="tabular order-1 text-2xl font-bold">
                            {String(value).padStart(2, '0')}
                        </dd>
                        <dt className="order-2 text-xs text-muted-foreground">
                            {unit}
                        </dt>
                    </div>
                ))}
            </dl>
        </div>
    );
}
