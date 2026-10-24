import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';

type Props = {
    order: {
        reference: string;
        name: string;
        status: string;
        quantity: number;
        amount: number;
    };
    event: {
        title: string;
        when: string;
        kind: string;
        venue: string | null;
        onlineLink: string | null;
    };
    tickets: { code: string; qr: string; used: boolean }[];
    pdf: string | null;
};

export default function Tickets({ order, event, tickets, pdf }: Props) {
    return (
        <>
            <Head title="Your tickets">
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <section className="mx-auto max-w-[760px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">Your tickets</h1>
                <p className="mt-2 text-lg">
                    {event.title} · {event.when} SAST ·{' '}
                    {event.kind === 'online' ? 'Online' : event.venue}
                </p>
                {order.status === 'pending' && (
                    <p
                        role="status"
                        className="mt-6 rounded-[10px] bg-brand-accent-soft p-4 text-brand-accent"
                    >
                        We’re confirming your payment. Your tickets appear here
                        and in your email as soon as it’s confirmed. Refresh
                        this page in a minute.
                    </p>
                )}
                {['expired', 'cancelled'].includes(order.status) && (
                    <p className="mt-6 rounded-[10px] bg-muted p-4">
                        This order was not completed. You can order again from
                        the event page.
                    </p>
                )}
                {event.onlineLink && (
                    <p className="mt-6 rounded-[10px] bg-muted p-4">
                        Join online:{' '}
                        <a
                            href={event.onlineLink}
                            className="font-semibold underline"
                            rel="noreferrer"
                        >
                            {event.onlineLink}
                        </a>
                    </p>
                )}
                {pdf && (
                    <a
                        href={pdf}
                        className="mt-6 inline-flex min-h-11 items-center gap-1.5 rounded-[10px] border border-input px-4 font-semibold"
                    >
                        <Download className="size-4" aria-hidden="true" />
                        Download as PDF
                    </a>
                )}
                <ul className="mt-6 grid gap-4 sm:grid-cols-2">
                    {tickets.map((t, i) => (
                        <li
                            key={t.code}
                            className="grid justify-items-center gap-2 rounded-[18px] border-2 border-primary bg-card p-5 text-center"
                        >
                            <p className="text-sm text-muted-foreground">
                                Ticket {i + 1} of {order.quantity} ·{' '}
                                {order.name}
                            </p>
                            <img
                                src={t.qr}
                                alt={`QR code for ticket ${t.code}`}
                                width={200}
                                height={200}
                                className={t.used ? 'opacity-30' : ''}
                            />
                            <p className="font-mono text-2xl font-bold tracking-widest">
                                {t.code}
                            </p>
                            {t.used && (
                                <p className="font-semibold text-muted-foreground">
                                    Used
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            </section>
        </>
    );
}
