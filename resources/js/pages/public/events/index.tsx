import { Head, Link } from '@inertiajs/react';
import { randCents } from '@/lib/cents';

type E = {
    slug: string;
    title: string;
    kind: string;
    venue: string | null;
    price: number;
    when: string;
    left: number;
};

export default function Events({ events }: { events: E[] }) {
    return (
        <>
            <Head title="Events" />
            <section className="mx-auto max-w-[1100px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl sm:text-5xl">Events</h1>
                <p className="mt-3 max-w-2xl text-lg text-muted-foreground">
                    Auction days, investor briefings and viewings, online and in
                    person.
                </p>
                <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                    {events.map((e) => (
                        <li
                            key={e.slug}
                            className="grid gap-2 rounded-[18px] border border-border bg-card p-6"
                        >
                            <p className="text-sm font-semibold text-primary">
                                {e.when} SAST
                            </p>
                            <Link
                                href={`/events/${e.slug}`}
                                className="font-display text-2xl underline-offset-4 hover:underline"
                            >
                                {e.title}
                            </Link>
                            <p className="text-muted-foreground">
                                {e.kind === 'online' ? 'Online' : e.venue} ·{' '}
                                {e.price === 0 ? 'Free' : randCents(e.price)} ·{' '}
                                {e.left > 0 ? `${e.left} places left` : 'Full'}
                            </p>
                        </li>
                    ))}
                    {events.length === 0 && (
                        <li className="text-muted-foreground">
                            No upcoming events right now.
                        </li>
                    )}
                </ul>
            </section>
        </>
    );
}
