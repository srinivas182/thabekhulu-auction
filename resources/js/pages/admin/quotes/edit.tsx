import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, FileDown, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import QuotesNav from '@/components/quotes-nav';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { randCents } from '@/lib/cents';
import { cn } from '@/lib/utils';
import {
    destroy,
    index,
    pdf,
    template as saveTemplate,
    update,
} from '@/routes/admin/quotes';

type Line = {
    kind: 'item' | 'heading';
    frequency: 'once' | 'monthly' | 'annual';
    description: string;
    quantity: number;
    unit: string | null;
    unit_price: number;
    discount_type: 'percent' | 'amount' | null;
    discount_value: number;
    vat_applicable: boolean;
};
type Milestone = {
    name: string;
    starts_on: string | null;
    ends_on: string | null;
    description: string | null;
};
type Payment = { label: string; percent: number; due: string | null };
type Quote = {
    ulid: string;
    reference: string;
    status: string;
    statusLabel: string;
    editable: boolean;
    title: string;
    client_id: number;
    listing_id: number | null;
    issue_date: string;
    valid_until: string;
    vatRate: number;
    payment_method: string | null;
    scope: string | null;
    assumptions: string | null;
    exclusions: string | null;
    terms: string | null;
    internal_notes: string | null;
    createdBy: string | null;
    lines: Line[];
    milestones: Milestone[];
    payments: Payment[];
};
type CatalogueItem = {
    id: number;
    name: string;
    description: string | null;
    unit: string;
    unit_price: number;
    vat_applicable: boolean;
    frequency: Line['frequency'];
};
type Props = {
    quote: Quote;
    clients: { id: number; name: string; company: string | null }[];
    catalogue: CatalogueItem[];
    listings: { id: number; title: string }[];
    can: { templates: boolean };
};

const select =
    'h-9 w-full rounded-md border border-input bg-background px-2 text-sm disabled:opacity-60';
const opts = { preserveScroll: true };

/** Same rounding as App\Domains\Quotes\Support\QuoteMath (server recalculates on save). */
function lineCalc(l: Line, vatBps: number) {
    if (l.kind !== 'item') return { gross: 0, discount: 0, net: 0, vat: 0 };
    const qty = Math.round((Number(l.quantity) || 0) * 100);
    const price = Math.round((Number(l.unit_price) || 0) * 100);
    const gross = Math.floor((qty * price + 50) / 100);
    const discount =
        l.discount_type === 'percent'
            ? Math.floor(
                  (gross *
                      Math.min(
                          Math.round((Number(l.discount_value) || 0) * 100),
                          10000,
                      ) +
                      5000) /
                      10000,
              )
            : l.discount_type === 'amount'
              ? Math.min(
                    Math.round((Number(l.discount_value) || 0) * 100),
                    gross,
                )
              : 0;
    const net = gross - discount;
    const vat = l.vat_applicable
        ? Math.floor((net * vatBps + 5000) / 10000)
        : 0;

    return { gross, discount, net, vat };
}

export default function QuoteEdit({
    quote,
    clients,
    catalogue,
    listings,
    can,
}: Props) {
    const ro = !quote.editable;
    const [f, setF] = useState({
        title: quote.title,
        client_id: quote.client_id,
        listing_id: quote.listing_id,
        issue_date: quote.issue_date,
        valid_until: quote.valid_until,
        payment_method: quote.payment_method ?? '',
        scope: quote.scope ?? '',
        assumptions: quote.assumptions ?? '',
        exclusions: quote.exclusions ?? '',
        terms: quote.terms ?? '',
        internal_notes: quote.internal_notes ?? '',
    });
    const [lines, setLines] = useState<Line[]>(quote.lines);
    const [milestones, setMilestones] = useState<Milestone[]>(quote.milestones);
    const [payments, setPayments] = useState<Payment[]>(quote.payments);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const vatBps = Math.round(quote.vatRate * 100);

    const setLine = (i: number, patch: Partial<Line>) =>
        setLines(lines.map((l, k) => (k === i ? { ...l, ...patch } : l)));
    const moveLine = (i: number, d: -1 | 1) => {
        const n = [...lines];
        [n[i], n[i + d]] = [n[i + d], n[i]];
        setLines(n);
    };
    const blank: Line = {
        kind: 'item',
        frequency: 'once',
        description: '',
        quantity: 1,
        unit: 'each',
        unit_price: 0,
        discount_type: null,
        discount_value: 0,
        vat_applicable: true,
    };

    const calcs = lines.map((l) => lineCalc(l, vatBps));
    const sum = (
        freq: Line['frequency'],
        key: 'gross' | 'discount' | 'vat' | 'total',
    ) =>
        lines.reduce(
            (t, l, i) =>
                l.kind === 'item' && l.frequency === freq
                    ? t +
                      (key === 'total'
                          ? calcs[i].net + calcs[i].vat
                          : calcs[i][key])
                    : t,
            0,
        );
    const paymentTotal = payments.reduce(
        (t, p) => t + (Number(p.percent) || 0),
        0,
    );
    const save = () =>
        router.put(
            update(quote.ulid).url,
            { ...f, lines, milestones, payments } as never,
            { ...opts, onError: setErrors, onSuccess: () => setErrors({}) },
        );
    const firstError = Object.entries(errors).find(([k]) =>
        k.startsWith('lines'),
    )?.[1];

    return (
        <>
            <Head title={quote.reference} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {quote.statusLabel}
                            {quote.createdBy
                                ? ` · prepared by ${quote.createdBy}`
                                : ''}
                        </p>
                        <h1 className="font-display text-3xl">
                            {quote.reference}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a
                            href={pdf(quote.ulid).url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex h-9 items-center gap-2 rounded-md border border-input px-3 text-sm font-medium"
                        >
                            <FileDown className="size-4" aria-hidden="true" />
                            Preview PDF
                        </a>
                        {can.templates && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    const name = window.prompt('Template name');
                                    if (name)
                                        router.post(
                                            saveTemplate(quote.ulid).url,
                                            { name },
                                            opts,
                                        );
                                }}
                            >
                                Save as template
                            </Button>
                        )}
                        {quote.status === 'draft' && (
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    window.confirm('Delete this draft?') &&
                                    router.delete(destroy(quote.ulid).url)
                                }
                            >
                                Delete draft
                            </Button>
                        )}
                        {!ro && <Button onClick={save}>Save</Button>}
                    </div>
                </div>
                <QuotesNav current="Quotations" />
                {errors.quote && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive px-4 py-3 text-sm text-destructive"
                    >
                        {errors.quote}
                    </p>
                )}

                <section
                    aria-label="Details"
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-2 xl:grid-cols-4"
                >
                    <div className="grid gap-1.5 md:col-span-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            disabled={ro}
                            value={f.title}
                            onChange={(e) =>
                                setF({ ...f, title: e.target.value })
                            }
                        />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="client">Client</Label>
                        <select
                            id="client"
                            disabled={ro}
                            className={select}
                            value={f.client_id}
                            onChange={(e) =>
                                setF({
                                    ...f,
                                    client_id: Number(e.target.value),
                                })
                            }
                        >
                            {clients.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.company
                                        ? `${c.company} (${c.name})`
                                        : c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="listing">Property (optional)</Label>
                        <select
                            id="listing"
                            disabled={ro}
                            className={select}
                            value={f.listing_id ?? ''}
                            onChange={(e) =>
                                setF({
                                    ...f,
                                    listing_id: e.target.value
                                        ? Number(e.target.value)
                                        : null,
                                })
                            }
                        >
                            <option value="">None</option>
                            {listings.map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.title}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="issued">Date</Label>
                        <Input
                            id="issued"
                            type="date"
                            disabled={ro}
                            value={f.issue_date}
                            onChange={(e) =>
                                setF({ ...f, issue_date: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="valid">Valid until</Label>
                        <Input
                            id="valid"
                            type="date"
                            disabled={ro}
                            value={f.valid_until}
                            onChange={(e) =>
                                setF({ ...f, valid_until: e.target.value })
                            }
                        />
                        <InputError message={errors.valid_until} />
                    </div>
                </section>

                <section
                    aria-label="Line items"
                    className="grid gap-3 rounded-xl border border-border bg-card p-5"
                >
                    <h2 className="font-semibold">
                        Line items{' '}
                        <span className="font-normal text-muted-foreground">
                            · prices excl. VAT ({quote.vatRate}%)
                        </span>
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1040px] text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr>
                                    <th className="py-2 pr-2 font-medium">
                                        Description
                                    </th>
                                    <th className="w-28 pr-2 font-medium">
                                        Billed
                                    </th>
                                    <th className="w-20 pr-2 font-medium">
                                        Qty
                                    </th>
                                    <th className="w-20 pr-2 font-medium">
                                        Unit
                                    </th>
                                    <th className="w-32 pr-2 font-medium">
                                        Unit price (R)
                                    </th>
                                    <th className="w-40 pr-2 font-medium">
                                        Discount
                                    </th>
                                    <th className="w-14 pr-2 font-medium">
                                        VAT
                                    </th>
                                    <th className="w-32 pr-2 text-right font-medium">
                                        Amount
                                    </th>
                                    <th className="w-24" />
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((l, i) => (
                                    <tr
                                        key={i}
                                        className={cn(
                                            'border-t border-border',
                                            l.kind === 'heading' && 'bg-muted',
                                        )}
                                    >
                                        <td className="py-2 pr-2">
                                            <Input
                                                aria-label={`Line ${i + 1} description`}
                                                disabled={ro}
                                                value={l.description}
                                                placeholder={
                                                    l.kind === 'heading'
                                                        ? 'Group heading'
                                                        : 'Description'
                                                }
                                                className={
                                                    l.kind === 'heading'
                                                        ? 'font-semibold'
                                                        : ''
                                                }
                                                onChange={(e) =>
                                                    setLine(i, {
                                                        description:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        {l.kind === 'item' ? (
                                            <>
                                                <td className="pr-2">
                                                    <select
                                                        aria-label="Billed"
                                                        disabled={ro}
                                                        className={select}
                                                        value={l.frequency}
                                                        onChange={(e) =>
                                                            setLine(i, {
                                                                frequency: e
                                                                    .target
                                                                    .value as Line['frequency'],
                                                            })
                                                        }
                                                    >
                                                        <option value="once">
                                                            Once-off
                                                        </option>
                                                        <option value="monthly">
                                                            Monthly
                                                        </option>
                                                        <option value="annual">
                                                            Annually
                                                        </option>
                                                    </select>
                                                </td>
                                                <td className="pr-2">
                                                    <Input
                                                        aria-label="Quantity"
                                                        disabled={ro}
                                                        inputMode="decimal"
                                                        value={l.quantity}
                                                        onChange={(e) =>
                                                            setLine(i, {
                                                                quantity: e
                                                                    .target
                                                                    .value as unknown as number,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="pr-2">
                                                    <Input
                                                        aria-label="Unit"
                                                        disabled={ro}
                                                        value={l.unit ?? ''}
                                                        onChange={(e) =>
                                                            setLine(i, {
                                                                unit: e.target
                                                                    .value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="pr-2">
                                                    <Input
                                                        aria-label="Unit price"
                                                        disabled={ro}
                                                        inputMode="decimal"
                                                        className="tabular"
                                                        value={l.unit_price}
                                                        onChange={(e) =>
                                                            setLine(i, {
                                                                unit_price: e
                                                                    .target
                                                                    .value as unknown as number,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="pr-2">
                                                    <div className="flex gap-1">
                                                        <select
                                                            aria-label="Discount type"
                                                            disabled={ro}
                                                            className={select}
                                                            value={
                                                                l.discount_type ??
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                setLine(i, {
                                                                    discount_type:
                                                                        (e
                                                                            .target
                                                                            .value ||
                                                                            null) as Line['discount_type'],
                                                                })
                                                            }
                                                        >
                                                            <option value="">
                                                                None
                                                            </option>
                                                            <option value="percent">
                                                                %
                                                            </option>
                                                            <option value="amount">
                                                                R
                                                            </option>
                                                        </select>
                                                        {l.discount_type && (
                                                            <Input
                                                                aria-label="Discount"
                                                                disabled={ro}
                                                                inputMode="decimal"
                                                                value={
                                                                    l.discount_value
                                                                }
                                                                onChange={(e) =>
                                                                    setLine(i, {
                                                                        discount_value:
                                                                            e
                                                                                .target
                                                                                .value as unknown as number,
                                                                    })
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="pr-2 text-center">
                                                    <input
                                                        type="checkbox"
                                                        aria-label="VAT applies"
                                                        disabled={ro}
                                                        className="size-4 accent-primary"
                                                        checked={
                                                            l.vat_applicable
                                                        }
                                                        onChange={(e) =>
                                                            setLine(i, {
                                                                vat_applicable:
                                                                    e.target
                                                                        .checked,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="tabular pr-2 text-right">
                                                    {randCents(calcs[i].net)}
                                                </td>
                                            </>
                                        ) : (
                                            <td colSpan={7} />
                                        )}
                                        <td className="whitespace-nowrap">
                                            {!ro && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        aria-label="Move up"
                                                        disabled={i === 0}
                                                        onClick={() =>
                                                            moveLine(i, -1)
                                                        }
                                                    >
                                                        <ArrowUp />
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        aria-label="Move down"
                                                        disabled={
                                                            i ===
                                                            lines.length - 1
                                                        }
                                                        onClick={() =>
                                                            moveLine(i, 1)
                                                        }
                                                    >
                                                        <ArrowDown />
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        aria-label="Remove line"
                                                        onClick={() =>
                                                            setLines(
                                                                lines.filter(
                                                                    (_, k) =>
                                                                        k !== i,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <InputError message={firstError} />
                    {!ro && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setLines([...lines, blank])}
                            >
                                Add item
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    setLines([
                                        ...lines,
                                        {
                                            ...blank,
                                            kind: 'heading',
                                            unit: null,
                                        },
                                    ])
                                }
                            >
                                Add heading
                            </Button>
                            {catalogue.length > 0 && (
                                <select
                                    aria-label="Add from catalogue"
                                    className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                                    value=""
                                    onChange={(e) => {
                                        const c = catalogue.find(
                                            (x) =>
                                                x.id === Number(e.target.value),
                                        );
                                        if (c)
                                            setLines([
                                                ...lines,
                                                {
                                                    ...blank,
                                                    description: c.name,
                                                    unit: c.unit,
                                                    unit_price: c.unit_price,
                                                    vat_applicable:
                                                        c.vat_applicable,
                                                    frequency: c.frequency,
                                                },
                                            ]);
                                    }}
                                >
                                    <option value="">
                                        Add from catalogue…
                                    </option>
                                    {catalogue.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name} · R {c.unit_price}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>
                    )}
                    <dl className="ml-auto grid w-full max-w-sm gap-1 text-sm">
                        <div className="flex justify-between">
                            <dt>Subtotal</dt>
                            <dd className="tabular">
                                {randCents(sum('once', 'gross'))}
                            </dd>
                        </div>
                        {sum('once', 'discount') > 0 && (
                            <div className="flex justify-between">
                                <dt>Discount</dt>
                                <dd className="tabular">
                                    − {randCents(sum('once', 'discount'))}
                                </dd>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <dt>VAT ({quote.vatRate}%)</dt>
                            <dd className="tabular">
                                {randCents(sum('once', 'vat'))}
                            </dd>
                        </div>
                        <div className="flex justify-between border-t border-border pt-1 text-base font-bold">
                            <dt>Once-off total</dt>
                            <dd className="tabular">
                                {randCents(sum('once', 'total'))}
                            </dd>
                        </div>
                        {sum('monthly', 'total') > 0 && (
                            <div className="flex justify-between font-semibold">
                                <dt>Ongoing per month (incl. VAT)</dt>
                                <dd className="tabular">
                                    {randCents(sum('monthly', 'total'))}
                                </dd>
                            </div>
                        )}
                        {sum('annual', 'total') > 0 && (
                            <div className="flex justify-between font-semibold">
                                <dt>Ongoing per year (incl. VAT)</dt>
                                <dd className="tabular">
                                    {randCents(sum('annual', 'total'))}
                                </dd>
                            </div>
                        )}
                    </dl>
                </section>

                <div className="grid gap-5 xl:grid-cols-2">
                    <section
                        aria-label="Delivery schedule"
                        className="grid content-start gap-3 rounded-xl border border-border bg-card p-5"
                    >
                        <h2 className="font-semibold">Delivery schedule</h2>
                        {milestones.map((m, i) => (
                            <div
                                key={i}
                                className="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-[1fr_140px_140px_auto]"
                            >
                                <Input
                                    aria-label={`Milestone ${i + 1}`}
                                    disabled={ro}
                                    value={m.name}
                                    onChange={(e) =>
                                        setMilestones(
                                            milestones.map((x, k) =>
                                                k === i
                                                    ? {
                                                          ...x,
                                                          name: e.target.value,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                <Input
                                    aria-label="Start"
                                    type="date"
                                    disabled={ro}
                                    value={m.starts_on ?? ''}
                                    onChange={(e) =>
                                        setMilestones(
                                            milestones.map((x, k) =>
                                                k === i
                                                    ? {
                                                          ...x,
                                                          starts_on:
                                                              e.target.value ||
                                                              null,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                <Input
                                    aria-label="End"
                                    type="date"
                                    disabled={ro}
                                    value={m.ends_on ?? ''}
                                    onChange={(e) =>
                                        setMilestones(
                                            milestones.map((x, k) =>
                                                k === i
                                                    ? {
                                                          ...x,
                                                          ends_on:
                                                              e.target.value ||
                                                              null,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                {!ro && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label="Remove milestone"
                                        onClick={() =>
                                            setMilestones(
                                                milestones.filter(
                                                    (_, k) => k !== i,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </div>
                        ))}
                        {!ro && (
                            <Button
                                size="sm"
                                variant="outline"
                                className="w-fit"
                                onClick={() =>
                                    setMilestones([
                                        ...milestones,
                                        {
                                            name: '',
                                            starts_on: null,
                                            ends_on: null,
                                            description: null,
                                        },
                                    ])
                                }
                            >
                                Add milestone
                            </Button>
                        )}
                    </section>
                    <section
                        aria-label="Payment schedule"
                        className="grid content-start gap-3 rounded-xl border border-border bg-card p-5"
                    >
                        <h2 className="font-semibold">
                            Payment schedule{' '}
                            <span
                                className={cn(
                                    'font-normal',
                                    payments.length > 0 && paymentTotal !== 100
                                        ? 'text-destructive'
                                        : 'text-muted-foreground',
                                )}
                            >
                                · {paymentTotal}% of 100%
                            </span>
                        </h2>
                        {payments.map((p, i) => (
                            <div
                                key={i}
                                className="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-[1fr_90px_1fr_auto]"
                            >
                                <Input
                                    aria-label={`Payment ${i + 1}`}
                                    disabled={ro}
                                    value={p.label}
                                    onChange={(e) =>
                                        setPayments(
                                            payments.map((x, k) =>
                                                k === i
                                                    ? {
                                                          ...x,
                                                          label: e.target.value,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                <Input
                                    aria-label="Percentage"
                                    disabled={ro}
                                    inputMode="decimal"
                                    value={p.percent}
                                    onChange={(e) =>
                                        setPayments(
                                            payments.map((x, k) =>
                                                k === i
                                                    ? {
                                                          ...x,
                                                          percent: e.target
                                                              .value as unknown as number,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                <Input
                                    aria-label="Due"
                                    disabled={ro}
                                    placeholder="e.g. On acceptance"
                                    value={p.due ?? ''}
                                    onChange={(e) =>
                                        setPayments(
                                            payments.map((x, k) =>
                                                k === i
                                                    ? {
                                                          ...x,
                                                          due: e.target.value,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                {!ro && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label="Remove payment"
                                        onClick={() =>
                                            setPayments(
                                                payments.filter(
                                                    (_, k) => k !== i,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </div>
                        ))}
                        <InputError message={errors.payments} />
                        {!ro && (
                            <Button
                                size="sm"
                                variant="outline"
                                className="w-fit"
                                onClick={() =>
                                    setPayments([
                                        ...payments,
                                        { label: '', percent: 0, due: null },
                                    ])
                                }
                            >
                                Add payment
                            </Button>
                        )}
                        <div className="grid gap-1.5">
                            <Label htmlFor="pm">How to pay</Label>
                            <textarea
                                id="pm"
                                disabled={ro}
                                className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={f.payment_method}
                                onChange={(e) =>
                                    setF({
                                        ...f,
                                        payment_method: e.target.value,
                                    })
                                }
                                placeholder="Bank details or escrow instructions"
                            />
                        </div>
                    </section>
                </div>

                {(['scope', 'assumptions', 'exclusions', 'terms'] as const).map(
                    (key) => (
                        <section
                            key={key}
                            aria-label={key}
                            className="grid gap-2 rounded-xl border border-border bg-card p-5"
                        >
                            <h2 className="font-semibold capitalize">
                                {key === 'terms' ? 'Terms and conditions' : key}
                            </h2>
                            {ro ? (
                                <div
                                    className="text-sm"
                                    dangerouslySetInnerHTML={{ __html: f[key] }}
                                />
                            ) : (
                                <RichTextEditor
                                    id={key}
                                    label={key}
                                    value={f[key]}
                                    onChange={(html) =>
                                        setF({ ...f, [key]: html })
                                    }
                                />
                            )}
                        </section>
                    ),
                )}
                <section
                    aria-label="Internal notes"
                    className="grid gap-2 rounded-xl border border-dashed border-border p-5"
                >
                    <Label htmlFor="notes">
                        Internal notes (never shown to the client)
                    </Label>
                    <textarea
                        id="notes"
                        disabled={ro}
                        className="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                        value={f.internal_notes}
                        onChange={(e) =>
                            setF({ ...f, internal_notes: e.target.value })
                        }
                    />
                </section>
                {!ro && (
                    <div>
                        <Button onClick={save}>Save quotation</Button>
                    </div>
                )}
            </div>
        </>
    );
}

QuoteEdit.layout = { breadcrumbs: [{ title: 'Quotations', href: index() }] };
