import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { randCents as rand } from '@/lib/cents';
import { cn } from '@/lib/utils';
import refunds from '@/routes/admin/finance/refunds';

type Props = {
    batch: {
        id: number;
        number: string;
        status: string;
        statusLabel: string;
        total: number;
        preparedBy: string;
        preparedById: number;
        approvedBy: string | null;
        hasProof: boolean;
    };
    refunds: {
        reference: string;
        listing: string;
        bidder: string;
        amount: number;
        channel: string | null;
        status: string;
        failure: string | null;
        account: string | null;
    }[];
    can: { issue: boolean; approve: boolean };
};

export default function RefundBatchPage({ batch, refunds: rows, can }: Props) {
    const page = usePage();
    const me = page.props.auth.user.id;
    const errors = (page.props.errors ?? {}) as Record<string, string>;
    const ownBatch = me === batch.preparedById;
    const eft = rows.filter((r) => r.channel === 'eft');

    return (
        <>
            <Head title={batch.number} />
            <div className="flex max-w-5xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <div>
                    <Link
                        href={refunds.index({ query: { tab: 'batches' } })}
                        className="text-sm underline"
                    >
                        Refund batches
                    </Link>
                    <h1 className="font-display text-3xl">{batch.number}</h1>
                    <p className="text-muted-foreground">
                        {batch.statusLabel} · {rows.length} refunds ·{' '}
                        {rand(batch.total)} · prepared by {batch.preparedBy}
                        {batch.approvedBy
                            ? ` · approved by ${batch.approvedBy}`
                            : ''}
                    </p>
                </div>
                {errors.batch && (
                    <p role="alert" className="text-sm text-destructive">
                        {errors.batch}
                    </p>
                )}

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    Deposit
                                </th>
                                <th className="font-medium">Bidder</th>
                                <th className="font-medium">Paid to</th>
                                <th className="text-right font-medium">
                                    Amount
                                </th>
                                <th className="px-4 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr
                                    key={r.reference}
                                    className="border-t border-border align-top"
                                >
                                    <td className="px-4 py-2">
                                        <span className="font-mono">
                                            {r.reference}
                                        </span>
                                        <br />
                                        <span className="text-muted-foreground">
                                            {r.listing}
                                        </span>
                                    </td>
                                    <td>{r.bidder}</td>
                                    <td>
                                        {r.channel === 'eft'
                                            ? r.account
                                            : `${r.channel} (escrow)`}
                                    </td>
                                    <td className="tabular text-right">
                                        {rand(r.amount)}
                                    </td>
                                    <td
                                        className={cn(
                                            'px-4',
                                            r.status === 'failed' &&
                                                'text-destructive',
                                        )}
                                    >
                                        {r.status}
                                        {r.failure ? `: ${r.failure}` : ''}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {batch.status === 'prepared' &&
                    can.approve &&
                    (ownBatch ? (
                        <p className="rounded-md bg-muted px-4 py-3 text-sm">
                            You prepared this batch, so another finance user
                            must approve it.
                        </p>
                    ) : (
                        <div className="grid gap-2 rounded-xl border border-border bg-card p-5">
                            <p className="text-sm">
                                Check each refund goes to the bidder’s own
                                verified account and the amounts match the
                                deposits held. Escrowed deposits are refunded by
                                the provider as soon as you approve.
                            </p>
                            <Button
                                className="w-fit"
                                onClick={() =>
                                    window.confirm(
                                        `Approve ${batch.number} for ${rand(batch.total)}?`,
                                    ) &&
                                    router.post(
                                        refunds.approve(batch.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Approve batch
                            </Button>
                        </div>
                    ))}

                {batch.status === 'approved' && can.issue && eft.length > 0 && (
                    <section
                        aria-labelledby="pay-h"
                        className="grid gap-3 rounded-xl border border-border bg-card p-5"
                    >
                        <h2 id="pay-h" className="font-semibold">
                            Pay the EFT refunds
                        </h2>
                        <ol className="list-decimal pl-5 text-sm leading-relaxed">
                            <li>
                                Download the payment file and load it in the
                                bank’s business banking (adjust the columns to
                                the bank’s import format if needed).
                            </li>
                            <li>Release the payment at the bank.</li>
                            <li>
                                Upload the bank’s payment confirmation below.
                                Receipts are then emailed and refund accounts
                                unlocked.
                            </li>
                        </ol>
                        <a
                            href={refunds.bankFile(batch.id).url}
                            className="w-fit text-sm font-semibold underline"
                        >
                            Download payment file ({eft.length} payments)
                        </a>
                        <Form
                            action={refunds.paid(batch.id).url}
                            method="post"
                            className="flex flex-wrap items-center gap-2"
                        >
                            {({ processing, errors: e }) => (
                                <>
                                    <input
                                        type="file"
                                        name="proof"
                                        accept=".pdf,.jpg,.jpeg,.png,.csv,.txt"
                                        aria-label="Bank payment confirmation"
                                        className="text-sm"
                                    />
                                    <Button size="sm" disabled={processing}>
                                        Record payment
                                    </Button>
                                    {e.proof && (
                                        <span className="text-sm text-destructive">
                                            {e.proof}
                                        </span>
                                    )}
                                </>
                            )}
                        </Form>
                    </section>
                )}
            </div>
        </>
    );
}

RefundBatchPage.layout = {
    breadcrumbs: [{ title: 'Refunds', href: refunds.index() }],
};
