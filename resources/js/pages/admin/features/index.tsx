import { Head, router } from '@inertiajs/react';
import { index, update } from '@/routes/admin/features';

type Flag = {
    key: string;
    label: string;
    description: string;
    phase: string;
    active: boolean;
};

export default function FeaturesIndex({ flags }: { flags: Flag[] }) {
    const toggle = (flag: Flag) => {
        const verb = flag.active ? 'Turn off' : 'Turn on';

        if (window.confirm(`${verb} ${flag.label}?`)) {
            router.put(
                update(flag.key).url,
                { active: !flag.active },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Feature flags" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Feature flags</h1>
                    <p className="mt-1 max-w-2xl text-muted-foreground">
                        Switch platform features on when they are ready to
                        launch. Every change is recorded in the audit log.
                    </p>
                </div>
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {flags.map((flag) => (
                        <li
                            key={flag.key}
                            className="flex flex-wrap items-center justify-between gap-4 px-5 py-4"
                        >
                            <div className="max-w-2xl">
                                <p className="font-medium">
                                    {flag.label}
                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                        {flag.phase}
                                    </span>
                                </p>
                                <p className="mt-0.5 text-sm text-muted-foreground">
                                    {flag.description}
                                </p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                aria-checked={flag.active}
                                aria-label={flag.label}
                                onClick={() => toggle(flag)}
                                className={
                                    'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ' +
                                    (flag.active ? 'bg-primary' : 'bg-input')
                                }
                            >
                                <span
                                    className={
                                        'absolute top-0.5 size-5 rounded-full bg-background shadow transition-transform ' +
                                        (flag.active
                                            ? 'translate-x-5.5'
                                            : 'translate-x-0.5')
                                    }
                                />
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

FeaturesIndex.layout = {
    breadcrumbs: [{ title: 'Feature flags', href: index() }],
};
