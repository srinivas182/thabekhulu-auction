import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    collections,
    galleries,
    home,
    menus,
    pages,
    posts,
} from '@/routes/admin/cms';

const items = [
    ['Pages', pages()],
    ['Investor insights', posts()],
    ['Gallery', galleries()],
    ['Menus', menus()],
    ['Collections', collections()],
    ['Home page', home()],
] as const;

export default function CmsNav({
    current,
}: {
    current: (typeof items)[number][0];
}) {
    return (
        <nav
            aria-label="Website content"
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
