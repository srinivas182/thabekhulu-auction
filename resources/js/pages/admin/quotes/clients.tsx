import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import QuotesNav from '@/components/quotes-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { clients as clientsRoute, index } from '@/routes/admin/quotes';
import clientRoutes from '@/routes/admin/quotes/clients';

type C = {
    id: number | null;
    name: string;
    company: string | null;
    email: string;
    phone: string | null;
    address: string | null;
    vat_number: string | null;
    notes: string | null;
    quotes?: number;
};

function ClientForm({ c }: { c: C }) {
    const [f, setF] = useState({
        ...c,
        company: c.company ?? '',
        phone: c.phone ?? '',
        address: c.address ?? '',
        vat_number: c.vat_number ?? '',
        notes: c.notes ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const done = {
        preserveScroll: true,
        onError: setErrors,
        onSuccess: () => {
            setErrors({});
            if (!c.id)
                setF({
                    ...f,
                    name: '',
                    company: '',
                    email: '',
                    phone: '',
                    address: '',
                    vat_number: '',
                    notes: '',
                });
        },
    };
    const id = c.id ?? 'new';

    return (
        <section
            aria-label={c.id ? c.name : 'New client'}
            className="grid gap-3 rounded-xl border border-border bg-card p-5"
        >
            <div className="flex justify-between">
                <h2 className="font-semibold">
                    {c.id ? c.name : 'New client'}
                </h2>
                {c.id !== null && (
                    <span className="text-sm text-muted-foreground">
                        {c.quotes} quotation(s)
                    </span>
                )}
            </div>
            <div className="grid gap-3 md:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor={`n${id}`}>Contact name</Label>
                    <Input
                        id={`n${id}`}
                        value={f.name}
                        onChange={(e) => setF({ ...f, name: e.target.value })}
                    />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`c${id}`}>Company</Label>
                    <Input
                        id={`c${id}`}
                        value={f.company}
                        onChange={(e) =>
                            setF({ ...f, company: e.target.value })
                        }
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`e${id}`}>Email</Label>
                    <Input
                        id={`e${id}`}
                        type="email"
                        value={f.email}
                        onChange={(e) => setF({ ...f, email: e.target.value })}
                    />
                    <InputError message={errors.email} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`p${id}`}>Phone</Label>
                    <Input
                        id={`p${id}`}
                        value={f.phone}
                        onChange={(e) => setF({ ...f, phone: e.target.value })}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`v${id}`}>VAT number</Label>
                    <Input
                        id={`v${id}`}
                        value={f.vat_number}
                        onChange={(e) =>
                            setF({ ...f, vat_number: e.target.value })
                        }
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`a${id}`}>Address</Label>
                    <Input
                        id={`a${id}`}
                        value={f.address}
                        onChange={(e) =>
                            setF({ ...f, address: e.target.value })
                        }
                    />
                </div>
            </div>
            <div>
                <Button
                    size="sm"
                    onClick={() =>
                        c.id
                            ? router.put(clientRoutes.update(c.id).url, f, done)
                            : router.post(clientRoutes.store().url, f, done)
                    }
                >
                    {c.id ? 'Save client' : 'Add client'}
                </Button>
            </div>
        </section>
    );
}

export default function Clients({ clients }: { clients: C[] }) {
    return (
        <>
            <Head title="Clients" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Quotations</h1>
                <QuotesNav current="Clients" />
                <ClientForm
                    c={{
                        id: null,
                        name: '',
                        company: '',
                        email: '',
                        phone: '',
                        address: '',
                        vat_number: '',
                        notes: '',
                    }}
                />
                {clients.map((c) => (
                    <ClientForm key={c.id} c={c} />
                ))}
            </div>
        </>
    );
}

Clients.layout = {
    breadcrumbs: [
        { title: 'Quotations', href: index() },
        { title: 'Clients', href: clientsRoute() },
    ],
};
