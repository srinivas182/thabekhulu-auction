import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import CmsNav from '@/components/cms-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { pages } from '@/routes/admin/cms';
import collectionsRoutes from '@/routes/admin/cms/collections';

type C = {
    id: number | null;
    name: string;
    description: string | null;
    categoryId: number | null;
    attributeId: number | null;
    attributeValue: string | null;
    renovatedOnly: boolean;
    visible: boolean;
    count?: number;
};
type Props = {
    collections: C[];
    categories: { id: number; name: string }[];
    attributes: {
        id: number;
        category_id: number;
        label: string;
        options: string[];
    }[];
};
const select =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm';

function Editor({
    c,
    categories,
    attributes,
}: { c: C } & Omit<Props, 'collections'>) {
    const [f, setF] = useState(c);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const attrs = attributes.filter((a) => a.category_id === f.categoryId);
    const options =
        attributes.find((a) => a.id === f.attributeId)?.options ?? [];
    const payload = {
        name: f.name,
        description: f.description,
        category_id: f.categoryId,
        category_attribute_id: f.attributeId,
        attribute_value: f.attributeValue,
        renovated_only: f.renovatedOnly,
        is_visible: f.visible,
    };
    const done = {
        preserveScroll: true,
        onError: setErrors,
        onSuccess: () => {
            setErrors({});
            if (!c.id) setF({ ...c });
        },
    };

    return (
        <section
            className="grid gap-3 rounded-xl border border-border bg-card p-5"
            aria-label={c.id ? c.name : 'New collection'}
        >
            <div className="flex items-center justify-between">
                <h2 className="font-semibold">
                    {c.id ? c.name : 'New collection'}
                </h2>
                {c.id && (
                    <span className="tabular text-sm text-muted-foreground">
                        {c.count} live and upcoming
                    </span>
                )}
            </div>
            <div className="grid gap-3 md:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`n-${c.id}`}>Name</Label>
                    <Input
                        id={`n-${c.id}`}
                        value={f.name}
                        onChange={(e) => setF({ ...f, name: e.target.value })}
                    />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`d-${c.id}`}>Description</Label>
                    <Input
                        id={`d-${c.id}`}
                        value={f.description ?? ''}
                        onChange={(e) =>
                            setF({ ...f, description: e.target.value })
                        }
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`c-${c.id}`}>Category</Label>
                    <select
                        id={`c-${c.id}`}
                        className={select}
                        value={f.categoryId ?? ''}
                        onChange={(e) =>
                            setF({
                                ...f,
                                categoryId: e.target.value
                                    ? Number(e.target.value)
                                    : null,
                                attributeId: null,
                                attributeValue: null,
                            })
                        }
                    >
                        <option value="">Any category</option>
                        {categories.map((x) => (
                            <option key={x.id} value={x.id}>
                                {x.name}
                            </option>
                        ))}
                    </select>
                </div>
                {attrs.length > 0 && (
                    <div className="grid grid-cols-2 gap-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor={`a-${c.id}`}>Where</Label>
                            <select
                                id={`a-${c.id}`}
                                className={select}
                                value={f.attributeId ?? ''}
                                onChange={(e) =>
                                    setF({
                                        ...f,
                                        attributeId: e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                        attributeValue: null,
                                    })
                                }
                            >
                                <option value="">No condition</option>
                                {attrs.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {f.attributeId && (
                            <div className="grid gap-1.5">
                                <Label htmlFor={`v-${c.id}`}>is</Label>
                                <select
                                    id={`v-${c.id}`}
                                    className={select}
                                    value={f.attributeValue ?? ''}
                                    onChange={(e) =>
                                        setF({
                                            ...f,
                                            attributeValue: e.target.value,
                                        })
                                    }
                                >
                                    <option value="">Choose…</option>
                                    {options.map((o) => (
                                        <option key={o} value={o}>
                                            {o}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.attribute_value} />
                            </div>
                        )}
                    </div>
                )}
            </div>
            <div className="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        className="size-4 accent-primary"
                        checked={f.renovatedOnly}
                        onChange={(e) =>
                            setF({ ...f, renovatedOnly: e.target.checked })
                        }
                    />
                    Renovated by Thabekhulu only
                </label>
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        className="size-4 accent-primary"
                        checked={f.visible}
                        onChange={(e) =>
                            setF({ ...f, visible: e.target.checked })
                        }
                    />
                    Show on the home page (when it has listings)
                </label>
            </div>
            <div className="flex gap-2">
                <Button
                    size="sm"
                    onClick={() =>
                        c.id
                            ? router.put(
                                  collectionsRoutes.update(c.id).url,
                                  payload,
                                  done,
                              )
                            : router.post(
                                  collectionsRoutes.store().url,
                                  payload,
                                  done,
                              )
                    }
                >
                    {c.id ? 'Save' : 'Add collection'}
                </Button>
                {c.id && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            window.confirm('Delete this collection?') &&
                            router.delete(
                                collectionsRoutes.destroy(c.id!).url,
                                { preserveScroll: true },
                            )
                        }
                    >
                        Delete
                    </Button>
                )}
            </div>
        </section>
    );
}

export default function Collections({
    collections,
    categories,
    attributes,
}: Props) {
    return (
        <>
            <Head title="Collections" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Website content</h1>
                <CmsNav current="Collections" />
                <p className="max-w-2xl text-muted-foreground">
                    Investment collections group listings by a rule and show a
                    live count on the home page.
                </p>
                {collections.map((c) => (
                    <Editor
                        key={c.id}
                        c={c}
                        categories={categories}
                        attributes={attributes}
                    />
                ))}
                <Editor
                    c={{
                        id: null,
                        name: '',
                        description: '',
                        categoryId: null,
                        attributeId: null,
                        attributeValue: null,
                        renovatedOnly: false,
                        visible: true,
                    }}
                    categories={categories}
                    attributes={attributes}
                />
            </div>
        </>
    );
}

Collections.layout = {
    breadcrumbs: [{ title: 'Website content', href: pages() }],
};
