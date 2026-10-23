import { router } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import {
    approve,
    returnMethod as returnRoute,
    send,
    submit,
    version,
} from '@/routes/admin/quotes';

export type Workflow = {
    needsApproval: boolean;
    threshold: number;
    clientEmail: string;
    clientLink: string | null;
    accepted: {
        name: string;
        at: string;
        ip: string | null;
        fingerprint: string;
    } | null;
    declineReason: string | null;
    events: {
        type: string;
        by: string | null;
        note: string | null;
        at: string;
    }[];
};

const eventLabel: Record<string, string> = {
    created: 'Created',
    submitted: 'Sent for approval',
    approved: 'Approved',
    returned: 'Returned to preparer',
    sent: 'Sent to client',
    viewed: 'Opened by client',
    reminded: 'Reminder sent',
    accepted: 'Accepted',
    declined: 'Declined',
    expired: 'Expired',
    superseded: 'Superseded',
};
const opts = { preserveScroll: true };

export default function QuoteWorkflowPanel({
    ulid,
    status,
    workflow,
    can,
}: {
    ulid: string;
    status: string;
    workflow: Workflow;
    can: { approve: boolean; send: boolean };
}) {
    const [sending, setSending] = useState(false);
    const [message, setMessage] = useState('');
    const [cc, setCc] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const post = (url: string, data: Record<string, unknown> = {}) =>
        router.post(url, data as never, {
            ...opts,
            onError: setErrors,
            onSuccess: () => {
                setErrors({});
                setSending(false);
            },
        });

    const mustApprove =
        status === 'draft' && (workflow.needsApproval || !can.send);

    return (
        <section
            aria-label="Workflow"
            className="grid gap-3 rounded-xl border border-border bg-card p-5"
        >
            {errors.quote && (
                <p
                    role="alert"
                    className="text-sm font-semibold text-destructive"
                >
                    {errors.quote}
                </p>
            )}

            {status === 'draft' && (
                <div className="flex flex-wrap items-center gap-3">
                    {mustApprove ? (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {workflow.needsApproval
                                    ? `Above R ${workflow.threshold.toLocaleString('en-ZA').replace(/,/g, ' ')}: an approver must check this quotation before it is sent.`
                                    : 'An approver will check this quotation before it is sent.'}
                            </p>
                            <Button onClick={() => post(submit(ulid).url)}>
                                Send for approval
                            </Button>
                        </>
                    ) : (
                        <Button onClick={() => setSending(true)}>
                            Send to client
                        </Button>
                    )}
                </div>
            )}

            {status === 'awaiting_approval' && (
                <div className="flex flex-wrap items-center gap-3">
                    <p className="text-sm">Waiting for approval.</p>
                    {can.approve && (
                        <>
                            <Button onClick={() => post(approve(ulid).url)}>
                                Approve
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    const reason = window.prompt(
                                        'What needs to change?',
                                    );
                                    if (reason)
                                        post(returnRoute(ulid).url, { reason });
                                }}
                            >
                                Return with comments
                            </Button>
                        </>
                    )}
                </div>
            )}

            {status === 'approved' && can.send && (
                <div>
                    <Button onClick={() => setSending(true)}>
                        Send to client
                    </Button>
                </div>
            )}

            {sending && (
                <div className="grid gap-3 rounded-lg border border-border p-4">
                    <p className="text-sm">
                        Sends the PDF and a secure link to{' '}
                        <strong>{workflow.clientEmail}</strong>. Prices are
                        locked once sent; changes need a new version.
                    </p>
                    <div className="grid gap-1.5">
                        <Label htmlFor="msg">Message (optional)</Label>
                        <textarea
                            id="msg"
                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            placeholder="Please find our quotation attached."
                        />
                    </div>
                    <div className="grid max-w-sm gap-1.5">
                        <Label htmlFor="cc">Copy to (optional)</Label>
                        <Input
                            id="cc"
                            type="email"
                            value={cc}
                            onChange={(e) => setCc(e.target.value)}
                        />
                        <InputError message={errors.cc} />
                    </div>
                    <div className="flex gap-2">
                        <Button
                            onClick={() =>
                                post(send(ulid).url, {
                                    message,
                                    cc: cc || null,
                                })
                            }
                        >
                            Send now
                        </Button>
                        <Button
                            variant="ghost"
                            onClick={() => setSending(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            )}

            {['sent', 'viewed', 'declined', 'expired'].includes(status) && (
                <div className="flex flex-wrap items-center gap-3">
                    {workflow.clientLink && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                navigator.clipboard?.writeText(
                                    workflow.clientLink!,
                                )
                            }
                        >
                            <Copy className="size-4" aria-hidden="true" />
                            Copy client link
                        </Button>
                    )}
                    <Button
                        variant="outline"
                        onClick={() =>
                            window.confirm(
                                'Create a new version? The current one will be marked superseded.',
                            ) && post(version(ulid).url)
                        }
                    >
                        Create new version
                    </Button>
                </div>
            )}

            {workflow.accepted && (
                <div className="rounded-lg bg-status-live/10 p-4 text-sm">
                    <p className="font-semibold text-status-live">
                        Accepted by {workflow.accepted.name},{' '}
                        {formatDateTime(workflow.accepted.at)}
                    </p>
                    <p className="mt-1 text-muted-foreground">
                        IP {workflow.accepted.ip ?? 'unknown'} · fingerprint{' '}
                        <span className="font-mono break-all">
                            {workflow.accepted.fingerprint}
                        </span>
                    </p>
                </div>
            )}
            {status === 'declined' && workflow.declineReason && (
                <p className="rounded-lg bg-muted p-4 text-sm">
                    <strong>Declined:</strong> {workflow.declineReason}
                </p>
            )}

            {workflow.events.length > 0 && (
                <details className="text-sm">
                    <summary className="cursor-pointer font-semibold">
                        History
                    </summary>
                    <ol className="mt-2 grid gap-1.5">
                        {workflow.events.map((e, i) => (
                            <li key={i}>
                                <strong>{eventLabel[e.type] ?? e.type}</strong>{' '}
                                <span className="text-muted-foreground">
                                    {e.by ? `by ${e.by}, ` : ''}
                                    {formatDateTime(e.at)}
                                    {e.note ? ` · ${e.note}` : ''}
                                </span>
                            </li>
                        ))}
                    </ol>
                </details>
            )}
        </section>
    );
}
