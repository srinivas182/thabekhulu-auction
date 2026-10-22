import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import CmsNav from '@/components/cms-nav';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { pages } from '@/routes/admin/cms';
import postsRoutes from '@/routes/admin/cms/posts';

type Post = {
    id: number;
    title: string;
    slug: string;
    topic: string;
    excerpt: string | null;
    body: string | null;
    status: string;
    state: string;
    publishedAt: string | null;
    seoTitle: string | null;
    seoDescription: string | null;
    cover: string | null;
};

export default function PostEdit({
    post,
    topics,
}: {
    post: Post;
    topics: string[];
}) {
    const [f, setF] = useState({
        title: post.title,
        slug: post.slug,
        topic: post.topic,
        excerpt: post.excerpt ?? '',
        body: post.body ?? '',
        published_at: post.publishedAt ?? '',
        seo_title: post.seoTitle ?? '',
        seo_description: post.seoDescription ?? '',
    });
    const [cover, setCover] = useState<File | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const save = (status: string) =>
        router.post(
            postsRoutes.update(post.id).url,
            { ...f, status, ...(cover ? { cover } : {}) },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: setErrors,
                onSuccess: () => {
                    setErrors({});
                    setCover(null);
                },
            },
        );

    return (
        <>
            <Head title={post.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">{post.title}</h1>
                <CmsNav current="Investor insights" />
                <p className="text-sm text-muted-foreground">
                    Status: <strong>{post.state}</strong>
                </p>
                <section
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-2"
                    aria-label="Article"
                >
                    <div className="grid gap-1.5 md:col-span-2">
                        <Label htmlFor="t">Title</Label>
                        <Input
                            id="t"
                            value={f.title}
                            onChange={(e) =>
                                setF({ ...f, title: e.target.value })
                            }
                        />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="s">Address</Label>
                        <div className="flex items-center gap-1">
                            <span className="text-muted-foreground">
                                /insights/
                            </span>
                            <Input
                                id="s"
                                value={f.slug}
                                onChange={(e) =>
                                    setF({
                                        ...f,
                                        slug: e.target.value.toLowerCase(),
                                    })
                                }
                            />
                        </div>
                        <InputError message={errors.slug} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="tp">Topic</Label>
                        <select
                            id="tp"
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            value={f.topic}
                            onChange={(e) =>
                                setF({ ...f, topic: e.target.value })
                            }
                        >
                            {topics.map((t) => (
                                <option key={t}>{t}</option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5 md:col-span-2">
                        <Label htmlFor="ex">Summary</Label>
                        <Input
                            id="ex"
                            maxLength={300}
                            value={f.excerpt}
                            onChange={(e) =>
                                setF({ ...f, excerpt: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5 md:col-span-2">
                        <Label htmlFor="body">Article</Label>
                        <RichTextEditor
                            id="body"
                            label="Article"
                            value={f.body}
                            onChange={(body) => setF({ ...f, body })}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="cv">Cover image</Label>
                        {post.cover && (
                            <img
                                src={post.cover}
                                alt=""
                                className="h-24 w-36 rounded object-cover"
                            />
                        )}
                        <input
                            id="cv"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            onChange={(e) =>
                                setCover(e.target.files?.[0] ?? null)
                            }
                            className="text-sm"
                        />
                        <InputError message={errors.cover} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="pd">Publish date and time (SAST)</Label>
                        <Input
                            id="pd"
                            type="datetime-local"
                            value={f.published_at}
                            onChange={(e) =>
                                setF({ ...f, published_at: e.target.value })
                            }
                        />
                        <p className="text-sm text-muted-foreground">
                            A future date schedules the article.
                        </p>
                        <InputError message={errors.published_at} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="st">Search title</Label>
                        <Input
                            id="st"
                            maxLength={120}
                            value={f.seo_title}
                            onChange={(e) =>
                                setF({ ...f, seo_title: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="sd">Search description</Label>
                        <Input
                            id="sd"
                            maxLength={300}
                            value={f.seo_description}
                            onChange={(e) =>
                                setF({ ...f, seo_description: e.target.value })
                            }
                        />
                    </div>
                </section>
                <div className="flex flex-wrap gap-2">
                    <Button onClick={() => save('published')}>
                        Publish or schedule
                    </Button>
                    <Button variant="outline" onClick={() => save('draft')}>
                        Save draft
                    </Button>
                    <Button
                        variant="ghost"
                        onClick={() =>
                            window.confirm('Delete this article?') &&
                            router.delete(postsRoutes.destroy(post.id).url)
                        }
                    >
                        Delete
                    </Button>
                </div>
            </div>
        </>
    );
}

PostEdit.layout = {
    breadcrumbs: [{ title: 'Website content', href: pages() }],
};
