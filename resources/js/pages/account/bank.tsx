import { Form, Head, Link } from '@inertiajs/react';
import { Check, Lock } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { reply, store } from '@/routes/account/bank';

type Props = {
    messages: {
        author: string;
        kind: string;
        body: string | null;
        by: string | null;
        at: string;
    }[];
    account: {
        bank: string;
        branchCode: string;
        last4: string;
        type: string;
        holder: string;
        status: string;
        rejectionReason: string | null;
        locked: boolean;
        infoRequested: boolean;
    } | null;
    verified: boolean;
    banks: Record<string, string>;
    types: Record<string, string>;
    name: string;
};

const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-base';
const statusText: Record<string, string> = {
    pending: 'Being checked',
    verified: 'Verified in your name',
    rejected: 'Not accepted',
};

export default function Bank({
    account,
    verified,
    banks,
    types,
    name,
    messages,
}: Props) {
    const [bank, setBank] = useState(account?.bank ?? 'Standard Bank');
    const [branch, setBranch] = useState(
        account?.branchCode ?? banks['Standard Bank'],
    );
    const [editing, setEditing] = useState(
        account === null ||
            account.status === 'rejected' ||
            account.status === 'info_requested',
    );

    return (
        <>
            <Head title="Refund bank account" />
            <section className="mx-auto max-w-[860px] px-5 py-12 sm:px-8">
                <h1 className="font-display text-4xl">Refund bank account</h1>
                <p className="mt-3 text-lg text-muted-foreground">
                    Deposits are refunded only to an account in your own name.
                    You can’t change it while a deposit or refund is being
                    processed.
                </p>
                {!verified && (
                    <p className="mt-5 rounded-[10px] bg-muted p-4">
                        Finish{' '}
                        <Link
                            href="/account/verification"
                            className="underline"
                        >
                            verifying your identity
                        </Link>{' '}
                        first; we check this account against your verified name.
                    </p>
                )}

                {account && !editing && (
                    <div className="mt-8 grid gap-3 rounded-[18px] border border-border bg-card p-6">
                        <p className="text-lg font-semibold">
                            {account.bank} · account ending {account.last4}
                        </p>
                        <p className="text-muted-foreground">
                            {types[account.type]} · branch {account.branchCode}{' '}
                            · holder “{account.holder}”
                        </p>
                        <p
                            className={cn(
                                'inline-flex items-center gap-1.5 font-semibold',
                                account.status === 'verified'
                                    ? 'text-status-live'
                                    : 'text-status-closing',
                            )}
                        >
                            {account.status === 'verified' && (
                                <Check className="size-4" aria-hidden="true" />
                            )}
                            {statusText[account.status]}
                        </p>
                        {account.locked ? (
                            <p className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                <Lock className="size-4" aria-hidden="true" />
                                Locked while a deposit or refund is being
                                processed.
                            </p>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setEditing(true)}
                                className="w-fit text-sm font-semibold underline"
                            >
                                Use a different account
                            </button>
                        )}
                    </div>
                )}

                {account?.status === 'rejected' && (
                    <p
                        role="alert"
                        className="mt-6 rounded-[10px] bg-muted p-4"
                    >
                        <strong>We couldn’t accept your last account:</strong>{' '}
                        {account.rejectionReason}
                    </p>
                )}

                {account?.infoRequested && (
                    <section
                        aria-labelledby="bank-req-h"
                        className="mt-6 grid gap-3 rounded-[14px] border border-brand-accent/30 bg-brand-accent-soft p-4"
                    >
                        <h2
                            id="bank-req-h"
                            className="font-semibold text-brand-accent"
                        >
                            We need a little more about your bank account
                        </h2>
                        <ol className="grid gap-2 text-sm">
                            {messages.map((m, i) => (
                                <li
                                    key={i}
                                    className={
                                        m.author === 'buyer'
                                            ? 'ml-6 rounded-lg bg-background/70 px-3 py-2'
                                            : 'mr-6 rounded-lg bg-background px-3 py-2'
                                    }
                                >
                                    <span className="block text-xs text-muted-foreground">
                                        {m.author === 'buyer'
                                            ? 'You'
                                            : 'Thabekhulu'}{' '}
                                        · {m.at}
                                    </span>
                                    {m.body}
                                </li>
                            ))}
                        </ol>
                        <Form
                            action={reply().url}
                            method="post"
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="grid gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <label
                                        htmlFor="bank-reply"
                                        className="text-sm font-semibold"
                                    >
                                        Reply (optional)
                                    </label>
                                    <textarea
                                        id="bank-reply"
                                        name="reply"
                                        rows={3}
                                        placeholder="For example: I’ve uploaded a letter dated this month."
                                        className="w-full rounded-[10px] border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    {errors.reply && (
                                        <span className="text-sm text-destructive">
                                            {errors.reply}
                                        </span>
                                    )}
                                    <button
                                        disabled={processing}
                                        className="w-fit rounded-[10px] border border-input bg-background px-4 py-2 text-sm font-semibold"
                                    >
                                        Send reply
                                    </button>
                                </>
                            )}
                        </Form>
                        <p className="text-sm">
                            If we asked for a new letter or different details,
                            submit them in the form below.
                        </p>
                    </section>
                )}

                {editing && verified && (
                    <Form
                        {...store.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditing(false)}
                        className="mt-8 grid gap-4 rounded-[18px] border border-border bg-card p-6 [&_*]:min-w-0"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                        Bank
                                        <select
                                            name="bank"
                                            value={bank}
                                            onChange={(e) => {
                                                setBank(e.target.value);
                                                if (banks[e.target.value])
                                                    setBranch(
                                                        banks[e.target.value],
                                                    );
                                            }}
                                            className={field}
                                        >
                                            {Object.keys(banks).map((b) => (
                                                <option key={b}>{b}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                        Branch code
                                        <input
                                            name="branch_code"
                                            inputMode="numeric"
                                            value={branch}
                                            onChange={(e) =>
                                                setBranch(e.target.value)
                                            }
                                            className={cn(field, 'tabular')}
                                        />
                                        {errors.branch_code && (
                                            <span className="text-destructive">
                                                {errors.branch_code}
                                            </span>
                                        )}
                                    </label>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                        Account number
                                        <input
                                            name="account_number"
                                            inputMode="numeric"
                                            autoComplete="off"
                                            className={cn(field, 'tabular')}
                                        />
                                        {errors.account_number && (
                                            <span className="text-destructive">
                                                {errors.account_number}
                                            </span>
                                        )}
                                    </label>
                                    <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                        Account type
                                        <select
                                            name="account_type"
                                            className={field}
                                        >
                                            {Object.entries(types).map(
                                                ([k, l]) => (
                                                    <option key={k} value={k}>
                                                        {l}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </label>
                                </div>
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    Account holder’s name
                                    <input
                                        name="holder_name"
                                        defaultValue={name}
                                        className={field}
                                    />
                                    <span className="font-normal text-muted-foreground">
                                        Must be your own name, as on your ID.
                                    </span>
                                    {errors.holder_name && (
                                        <span className="text-destructive">
                                            {errors.holder_name}
                                        </span>
                                    )}
                                </label>
                                <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                    Bank letter or recent statement
                                    <input
                                        type="file"
                                        name="proof"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        className="text-sm font-normal"
                                    />
                                    <span className="font-normal text-muted-foreground">
                                        Showing the account holder’s name and
                                        account number.
                                    </span>
                                    {errors.proof && (
                                        <span className="text-destructive">
                                            {errors.proof}
                                        </span>
                                    )}
                                </label>
                                <button
                                    disabled={processing}
                                    className="min-h-12 w-fit rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground disabled:opacity-60"
                                >
                                    Save bank account
                                </button>
                            </>
                        )}
                    </Form>
                )}
            </section>
        </>
    );
}
