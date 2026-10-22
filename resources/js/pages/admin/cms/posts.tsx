import { Form, Head, Link } from '@inertiajs/react';
import CmsNav from '@/components/cms-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { pages } from '@/routes/admin/cms';
import postsRoutes from '@/routes/admin/cms/posts';

type P = {
    id: number;
    title: string;
    topic: string;
    status: string;
    publishedAt: string | null;
};

export default function Posts({ posts }: { posts: P[] }) {
    return (
        <>
            <Head title="Investor insights" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Website content</h1>
                <CmsNav current="Investor insights" />
                <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                    {posts.map((p) => (
                        <li
                            key={p.id}
                            className="flex flex-wrap items-center justify-between gap-2 px-5 py-3"
                        >
                            <div>
                                <Link
                                    href={postsRoutes.edit(p.id)}
                                    className="font-medium underline-offset-4 hover:underline"
                                >
                                    {p.title}
                                </Link>
                                <p className="text-sm text-muted-foreground">
                                    {p.topic}
                                </p>
                            </div>
                            <p className="text-sm">
                                {p.status}
                                {p.publishedAt && p.status !== 'Draft'
                                    ? ` · ${formatDateTime(p.publishedAt)}`
                                    : ''}
                            </p>
                        </li>
                    ))}
                    {posts.length === 0 && (
                        <li className="px-5 py-6 text-muted-foreground">
                            No articles yet.
                        </li>
                    )}
                </ul>
                <Form
                    {...postsRoutes.store.form()}
                    className="flex max-w-xl flex-col gap-3 rounded-xl border border-border bg-card p-5 sm:flex-row sm:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="new-post">New article</Label>
                                <Input id="new-post" name="title" required />
                                <InputError message={errors.title} />
                            </div>
                            <Button disabled={processing}>
                                Create article
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Posts.layout = { breadcrumbs: [{ title: 'Website content', href: pages() }] };
