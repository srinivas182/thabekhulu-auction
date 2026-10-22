import { Head, router } from '@inertiajs/react';
import QuotesNav from '@/components/quotes-nav';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/admin/quotes';
import templateRoutes from '@/routes/admin/quotes/templates';

type T = { id: number; name: string; needsReview: boolean; lines: number };

export default function Templates({ templates }: { templates: T[] }) {
    return (
        <>
            <Head title="Templates" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Quotations</h1>
                <QuotesNav current="Templates" />
                <p className="max-w-2xl text-muted-foreground">
                    Templates pre-fill a new quotation. To create or update one,
                    build a quotation the way you want it and use “Save as
                    template”.
                </p>
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {templates.map((t) => (
                        <li
                            key={t.id}
                            className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                        >
                            <div>
                                <p className="font-medium">{t.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {t.lines} line item(s)
                                    {t.needsReview && (
                                        <span className="ml-2 font-semibold text-status-closing">
                                            Needs review
                                        </span>
                                    )}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                {t.needsReview && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.put(
                                                templateRoutes.update(t.id).url,
                                                {
                                                    name: t.name,
                                                    needs_review: false,
                                                },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Mark as reviewed
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        window.confirm(
                                            `Delete the template “${t.name}”?`,
                                        ) &&
                                        router.delete(
                                            templateRoutes.destroy(t.id).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Delete
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

Templates.layout = { breadcrumbs: [{ title: 'Quotations', href: index() }] };
