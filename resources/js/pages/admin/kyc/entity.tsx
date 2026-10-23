import { Head, router } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/admin/kyc';
import entityRoutes from '@/routes/admin/kyc/entity';

type Props = {
    entity: {
        ulid: string;
        type: string;
        name: string;
        registration: string;
        status: string;
        rejectionReason: string | null;
        person: string;
        email: string;
        controllers: {
            name: string;
            role: string;
            id_number: string;
            ownership: number;
        }[];
        documents: { id: number; kind: string; mime: string; url: string }[];
    };
    can: { decide: boolean };
};

export default function EntityShow({ entity: e, can }: Props) {
    return (
        <>
            <Head title={e.name} />
            <div className="flex max-w-4xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {e.type === 'company' ? 'Company' : 'Trust'} ·{' '}
                        {e.status}
                    </p>
                    <h1 className="font-display text-3xl">{e.name}</h1>
                    <p className="text-muted-foreground">
                        {e.registration} · submitted by {e.person} ({e.email})
                    </p>
                </div>
                <p className="rounded-md bg-muted px-4 py-3 text-sm">
                    Check the registration against{' '}
                    {e.type === 'company' ? 'CIPC' : 'the Master’s Office'},
                    that {e.person} appears below with authority to act, and
                    that everyone owning 25% or more is listed and screened.
                </p>
                <section
                    className="rounded-xl border border-border bg-card p-5"
                    aria-label="People"
                >
                    <h2 className="font-semibold">People who control it</h2>
                    <table className="mt-2 w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="py-1 font-medium">Name</th>
                                <th className="font-medium">Role</th>
                                <th className="font-medium">ID / passport</th>
                                <th className="text-right font-medium">Owns</th>
                            </tr>
                        </thead>
                        <tbody>
                            {e.controllers.map((c, i) => (
                                <tr key={i} className="border-t border-border">
                                    <td className="py-1.5">{c.name}</td>
                                    <td>{c.role}</td>
                                    <td className="font-mono">{c.id_number}</td>
                                    <td className="tabular text-right">
                                        {c.ownership}%
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
                <section
                    className="rounded-xl border border-border bg-card p-5"
                    aria-label="Documents"
                >
                    <h2 className="font-semibold">Documents</h2>
                    <ul className="mt-2 grid gap-1.5 text-sm">
                        {e.documents.map((d) => (
                            <li key={d.id}>
                                <a
                                    href={d.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1.5 underline"
                                >
                                    <ExternalLink
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {d.kind}
                                </a>
                            </li>
                        ))}
                    </ul>
                </section>
                {e.status === 'pending' && can.decide && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            onClick={() =>
                                window.confirm(`Verify ${e.name}?`) &&
                                router.post(
                                    entityRoutes.decide(e.ulid).url,
                                    { decision: 'verify' },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Verify
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                const reason = window.prompt(
                                    'Reason (emailed to the buyer)',
                                );
                                if (reason)
                                    router.post(
                                        entityRoutes.decide(e.ulid).url,
                                        { decision: 'reject', reason },
                                        { preserveScroll: true },
                                    );
                            }}
                        >
                            Reject
                        </Button>
                    </div>
                )}
                {e.rejectionReason && (
                    <p className="rounded-md bg-muted p-3 text-sm">
                        <strong>Rejected:</strong> {e.rejectionReason}
                    </p>
                )}
            </div>
        </>
    );
}

EntityShow.layout = { breadcrumbs: [{ title: 'KYC review', href: index() }] };
