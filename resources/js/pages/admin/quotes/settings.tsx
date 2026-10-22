import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import QuotesNav from '@/components/quotes-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/quotes';
import settingsRoutes from '@/routes/admin/quotes/settings';

type S = {
    company_name: string;
    registration_number: string;
    vat_number: string;
    address: string;
    bank_details: string;
    vat_rate: number;
    validity_days: number;
    approval_threshold: number;
    reminder_days: number;
    number_prefix: string;
};

export default function QuoteSettings({ settings }: { settings: S }) {
    const [f, setF] = useState(settings);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const field = (
        key: keyof S,
        label: string,
        hint?: string,
        type = 'text',
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={key}>{label}</Label>
            <Input
                id={key}
                type={type}
                value={f[key] as string | number}
                onChange={(e) =>
                    setF({
                        ...f,
                        [key]:
                            type === 'number'
                                ? Number(e.target.value)
                                : e.target.value,
                    })
                }
            />
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError message={errors[key]} />
        </div>
    );

    return (
        <>
            <Head title="Quotation settings" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Quotations</h1>
                <QuotesNav current="Settings" />
                <section
                    aria-label="Letterhead"
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-2"
                >
                    <h2 className="font-semibold md:col-span-2">Letterhead</h2>
                    {field('company_name', 'Company legal name')}
                    {field(
                        'registration_number',
                        'Company registration number',
                    )}
                    {field('vat_number', 'VAT number')}
                    <div className="grid gap-1.5">
                        <Label htmlFor="address">Address</Label>
                        <textarea
                            id="address"
                            className="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={f.address}
                            onChange={(e) =>
                                setF({ ...f, address: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5 md:col-span-2">
                        <Label htmlFor="bank">
                            Bank details (default “How to pay”)
                        </Label>
                        <textarea
                            id="bank"
                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={f.bank_details}
                            onChange={(e) =>
                                setF({ ...f, bank_details: e.target.value })
                            }
                        />
                    </div>
                </section>
                <section
                    aria-label="Defaults"
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-3"
                >
                    <h2 className="font-semibold md:col-span-3">Defaults</h2>
                    {field('vat_rate', 'VAT rate (%)', undefined, 'number')}
                    {field(
                        'validity_days',
                        'Quotations valid for (days)',
                        undefined,
                        'number',
                    )}
                    {field(
                        'approval_threshold',
                        'Approval needed above (R)',
                        'Once-off total including VAT.',
                        'number',
                    )}
                    {field(
                        'reminder_days',
                        'Reminder before expiry (days)',
                        undefined,
                        'number',
                    )}
                    {field(
                        'number_prefix',
                        'Number prefix',
                        'e.g. TDG-Q gives TDG-Q-2026-0001.',
                    )}
                </section>
                <div>
                    <Button
                        onClick={() =>
                            router.put(
                                settingsRoutes.update().url,
                                f as never,
                                {
                                    preserveScroll: true,
                                    onError: setErrors,
                                    onSuccess: () => setErrors({}),
                                },
                            )
                        }
                    >
                        Save settings
                    </Button>
                </div>
            </div>
        </>
    );
}

QuoteSettings.layout = {
    breadcrumbs: [{ title: 'Quotations', href: index() }],
};
