import { Form, Head, Link } from '@inertiajs/react';
import CmsNav from '@/components/cms-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { pages as pagesRoute } from '@/routes/admin/cms';
import pagesRoutes from '@/routes/admin/cms/pages';

type Page = {
    id: number;
    title: string;
    slug: string;
    status: string;
    system: boolean;
    needsContent: boolean;
    updatedAt: string | null;
};

export default function CmsPages({ pages }: { pages: Page[] }) {
    return (
        <>
            <Head title="Pages" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Website content</h1>
                <CmsNav current="Pages" />
                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="border-b border-border text-muted-foreground">
                            <tr>
                                <th className="px-5 py-3 font-medium">Page</th>
                                <th className="px-5 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-5 py-3 font-medium">
                                    Last updated
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {pages.map((p) => (
                                <tr key={p.id}>
                                    <td className="px-5 py-3">
                                        <Link
                                            href={pagesRoutes.edit(p.id)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {p.title}
                                        </Link>
                                        <p className="text-muted-foreground">
                                            /{p.slug}
                                        </p>
                                    </td>
                                    <td className="px-5 py-3">
                                        {p.status === 'published'
                                            ? 'Published'
                                            : 'Draft'}
                                        {p.needsContent && (
                                            <span className="ml-2 rounded-full bg-status-closing/10 px-2 py-0.5 text-xs font-semibold text-status-closing">
                                                Needs content
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3 text-muted-foreground">
                                        {formatDateTime(p.updatedAt)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Form
                    {...pagesRoutes.store.form()}
                    className="flex max-w-xl flex-col gap-3 rounded-xl border border-border bg-card p-5 sm:flex-row sm:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="new-page">New page</Label>
                                <Input
                                    id="new-page"
                                    name="title"
                                    required
                                    placeholder="e.g. Selling a mining right"
                                />
                                <InputError message={errors.title} />
                            </div>
                            <Button disabled={processing}>Create page</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CmsPages.layout = {
    breadcrumbs: [{ title: 'Website content', href: pagesRoute() }],
};
