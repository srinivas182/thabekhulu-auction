import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { destroy, index, store } from '@/routes/admin/premium';

type Props = {
    slots: {
        id: number;
        listing: string;
        category: string;
        city: string;
        position: number;
        from: string;
        to: string;
        active: boolean;
    }[];
    listings: { ulid: string; title: string; category: number; city: number }[];
    categories: { id: number; name: string }[];
    cities: { id: number; name: string }[];
};
const select =
    'h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm';

export default function Premium({
    slots,
    listings,
    cities,
    categories,
}: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [f, setF] = useState({
        listing: listings[0]?.ulid ?? '',
        city_id: '',
        position: '1',
        starts_on: '',
        ends_on: '',
    });
    const chosen = listings.find((l) => l.ulid === f.listing);
    const cityName = cities.find((c) => c.id === chosen?.city)?.name;
    const categoryName = categories.find(
        (c) => c.id === chosen?.category,
    )?.name;

    return (
        <>
            <Head title="Premium slots" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Premium slots</h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        Up to three premium positions per category, for chosen
                        dates: either across the whole category, or only in the
                        listing’s city. Premium listings appear first when
                        buyers browse there, with a Premium badge.
                    </p>
                </div>
                <section
                    aria-labelledby="new-h"
                    className="grid gap-3 rounded-xl border border-border bg-card p-5 [&_*]:min-w-0"
                >
                    <h2 id="new-h" className="font-semibold">
                        Assign a slot
                    </h2>
                    <div className="grid gap-3 md:grid-cols-5">
                        <div className="grid gap-1.5 md:col-span-2">
                            <Label htmlFor="l">Listing</Label>
                            <select
                                id="l"
                                className={select}
                                value={f.listing}
                                onChange={(e) =>
                                    setF({
                                        ...f,
                                        listing: e.target.value,
                                        city_id: '',
                                    })
                                }
                            >
                                {listings.map((l) => (
                                    <option key={l.ulid} value={l.ulid}>
                                        {l.title}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.listing} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="c">Where it appears</Label>
                            <select
                                id="c"
                                className={select}
                                value={f.city_id}
                                onChange={(e) =>
                                    setF({ ...f, city_id: e.target.value })
                                }
                            >
                                <option value="">
                                    {categoryName
                                        ? `All ${categoryName} auctions`
                                        : 'Whole category'}
                                </option>
                                {chosen && (
                                    <option value={chosen.city}>
                                        {categoryName
                                            ? `${categoryName} in ${cityName} only`
                                            : `${cityName} only`}
                                    </option>
                                )}
                            </select>
                            <span className="text-sm text-muted-foreground">
                                {chosen
                                    ? `Category: ${categoryName ?? '—'} (from the listing). Choose whether it’s promoted on every ${categoryName ?? 'category'} page, or only when buyers look in ${cityName ?? 'its city'}.`
                                    : 'Choose a listing first. Its category comes from the listing.'}
                            </span>
                            <InputError message={errors.city_id} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="p">Position</Label>
                            <select
                                id="p"
                                className={select}
                                value={f.position}
                                onChange={(e) =>
                                    setF({ ...f, position: e.target.value })
                                }
                            >
                                {[1, 2, 3].map((n) => (
                                    <option key={n} value={n}>
                                        {n}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.position} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Dates</Label>
                            <div className="grid grid-cols-2 gap-1.5">
                                <Input
                                    type="date"
                                    aria-label="From"
                                    value={f.starts_on}
                                    onChange={(e) =>
                                        setF({
                                            ...f,
                                            starts_on: e.target.value,
                                        })
                                    }
                                />
                                <Input
                                    type="date"
                                    aria-label="To"
                                    value={f.ends_on}
                                    onChange={(e) =>
                                        setF({ ...f, ends_on: e.target.value })
                                    }
                                />
                            </div>
                            <InputError
                                message={errors.starts_on ?? errors.ends_on}
                            />
                        </div>
                    </div>
                    <div>
                        <Button
                            onClick={() =>
                                router.post(
                                    store().url,
                                    { ...f, city_id: f.city_id || null },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Assign
                        </Button>
                    </div>
                </section>
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {slots.map((s) => (
                        <li
                            key={s.id}
                            className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                        >
                            <div>
                                <p className="font-medium">{s.listing}</p>
                                <p className="text-sm text-muted-foreground">
                                    {s.category} · {s.city} · position{' '}
                                    {s.position} · {s.from} to {s.to}
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <span
                                    className={cn(
                                        'text-xs font-semibold',
                                        s.active
                                            ? 'text-status-live'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {s.active ? 'Active' : 'Not active'}
                                </span>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        window.confirm('Remove this slot?') &&
                                        router.delete(destroy(s.id).url, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    Remove
                                </Button>
                            </div>
                        </li>
                    ))}
                    {slots.length === 0 && (
                        <li className="px-5 py-8 text-center text-muted-foreground">
                            No premium slots yet.
                        </li>
                    )}
                </ul>
            </div>
        </>
    );
}

Premium.layout = { breadcrumbs: [{ title: 'Premium slots', href: index() }] };
