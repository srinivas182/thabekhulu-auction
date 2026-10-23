import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { settings as settingsRoute } from '@/routes/admin/finance';
import settingsRoutes from '@/routes/admin/finance/settings';

type S = {
    trust_bank: string;
    trust_account_name: string;
    trust_account_number: string;
    trust_branch_code: string;
    refund_business_days: number;
    runner_up_hold_days: number;
};
const fields: [keyof S, string, string?][] = [
    ['trust_bank', 'Bank'],
    ['trust_account_name', 'Account name'],
    ['trust_account_number', 'Account number', 'numeric'],
    ['trust_branch_code', 'Branch code', 'numeric'],
    [
        'refund_business_days',
        'Refund unsuccessful bidders after (business days)',
        'numeric',
    ],
    [
        'runner_up_hold_days',
        'Hold the runner-up’s deposit for (days)',
        'numeric',
    ],
];

export default function PaymentSettingsPage({ settings }: { settings: S }) {
    const [f, setF] = useState(settings);

    return (
        <>
            <Head title="Payment settings" />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Payment settings</h1>
                    <p className="mt-1 text-muted-foreground">
                        The trust account bidders pay deposits into. Bidders see
                        these details only after they apply to bid.
                    </p>
                </div>
                <div className="grid gap-4 rounded-xl border border-border bg-card p-5 sm:grid-cols-2">
                    {fields.map(([k, label, mode]) => (
                        <div key={k} className="grid gap-1.5">
                            <Label htmlFor={k}>{label}</Label>
                            <Input
                                id={k}
                                inputMode={mode as 'numeric' | undefined}
                                value={String(f[k])}
                                onChange={(e) =>
                                    setF({
                                        ...f,
                                        [k]:
                                            typeof settings[k] === 'number'
                                                ? Number(e.target.value)
                                                : e.target.value,
                                    })
                                }
                            />
                        </div>
                    ))}
                </div>
                <div>
                    <Button
                        onClick={() =>
                            router.put(
                                settingsRoutes.update().url,
                                f as never,
                                { preserveScroll: true },
                            )
                        }
                    >
                        Save
                    </Button>
                </div>
            </div>
        </>
    );
}

PaymentSettingsPage.layout = {
    breadcrumbs: [{ title: 'Payment settings', href: settingsRoute() }],
};
