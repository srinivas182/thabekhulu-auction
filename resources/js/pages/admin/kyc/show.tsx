import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    approve,
    bank as bankRoute,
    index,
    reject,
    requestInfo,
    screen,
} from '@/routes/admin/kyc';

type Doc = { id: number; kind: string; mime: string; url: string };
type Check = {
    provider: string;
    outcome: string;
    reasons: string[];
    reference: string | null;
    at: string;
};
type Props = {
    verification: {
        ulid: string;
        status: string;
        statusLabel: string;
        name: string;
        email: string;
        mobile: string | null;
        documentType: string | null;
        idNumber: string | null;
        passportCountry: string | null;
        dateOfBirth: string | null;
        age: number | null;
        passportCountryName: string | null;
        passportExpiresOn: string | null;
        passportDaysLeft: number | null;
        settlementConflicts: { listing: string; settles: string }[];
        messages: {
            author: string;
            kind: string;
            body: string | null;
            by: string | null;
            at: string;
        }[];
        previousDocuments: {
            id: number;
            kind: string;
            mime: string;
            url: string;
            replacedAt: string | null;
        }[];
        maritalStatus: string | null;
        spouse: { name: string | null; idNumber: string | null } | null;
        checks: Record<string, Check>;
        rejectionReason: string | null;
        infoRequested: string | null;
        submittedAt: string | null;
        documents: Doc[];
    };
    screenings: {
        result: string;
        resultLabel: string;
        lists: string;
        provider: string;
        note: string | null;
        by: string | null;
        at: string;
    }[];
    bank: {
        id: number;
        bank: string;
        branchCode: string;
        accountNumber: string;
        type: string;
        holder: string;
        status: string;
        locked: boolean;
        proofUrl: string | null;
    } | null;
    screeningResults: Record<string, string>;
    can: { approve: boolean; reject: boolean };
};

const kindLabel: Record<string, string> = {
    id_front: 'ID front',
    id_back: 'ID back',
    passport: 'Passport',
    selfie: 'Selfie',
    proof_of_address: 'Proof of address',
    spouse_consent: 'Spouse consent',
};
const outcomeTone: Record<string, string> = {
    passed: 'text-status-live',
    review: 'text-status-closing',
    failed: 'text-destructive',
};
const opts = { preserveScroll: true };

const msgLabel: Record<string, string> = {
    info_request: 'Asked for more information',
    reply: 'Replied',
    document: 'Uploaded',
    submitted: 'Submitted',
    rejected: 'Rejected',
    verified: 'Verified',
};

export default function KycShow({
    verification: v,
    screenings,
    bank,
    screeningResults,
    can,
}: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [s, setS] = useState({
        result: 'clear',
        lists: 'FIC targeted financial sanctions, UN Security Council, PEP',
        note: '',
    });
    const open = ['submitted', 'review'].includes(v.status);
    // Branded dialogs instead of the browser's prompt and confirm boxes.
    type Dlg = {
        title: string;
        label?: string;
        hint: string;
        confirm: string;
        danger?: boolean;
        presets?: string[];
        run: (text: string) => void;
    };
    const [dlg, setDlg] = useState<Dlg | null>(null);
    const [text, setText] = useState('');
    const dialogs: Record<string, Omit<Dlg, 'run'>> = {
        'Why is this account being rejected?': {
            title: 'Reject this bank account',
            label: 'Reason',
            hint: 'The buyer sees this in their account and by email, and can add a corrected account.',
            confirm: 'Reject account',
            danger: true,
        },
        'What should the buyer add or correct? (emailed to them)': {
            title: 'Ask for more information',
            label: 'What should the buyer add or correct?',
            hint: 'The buyer sees this in their account and by email. They can reply and upload new documents, then resubmit.',
            confirm: 'Send request',
            presets: [
                'Please upload a clearer selfie: face the camera in good light, without glasses or a hat.',
                'Please upload a proof of address dated within the last three months.',
                'The photo of your ID is blurred. Please upload a clearer photo of the front.',
                'The name on your document doesn’t match your account. Please check and correct it.',
            ],
        },
        'Reason for rejecting (emailed to the buyer)': {
            title: 'Reject this verification',
            label: 'Reason',
            hint: 'The buyer sees this in their account and by email.',
            confirm: 'Reject verification',
            danger: true,
        },
    };
    const ask = (message: string, action: (text: string) => void) => {
        setText('');
        setDlg({
            ...(dialogs[message] ?? {
                title: message,
                label: 'Details',
                hint: '',
                confirm: 'Continue',
            }),
            run: action,
        });
    };
    const confirmDialog = (d: Omit<Dlg, 'label' | 'presets'>) => {
        setText('');
        setDlg(d);
    };
    // Keep the case current while it's open: new uploads and replies appear without reloading.
    usePoll(20000, { only: ['verification'] });

    return (
        <>
            <Head title={`KYC: ${v.name}`} />
            <div className="grid flex-1 gap-6 p-4 sm:p-6 xl:grid-cols-[1fr_380px]">
                <div className="grid content-start gap-5">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {v.statusLabel}
                            {v.submittedAt
                                ? ` · submitted ${formatDateTime(v.submittedAt)}`
                                : ''}
                        </p>
                        <h1 className="font-display text-3xl">{v.name}</h1>
                    </div>
                    {errors.decision && (
                        <p
                            role="alert"
                            className="rounded-md border border-destructive px-4 py-3 text-sm text-destructive"
                        >
                            {errors.decision}
                        </p>
                    )}

                    <dl className="grid gap-3 rounded-xl border border-border bg-card p-5 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">
                                {v.documentType === 'passport'
                                    ? `Passport (${v.passportCountryName ?? v.passportCountry})`
                                    : 'SA ID number'}
                            </dt>
                            <dd className="font-mono">{v.idNumber}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Date of birth
                            </dt>
                            <dd>
                                {v.dateOfBirth ?? '—'}
                                {v.age !== null && (
                                    <span
                                        className={
                                            v.age < 18
                                                ? 'ml-2 font-semibold text-destructive'
                                                : 'ml-2 text-muted-foreground'
                                        }
                                    >
                                        (age {v.age})
                                    </span>
                                )}
                            </dd>
                        </div>
                        {v.documentType === 'passport' && (
                            <div>
                                <dt className="text-muted-foreground">
                                    Passport expiry
                                </dt>
                                <dd>
                                    {v.passportExpiresOn ?? '—'}
                                    {v.passportDaysLeft !== null && (
                                        <span
                                            className={
                                                v.passportDaysLeft <= 0
                                                    ? 'ml-2 font-semibold text-destructive'
                                                    : v.passportDaysLeft < 92
                                                      ? 'ml-2 font-semibold text-amber-700 dark:text-amber-400'
                                                      : 'ml-2 text-muted-foreground'
                                            }
                                        >
                                            {v.passportDaysLeft <= 0
                                                ? '(expired)'
                                                : `(${v.passportDaysLeft} days left)`}
                                        </span>
                                    )}
                                </dd>
                            </div>
                        )}
                        {v.settlementConflicts.length > 0 && (
                            <div
                                role="alert"
                                className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-amber-900 sm:col-span-2 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100"
                            >
                                <p className="font-semibold">
                                    This passport expires before settlement of:
                                </p>
                                <ul className="mt-1 list-disc pl-5">
                                    {v.settlementConflicts.map((c) => (
                                        <li key={c.listing}>
                                            {c.listing} (settles {c.settles})
                                        </li>
                                    ))}
                                </ul>
                                <p className="mt-1">
                                    Ask the buyer for a renewed passport before
                                    approving them for these auctions.
                                </p>
                            </div>
                        )}
                        <div>
                            <dt className="text-muted-foreground">Email</dt>
                            <dd>{v.email}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Mobile</dt>
                            <dd>{v.mobile ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Marital status
                            </dt>
                            <dd>{v.maritalStatus ?? '—'}</dd>
                        </div>
                        {v.spouse && (
                            <div>
                                <dt className="text-muted-foreground">
                                    Spouse
                                </dt>
                                <dd>
                                    {v.spouse.name} ·{' '}
                                    <span className="font-mono">
                                        {v.spouse.idNumber}
                                    </span>
                                </dd>
                            </div>
                        )}
                    </dl>

                    <section
                        aria-label="Automatic checks"
                        className="rounded-xl border border-border bg-card p-5"
                    >
                        <h2 className="font-semibold">Checks</h2>
                        <ul className="mt-2 grid gap-2 text-sm">
                            {Object.entries(v.checks).map(([name, c]) => (
                                <li key={name}>
                                    <strong className="capitalize">
                                        {name}
                                    </strong>{' '}
                                    ·{' '}
                                    <span
                                        className={cn(
                                            'font-semibold',
                                            outcomeTone[c.outcome],
                                        )}
                                    >
                                        {c.outcome}
                                    </span>{' '}
                                    <span className="text-muted-foreground">
                                        ({c.provider}
                                        {c.reference
                                            ? `, ref ${c.reference}`
                                            : ''}
                                        )
                                    </span>
                                    <br />
                                    <span className="text-muted-foreground">
                                        {c.reasons.join(' ')}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section
                        aria-label="Documents"
                        className="rounded-xl border border-border bg-card p-5"
                    >
                        <h2 className="font-semibold">Documents</h2>
                        <p className="text-sm text-muted-foreground">
                            Every opening is recorded in the audit log.
                        </p>
                        <ul className="mt-3 grid gap-4 sm:grid-cols-2">
                            {v.documents.map((d) => (
                                <li
                                    key={d.id}
                                    className="rounded-lg border border-border p-3"
                                >
                                    <p className="mb-2 text-sm font-semibold">
                                        {kindLabel[d.kind] ?? d.kind}
                                    </p>
                                    {d.mime.startsWith('image/') ? (
                                        <a
                                            href={d.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <img
                                                src={d.url}
                                                alt={
                                                    kindLabel[d.kind] ?? d.kind
                                                }
                                                className="max-h-64 w-full rounded object-contain"
                                            />
                                        </a>
                                    ) : (
                                        <a
                                            href={d.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1.5 text-sm underline"
                                        >
                                            <ExternalLink
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Open PDF
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                        {v.previousDocuments.length > 0 && (
                            <details className="mt-4 rounded-lg border border-border p-3 text-sm">
                                <summary className="cursor-pointer font-semibold">
                                    Earlier versions (
                                    {v.previousDocuments.length})
                                </summary>
                                <ul className="mt-3 grid gap-3 sm:grid-cols-2">
                                    {v.previousDocuments.map((d) => (
                                        <li
                                            key={d.id}
                                            className="rounded-lg bg-muted/50 p-3"
                                        >
                                            <p className="mb-2 text-muted-foreground">
                                                {kindLabel[d.kind] ?? d.kind},
                                                replaced {d.replacedAt}
                                            </p>
                                            {d.mime.startsWith('image/') ? (
                                                <a
                                                    href={d.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <img
                                                        src={d.url}
                                                        alt={`Earlier ${kindLabel[d.kind] ?? d.kind}`}
                                                        className="max-h-48 w-full rounded object-contain opacity-80"
                                                    />
                                                </a>
                                            ) : (
                                                <a
                                                    href={d.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="underline"
                                                >
                                                    Open
                                                </a>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        )}
                    </section>

                    <section
                        aria-labelledby="conv-h"
                        className="rounded-xl border border-border bg-card p-5"
                    >
                        <h2 id="conv-h" className="font-semibold">
                            Conversation
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Requests, the buyer’s replies and re-uploads, and
                            decisions. Updates every 20 seconds.
                        </p>
                        <ol className="mt-3 grid gap-3">
                            {v.messages.map((m, i) => (
                                <li
                                    key={i}
                                    className={cn(
                                        'rounded-lg p-3 text-sm',
                                        m.author === 'buyer'
                                            ? 'ml-6 bg-muted/60'
                                            : 'mr-6 border border-border',
                                    )}
                                >
                                    <p className="text-xs text-muted-foreground">
                                        {m.author === 'buyer'
                                            ? 'Buyer'
                                            : (m.by ?? 'Reviewer')}{' '}
                                        · {msgLabel[m.kind] ?? m.kind} · {m.at}
                                    </p>
                                    {m.body && (
                                        <p className="mt-1 whitespace-pre-line">
                                            {m.body}
                                        </p>
                                    )}
                                </li>
                            ))}
                            {v.messages.length === 0 && (
                                <li className="text-sm text-muted-foreground">
                                    Nothing yet.
                                </li>
                            )}
                        </ol>
                    </section>

                    {bank && (
                        <section
                            aria-label="Refund bank account"
                            className="grid gap-3 rounded-xl border border-border bg-card p-5"
                        >
                            <h2 className="font-semibold">
                                Refund bank account{' '}
                                <span className="font-normal text-muted-foreground">
                                    · {bank.status}
                                    {bank.locked ? ' · locked' : ''}
                                </span>
                            </h2>
                            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Account holder
                                    </dt>
                                    <dd className="font-semibold">
                                        {bank.holder}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Bank
                                    </dt>
                                    <dd>
                                        {bank.bank} · branch {bank.branchCode} ·{' '}
                                        {bank.type}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Account number
                                    </dt>
                                    <dd className="font-mono">
                                        {bank.accountNumber}
                                    </dd>
                                </div>
                                {bank.proofUrl && (
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Proof
                                        </dt>
                                        <dd>
                                            <a
                                                href={bank.proofUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="underline"
                                            >
                                                Open bank letter or statement
                                            </a>
                                        </dd>
                                    </div>
                                )}
                            </dl>
                            {bank.status === 'pending' && (
                                <div className="flex flex-wrap gap-2">
                                    {can.approve && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    bankRoute(bank.id).url,
                                                    { decision: 'verify' },
                                                    opts,
                                                )
                                            }
                                        >
                                            Account is in the buyer’s name
                                        </Button>
                                    )}
                                    {can.reject && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                ask(
                                                    'Why is this account being rejected?',
                                                    (reason) =>
                                                        router.post(
                                                            bankRoute(bank.id)
                                                                .url,
                                                            {
                                                                decision:
                                                                    'reject',
                                                                reason,
                                                            },
                                                            opts,
                                                        ),
                                                )
                                            }
                                        >
                                            Reject
                                        </Button>
                                    )}
                                </div>
                            )}
                        </section>
                    )}
                </div>

                <aside className="grid content-start gap-5">
                    <section
                        aria-label="Screening"
                        className="grid gap-3 rounded-xl border border-border bg-card p-5"
                    >
                        <h2 className="font-semibold">
                            Sanctions and PEP screening
                        </h2>
                        <ul className="grid gap-2 text-sm">
                            {screenings.map((x, i) => (
                                <li key={i}>
                                    <span
                                        className={cn(
                                            'font-semibold',
                                            x.result === 'clear'
                                                ? 'text-status-live'
                                                : 'text-destructive',
                                        )}
                                    >
                                        {x.resultLabel}
                                    </span>{' '}
                                    <span className="text-muted-foreground">
                                        · {formatDateTime(x.at)}
                                        {x.by ? ` by ${x.by}` : ''} · {x.lists}
                                    </span>
                                    {x.note && (
                                        <p className="text-muted-foreground">
                                            {x.note}
                                        </p>
                                    )}
                                </li>
                            ))}
                            {screenings.length === 0 && (
                                <li className="text-muted-foreground">
                                    Not screened yet.
                                </li>
                            )}
                        </ul>
                        {can.approve && (
                            <div className="grid gap-2 border-t border-border pt-3">
                                <Label htmlFor="sr">Record a screening</Label>
                                <select
                                    id="sr"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    value={s.result}
                                    onChange={(e) =>
                                        setS({ ...s, result: e.target.value })
                                    }
                                >
                                    {Object.entries(screeningResults).map(
                                        ([k, l]) => (
                                            <option key={k} value={k}>
                                                {l}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <Input
                                    aria-label="Lists checked"
                                    value={s.lists}
                                    onChange={(e) =>
                                        setS({ ...s, lists: e.target.value })
                                    }
                                />
                                {s.result !== 'clear' && (
                                    <textarea
                                        aria-label="Details of the match"
                                        className="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        placeholder="Details of the match"
                                        value={s.note}
                                        onChange={(e) =>
                                            setS({ ...s, note: e.target.value })
                                        }
                                    />
                                )}
                                <InputError
                                    message={errors.note ?? errors.lists}
                                />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="w-fit"
                                    onClick={() =>
                                        router.post(screen(v.ulid).url, s, {
                                            ...opts,
                                            onSuccess: () =>
                                                setS({ ...s, note: '' }),
                                        })
                                    }
                                >
                                    Save screening
                                </Button>
                            </div>
                        )}
                    </section>

                    {open && (can.approve || can.reject) && (
                        <section
                            aria-label="Decision"
                            className="grid gap-3 rounded-xl border border-border bg-card p-5"
                        >
                            <h2 className="font-semibold">Decision</h2>
                            <p className="text-sm text-muted-foreground">
                                Check the selfie against the ID photo, the names
                                and dates, and the proof of address. A clear
                                screening in the last 30 days is required to
                                approve.
                            </p>
                            {can.approve && (
                                <Button
                                    onClick={() =>
                                        confirmDialog({
                                            title: `Verify ${v.name}?`,
                                            hint: 'They can request approval to bid straight away. Verification lasts 24 months, or until the passport expires if that’s sooner.',
                                            confirm: 'Verify buyer',
                                            run: () =>
                                                router.post(
                                                    approve(v.ulid).url,
                                                    {},
                                                    opts,
                                                ),
                                        })
                                    }
                                >
                                    Verify buyer
                                </Button>
                            )}
                            {can.reject && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        ask(
                                            'What should the buyer add or correct? (emailed to them)',
                                            (message) =>
                                                router.post(
                                                    requestInfo(v.ulid).url,
                                                    { message },
                                                    opts,
                                                ),
                                        )
                                    }
                                >
                                    Ask for more information
                                </Button>
                            )}
                            {can.reject && (
                                <Button
                                    variant="ghost"
                                    onClick={() =>
                                        ask(
                                            'Reason for rejecting (emailed to the buyer)',
                                            (reason) =>
                                                router.post(
                                                    reject(v.ulid).url,
                                                    { reason },
                                                    opts,
                                                ),
                                        )
                                    }
                                >
                                    Reject
                                </Button>
                            )}
                        </section>
                    )}
                    {v.rejectionReason && (
                        <p className="rounded-xl bg-muted p-4 text-sm">
                            <strong>Rejected:</strong> {v.rejectionReason}
                        </p>
                    )}
                    {v.infoRequested && (
                        <p className="rounded-xl bg-muted p-4 text-sm">
                            <strong>Information requested:</strong>{' '}
                            {v.infoRequested}
                        </p>
                    )}
                </aside>
            </div>
            <Dialog
                open={dlg !== null}
                onOpenChange={(open) => !open && setDlg(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display text-2xl">
                            {dlg?.title}
                        </DialogTitle>
                        {dlg?.hint && (
                            <DialogDescription>{dlg.hint}</DialogDescription>
                        )}
                    </DialogHeader>
                    {dlg?.label && (
                        <div className="grid gap-2">
                            <Label htmlFor="dlg-text">{dlg.label}</Label>
                            <textarea
                                id="dlg-text"
                                value={text}
                                onChange={(e) => setText(e.target.value)}
                                rows={4}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            {dlg.presets && (
                                <div className="grid gap-1.5">
                                    <p className="text-xs text-muted-foreground">
                                        Suggestions (click to use, then edit):
                                    </p>
                                    {dlg.presets.map((p) => (
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
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDlg(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant={dlg?.danger ? 'destructive' : 'default'}
                            disabled={
                                dlg?.label !== undefined && text.trim() === ''
                            }
                            onClick={() => {
                                dlg?.run(text.trim());
                                setDlg(null);
                            }}
                        >
                            {dlg?.confirm}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

KycShow.layout = { breadcrumbs: [{ title: 'KYC review', href: index() }] };
