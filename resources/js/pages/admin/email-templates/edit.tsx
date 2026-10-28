import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import routes, { index } from '@/routes/admin/email-templates';

type Template = {
    subject: string;
    greeting: string;
    body: string;
    after: string;
    button: string | null;
    edited: boolean;
};
type Props = {
    key: string;
    name: string;
    when: string;
    hasButton: boolean;
    template: Template;
    variables: Record<string, string>;
    required: string[];
    sending: boolean;
};

const xsrf = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

export default function EditEmailTemplate(props: Props) {
    const { name, when, hasButton, template, variables, required, sending } =
        props;
    const key = props.key;
    const [v, setV] = useState({
        subject: template.subject,
        greeting: template.greeting,
        body: template.body,
        after: template.after,
        button: template.button ?? '',
    });
    const [preview, setPreview] = useState<{
        subject: string;
        html: string;
    } | null>(null);
    const set =
        (k: keyof typeof v) =>
        (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
            setV({ ...v, [k]: e.target.value });

    useEffect(() => {
        const t = setTimeout(() => {
            fetch(routes.preview(key).url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrf(),
                },
                body: JSON.stringify(v),
            })
                .then((r) => (r.ok ? r.json() : null))
                .then((d) => d && setPreview(d))
                .catch(() => undefined);
        }, 400);

        return () => clearTimeout(t);
    }, [v, key]);

    const area =
        'min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 font-normal';

    return (
        <>
            <Head title={`Email: ${name}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div>
                    <Link href={index()} className="text-sm underline">
                        Email templates
                    </Link>
                    <h1 className="font-display text-3xl">{name}</h1>
                    <p className="text-muted-foreground">{when}</p>
                </div>
                <div className="grid gap-6 xl:grid-cols-2">
                    <Form
                        action={routes.update(key).url}
                        method="put"
                        options={{ preserveScroll: true }}
                        className="grid content-start gap-4 rounded-xl border border-border bg-card p-5 text-sm"
                    >
                        {({ processing, errors }) => (
                            <>
                                <label className="grid gap-1 font-medium">
                                    Subject
                                    <Input
                                        name="subject"
                                        value={v.subject}
                                        onChange={set('subject')}
                                    />
                                    {errors.subject && (
                                        <span className="font-normal text-destructive">
                                            {errors.subject}
                                        </span>
                                    )}
                                </label>
                                <label className="grid gap-1 font-medium">
                                    Greeting
                                    <Input
                                        name="greeting"
                                        value={v.greeting}
                                        onChange={set('greeting')}
                                    />
                                </label>
                                <label className="grid gap-1 font-medium">
                                    Message
                                    <textarea
                                        name="body"
                                        value={v.body}
                                        onChange={set('body')}
                                        className={`${area} min-h-40`}
                                    />
                                    <span className="font-normal text-muted-foreground">
                                        Leave a blank line between paragraphs.
                                        **Two asterisks** make text bold.
                                    </span>
                                    {errors.body && (
                                        <span className="font-normal text-destructive">
                                            {errors.body}
                                        </span>
                                    )}
                                </label>
                                {hasButton && (
                                    <label className="grid gap-1 font-medium">
                                        Button label
                                        <Input
                                            name="button"
                                            value={v.button}
                                            onChange={set('button')}
                                        />
                                        <span className="font-normal text-muted-foreground">
                                            Where the button goes is set by the
                                            system.
                                        </span>
                                        {errors.button && (
                                            <span className="font-normal text-destructive">
                                                {errors.button}
                                            </span>
                                        )}
                                    </label>
                                )}
                                <label className="grid gap-1 font-medium">
                                    {hasButton
                                        ? 'After the button'
                                        : 'Closing text'}
                                    <textarea
                                        name="after"
                                        value={v.after}
                                        onChange={set('after')}
                                        className={area}
                                    />
                                </label>

                                <div className="rounded-lg bg-muted p-3">
                                    <p className="font-medium">
                                        Details you can use
                                    </p>
                                    <ul className="mt-1 grid gap-0.5">
                                        {Object.entries(variables).map(
                                            ([k, d]) => (
                                                <li key={k}>
                                                    <code className="rounded bg-background px-1">{`{{${k}}}`}</code>{' '}
                                                    {d}
                                                    {required.includes(k) && (
                                                        <span className="font-medium">
                                                            {' '}
                                                            (required)
                                                        </span>
                                                    )}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <Button disabled={processing}>Save</Button>
                                    {template.edited && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                confirm(
                                                    'Go back to the default wording? Your changes to this email will be removed.',
                                                ) &&
                                                router.delete(
                                                    routes.reset(key).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Reset to default
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={!sending}
                                        onClick={() =>
                                            router.post(
                                                routes.test(key).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Send test to me
                                    </Button>
                                </div>
                                {errors.test && (
                                    <p className="text-destructive">
                                        {errors.test}
                                    </p>
                                )}
                                {!sending && (
                                    <p className="text-muted-foreground">
                                        Tests need the email provider to be set
                                        up and on.
                                    </p>
                                )}
                            </>
                        )}
                    </Form>

                    <section
                        aria-label="Preview"
                        className="grid content-start gap-2"
                    >
                        <p className="text-sm text-muted-foreground">
                            Preview with example details (unsaved changes
                            included)
                        </p>
                        <p className="rounded-lg border border-border bg-card px-4 py-2 text-sm">
                            <span className="text-muted-foreground">
                                Subject:{' '}
                            </span>
                            {preview?.subject ?? '…'}
                        </p>
                        <iframe
                            title="Email preview"
                            sandbox=""
                            srcDoc={preview?.html ?? ''}
                            className="h-[720px] w-full rounded-xl border border-border bg-white"
                        />
                    </section>
                </div>
            </div>
        </>
    );
}

EditEmailTemplate.layout = {
    breadcrumbs: [{ title: 'Email templates', href: index() }],
};
