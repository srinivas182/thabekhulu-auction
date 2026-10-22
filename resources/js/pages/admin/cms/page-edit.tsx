import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CmsNav from '@/components/cms-nav';
import type { Block } from '@/components/content-blocks';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { pages } from '@/routes/admin/cms';
import pagesRoutes from '@/routes/admin/cms/pages';

type Props = {
    page: {
        id: number;
        title: string;
        slug: string;
        status: string;
        blocks: Block[];
        system: boolean;
        needsContent: boolean;
        seoTitle: string | null;
        seoDescription: string | null;
    };
    blockTypes: Record<string, string>;
};

const blank = (type: string): Block => {
    switch (type) {
        case 'two_column':
            return { type: 'two_column', left: '', right: '' };
        case 'cta':
            return {
                type: 'cta',
                title: '',
                text: '',
                button_label: '',
                button_url: '',
            };
        case 'faq':
            return { type: 'faq', items: [{ question: '', answer: '' }] };
        default:
            return { type: 'rich_text', html: '' };
    }
};

export default function PageEdit({ page, blockTypes }: Props) {
    const [p, setP] = useState({
        title: page.title,
        slug: page.slug,
        status: page.status,
        needs_content: page.needsContent,
        seo_title: page.seoTitle ?? '',
        seo_description: page.seoDescription ?? '',
    });
    const [blocks, setBlocks] = useState<Block[]>(page.blocks);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const set = (i: number, b: Block) =>
        setBlocks(blocks.map((x, k) => (k === i ? b : x)));
    const move = (i: number, d: -1 | 1) => {
        const n = [...blocks];
        [n[i], n[i + d]] = [n[i + d], n[i]];
        setBlocks(n);
    };
    const save = (status: string) =>
        router.put(
            pagesRoutes.update(page.id).url,
            { ...p, status, blocks } as never,
            {
                preserveScroll: true,
                onError: setErrors,
                onSuccess: () => {
                    setErrors({});
                    setP({ ...p, status });
                },
            },
        );

    return (
        <>
            <Head title={page.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">{page.title}</h1>
                <CmsNav current="Pages" />
                <section
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-2"
                    aria-label="Page details"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            value={p.title}
                            onChange={(e) =>
                                setP({ ...p, title: e.target.value })
                            }
                        />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="slug">Address</Label>
                        <div className="flex items-center gap-1">
                            <span className="text-muted-foreground">/</span>
                            <Input
                                id="slug"
                                value={p.slug}
                                disabled={page.system}
                                onChange={(e) =>
                                    setP({
                                        ...p,
                                        slug: e.target.value.toLowerCase(),
                                    })
                                }
                            />
                        </div>
                        <InputError message={errors.slug} />
                        {page.system && (
                            <p className="text-sm text-muted-foreground">
                                Standard pages keep their address so links and
                                menus never break.
                            </p>
                        )}
                    </div>
                    <label className="flex items-center gap-2 text-sm md:col-span-2">
                        <input
                            type="checkbox"
                            className="size-4 accent-primary"
                            checked={p.needs_content}
                            onChange={(e) =>
                                setP({ ...p, needs_content: e.target.checked })
                            }
                        />
                        Still needs final content (flagged in the pages list and
                        the launch checklist)
                    </label>
                </section>

                {blocks.map((b, i) => (
                    <section
                        key={i}
                        className="grid gap-3 rounded-xl border border-border bg-card p-5"
                        aria-label={`Block ${i + 1}: ${blockTypes[b.type]}`}
                    >
                        <div className="flex items-center justify-between">
                            <p className="font-semibold">
                                {blockTypes[b.type]}
                            </p>
                            <div className="flex">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    aria-label="Move up"
                                    disabled={i === 0}
                                    onClick={() => move(i, -1)}
                                >
                                    <ArrowUp />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    aria-label="Move down"
                                    disabled={i === blocks.length - 1}
                                    onClick={() => move(i, 1)}
                                >
                                    <ArrowDown />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    aria-label="Remove block"
                                    onClick={() =>
                                        window.confirm('Remove this block?') &&
                                        setBlocks(
                                            blocks.filter((_, k) => k !== i),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </div>
                        {b.type === 'rich_text' && (
                            <RichTextEditor
                                id={`b-${i}`}
                                label="Text"
                                value={b.html}
                                onChange={(html) => set(i, { ...b, html })}
                            />
                        )}
                        {b.type === 'two_column' && (
                            <div className="grid gap-3 md:grid-cols-2">
                                <RichTextEditor
                                    id={`b-${i}-l`}
                                    label="Left column"
                                    value={b.left}
                                    onChange={(left) => set(i, { ...b, left })}
                                />
                                <RichTextEditor
                                    id={`b-${i}-r`}
                                    label="Right column"
                                    value={b.right}
                                    onChange={(right) =>
                                        set(i, { ...b, right })
                                    }
                                />
                            </div>
                        )}
                        {b.type === 'cta' && (
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor={`b-${i}-t`}>Heading</Label>
                                    <Input
                                        id={`b-${i}-t`}
                                        value={b.title}
                                        onChange={(e) =>
                                            set(i, {
                                                ...b,
                                                title: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor={`b-${i}-x`}>Text</Label>
                                    <Input
                                        id={`b-${i}-x`}
                                        value={b.text}
                                        onChange={(e) =>
                                            set(i, {
                                                ...b,
                                                text: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor={`b-${i}-bl`}>
                                        Button label
                                    </Label>
                                    <Input
                                        id={`b-${i}-bl`}
                                        value={b.button_label}
                                        onChange={(e) =>
                                            set(i, {
                                                ...b,
                                                button_label: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor={`b-${i}-bu`}>
                                        Button link
                                    </Label>
                                    <Input
                                        id={`b-${i}-bu`}
                                        value={b.button_url}
                                        placeholder="/auctions"
                                        onChange={(e) =>
                                            set(i, {
                                                ...b,
                                                button_url: e.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors[`blocks.${i}.button_url`]
                                        }
                                    />
                                </div>
                            </div>
                        )}
                        {b.type === 'faq' && (
                            <div className="grid gap-4">
                                {b.items.map((item, k) => (
                                    <div
                                        key={k}
                                        className="grid gap-2 rounded-lg border border-border p-3"
                                    >
                                        <Label htmlFor={`b-${i}-q${k}`}>
                                            Question {k + 1}
                                        </Label>
                                        <Input
                                            id={`b-${i}-q${k}`}
                                            value={item.question}
                                            onChange={(e) =>
                                                set(i, {
                                                    ...b,
                                                    items: b.items.map(
                                                        (x, j) =>
                                                            j === k
                                                                ? {
                                                                      ...x,
                                                                      question:
                                                                          e
                                                                              .target
                                                                              .value,
                                                                  }
                                                                : x,
                                                    ),
                                                })
                                            }
                                        />
                                        <RichTextEditor
                                            id={`b-${i}-a${k}`}
                                            label={`Answer ${k + 1}`}
                                            value={item.answer}
                                            onChange={(answer) =>
                                                set(i, {
                                                    ...b,
                                                    items: b.items.map(
                                                        (x, j) =>
                                                            j === k
                                                                ? {
                                                                      ...x,
                                                                      answer,
                                                                  }
                                                                : x,
                                                    ),
                                                })
                                            }
                                        />
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="w-fit"
                                            onClick={() =>
                                                set(i, {
                                                    ...b,
                                                    items: b.items.filter(
                                                        (_, j) => j !== k,
                                                    ),
                                                })
                                            }
                                        >
                                            Remove question
                                        </Button>
                                    </div>
                                ))}
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="w-fit"
                                    onClick={() =>
                                        set(i, {
                                            ...b,
                                            items: [
                                                ...b.items,
                                                { question: '', answer: '' },
                                            ],
                                        })
                                    }
                                >
                                    Add question
                                </Button>
                            </div>
                        )}
                    </section>
                ))}

                <div className="flex flex-wrap items-center gap-2 rounded-xl border border-dashed border-border p-4">
                    <span className="text-sm text-muted-foreground">
                        Add a block:
                    </span>
                    {Object.entries(blockTypes).map(([type, label]) => (
                        <Button
                            key={type}
                            size="sm"
                            variant="outline"
                            onClick={() => setBlocks([...blocks, blank(type)])}
                        >
                            {label}
                        </Button>
                    ))}
                </div>

                <section
                    className="grid gap-3 rounded-xl border border-border bg-card p-5"
                    aria-label="Search"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="seot">Search title</Label>
                        <Input
                            id="seot"
                            maxLength={120}
                            value={p.seo_title}
                            onChange={(e) =>
                                setP({ ...p, seo_title: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="seod">Search description</Label>
                        <Input
                            id="seod"
                            maxLength={300}
                            value={p.seo_description}
                            onChange={(e) =>
                                setP({ ...p, seo_description: e.target.value })
                            }
                        />
                    </div>
                </section>

                <div className="flex flex-wrap gap-2">
                    <Button onClick={() => save('published')}>
                        {p.status === 'published'
                            ? 'Save and keep published'
                            : 'Publish'}
                    </Button>
                    <Button variant="outline" onClick={() => save('draft')}>
                        {p.status === 'published'
                            ? 'Unpublish (save as draft)'
                            : 'Save draft'}
                    </Button>
                    {!page.system && (
                        <Button
                            variant="ghost"
                            onClick={() =>
                                window.confirm('Delete this page?') &&
                                router.delete(pagesRoutes.destroy(page.id).url)
                            }
                        >
                            Delete page
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

PageEdit.layout = {
    breadcrumbs: [{ title: 'Website content', href: pages() }],
};
