import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    catalogue,
    clients,
    index,
    settings,
    templates,
    reports,
} from '@/routes/admin/quotes';

export default function QuotesNav({ current }: { current: string }) {
    const perms =
        (usePage().props.auth as { permissions?: string[] }).permissions ?? [];
    const canSetup = perms.includes('quotes.settings');
    const canReports = perms.includes('quotes.view_all');
    const items: [string, ReturnType<typeof index>][] = [
        ['Quotations', index()],
        ['Clients', clients()],
        ...(canReports
            ? ([['Reports', reports()]] as [string, ReturnType<typeof index>][])
            : []),
        ...(canSetup
            ? ([
                  ['Catalogue', catalogue()],
                  ['Templates', templates()],
                  ['Settings', settings()],
              ] as [string, ReturnType<typeof index>][])
            : []),
    ];

    return (
        <nav
            aria-label="Quotations"
            className="flex flex-wrap gap-x-6 border-b border-border text-sm"
        >
            {items.map(([label, route]) => (
                <Link
                    key={label}
                    href={route}
                    aria-current={label === current ? 'page' : undefined}
                    className={cn(
                        '-mb-px border-b-2 py-2.5',
                        label === current
                            ? 'border-primary font-semibold'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {label}
                </Link>
            ))}
        </nav>
    );
}
