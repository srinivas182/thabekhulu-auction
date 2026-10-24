import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { submit } from '@/routes/seller';

type Props = {
    verification: string;
    packageExpires: string | null;
    submissions: {
        ulid: string;
        property: string;
        status: string;
        submitted: string | null;
        documents: number;
    }[];
    listings: {
        title: string;
        status: string;
        bids: number;
        result: string | null;
        payout: string | null;
    }[];
    docKinds: Record<string, string>;
};
const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-base';

export default function SellerDashboard({
    verification,
    packageExpires,
    submissions,
    listings,
    docKinds,
}: Props) {
    const [docs, setDocs] = useState([0]);

    return (
        <>
            <Head title="Seller account" />
            <section className="mx-auto flex max-w-[1000px] flex-col gap-8 px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">Your properties</h1>
                <dl className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-[18px] border border-border bg-card p-5">
                        <dt className="text-sm text-muted-foreground">
                            Identity
                        </dt>
                        <dd className="text-lg font-semibold">
                            {verification} ·{' '}
                            <Link
                                href="/account/verification"
                                className="text-sm underline"
                            >
                                verify
                            </Link>
                        </dd>
                    </div>
                    <div className="rounded-[18px] border border-border bg-card p-5">
                        <dt className="text-sm text-muted-foreground">
                            Package
                        </dt>
                        <dd className="text-lg font-semibold">
                            {packageExpires
                                ? `Active until ${packageExpires}`
                                : 'None yet'}
                        </dd>
                    </div>
                </dl>

                <section
                    aria-labelledby="l-h"
                    className="rounded-[18px] border border-border bg-card"
                >
                    <h2 id="l-h" className="px-6 pt-5 font-display text-2xl">
                        Listings
                    </h2>
                    <ul className="mt-2 divide-y divide-border">
                        {listings.map((l) => (
                            <li
                                key={l.title}
                                className="flex flex-wrap justify-between gap-2 px-6 py-3"
                            >
                                <span className="font-semibold">{l.title}</span>
                                <span className="text-sm text-muted-foreground">
                                    {l.status} · {l.bids} bids
                                    {l.result ? ` · ${l.result}` : ''}
                                    {l.payout
                                        ? ` · payout ${l.payout.toLowerCase()}`
                                        : ''}
                                </span>
                            </li>
                        ))}
                        {listings.length === 0 && (
                            <li className="px-6 py-5 text-muted-foreground">
                                No listings yet. Submit a property below.
                            </li>
                        )}
                    </ul>
                </section>

                <section
                    aria-labelledby="s-h"
                    className="rounded-[18px] border border-border bg-card"
                >
                    <h2 id="s-h" className="px-6 pt-5 font-display text-2xl">
                        Submissions
                    </h2>
                    <ul className="mt-2 divide-y divide-border">
                        {submissions.map((s) => (
                            <li
                                key={s.ulid}
                                className="flex flex-wrap justify-between gap-2 px-6 py-3"
                            >
                                <span>{s.property}</span>
                                <span className="text-sm text-muted-foreground">
                                    {s.submitted} ·{' '}
                                    {s.status.replace(/_/g, ' ')} ·{' '}
                                    {s.documents} documents
                                </span>
                            </li>
                        ))}
                        {submissions.length === 0 && (
                            <li className="px-6 py-5 text-muted-foreground">
                                Nothing submitted yet.
                            </li>
                        )}
                    </ul>
                </section>

                <Form
                    action={submit().url}
                    method="post"
                    resetOnSuccess
                    className="grid gap-4 rounded-[18px] border border-border bg-card p-6 [&_*]:min-w-0"
                >
                    {({ processing, errors }) => (
                        <>
                            <h2 className="font-display text-2xl">
                                Submit a property
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    Property type
                                    <input
                                        name="property_type"
                                        placeholder="e.g. Home, Land, Mining right"
                                        className={field}
                                    />
                                    {errors.property_type && (
                                        <span className="text-destructive">
                                            {errors.property_type}
                                        </span>
                                    )}
                                </label>
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    Location
                                    <input name="location" className={field} />
                                    {errors.location && (
                                        <span className="text-destructive">
                                            {errors.location}
                                        </span>
                                    )}
                                </label>
                            </div>
                            <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                Asking price (R, optional)
                                <input
                                    name="asking_price"
                                    inputMode="numeric"
                                    className={field}
                                />
                            </label>
                            <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                Description
                                <textarea
                                    name="description"
                                    className="min-h-28 rounded-[10px] border border-input bg-card px-3 py-2 text-base"
                                />
                                {errors.description && (
                                    <span className="text-destructive">
                                        {errors.description}
                                    </span>
                                )}
                            </label>
                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-semibold">
                                    Documents (title deed, mining right,
                                    authority to sell)
                                </legend>
                                {docs.map((k, i) => (
                                    <div
                                        key={k}
                                        className="grid gap-2 sm:grid-cols-[1fr_1.4fr]"
                                    >
                                        <select
                                            name={`documents[${i}][kind]`}
                                            aria-label="Document type"
                                            className={field}
                                        >
                                            {Object.entries(docKinds).map(
                                                ([v, l]) => (
                                                    <option key={v} value={v}>
                                                        {l}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <input
                                            type="file"
                                            name={`documents[${i}][file]`}
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            aria-label="Document file"
                                            className="self-center text-sm"
                                        />
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    onClick={() =>
                                        setDocs([
                                            ...docs,
                                            Math.max(...docs) + 1,
                                        ])
                                    }
                                    className="w-fit text-sm font-semibold underline"
                                >
                                    Add another document
                                </button>
                            </fieldset>
                            <button
                                disabled={processing}
                                className="min-h-12 w-fit rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground"
                            >
                                Submit for review
                            </button>
                        </>
                    )}
                </Form>
            </section>
        </>
    );
}
