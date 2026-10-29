import { Form, Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { randCents } from '@/lib/cents';
import { rand } from '@/lib/money';
import {
    complete,
    defaultMethod,
    index,
    questions,
    step,
} from '@/routes/admin/settlement';
import defaultRoutes from '@/routes/admin/settlement/default';
import {
    invoice as invoiceRoute,
    payout as payoutRoute,
    transfer as transferRoute,
} from '@/routes/admin/settlement';
import payoutRoutes from '@/routes/admin/settlement/payout';
import uploadRoutes from '@/routes/admin/settlement-uploads';

type Props = {
    case: {
        messages: {
            author: string;
            kind: string;
            body: string | null;
            by: string | null;
            at: string;
            upload: number | null;
        }[];
        openQuestion: boolean;
        uploads: {
            id: number;
            step: string;
            status: string;
            reason: string | null;
            url: string;
            at: string;
        }[];
        balance: {
            reference: string;
            due: number;
            received: number;
            status: string;
            proofUrl: string | null;
        } | null;
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
    can: {
        manage: boolean;
        declare: boolean;
        confirm: boolean;
        payout: boolean;
        approvePayout: boolean;
    };
    money: {
        figures: {
            deal: string;
            price: number;
            fee: number;
            vat: number;
            amount: number;
            vatRate: number;
        };
        payout: {
            id: number;
            status: string;
            payee: string;
            reference: string | null;
            amount: number;
            preparedByMe: boolean;
            preparedBy: string | null;
            approvedBy: string | null;
        } | null;
        invoice: { number: string; total: number; url: string } | null;
    } | null;
    transfer: {
        stages: { key: string; label: string; doneOn: string | null }[];
    } | null;
};

export default function SettlementShow({
    case: c,
    steps,
    default: d,
    can,
    money,
    transfer,
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

                <aside className="grid min-w-0 content-start gap-5 [&_*]:min-w-0">
                    <BuyerPayments c={c} />
                    <Conversation c={c} />
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
                    {money && (
                        <section
                            aria-labelledby="pay-h"
                            className="grid gap-3 rounded-xl border border-border bg-card p-5 text-sm"
                        >
                            <h2 id="pay-h" className="font-semibold">
                                Seller payout
                            </h2>
                            <dl className="grid gap-1">
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Price
                                    </dt>
                                    <dd className="tabular">
                                        {randCents(money.figures.price)}
                                    </dd>
                                </div>
                                {money.figures.deal === 'commission' ? (
                                    <>
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">
                                                Platform fee
                                            </dt>
                                            <dd className="tabular">
                                                − {randCents(money.figures.fee)}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">
                                                VAT on the fee (
                                                {money.figures.vatRate / 100}%)
                                            </dt>
                                            <dd className="tabular">
                                                − {randCents(money.figures.vat)}
                                            </dd>
                                        </div>
                                    </>
                                ) : (
                                    <p className="text-muted-foreground">
                                        Thabekhulu-renovated: the seller
                                        receives the fixed seller price.
                                    </p>
                                )}
                                <div className="flex justify-between border-t border-border pt-1 font-semibold">
                                    <dt>To the seller</dt>
                                    <dd className="tabular">
                                        {randCents(money.figures.amount)}
                                    </dd>
                                </div>
                            </dl>
                            {!money.payout && can.payout && (
                                <Form
                                    action={payoutRoute(c.ulid).url}
                                    method="post"
                                    options={opts}
                                    className="grid gap-2 border-t border-border pt-3"
                                >
                                    {({ processing, errors: e }) => (
                                        <>
                                            <Label htmlFor="payee">
                                                Payee (seller)
                                            </Label>
                                            <Input id="payee" name="payee" />
                                            {e.payee && (
                                                <p className="text-destructive">
                                                    {e.payee}
                                                </p>
                                            )}
                                            <Label htmlFor="pref">
                                                Bank and account (as given to
                                                the bank)
                                            </Label>
                                            <Input
                                                id="pref"
                                                name="payee_reference"
                                            />
                                            <Button
                                                size="sm"
                                                disabled={processing}
                                                className="w-fit"
                                            >
                                                Prepare payout
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                            {money.payout && (
                                <div className="grid gap-2 border-t border-border pt-3">
                                    <p>
                                        <strong>
                                            {money.payout.status === 'paid'
                                                ? 'Paid'
                                                : money.payout.status ===
                                                    'approved'
                                                  ? 'Approved: pay at the bank'
                                                  : 'Waiting for approval'}
                                        </strong>{' '}
                                        · {money.payout.payee} · prepared by{' '}
                                        {money.payout.preparedBy}
                                        {money.payout.approvedBy
                                            ? ` · approved by ${money.payout.approvedBy}`
                                            : ''}
                                    </p>
                                    {money.payout.status === 'prepared' &&
                                        can.approvePayout &&
                                        (money.payout.preparedByMe ? (
                                            <p className="rounded-md bg-muted px-3 py-2">
                                                You prepared this payout;
                                                another finance user must
                                                approve it.
                                            </p>
                                        ) : (
                                            <Button
                                                size="sm"
                                                className="w-fit"
                                                onClick={() =>
                                                    window.confirm(
                                                        `Approve the payout of ${randCents(money.payout!.amount)}?`,
                                                    ) &&
                                                    router.post(
                                                        payoutRoutes.approve(
                                                            money.payout!.id,
                                                        ).url,
                                                        {},
                                                        opts,
                                                    )
                                                }
                                            >
                                                Approve payout
                                            </Button>
                                        ))}
                                    {money.payout.status === 'approved' &&
                                        can.payout && (
                                            <Form
                                                action={
                                                    payoutRoutes.paid(
                                                        money.payout.id,
                                                    ).url
                                                }
                                                method="post"
                                                options={opts}
                                                className="flex flex-wrap items-center gap-2"
                                            >
                                                {({
                                                    processing,
                                                    errors: e,
                                                }) => (
                                                    <>
                                                        <input
                                                            type="file"
                                                            name="proof"
                                                            accept=".pdf,.jpg,.jpeg,.png"
                                                            aria-label="Bank payment confirmation"
                                                            className="text-xs"
                                                        />
                                                        <Button
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Record payment
                                                        </Button>
                                                        {e.proof && (
                                                            <span className="text-destructive">
                                                                {e.proof}
                                                            </span>
                                                        )}
                                                    </>
                                                )}
                                            </Form>
                                        )}
                                </div>
                            )}
                            {money.figures.deal === 'commission' &&
                                (money.invoice ? (
                                    <p className="border-t border-border pt-3">
                                        Tax invoice{' '}
                                        <a
                                            href={money.invoice.url}
                                            className="font-semibold underline"
                                        >
                                            {money.invoice.number}
                                        </a>{' '}
                                        · {randCents(money.invoice.total)} incl.
                                        VAT
                                    </p>
                                ) : (
                                    can.payout && (
                                        <Form
                                            action={invoiceRoute(c.ulid).url}
                                            method="post"
                                            options={opts}
                                            className="grid gap-2 border-t border-border pt-3"
                                        >
                                            {({ processing, errors: e }) => (
                                                <>
                                                    <Label htmlFor="rec">
                                                        Invoice to (seller)
                                                    </Label>
                                                    <Input
                                                        id="rec"
                                                        name="recipient"
                                                    />
                                                    {e.recipient && (
                                                        <p className="text-destructive">
                                                            {e.recipient}
                                                        </p>
                                                    )}
                                                    <Label htmlFor="radd">
                                                        Address
                                                    </Label>
                                                    <Input
                                                        id="radd"
                                                        name="recipient_address"
                                                    />
                                                    <Label htmlFor="rvat">
                                                        Seller VAT number (if
                                                        registered)
                                                    </Label>
                                                    <Input
                                                        id="rvat"
                                                        name="recipient_vat_number"
                                                    />
                                                    {e.recipient_vat_number && (
                                                        <p className="text-destructive">
                                                            {
                                                                e.recipient_vat_number
                                                            }
                                                        </p>
                                                    )}
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={processing}
                                                        className="w-fit"
                                                    >
                                                        Issue tax invoice
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    )
                                ))}
                            {(errors.payout || errors.invoice) && (
                                <p className="text-destructive">
                                    {errors.payout ?? errors.invoice}
                                </p>
                            )}
                        </section>
                    )}

                    {transfer && (
                        <section
                            aria-labelledby="tr-h"
                            className="grid gap-3 rounded-xl border border-border bg-card p-5 text-sm"
                        >
                            <h2 id="tr-h" className="font-semibold">
                                Transfer
                            </h2>
                            <ul className="grid gap-1.5">
                                {transfer.stages.map((t) => (
                                    <li
                                        key={t.key}
                                        className="flex items-center gap-2"
                                    >
                                        {t.doneOn ? (
                                            <Check
                                                className="size-4 text-status-live"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <span className="inline-block size-4 rounded-full border border-input" />
                                        )}
                                        {t.label}
                                        {t.doneOn && (
                                            <span className="text-muted-foreground">
                                                · {t.doneOn}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {can.manage && (
                                <Form
                                    action={transferRoute(c.ulid).url}
                                    method="post"
                                    options={opts}
                                    className="grid gap-2 border-t border-border pt-3 2xl:grid-cols-[1fr_150px_auto] 2xl:items-end"
                                >
                                    {({ processing }) => (
                                        <>
                                            <select
                                                name="stage"
                                                aria-label="Stage"
                                                className="h-9 rounded-md border border-input bg-background px-2"
                                            >
                                                {transfer.stages.map((t) => (
                                                    <option
                                                        key={t.key}
                                                        value={t.key}
                                                    >
                                                        {t.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <Input
                                                type="date"
                                                name="done_on"
                                                aria-label="Date"
                                            />
                                            <Button
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Record
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </section>
                    )}
                </aside>
            </div>
        </>
    );
}

SettlementShow.layout = {
    breadcrumbs: [{ title: 'Settlement', href: index() }],
};

/** The winner's balance payment and their uploads, with accept or reject for each upload. */
function BuyerPayments({ c }: { c: Props['case'] }) {
    const [rejecting, setRejecting] = useState<number | null>(null);
    const [reason, setReason] = useState('');
    const opts = { preserveScroll: true };

    return (
        <section
            aria-labelledby="buyer-pay-h"
            className="grid gap-3 rounded-xl border border-border bg-card p-5 text-sm"
        >
            <h2 id="buyer-pay-h" className="font-semibold">
                Buyer payments and uploads
            </h2>
            {c.balance ? (
                <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1">
                    <dt>Balance due</dt>
                    <dd className="tabular text-right">
                        {randCents(c.balance.due)}
                    </dd>
                    <dt>Received</dt>
                    <dd className="tabular text-right">
                        {randCents(c.balance.received)}
                    </dd>
                    <dt>Status</dt>
                    <dd className="text-right font-medium">
                        {c.balance.status}
                    </dd>
                    <dt>Reference</dt>
                    <dd className="text-right font-mono text-xs">
                        {c.balance.reference}
                    </dd>
                    {c.balance.proofUrl && (
                        <>
                            <dt>Proof of payment</dt>
                            <dd className="text-right">
                                <a
                                    href={c.balance.proofUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline"
                                >
                                    Open
                                </a>
                            </dd>
                        </>
                    )}
                </dl>
            ) : (
                <p className="text-muted-foreground">
                    The buyer hasn’t opened their balance payment yet.
                </p>
            )}
            <p className="text-muted-foreground">
                Confirm money received in Finance » Balances (statement import
                or “Confirm received”, two people).
            </p>

            <h3 className="mt-2 font-semibold">Uploaded by the buyer</h3>
            {c.uploads.length === 0 && (
                <p className="text-muted-foreground">Nothing yet.</p>
            )}
            <ul className="grid gap-2">
                {c.uploads.map((u) => (
                    <li
                        key={u.id}
                        className="rounded-lg border border-border p-3"
                    >
                        <p>
                            <span className="font-medium">{u.step}</span> ·{' '}
                            {u.at} · {u.status}
                            {u.reason ? `: ${u.reason}` : ''}
                        </p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <a
                                href={u.url}
                                target="_blank"
                                rel="noreferrer"
                                className="underline"
                            >
                                Open
                            </a>
                            {u.status === 'pending' && (
                                <>
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                uploadRoutes.accept(u.id).url,
                                                {},
                                                opts,
                                            )
                                        }
                                    >
                                        Accept and record step
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setReason('');
                                            setRejecting(u.id);
                                        }}
                                    >
                                        Reject
                                    </Button>
                                </>
                            )}
                        </div>
                    </li>
                ))}
            </ul>

            <Dialog
                open={rejecting !== null}
                onOpenChange={(o) => !o && setRejecting(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display text-2xl">
                            Reject this upload
                        </DialogTitle>
                        <DialogDescription>
                            The buyer sees the reason and can upload again.
                        </DialogDescription>
                    </DialogHeader>
                    <Label htmlFor="upl-reason">Reason</Label>
                    <textarea
                        id="upl-reason"
                        rows={3}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        className="w-full rounded-md border border-input bg-background px-3 py-2"
                    />
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRejecting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={reason.trim() === ''}
                            onClick={() => {
                                if (rejecting !== null)
                                    router.post(
                                        uploadRoutes.reject(rejecting).url,
                                        { reason: reason.trim() },
                                        opts,
                                    );
                                setRejecting(null);
                            }}
                        >
                            Reject upload
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}

const msgLabel: Record<string, string> = {
    question: 'Asked',
    reply: 'Replied',
    upload: 'Uploaded',
    accepted: 'Accepted',
    rejected: 'Rejected',
};
const stepLabel: Record<string, string> = {
    agreement_signed: 'signed agreement',
    balance_paid: 'balance proof',
    attorney_appointed: 'attorney appointment',
};

/** Questions to the buyer, their replies and uploads, and decisions; refreshes while the case is open. */
function Conversation({ c }: { c: Props['case'] }) {
    const [asking, setAsking] = useState(false);
    const [text, setText] = useState('');
    const [upload, setUpload] = useState('');
    usePoll(20000, { only: ['case'] });
    const presets = [
        'The signed agreement is missing your initials on some pages. Please initial every page and upload it again.',
        'Please confirm which bank account the balance was paid from, and upload the bank’s confirmation.',
        'Please upload the guarantee letter from your bank, or confirm when it will be issued.',
        'Which attorney would you like to handle the transfer? Please send their name and contact details.',
    ];
    const pending = c.uploads.filter((u) => u.status === 'pending');

    return (
        <section
            aria-labelledby="conv-h"
            className="grid gap-3 rounded-xl border border-border bg-card p-5 text-sm"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 id="conv-h" className="font-semibold">
                        Conversation with the buyer
                    </h2>
                    <p className="text-muted-foreground">
                        {c.openQuestion
                            ? 'Waiting for the buyer to reply.'
                            : 'Questions, replies, uploads and decisions. Updates every 20 seconds.'}
                    </p>
                </div>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => {
                        setText('');
                        setUpload('');
                        setAsking(true);
                    }}
                >
                    Ask a question
                </Button>
            </div>
            <ol className="grid gap-2">
                {c.messages.map((m, i) => (
                    <li
                        key={i}
                        className={
                            m.author === 'buyer'
                                ? 'ml-6 rounded-lg bg-muted/60 p-3'
                                : 'mr-6 rounded-lg border border-border p-3'
                        }
                    >
                        <p className="text-xs text-muted-foreground">
                            {m.author === 'buyer' ? 'Buyer' : (m.by ?? 'Staff')}{' '}
                            · {msgLabel[m.kind] ?? m.kind} · {m.at}
                        </p>
                        {m.body && (
                            <p className="mt-1 whitespace-pre-line">{m.body}</p>
                        )}
                    </li>
                ))}
                {c.messages.length === 0 && (
                    <li className="text-muted-foreground">Nothing yet.</li>
                )}
            </ol>

            <Dialog open={asking} onOpenChange={setAsking}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display text-2xl">
                            Ask the buyer a question
                        </DialogTitle>
                        <DialogDescription>
                            The buyer is emailed and sees it on their Purchases
                            page, where they can reply and upload. Any pending
                            upload stays pending.
                        </DialogDescription>
                    </DialogHeader>
                    {pending.length > 0 && (
                        <div className="grid gap-1">
                            <Label htmlFor="q-upload">About</Label>
                            <select
                                id="q-upload"
                                value={upload}
                                onChange={(e) => setUpload(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            >
                                <option value="">
                                    The purchase in general
                                </option>
                                {pending.map((u) => (
                                    <option
                                        key={u.id}
                                        value={u.id}
                                    >{`The ${stepLabel[u.step] ?? u.step} uploaded ${u.at}`}</option>
                                ))}
                            </select>
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="q-text">Question</Label>
                        <textarea
                            id="q-text"
                            rows={4}
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <p className="text-xs text-muted-foreground">
                            Suggestions (click to use, then edit):
                        </p>
                        {presets.map((p) => (
                            <button
                                key={p}
                                type="button"
                                onClick={() => setText(p)}
                                className="rounded-md border border-border px-3 py-1.5 text-left text-xs hover:bg-muted"
                            >
                                {p}
                            </button>
                        ))}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setAsking(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={text.trim() === ''}
                            onClick={() => {
                                router.post(
                                    questions(c.ulid).url,
                                    {
                                        question: text.trim(),
                                        upload: upload || null,
                                    },
                                    { preserveScroll: true },
                                );
                                setAsking(false);
                            }}
                        >
                            Send question
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
