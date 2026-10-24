import { Head, Link, router } from '@inertiajs/react';
import ListingCard from '@/components/auctions/listing-card';
import type { ListingCardData } from '@/components/auctions/types';
import { destroy } from '@/routes/account/saved-searches';

type Props = {
    searches: { ulid: string; name: string; url: string }[];
    watching: ListingCardData[];
};

export default function Saved({ searches, watching }: Props) {
    return (
        <>
            <Head title="Saved searches and watchlist" />
            <section className="mx-auto max-w-[1100px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">
                    Saved searches and watchlist
                </h1>
                <h2 className="mt-8 font-display text-2xl">Saved searches</h2>
                <p className="mt-1 text-muted-foreground">
                    We email you when new auctions match. Save a search from the
                    auctions page.
                </p>
                <ul className="mt-4 divide-y divide-border rounded-[18px] border border-border bg-card">
                    {searches.map((s) => (
                        <li
                            key={s.ulid}
                            className="flex items-center justify-between gap-3 px-5 py-3"
                        >
                            <Link
                                href={s.url}
                                className="font-semibold underline-offset-4 hover:underline"
                            >
                                {s.name}
                            </Link>
                            <button
                                type="button"
                                className="text-sm underline"
                                onClick={() =>
                                    router.delete(destroy(s.ulid).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Stop alerts
                            </button>
                        </li>
                    ))}
                    {searches.length === 0 && (
                        <li className="px-5 py-5 text-muted-foreground">
                            No saved searches yet.
                        </li>
                    )}
                </ul>
                <h2 className="mt-10 font-display text-2xl">Watchlist</h2>
                <p className="mt-1 text-muted-foreground">
                    We remind you a day before these close.
                </p>
                <div className="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {watching.map((l) => (
                        <ListingCard key={l.ulid} listing={l} />
                    ))}
                </div>
                {watching.length === 0 && (
                    <p className="mt-2 text-muted-foreground">
                        Nothing on your watchlist yet.
                    </p>
                )}
            </section>
        </>
    );
}
