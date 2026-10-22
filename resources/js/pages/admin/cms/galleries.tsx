import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import CmsNav from '@/components/cms-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { pages } from '@/routes/admin/cms';
import galleriesRoutes from '@/routes/admin/cms/galleries';
import images from '@/routes/admin/cms/galleries/images';

type Img = { id: number; url: string; alt: string; caption: string };
type G = {
    id: number;
    title: string;
    description: string | null;
    visible: boolean;
    images: Img[];
};
const opts = { preserveScroll: true };

function Album({ g }: { g: G }) {
    const [f, setF] = useState({
        title: g.title,
        description: g.description ?? '',
        is_visible: g.visible,
    });

    return (
        <section
            className="grid gap-4 rounded-xl border border-border bg-card p-5"
            aria-label={g.title}
        >
            <div className="grid gap-3 md:grid-cols-[1fr_2fr_auto] md:items-end">
                <div className="grid gap-1.5">
                    <Label htmlFor={`t${g.id}`}>Album</Label>
                    <Input
                        id={`t${g.id}`}
                        value={f.title}
                        onChange={(e) => setF({ ...f, title: e.target.value })}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`d${g.id}`}>Description</Label>
                    <Input
                        id={`d${g.id}`}
                        value={f.description}
                        onChange={(e) =>
                            setF({ ...f, description: e.target.value })
                        }
                    />
                </div>
                <label className="flex h-9 items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        className="size-4 accent-primary"
                        checked={f.is_visible}
                        onChange={(e) =>
                            setF({ ...f, is_visible: e.target.checked })
                        }
                    />
                    Visible
                </label>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button
                    size="sm"
                    onClick={() =>
                        router.put(galleriesRoutes.update(g.id).url, f, opts)
                    }
                >
                    Save album
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                        window.confirm('Delete this album and its images?') &&
                        router.delete(galleriesRoutes.destroy(g.id).url, opts)
                    }
                >
                    Delete album
                </Button>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor={`u${g.id}`}>Add images</Label>
                <input
                    id={`u${g.id}`}
                    type="file"
                    multiple
                    accept=".jpg,.jpeg,.png,.webp"
                    className="text-sm"
                    onChange={(e) =>
                        e.target.files?.length &&
                        router.post(
                            images.store(g.id).url,
                            { files: Array.from(e.target.files) },
                            { ...opts, forceFormData: true },
                        )
                    }
                />
            </div>
            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {g.images.map((img) => (
                    <li
                        key={img.id}
                        className="grid gap-2 rounded-lg border border-border p-3"
                    >
                        <img
                            src={img.url}
                            alt={img.alt}
                            className="aspect-[4/3] w-full rounded object-cover"
                        />
                        <Input
                            aria-label="Describe the image"
                            placeholder="Describe the image (required)"
                            defaultValue={img.alt}
                            className={img.alt ? '' : 'border-destructive'}
                            onBlur={(e) =>
                                e.target.value.trim() &&
                                e.target.value !== img.alt &&
                                router.put(
                                    images.update({
                                        gallery: g.id,
                                        media: img.id,
                                    }).url,
                                    {
                                        alt: e.target.value,
                                        caption: img.caption,
                                    },
                                    opts,
                                )
                            }
                        />
                        <Input
                            aria-label="Caption"
                            placeholder="Caption (optional)"
                            defaultValue={img.caption}
                            onBlur={(e) =>
                                img.alt &&
                                e.target.value !== img.caption &&
                                router.put(
                                    images.update({
                                        gallery: g.id,
                                        media: img.id,
                                    }).url,
                                    { alt: img.alt, caption: e.target.value },
                                    opts,
                                )
                            }
                        />
                        <Button
                            size="sm"
                            variant="ghost"
                            className="w-fit"
                            aria-label="Remove image"
                            onClick={() =>
                                router.delete(
                                    images.destroy({
                                        gallery: g.id,
                                        media: img.id,
                                    }).url,
                                    opts,
                                )
                            }
                        >
                            <Trash2 />
                        </Button>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Galleries({ galleries }: { galleries: G[] }) {
    const [title, setTitle] = useState('');

    return (
        <>
            <Head title="Gallery" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Website content</h1>
                <CmsNav current="Gallery" />
                {galleries.map((g) => (
                    <Album key={g.id} g={g} />
                ))}
                <div className="flex max-w-xl items-end gap-3 rounded-xl border border-border bg-card p-5">
                    <div className="grid flex-1 gap-1.5">
                        <Label htmlFor="new-album">New album</Label>
                        <Input
                            id="new-album"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            placeholder="e.g. Viewing day, Midrand"
                        />
                    </div>
                    <Button
                        onClick={() =>
                            title.trim() &&
                            router.post(
                                galleriesRoutes.store().url,
                                { title, is_visible: true },
                                { ...opts, onSuccess: () => setTitle('') },
                            )
                        }
                    >
                        Add album
                    </Button>
                </div>
            </div>
        </>
    );
}

Galleries.layout = {
    breadcrumbs: [{ title: 'Website content', href: pages() }],
};
