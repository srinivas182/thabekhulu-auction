import { Form, Head, Link } from '@inertiajs/react';
import { lazy, Suspense, useState } from 'react';
import ListingCard from '@/components/auctions/listing-card';
import type { ListingCardData } from '@/components/auctions/types';
import { Button } from '@/components/ui/button';
import { useMounted } from '@/hooks/use-mounted';
import { cn } from '@/lib/utils';

const ListingMap = lazy(() => import('@/components/auctions/listing-map'));

type Props = {
    heading: string;
    intro: string | null;
    seo: { title: string; description: string };
    results: {
        data: ListingCardData[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: {
        category: string | null;
        province: string | null;
        min: number | null;
        max: number | null;
        within: string | null;
        status: string | null;
        renovated: boolean;
        sort: string;
    };
    categories: { name: string; slug: string }[];
    provinces: { name: string; slug: string }[];
    sorts: Record<string, string>;
    basePath: string;
};

const field =
    'h-12 w-full rounded-[10px] border border-input bg-card px-3 text-base';
const label = 'flex flex-col gap-1.5 text-sm font-semibold';
const pageLabel = (l: string) =>
    l
        .replace('&laquo; Previous', 'Previous page')
        .replace('Next &raquo;', 'Next page');

export default function AuctionsIndex({
    heading,
    intro,
    seo,
    results,
    filters,
    categories,
    provinces,
    sorts,
    basePath,
}: Props) {
    const [view, setView] = useState<'list' | 'map'>('list');
    const mounted = useMounted();

    return (
        <>
            <Head title={seo.title}>
                <meta name="description" content={seo.description} />
            </Head>
            <section className="mx-auto max-w-[1280px] px-5 pt-10 sm:px-8">
                <h1 className="font-display text-4xl sm:text-5xl">{heading}</h1>
                <p className="mt-2 text-lg text-muted-foreground">
                    {intro ??
                        `${results.total} live and upcoming ${results.total === 1 ? 'auction' : 'auctions'} across South Africa`}
                </p>

                <Form
                    action={basePath}
                    method="get"
                    className="mt-6 grid gap-3 rounded-[18px] border border-border bg-card p-5 sm:grid-cols-2 lg:grid-cols-4 lg:items-end"
                    aria-label="Filter auctions"
                >
                    {!basePath.match(/\/auctions\/.+/) && (
                        <label className={label}>
                            Category
                            <select
                                name="category"
                                defaultValue={filters.category ?? ''}
                                className={field}
                            >
                                <option value="">All categories</option>
                                {categories.map((c) => (
                                    <option key={c.slug} value={c.slug}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <label className={label}>
                        Province
                        <select
                            name="province"
                            defaultValue={filters.province ?? ''}
                            className={field}
                        >
                            <option value="">All provinces</option>
                            {provinces.map((p) => (
                                <option key={p.slug} value={p.slug}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className={label}>
                        Minimum price (R)
                        <input
                            name="min"
                            inputMode="numeric"
                            defaultValue={filters.min ?? ''}
                            placeholder="0"
                            className={field}
                        />
                    </label>
                    <label className={label}>
                        Maximum price (R)
                        <input
                            name="max"
                            inputMode="numeric"
                            defaultValue={filters.max ?? ''}
                            placeholder="Any"
                            className={field}
                        />
                    </label>
                    <label className={label}>
                        Closing within
                        <select
                            name="within"
                            defaultValue={filters.within ?? ''}
                            className={field}
                        >
                            <option value="">Any time</option>
                            <option value="24h">24 hours</option>
                            <option value="7d">7 days</option>
                            <option value="30d">30 days</option>
                        </select>
                    </label>
                    <label className={label}>
                        Status
                        <select
                            name="status"
                            defaultValue={filters.status ?? ''}
                            className={field}
                        >
                            <option value="">Live and coming soon</option>
                            <option value="live">Live only</option>
                            <option value="soon">Coming soon</option>
                        </select>
                    </label>
                    <label className="flex min-h-12 items-center gap-2.5 text-base">
                        <input
                            type="checkbox"
                            name="renovated"
                            value="1"
                            defaultChecked={filters.renovated}
                            className="size-5 accent-primary"
                        />
                        Renovated by Thabekhulu
                    </label>
                    <label className={label}>
                        Sort by
                        <select
                            name="sort"
                            defaultValue={filters.sort}
                            className={field}
                        >
                            {Object.entries(sorts).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button className="h-12 lg:col-span-4 lg:w-fit">
                        Show auctions
                    </Button>
                </Form>

                <div
                    role="group"
                    aria-label="View"
                    className="mt-5 flex w-fit overflow-hidden rounded-[10px] border border-input lg:hidden"
                >
                    {(['list', 'map'] as const).map((v) => (
                        <button
                            key={v}
                            type="button"
                            aria-pressed={view === v}
                            onClick={() => setView(v)}
                            className={cn(
                                'min-h-11 px-5 font-semibold',
                                view === v
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-card',
                            )}
                        >
                            {v === 'list' ? 'List' : 'Map'}
                        </button>
                    ))}
                </div>
            </section>

            <section
                aria-label="Results"
                className="mx-auto mt-5 flex max-w-[1280px] flex-wrap items-start gap-6 px-5 pb-16 sm:px-8"
            >
                <div
                    className={cn(
                        'grid min-w-0 flex-[3_1_640px] grid-cols-[repeat(auto-fill,minmax(290px,1fr))] gap-5',
                        view === 'map' && 'hidden lg:grid',
                    )}
                >
                    {results.data.map((l) => (
                        <ListingCard key={l.ulid} listing={l} />
                    ))}
                    {results.data.length === 0 && (
                        <div className="col-span-full rounded-[18px] border border-border bg-card p-10 text-center">
                            <p className="font-display text-2xl">
                                No auctions match these filters
                            </p>
                            <p className="mt-2 text-muted-foreground">
                                Try a wider price range or another province.
                            </p>
                            <Link
                                href={basePath}
                                className="mt-4 inline-block font-semibold"
                            >
                                Clear filters
                            </Link>
                        </div>
                    )}
                </div>
                <div
                    className={cn(
                        'min-h-[520px] flex-[2_1_380px] overflow-hidden rounded-[18px] border border-border lg:sticky lg:top-4',
                        view === 'list' && 'hidden lg:block',
                    )}
                >
                    <Suspense
                        fallback={
                            <div className="flex h-[520px] items-center justify-center text-muted-foreground">
                                Loading map…
                            </div>
                        }
                    >
                        {mounted && (
                            <ListingMap
                                listings={results.data}
                                className="h-[640px] w-full"
                                label="Map of auction locations"
                            />
                        )}
                    </Suspense>
                </div>
            </section>

            {results.links.length > 3 && (
                <nav
                    aria-label="Pages"
                    className="mx-auto flex max-w-[1280px] flex-wrap gap-1.5 px-5 pb-16 sm:px-8"
                >
                    {results.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                aria-current={link.active ? 'page' : undefined}
                                className={cn(
                                    'inline-flex min-h-11 min-w-11 items-center justify-center rounded-[10px] px-3',
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-accent',
                                )}
                            >
                                {pageLabel(link.label)}
                            </Link>
                        ) : null,
                    )}
                </nav>
            )}
        </>
    );
}
