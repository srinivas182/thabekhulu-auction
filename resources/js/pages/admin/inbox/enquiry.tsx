import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { close, index, reply } from '@/routes/admin/enquiries';

type E = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    subject: string;
    message: string;
    status: string;
    listing: string | null;
    createdAt: string | null;
    replies: { body: string; by: string; at: string }[];
};

export default function EnquiryShow({
    enquiry: e,
    canRespond,
}: {
    enquiry: E;
    canRespond: boolean;
}) {
    const [body, setBody] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    return (
        <>
            <Head title={e.subject} />
            <div className="flex max-w-3xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <h1 className="font-display text-3xl">{e.subject}</h1>
                <section
                    className="rounded-xl border border-border bg-card p-5"
                    aria-label="Message"
                >
                    <p className="text-sm text-muted-foreground">
                        {e.name} ·{' '}
                        <a href={`mailto:${e.email}`} className="underline">
                            {e.email}
                        </a>
                        {e.phone ? ` · ${e.phone}` : ''} ·{' '}
                        {formatDateTime(e.createdAt)}
                    </p>
                    {e.listing && (
                        <p className="mt-1 text-sm">
                            About: <strong>{e.listing}</strong>
                        </p>
                    )}
                    <p className="mt-3 whitespace-pre-line">{e.message}</p>
                </section>
                {e.replies.map((r, i) => (
                    <section
                        key={i}
                        className="ml-6 rounded-xl border border-border bg-muted p-5"
                        aria-label={`Reply ${i + 1}`}
                    >
                        <p className="text-sm text-muted-foreground">
                            {r.by} replied · {formatDateTime(r.at)}
                        </p>
                        <p className="mt-2 whitespace-pre-line">{r.body}</p>
                    </section>
                ))}
                {canRespond && e.status !== 'closed' && (
                    <section
                        className="grid gap-3 rounded-xl border border-border bg-card p-5"
                        aria-label="Reply"
                    >
                        <Label htmlFor="reply">
                            Reply by email to {e.email}
                        </Label>
                        <textarea
                            id="reply"
                            className="min-h-36 rounded-md border border-input bg-background px-3 py-2"
                            value={body}
                            onChange={(ev) => setBody(ev.target.value)}
                        />
                        <InputError message={errors.body} />
                        <div className="flex gap-2">
                            <Button
                                onClick={() =>
                                    router.post(
                                        reply(e.id).url,
                                        { body },
                                        {
                                            preserveScroll: true,
                                            onError: setErrors,
                                            onSuccess: () => {
                                                setBody('');
                                                setErrors({});
                                            },
                                        },
                                    )
                                }
                            >
                                Send reply
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.post(
                                        close(e.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Close without reply
                            </Button>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

EnquiryShow.layout = { breadcrumbs: [{ title: 'Enquiries', href: index() }] };
