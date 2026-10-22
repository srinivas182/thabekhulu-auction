import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CmsNav from '@/components/cms-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { pages } from '@/routes/admin/cms';
import menusRoutes from '@/routes/admin/cms/menus';

type Item = { label: string; url: string; newTab: boolean };

function MenuEditor({
    name,
    title,
    initial,
    suggestions,
}: {
    name: 'header' | 'footer';
    title: string;
    initial: Item[];
    suggestions: { label: string; url: string }[];
}) {
    const [items, setItems] = useState<Item[]>(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const set = (i: number, item: Item) =>
        setItems(items.map((x, k) => (k === i ? item : x)));
    const move = (i: number, d: -1 | 1) => {
        const n = [...items];
        [n[i], n[i + d]] = [n[i + d], n[i]];
        setItems(n);
    };

    return (
        <section
            className="grid gap-3 rounded-xl border border-border bg-card p-5"
            aria-label={title}
        >
            <h2 className="font-semibold">{title}</h2>
            {items.map((item, i) => (
                <div key={i} className="flex flex-wrap items-start gap-2">
                    <div className="grid gap-1">
                        <Input
                            aria-label={`Label ${i + 1}`}
                            value={item.label}
                            onChange={(e) =>
                                set(i, { ...item, label: e.target.value })
                            }
                            className="w-48"
                        />
                    </div>
                    <div className="grid gap-1">
                        <Input
                            aria-label={`Link ${i + 1}`}
                            value={item.url}
                            onChange={(e) =>
                                set(i, { ...item, url: e.target.value })
                            }
                            className="w-64"
                        />
                        <InputError message={errors[`items.${i}.url`]} />
                    </div>
                    <label className="flex h-9 items-center gap-1.5 text-sm">
                        <input
                            type="checkbox"
                            className="size-4 accent-primary"
                            checked={item.newTab}
                            onChange={(e) =>
                                set(i, { ...item, newTab: e.target.checked })
                            }
                        />
                        New tab
                    </label>
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
                        disabled={i === items.length - 1}
                        onClick={() => move(i, 1)}
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        aria-label={`Remove ${item.label}`}
                        onClick={() =>
                            setItems(items.filter((_, k) => k !== i))
                        }
                    >
                        <Trash2 />
                    </Button>
                </div>
            ))}
            <div className="flex flex-wrap items-center gap-2">
                <select
                    aria-label="Add a link"
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    value=""
                    onChange={(e) => {
                        const s = suggestions.find(
                            (x) => x.url === e.target.value,
                        );
                        if (s) setItems([...items, { ...s, newTab: false }]);
                    }}
                >
                    <option value="">Add a page or category…</option>
                    {suggestions.map((s) => (
                        <option key={s.url} value={s.url}>
                            {s.label}
                        </option>
                    ))}
                </select>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        setItems([
                            ...items,
                            { label: '', url: '/', newTab: false },
                        ])
                    }
                >
                    Add custom link
                </Button>
            </div>
            <div>
                <Button
                    onClick={() =>
                        router.put(
                            menusRoutes.update(name).url,
                            { items } as never,
                            {
                                preserveScroll: true,
                                onError: setErrors,
                                onSuccess: () => setErrors({}),
                            },
                        )
                    }
                >
                    Save {name} menu
                </Button>
            </div>
        </section>
    );
}

export default function Menus({
    header,
    footer,
    suggestions,
}: {
    header: Item[];
    footer: Item[];
    suggestions: { label: string; url: string }[];
}) {
    return (
        <>
            <Head title="Menus" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Website content</h1>
                <CmsNav current="Menus" />
                <MenuEditor
                    name="header"
                    title="Header menu"
                    initial={header}
                    suggestions={suggestions}
                />
                <MenuEditor
                    name="footer"
                    title="Footer menu"
                    initial={footer}
                    suggestions={suggestions}
                />
            </div>
        </>
    );
}

Menus.layout = { breadcrumbs: [{ title: 'Website content', href: pages() }] };
