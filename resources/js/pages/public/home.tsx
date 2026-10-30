import { Form, Link } from '@inertiajs/react';
import Countdown from '@/components/auctions/countdown';
import SeoHead from '@/components/seo-head';
import type { Seo } from '@/components/seo-head';
import ListingCard from '@/components/auctions/listing-card';
import StatusBadge from '@/components/auctions/status-badge';
import type { ListingCardData } from '@/components/auctions/types';
import { rand } from '@/lib/money';

type Props = {
    seo: Seo;
    hero: { title: string; text: string; image: string | null };
    featured: (ListingCardData & { closesLabel: string | null }) | null;
    closingSoon: ListingCardData[];
    categories: {
        name: string;
        slug: string;
        description: string | null;
        count: number;
    }[];
    collections: {
        name: string;
        description: string | null;
        count: number;
        url: string;
    }[];
    steps: { title: string; text: string }[];
    calendar: { week: string; count: number; categories: string }[];
    ffcNumber: string;
};

const field =
    'h-12 w-full rounded-[10px] border border-input bg-card px-3 text-base';
const h2 = 'font-display text-4xl sm:text-[44px] leading-tight';

/** Approved home page (Concept A + calendar). Sections with no data are hidden. */
export default function Home({
    hero,
    featured,
    closingSoon,
    categories,
    collections,
    steps,
    calendar,
    ffcNumber,
    seo,
}: Props) {
    return (
        <>
            <SeoHead seo={seo} />

            <section
                aria-label="Introduction"
                className="relative bg-brand text-brand-foreground"
            >
                {(featured?.image ?? hero.image) && (
                    // Decorative: the featured auction's photo, else the site's banner photo (kept through the launch reset).
                    <img
                        src={(featured?.image ?? hero.image) as string}
                        alt=""
                        className="absolute inset-0 size-full object-cover opacity-45"
                    />
                )}
                <div className="relative mx-auto flex max-w-[1280px] flex-wrap items-end justify-between gap-8 px-5 py-16 sm:px-8 sm:py-24">
                    <div className="max-w-3xl flex-[1_1_520px]">
                        <h1 className="font-display text-5xl leading-[1.02] sm:text-7xl">
                            {hero.title}
                        </h1>
                        <p className="mt-4 max-w-2xl text-lg opacity-90 sm:text-xl">
                            {hero.text}
                        </p>
                    </div>
                    {featured && (
                        <div
                            aria-label="Featured auction"
                            role="region"
                            className="flex flex-[0_1_380px] flex-col gap-4 rounded-[18px] bg-card p-7 text-foreground shadow-[0_24px_48px_rgba(14,29,25,0.25)]"
                        >
                            <div className="grid gap-1.5">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        status={featured.status.key}
                                        label={
                                            featured.status.key === 'soon'
                                                ? featured.status.label
                                                : 'Live auction'
                                        }
                                    />
                                    <span className="text-[13px] text-muted-foreground">
                                        Featured auction
                                    </span>
                                </div>
                                <p className="line-clamp-2 text-lg leading-snug font-semibold">
                                    {featured.title}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {[featured.city, featured.province]
                                        .filter(Boolean)
                                        .join(', ')}
                                </p>
                            </div>
                            <div>
                                <p className="text-[13px] text-muted-foreground">
                                    Minimum bid
                                </p>
                                <p className="tabular text-[34px] font-bold">
                                    {rand(featured.minimum)}
                                </p>
                            </div>
                            {featured.endsAt &&
                                featured.status.key !== 'soon' && (
                                    <Countdown
                                        to={featured.endsAt}
                                        label="Time left in the featured auction"
                                    />
                                )}
                            {featured.closesLabel && (
                                <p className="text-sm text-muted-foreground">
                                    Closes {featured.closesLabel} SAST
                                </p>
                            )}
                            <Link
                                href={featured.url}
                                className="flex min-h-12 items-center justify-center rounded-[10px] bg-primary text-[17px] font-semibold text-primary-foreground"
                            >
                                View and bid
                            </Link>
                        </div>
                    )}
                </div>
            </section>

            <section aria-label="Search auctions" className="px-5 sm:px-8">
                <Form
                    action="/auctions"
                    method="get"
                    className="mx-auto -mt-px grid max-w-[1280px] gap-4 rounded-b-[18px] border border-t-0 border-border bg-card p-5 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
                >
                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                        Category
                        <select name="category" className={field}>
                            <option value="">All categories</option>
                            {categories.map((c) => (
                                <option key={c.slug} value={c.slug}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                        Minimum price (R)
                        <input
                            name="min"
                            inputMode="numeric"
                            placeholder="0"
                            className={field}
                        />
                    </label>
                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                        Maximum price (R)
                        <input
                            name="max"
                            inputMode="numeric"
                            placeholder="Any"
                            className={field}
                        />
                    </label>
                    <button className="h-12 rounded-[10px] bg-primary font-semibold text-primary-foreground">
                        Search auctions
                    </button>
                </Form>
            </section>

            {closingSoon.length > 0 && (
                <section
                    aria-labelledby="closing"
                    className="mx-auto max-w-[1280px] px-5 pt-24 sm:px-8"
                >
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <h2 id="closing" className={h2}>
                            Closing soon
                        </h2>
                        <Link
                            href="/auctions?within=7d"
                            className="font-semibold"
                        >
                            See all live auctions
                        </Link>
                    </div>
                    <div className="mt-8 grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-6">
                        {closingSoon.map((l) => (
                            <ListingCard key={l.ulid} listing={l} />
                        ))}
                    </div>
                </section>
            )}

            {collections.length > 0 && (
                <section
                    aria-labelledby="collections"
                    className="mx-auto max-w-[1280px] px-5 pt-24 sm:px-8"
                >
                    <h2 id="collections" className={h2}>
                        Investment collections
                    </h2>
                    <ul className="mt-8 grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-4">
                        {collections.map((c) => (
                            <li key={c.name}>
                                <Link
                                    href={c.url}
                                    className="flex h-full flex-col gap-1 rounded-[18px] border border-border bg-card p-6 hover:border-primary"
                                >
                                    <span className="font-display text-2xl">
                                        {c.name}
                                    </span>
                                    {c.description && (
                                        <span className="text-muted-foreground">
                                            {c.description}
                                        </span>
                                    )}
                                    <span className="tabular mt-2 font-semibold">
                                        {c.count}{' '}
                                        {c.count === 1 ? 'auction' : 'auctions'}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section
                aria-labelledby="browse"
                className="mx-auto max-w-[1280px] px-5 pt-24 sm:px-8"
            >
                <h2 id="browse" className={h2}>
                    Browse by category
                </h2>
                <ul className="mt-8 grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
                    {categories.map((c, i) => (
                        <li key={c.slug}>
                            <Link
                                href={`/auctions/${c.slug}`}
                                className="flex min-h-56 flex-col justify-end gap-1 rounded-[18px] p-7 text-white"
                                style={{
                                    background: [
                                        '#2a4a40',
                                        '#3e6155',
                                        '#173b33',
                                        '#0f2b25',
                                    ][i % 4],
                                }}
                            >
                                <span className="font-display text-[34px]">
                                    {c.name}
                                </span>
                                <span className="text-[#dce6e1]">
                                    {c.count === 0
                                        ? 'Auctions opening soon'
                                        : `${c.count} live and upcoming`}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </section>

            {steps.length > 0 && (
                <section
                    aria-labelledby="how"
                    className="mx-auto max-w-[1280px] px-5 pt-24 sm:px-8"
                >
                    <h2 id="how" className={h2}>
                        How bidding works
                    </h2>
                    <ol className="mt-8 grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-6">
                        {steps.map((s, i) => (
                            <li
                                key={i}
                                className="border-t-2 border-primary pt-5"
                            >
                                <p className="font-bold text-brand-accent">
                                    Step {i + 1}
                                </p>
                                <h3 className="mt-1.5 text-[22px] font-semibold">
                                    {s.title}
                                </h3>
                                <p className="mt-2 leading-relaxed text-muted-foreground">
                                    {s.text}
                                </p>
                            </li>
                        ))}
                    </ol>
                    <Link
                        href="/how-it-works"
                        className="mt-6 inline-block font-semibold"
                    >
                        Read the full guide
                    </Link>
                </section>
            )}

            {calendar.length > 0 && (
                <section
                    aria-labelledby="calendar"
                    className="mx-auto max-w-[1280px] px-5 pt-24 sm:px-8"
                >
                    <h2 id="calendar" className={h2}>
                        Auction calendar
                    </h2>
                    <p className="mt-2 text-lg text-muted-foreground">
                        Auctions run for 30 days. A bid near the close extends
                        the auction by a few minutes.
                    </p>
                    <div className="mt-7 overflow-x-auto rounded-[18px] border border-border bg-card">
                        <table className="w-full min-w-[560px] text-left">
                            <thead className="text-sm text-muted-foreground">
                                <tr>
                                    <th className="border-b border-border px-6 py-4 font-medium">
                                        Closing week
                                    </th>
                                    <th className="border-b border-border px-6 py-4 font-medium">
                                        Auctions
                                    </th>
                                    <th className="border-b border-border px-6 py-4 font-medium">
                                        Categories
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {calendar.map((w) => (
                                    <tr key={w.week}>
                                        <td className="border-b border-border px-6 py-4 font-semibold">
                                            {w.week}
                                        </td>
                                        <td className="tabular border-b border-border px-6 py-4">
                                            {w.count}
                                        </td>
                                        <td className="border-b border-border px-6 py-4 text-muted-foreground">
                                            {w.categories}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            <section
                aria-label="Why buyers trust Thabekhulu"
                className="mx-auto grid max-w-[1280px] grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-8 px-5 py-24 sm:px-8"
            >
                <div>
                    <h3 className="text-lg font-semibold">
                        Registered with the PPRA
                    </h3>
                    <p className="mt-1 text-muted-foreground">
                        {ffcNumber
                            ? `Fidelity Fund Certificate ${ffcNumber}`
                            : 'Fidelity Fund Certificate details shown here before launch.'}
                    </p>
                </div>
                <div>
                    <h3 className="text-lg font-semibold">
                        Deposits held separately
                    </h3>
                    <p className="mt-1 text-muted-foreground">
                        Refunded to your own account if you don’t win.
                    </p>
                </div>
                <div>
                    <h3 className="text-lg font-semibold">
                        Rules you can read first
                    </h3>
                    <p className="mt-1 text-muted-foreground">
                        <Link href="/rules-of-auction" className="underline">
                            Rules of Auction
                        </Link>{' '}
                        and every fee published before you bid.
                    </p>
                </div>
            </section>
        </>
    );
}
