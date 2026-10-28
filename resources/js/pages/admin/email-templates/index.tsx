import { Head, Link } from '@inertiajs/react';
import { edit, index } from '@/routes/admin/email-templates';
import { email as emailProvider } from '@/routes/admin/settings';

type Props = {
    templates: {
        key: string;
        group: string;
        name: string;
        when: string;
        edited: boolean;
    }[];
    sending: boolean;
};

export default function EmailTemplates({ templates, sending }: Props) {
    const groups = [...new Set(templates.map((t) => t.group))];

    return (
        <>
            <Head title="Email templates" />
            <div className="flex max-w-4xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Email templates</h1>
                    <p className="mt-1 text-muted-foreground">
                        Change the wording of the emails buyers and staff
                        receive. Amounts, references, links and attachments are
                        always filled in by the system.
                    </p>
                    {!sending && (
                        <p className="mt-2 text-sm">
                            Email sending is off, so these emails aren’t being
                            sent.{' '}
                            <Link
                                href={emailProvider()}
                                className="font-semibold underline"
                            >
                                Set up the email provider
                            </Link>
                        </p>
                    )}
                </div>
                {groups.map((g) => (
                    <section key={g} aria-labelledby={`g-${g}`}>
                        <h2 id={`g-${g}`} className="mb-2 font-semibold">
                            {g}
                        </h2>
                        <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                            {templates
                                .filter((t) => t.group === g)
                                .map((t) => (
                                    <li key={t.key}>
                                        <Link
                                            href={edit(t.key)}
                                            className="flex items-center justify-between gap-3 px-5 py-3 hover:bg-muted/50"
                                        >
                                            <span>
                                                <span className="font-medium">
                                                    {t.name}
                                                </span>
                                                <br />
                                                <span className="text-sm text-muted-foreground">
                                                    {t.when}
                                                </span>
                                            </span>
                                            <span
                                                className={
                                                    t.edited
                                                        ? 'shrink-0 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary'
                                                        : 'shrink-0 text-xs text-muted-foreground'
                                                }
                                            >
                                                {t.edited
                                                    ? 'Edited'
                                                    : 'Default'}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                        </ul>
                    </section>
                ))}
            </div>
        </>
    );
}

EmailTemplates.layout = {
    breadcrumbs: [{ title: 'Email templates', href: index() }],
};
