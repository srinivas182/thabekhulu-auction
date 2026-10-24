import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/events';
import checkinRoutes from '@/routes/admin/events/checkin';

type Result = {
    result: 'admitted' | 'already' | 'invalid' | 'other_event';
    name?: string;
    at?: string;
    checkedIn: number;
};
const messages: Record<Result['result'], string> = {
    admitted: 'Admitted',
    already: 'Already used',
    invalid: 'Not a valid ticket',
    other_event: 'Ticket for another event',
};

/** Door check-in: phone camera scanning (qr-scanner works in iPhone Safari too) or a typed door code. */
export default function CheckIn({
    event,
}: {
    event: { ulid: string; title: string; checkedIn: number; issued: number };
}) {
    const video = useRef<HTMLVideoElement>(null);
    const [scanning, setScanning] = useState(false);
    const [last, setLast] = useState<Result | null>(null);
    const [count, setCount] = useState(event.checkedIn);
    const [code, setCode] = useState('');
    const busy = useRef(false);

    const send = async (value: string) => {
        if (busy.current) return;
        busy.current = true;
        try {
            const xsrf = decodeURIComponent(
                document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '',
            );
            const r = await fetch(checkinRoutes.store(event.ulid).url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrf,
                },
                body: JSON.stringify({ code: value }),
            });
            const data = (await r.json()) as Result;
            setLast(data);
            setCount(data.checkedIn ?? count);
        } finally {
            setTimeout(() => {
                busy.current = false;
            }, 1500); // one result at a time; avoids double reads of the same code
        }
    };

    useEffect(() => {
        if (!scanning || !video.current) return;
        let scanner: {
            start: () => Promise<void>;
            stop: () => void;
            destroy: () => void;
        } | null = null;
        void import('qr-scanner').then(({ default: QrScanner }) => {
            scanner = new QrScanner(
                video.current!,
                (r: { data: string }) => void send(r.data),
                { highlightScanRegion: true, maxScansPerSecond: 4 },
            );
            void scanner.start();
        });

        return () => {
            scanner?.stop();
            scanner?.destroy();
        };
    }, [scanning]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <Head title={`Check in: ${event.title}`} />
            <div className="mx-auto flex w-full max-w-xl flex-1 flex-col gap-4 p-4">
                <div>
                    <Link href={index()} className="text-sm underline">
                        Events
                    </Link>
                    <h1 className="font-display text-2xl">{event.title}</h1>
                    <p className="text-muted-foreground">
                        {count} of {event.issued} checked in
                    </p>
                </div>
                {last && (
                    <div
                        role="status"
                        aria-live="assertive"
                        className={cn(
                            'rounded-xl p-5 text-center text-white',
                            last.result === 'admitted'
                                ? 'bg-status-live'
                                : last.result === 'already'
                                  ? 'bg-status-closing'
                                  : 'bg-destructive',
                        )}
                    >
                        <p className="text-3xl font-bold">
                            {messages[last.result]}
                        </p>
                        {last.name && (
                            <p className="text-lg">
                                {last.name}
                                {last.at ? ` · at ${last.at}` : ''}
                            </p>
                        )}
                    </div>
                )}
                <video
                    ref={video}
                    className={cn(
                        'aspect-square w-full rounded-xl bg-black object-cover',
                        !scanning && 'hidden',
                    )}
                    muted
                    playsInline
                />
                <button
                    type="button"
                    onClick={() => setScanning(!scanning)}
                    className="min-h-12 rounded-[10px] bg-primary font-semibold text-primary-foreground"
                >
                    {scanning ? 'Stop camera' : 'Scan tickets with the camera'}
                </button>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (code.trim()) {
                            void send(code);
                            setCode('');
                        }
                    }}
                    className="flex gap-2"
                >
                    <input
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                        placeholder="Door code, e.g. K7Q3-9XWM"
                        aria-label="Door code"
                        className="h-12 min-w-0 flex-1 rounded-[10px] border border-input bg-card px-3 font-mono text-lg"
                    />
                    <button className="min-h-12 rounded-[10px] border border-input px-4 font-semibold">
                        Check
                    </button>
                </form>
            </div>
        </>
    );
}

CheckIn.layout = { breadcrumbs: [{ title: 'Events', href: index() }] };
