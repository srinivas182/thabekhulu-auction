import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import QuotesNav from '@/components/quotes-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/quotes';
import catalogueRoutes from '@/routes/admin/quotes/catalogue';

type I = {
    id: number | null;
    name: string;
    description: string | null;
    unit: string;
    unit_price: number;
    vat_applicable: boolean;
    frequency: string;
    is_active: boolean;
};
const select = 'h-9 rounded-md border border-input bg-background px-2 text-sm';

function Row({ item }: { item: I }) {
    const [f, setF] = useState({
        ...item,
        description: item.description ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const done = {
        preserveScroll: true,
        onError: setErrors,
        onSuccess: () => {
            setErrors({});
            if (!item.id)
                setF({ ...f, name: '', description: '', unit_price: 0 });
        },
    };

    return (
        <tr className="border-t border-border align-top">
            <td className="py-2 pr-2">
                <Input
                    aria-label="Name"
                    value={f.name}
                    onChange={(e) => setF({ ...f, name: e.target.value })}
                    placeholder={item.id ? '' : 'New item'}
                />
                <InputError message={errors.name} />
            </td>
            <td className="pr-2">
                <Input
                    aria-label="Unit"
                    value={f.unit}
                    onChange={(e) => setF({ ...f, unit: e.target.value })}
                />
            </td>
            <td className="pr-2">
                <Input
                    aria-label="Unit price (R)"
                    inputMode="decimal"
                    className="tabular"
                    value={f.unit_price}
                    onChange={(e) =>
                        setF({
                            ...f,
                            unit_price: e.target.value as unknown as number,
                        })
                    }
                />
                <InputError message={errors.unit_price} />
            </td>
            <td className="pr-2">
                <select
                    aria-label="Billed"
                    className={select}
                    value={f.frequency}
                    onChange={(e) => setF({ ...f, frequency: e.target.value })}
                >
                    <option value="once">Once-off</option>
                    <option value="monthly">Monthly</option>
                    <option value="annual">Annually</option>
                </select>
            </td>
            <td className="pr-2 text-center">
                <input
                    type="checkbox"
                    aria-label="VAT applies"
                    className="size-4 accent-primary"
                    checked={f.vat_applicable}
                    onChange={(e) =>
                        setF({ ...f, vat_applicable: e.target.checked })
                    }
                />
            </td>
            <td className="pr-2 text-center">
                <input
                    type="checkbox"
                    aria-label="Active"
                    className="size-4 accent-primary"
                    checked={f.is_active}
                    onChange={(e) =>
                        setF({ ...f, is_active: e.target.checked })
                    }
                />
            </td>
            <td>
                <Button
                    size="sm"
                    variant={item.id ? 'outline' : 'default'}
                    onClick={() =>
                        item.id
                            ? router.put(
                                  catalogueRoutes.update(item.id).url,
                                  f,
                                  done,
                              )
                            : router.post(catalogueRoutes.store().url, f, done)
                    }
                >
                    {item.id ? 'Save' : 'Add'}
                </Button>
            </td>
        </tr>
    );
}

export default function Catalogue({ items }: { items: I[] }) {
    return (
        <>
            <Head title="Price catalogue" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Quotations</h1>
                <QuotesNav current="Catalogue" />
                <p className="max-w-2xl text-muted-foreground">
                    Standard items staff can add to a quotation. Changing a
                    price here never changes quotations already created.
                </p>
                <div className="overflow-x-auto rounded-xl border border-border bg-card p-4">
                    <table className="w-full min-w-[860px] text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="pb-2 font-medium">Item</th>
                                <th className="w-24 font-medium">Unit</th>
                                <th className="w-36 font-medium">
                                    Unit price (R)
                                </th>
                                <th className="w-32 font-medium">Billed</th>
                                <th className="w-14 font-medium">VAT</th>
                                <th className="w-16 font-medium">Active</th>
                                <th className="w-20" />
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((i) => (
                                <Row key={i.id} item={i} />
                            ))}
                            <Row
                                item={{
                                    id: null,
                                    name: '',
                                    description: '',
                                    unit: 'each',
                                    unit_price: 0,
                                    vat_applicable: true,
                                    frequency: 'once',
                                    is_active: true,
                                }}
                            />
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Catalogue.layout = { breadcrumbs: [{ title: 'Quotations', href: index() }] };
