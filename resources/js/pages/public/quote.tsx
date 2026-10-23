import { Form, Head } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useState } from 'react';
import { randCents } from '@/lib/cents';

type Line = {
    kind: string;
    frequency: string;
    description: string;
    quantity: number;
    unit: string | null;
    unitPrice: number;
    net: number;
    vat: number;
};
type Q = {
    reference: string;
    title: string;
    status: string;
    statusLabel: string;
    open: boolean;
    client: string;
    issueDate: string;
    validUntil: string;
    vatRate: number;
    scope: string | null;
    assumptions: string | null;
    exclusions: string | null;
    terms: string | null;
    paymentMethod: string | null;
    lines: Line[];
    milestones: { name: string; start: string | null; end: string | null }[];
    payments: {
        label: string;
        percent: number;
        amount: number;
        due: string | null;
    }[];
    totals: {
        once_subtotal: number;
        once_discount: number;
        once_vat: number;
        once_total: number;
        monthly_total: number;
        annual_total: number;
    };
    accepted: { name: string; at: string; fingerprint: string } | null;
};
type Props = {
    quote: Q;
    company: string;
    links: { pdf: string; accept: string; decline: string };
};

const prose =
    'leading-relaxed [&_li]:mt-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-2 [&_ul]:list-disc [&_ul]:pl-6';
const field =
    'h-12 w-full rounded-[10px] border border-input bg-card px-3 text-base';

/** The client's view of a quotation (signed link). */
export default function ClientQuote({ quote: q, company, links }: Props) {
    const [declining, setDeclining] = useState(false);
    const once = q.lines.filter((l) => l.frequency === 'once');
    const recurring = q.lines.filter(
        (l) => l.kind === 'item' && l.frequency !== 'once',
    );

    return (
        <>
            <Head title={`Quotation ${q.reference} | ${company}`}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <article className="mx-auto grid max-w-[1100px] gap-8 px-5 py-10 sm:px-8 lg:grid-cols-[1fr_340px]">
                <div className="grid content-start gap-8">
                    <header>
                        <p className="text-sm text-muted-foreground">
                            Quotation {q.reference} · for {q.client}
                        </p>
                        <h1 className="mt-1 font-display text-4xl leading-tight">
                            {q.title}
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            Issued {q.issueDate} · valid until{' '}
                            <strong className="text-foreground">
                                {q.validUntil}
                            </strong>
                        </p>
                    </header>

                    {q.scope && (
                        <section aria-labelledby="scope">
                            <h2 id="scope" className="font-display text-2xl">
                                Scope
                            </h2>
                            <div
                                className={prose}
                                dangerouslySetInnerHTML={{ __html: q.scope }}
                            />
                        </section>
                    )}

                    {once.length > 0 && (
                        <section aria-labelledby="costs">
                            <h2
                                id="costs"
                                className="mb-3 font-display text-2xl"
                            >
                                Once-off costs
                            </h2>
                            <div className="overflow-x-auto rounded-[14px] border border-border bg-card">
                                <table className="w-full min-w-[520px] text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Description
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Qty
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Amount (excl. VAT)
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {once.map((l, i) =>
                                            l.kind === 'heading' ? (
                                                <tr
                                                    key={i}
                                                    className="bg-muted"
                                                >
                                                    <td
                                                        colSpan={3}
                                                        className="px-4 py-2 font-semibold"
                                                    >
                                                        {l.description}
                                                    </td>
                                                </tr>
                                            ) : (
                                                <tr
                                                    key={i}
                                                    className="border-t border-border"
                                                >
                                                    <td className="px-4 py-3">
                                                        {l.description}
                                                    </td>
                                                    <td className="tabular px-4 py-3 text-right">
                                                        {l.quantity} {l.unit}
                                                    </td>
                                                    <td className="tabular px-4 py-3 text-right">
                                                        {randCents(l.net)}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    {recurring.length > 0 && (
                        <section aria-labelledby="ongoing">
                            <h2
                                id="ongoing"
                                className="mb-3 font-display text-2xl"
                            >
                                Ongoing costs
                            </h2>
                            <ul className="divide-y divide-border rounded-[14px] border border-border bg-card text-sm">
                                {recurring.map((l, i) => (
                                    <li
                                        key={i}
                                        className="flex justify-between gap-3 px-4 py-3"
                                    >
                                        <span>
                                            {l.description}{' '}
                                            <span className="text-muted-foreground">
                                                (
                                                {l.frequency === 'monthly'
                                                    ? 'monthly'
                                                    : 'annually'}
                                                )
                                            </span>
                                        </span>
                                        <span className="tabular">
                                            {randCents(l.net + l.vat)} incl. VAT
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {q.milestones.length > 0 && (
                        <section aria-labelledby="schedule">
                            <h2
                                id="schedule"
                                className="mb-3 font-display text-2xl"
                            >
                                Delivery schedule
                            </h2>
                            <ol className="grid gap-2">
                                {q.milestones.map((m, i) => (
                                    <li
                                        key={i}
                                        className="rounded-[14px] border border-border bg-card px-4 py-3"
                                    >
                                        <strong>{m.name}</strong>
                                        {(m.start || m.end) && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {m.start ?? '…'} to{' '}
                                                {m.end ?? '…'}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </section>
                    )}

                    {q.payments.length > 0 && (
                        <section aria-labelledby="payments">
                            <h2
                                id="payments"
                                className="mb-3 font-display text-2xl"
                            >
                                Payment schedule
                            </h2>
                            <ul className="divide-y divide-border rounded-[14px] border border-border bg-card text-sm">
                                {q.payments.map((p, i) => (
                                    <li
                                        key={i}
                                        className="flex flex-wrap justify-between gap-2 px-4 py-3"
                                    >
                                        <span>
                                            {p.label} ({p.percent}%)
                                            {p.due ? (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {p.due}
                                                </span>
                                            ) : null}
                                        </span>
                                        <span className="tabular">
                                            {randCents(p.amount)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            {q.paymentMethod && (
                                <p className="mt-3 rounded-[14px] bg-muted p-4 text-sm whitespace-pre-line">
                                    {q.paymentMethod}
                                </p>
                            )}
                        </section>
                    )}

                    {(['assumptions', 'exclusions', 'terms'] as const).map(
                        (k) =>
                            q[k] && (
                                <section key={k} aria-labelledby={k}>
                                    <h2
                                        id={k}
                                        className="font-display text-2xl"
                                    >
                                        {k === 'terms'
                                            ? 'Terms and conditions'
                                            : k[0].toUpperCase() + k.slice(1)}
                                    </h2>
                                    <div
                                        className={prose}
                                        dangerouslySetInnerHTML={{
                                            __html: q[k]!,
                                        }}
                                    />
                                </section>
                            ),
                    )}
                </div>

                <aside
                    aria-label="Summary and decision"
                    className="grid h-fit gap-4 rounded-[18px] border border-border bg-card p-6 shadow-[0_12px_32px_rgba(14,29,25,0.08)] lg:sticky lg:top-4"
                >
                    <dl className="grid gap-1 text-sm">
                        <div className="flex justify-between">
                            <dt>Subtotal</dt>
                            <dd className="tabular">
                                {randCents(q.totals.once_subtotal)}
                            </dd>
                        </div>
                        {q.totals.once_discount > 0 && (
                            <div className="flex justify-between">
                                <dt>Discount</dt>
                                <dd className="tabular">
                                    − {randCents(q.totals.once_discount)}
                                </dd>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <dt>VAT ({q.vatRate}%)</dt>
                            <dd className="tabular">
                                {randCents(q.totals.once_vat)}
                            </dd>
                        </div>
                        <div className="flex justify-between border-t border-border pt-2 text-lg font-bold">
                            <dt>Total</dt>
                            <dd className="tabular">
                                {randCents(q.totals.once_total)}
                            </dd>
                        </div>
                        {q.totals.monthly_total > 0 && (
                            <div className="flex justify-between font-semibold">
                                <dt>Plus per month</dt>
                                <dd className="tabular">
                                    {randCents(q.totals.monthly_total)}
                                </dd>
                            </div>
                        )}
                        {q.totals.annual_total > 0 && (
                            <div className="flex justify-between font-semibold">
                                <dt>Plus per year</dt>
                                <dd className="tabular">
                                    {randCents(q.totals.annual_total)}
                                </dd>
                            </div>
                        )}
                    </dl>
                    <a
                        href={links.pdf}
                        className="flex min-h-12 items-center justify-center gap-2 rounded-[10px] border border-input font-semibold"
                    >
                        <FileDown className="size-5" aria-hidden="true" />
                        Download PDF
                    </a>

                    {q.accepted ? (
                        <div
                            role="status"
                            className="rounded-[10px] bg-status-live/10 p-4 text-sm"
                        >
                            <p className="font-semibold text-status-live">
                                Accepted by {q.accepted.name} on {q.accepted.at}{' '}
                                SAST
                            </p>
                            <p className="mt-1 break-all text-muted-foreground">
                                Fingerprint {q.accepted.fingerprint}
                            </p>
                        </div>
                    ) : q.open ? (
                        declining ? (
                            <Form
                                action={links.decline}
                                method="post"
                                className="grid gap-3"
                            >
                                {({ processing }) => (
                                    <>
                                        <label className="grid gap-1.5 text-sm font-semibold">
                                            Reason (optional)
                                            <textarea
                                                name="reason"
                                                className="min-h-24 rounded-[10px] border border-input bg-card px-3 py-2 font-normal"
                                            />
                                        </label>
                                        <button
                                            disabled={processing}
                                            className="min-h-12 rounded-[10px] border border-input font-semibold"
                                        >
                                            Decline quotation
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setDeclining(false)}
                                            className="text-sm underline"
                                        >
                                            Back
                                        </button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <Form
                                action={links.accept}
                                method="post"
                                className="grid gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <label className="grid gap-1.5 text-sm font-semibold">
                                            Your full name
                                            <input
                                                name="name"
                                                required
                                                autoComplete="name"
                                                className={field}
                                            />
                                            {errors.name && (
                                                <span className="text-destructive">
                                                    {errors.name}
                                                </span>
                                            )}
                                        </label>
                                        <label className="flex items-start gap-3 text-sm leading-relaxed">
                                            <input
                                                type="checkbox"
                                                name="agree"
                                                value="1"
                                                className="mt-0.5 size-5 accent-primary"
                                            />
                                            <span>
                                                I accept this quotation and its
                                                terms on behalf of {q.client}.
                                            </span>
                                        </label>
                                        {(errors.agree || errors.quote) && (
                                            <p className="text-sm font-semibold text-destructive">
                                                {errors.agree ?? errors.quote}
                                            </p>
                                        )}
                                        <button
                                            disabled={processing}
                                            className="min-h-12 rounded-[10px] bg-primary text-[17px] font-semibold text-primary-foreground disabled:opacity-60"
                                        >
                                            Accept quotation
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setDeclining(true)}
                                            className="text-sm underline"
                                        >
                                            Decline instead
                                        </button>
                                    </>
                                )}
                            </Form>
                        )
                    ) : (
                        <p className="rounded-[10px] bg-muted p-4 text-sm">
                            This quotation is {q.statusLabel.toLowerCase()}.
                            Contact {company} for an updated quotation.
                        </p>
                    )}
                </aside>
            </article>
        </>
    );
}
