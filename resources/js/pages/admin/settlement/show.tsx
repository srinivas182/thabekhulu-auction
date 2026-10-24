import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { randCents } from '@/lib/cents';
import { rand } from '@/lib/money';
import {
    complete,
    defaultMethod,
    index,
    step,
} from '@/routes/admin/settlement';
import defaultRoutes from '@/routes/admin/settlement/default';

type Props = {
    case: {
        ulid: string;
        listing: string;
        mining: boolean;
        kind: string;
        buyer: string;
        email: string;
        price: number;
        status: string;
        statusLabel: string;
        agreementDue: string | null;
        balanceDue: string | null;
        offerExpires: string | null;
        missed: string | null;
        depositTerms: string;
        held: number | null;
        depositRef: string | null;
        tenPercent: number;
    };
    steps: {
        key: string;
        label: string;
        doneOn: string | null;
        note: string | null;
        document: string | null;
    }[];
    default: {
        status: string;
        reason: string;
        auctionCost: number;
        forfeit: number;
        securitySignoff: boolean;
        declaredBy: string | null;
        declaredByMe: boolean;
        confirmedBy: string | null;
    } | null;
    can: { manage: boolean; declare: boolean; confirm: boolean };
};

export default function SettlementShow({
    case: c,
    steps,
    default: d,
    can,
}: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [cost, setCost] = useState('');
    const costCents = Math.round(
        (Number(cost.replace(/[^\d.]/g, '')) || 0) * 100,
    );
    const cap = Math.max(0, Math.min(c.tenPercent, costCents, c.held ?? 0));
    const opts = { preserveScroll: true };

    return (
        <>
            <Head title={`Settlement: ${c.listing}`} />
            <div className="grid flex-1 gap-5 p-4 sm:p-6 xl:grid-cols-[1fr_380px]">
                <div className="grid content-start gap-5">
                    <div>
                        <Link href={index()} className="text-sm underline">
                            Settlement
                        </Link>
                        <h1 className="font-display text-3xl">{c.listing}</h1>
                        <p className="text-muted-foreground">
                            {c.mining
                                ? 'Preferred bidder: subject to approvals'
                                : 'Sold, pending transfer'}{' '}
                            · {c.buyer} · {rand(c.price)} · {c.statusLabel}
                        </p>
                    </div>
                    {(errors.case || errors.default) && (
                        <p
                            role="alert"
                            className="rounded-md border border-destructive px-4 py-3 text-sm text-destructive"
                        >
                            {errors.case ?? errors.default}
                        </p>
                    )}
                    {c.missed && (
                        <p
                            role="alert"
                            className="rounded-md bg-destructive/10 px-4 py-3 text-sm font-semibold text-destructive"
                        >
                            Deadline missed: {c.missed}
                        </p>
                    )}
                    {c.offerExpires && (
                        <p className="rounded-md bg-muted px-4 py-3 text-sm">
                            Offered to the runner-up at their own highest bid;
                            they have until {c.offerExpires} to accept.
                        </p>
                    )}

                    <section
                        aria-labelledby="steps-h"
                        className="grid gap-3 rounded-xl border border-border bg-card p-5"
                    >
                        <h2 id="steps-h" className="font-semibold">
                            Steps{' '}
                            <span className="font-normal text-muted-foreground">
                                · agreement by {c.agreementDue ?? '—'} · balance
                                by {c.balanceDue ?? '—'}
                            </span>
                        </h2>
                        <ul className="grid gap-2 text-sm">
                            {steps.map((s) => (
                                <li
                                    key={s.key}
                                    className="flex flex-wrap items-center gap-2"
                                >
                                    {s.doneOn ? (
                                        <Check
                                            className="size-4 text-status-live"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <span className="inline-block size-4 rounded-full border border-input" />
                                    )}
                                    <span className="font-medium">
                                        {s.label}
                                    </span>
                                    {s.doneOn && (
                                        <span className="text-muted-foreground">
                                            · {s.doneOn}
                                            {s.note ? ` · ${s.note}` : ''}
                                        </span>
                                    )}
                                    {s.document && (
                                        <a
                                            href={s.document}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="underline"
                                        >
                                            document
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                        {can.manage && c.status === 'open' && (
                            <Form
                                action={step(c.ulid).url}
                                method="post"
                                options={opts}
                                className="grid gap-2 border-t border-border pt-3 sm:grid-cols-[1.3fr_170px_1fr_auto] sm:items-end"
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="grid gap-1">
                                            <Label htmlFor="st">Step</Label>
                                            <select
                                                id="st"
                                                name="step"
                                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                            >
                                                {steps.map((s) => (
                                                    <option
                                                        key={s.key}
                                                        value={s.key}
                                                    >
                                                        {s.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="dn">Date</Label>
                                            <Input
                                                id="dn"
                                                type="date"
                                                name="done_on"
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="doc">
                                                Document
                                            </Label>
                                            <input
                                                id="doc"
                                                type="file"
                                                name="document"
                                                accept=".pdf,.jpg,.jpeg,.png"
                                                className="text-xs"
                                            />
                                        </div>
                                        <Button size="sm" disabled={processing}>
                                            Record
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                        {can.manage && c.status === 'open' && (
                            <Button
                                className="w-fit"
                                variant="outline"
                                onClick={() =>
                                    window.confirm(
                                        c.depositTerms === 'applied'
                                            ? 'Complete settlement? The deposit is applied to the purchase price.'
                                            : 'Complete settlement? The security deposit is scheduled for refund.',
                                    ) &&
                                    router.post(complete(c.ulid).url, {}, opts)
                                }
                            >
                                Record completion
                            </Button>
                        )}
                    </section>
                </div>

                <aside className="grid content-start gap-5">
                    <section
                        aria-labelledby="dep-h"
                        className="rounded-xl border border-border bg-card p-5 text-sm"
                    >
                        <h2 id="dep-h" className="font-semibold">
                            Deposit
                        </h2>
                        <p className="mt-1">
                            {c.depositRef} · held {randCents(c.held ?? 0)} ·{' '}
                            {c.depositTerms === 'security'
                                ? 'security deposit'
                                : 'applied to the price'}
                        </p>
                    </section>

                    <section
                        aria-labelledby="def-h"
                        className="grid gap-3 rounded-xl border border-border bg-card p-5"
                    >
                        <h2 id="def-h" className="font-semibold">
                            Default
                        </h2>
                        {d ? (
                            <div className="grid gap-2 text-sm">
                                <p>
                                    <strong>
                                        {d.status === 'confirmed'
                                            ? 'Confirmed'
                                            : 'Waiting for a second person'}
                                    </strong>{' '}
                                    · declared by {d.declaredBy}
                                    {d.confirmedBy
                                        ? ` · confirmed by ${d.confirmedBy}`
                                        : ''}
                                </p>
                                <p className="text-muted-foreground">
                                    {d.reason}
                                </p>
                                <p>
                                    Forfeiture:{' '}
                                    <strong>{randCents(d.forfeit)}</strong>{' '}
                                    (auction cost {randCents(d.auctionCost)})
                                </p>
                                {d.status === 'proposed' &&
                                    can.confirm &&
                                    (d.declaredByMe ? (
                                        <p className="rounded-md bg-muted px-3 py-2">
                                            You declared this default; another
                                            person must confirm it.
                                        </p>
                                    ) : (
                                        <Button
                                            onClick={() =>
                                                window.confirm(
                                                    `Confirm the default? ${randCents(d.forfeit)} is forfeited, any excess refunded, the buyer suspended and the runner-up offered the property.`,
                                                ) &&
                                                router.post(
                                                    defaultRoutes.confirm(
                                                        c.ulid,
                                                    ).url,
                                                    {},
                                                    opts,
                                                )
                                            }
                                        >
                                            Confirm default
                                        </Button>
                                    ))}
                            </div>
                        ) : c.status === 'open' && can.declare ? (
                            c.missed ? (
                                <Form
                                    action={defaultMethod(c.ulid).url}
                                    method="post"
                                    options={opts}
                                    className="grid gap-2 text-sm"
                                >
                                    {({ processing }) => (
                                        <>
                                            <Label htmlFor="reason">
                                                Reason
                                            </Label>
                                            <textarea
                                                id="reason"
                                                name="reason"
                                                className="min-h-20 rounded-md border border-input bg-background px-3 py-2"
                                            />
                                            <Label htmlFor="cost">
                                                Actual auction cost (R)
                                            </Label>
                                            <Input
                                                id="cost"
                                                name="auction_cost"
                                                inputMode="decimal"
                                                value={cost}
                                                onChange={(e) =>
                                                    setCost(e.target.value)
                                                }
                                            />
                                            {errors.auction_cost && (
                                                <p className="text-destructive">
                                                    {errors.auction_cost}
                                                </p>
                                            )}
                                            <p className="text-muted-foreground">
                                                Forfeiture = the lowest of 10%
                                                of the price (
                                                {randCents(c.tenPercent)}), the
                                                auction cost (
                                                {randCents(costCents)}) and the
                                                deposit held (
                                                {randCents(c.held ?? 0)}):{' '}
                                                <strong className="text-foreground">
                                                    {randCents(cap)}
                                                </strong>
                                            </p>
                                            {c.depositTerms === 'security' && (
                                                <label className="flex items-start gap-2">
                                                    <input
                                                        type="checkbox"
                                                        name="security_signoff"
                                                        value="1"
                                                        className="mt-0.5 size-4 accent-primary"
                                                    />
                                                    <span>
                                                        This is a security
                                                        deposit. I confirm
                                                        forfeiture is approved
                                                        under the legally
                                                        reviewed sale terms.
                                                    </span>
                                                </label>
                                            )}
                                            {errors.security_signoff && (
                                                <p className="text-destructive">
                                                    {errors.security_signoff}
                                                </p>
                                            )}
                                            <Button
                                                variant="destructive"
                                                disabled={processing}
                                                className="w-fit"
                                            >
                                                Declare default
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    A default can be declared only after a
                                    missed deadline.
                                </p>
                            )
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No default.
                            </p>
                        )}
                    </section>
                </aside>
            </div>
        </>
    );
}

SettlementShow.layout = {
    breadcrumbs: [{ title: 'Settlement', href: index() }],
};
