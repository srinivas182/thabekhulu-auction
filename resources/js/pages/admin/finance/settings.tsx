import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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

/** The trust account buyers pay deposits into, and refund timing. */
export default function PaymentSettingsPage({
    settings,
    banks,
    visibleToBuyers,
}: {
    settings: S;
    banks: Record<string, string>;
    visibleToBuyers: boolean;
}) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [f, setF] = useState(settings);
    const [saving, setSaving] = useState(false);
    const set = (k: keyof S, v: string | number) => setF({ ...f, [k]: v });
    const chooseBank = (bank: string) => {
        // Universal branch code fills in; it can still be changed.
        setF({
            ...f,
            trust_bank: bank,
            trust_branch_code: banks[bank] ?? f.trust_branch_code,
        });
    };

    return (
        <>
            <Head title="Trust account and settings" />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">
                        Trust account and settings
                    </h1>
                    <p className="mt-1 text-muted-foreground">
                        The account buyers pay deposits into by EFT. They see it
                        on their deposit page once they’re approved to bid.
                    </p>
                </div>

                <p
                    role="status"
                    className={
                        visibleToBuyers
                            ? 'rounded-[10px] bg-status-live/10 px-4 py-3 text-sm font-medium text-status-live'
                            : 'rounded-[10px] bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-100'
                    }
                >
                    {visibleToBuyers
                        ? 'Buyers can see these bank details on their deposit page.'
                        : 'Buyers can’t see any bank details yet: fill in the bank, account number and branch code, then save.'}
                </p>

                <section
                    aria-labelledby="trust-h"
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 sm:grid-cols-2"
                >
                    <h2 id="trust-h" className="font-semibold sm:col-span-2">
                        Trust account
                    </h2>
                    <div className="grid gap-1.5">
                        <Label htmlFor="trust_bank">Bank</Label>
                        <select
                            id="trust_bank"
                            value={f.trust_bank}
                            onChange={(e) => chooseBank(e.target.value)}
                            className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                        >
                            <option value="">Choose a bank</option>
                            {Object.keys(banks).map((b) => (
                                <option key={b} value={b}>
                                    {b}
                                </option>
                            ))}
                            {f.trust_bank && !(f.trust_bank in banks) && (
                                <option value={f.trust_bank}>
                                    {f.trust_bank}
                                </option>
                            )}
                        </select>
                        <InputError message={errors.trust_bank} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="trust_account_name">Account name</Label>
                        <Input
                            id="trust_account_name"
                            value={f.trust_account_name}
                            onChange={(e) =>
                                set('trust_account_name', e.target.value)
                            }
                            placeholder="Thabekhulu Trust Account"
                        />
                        <InputError message={errors.trust_account_name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="trust_account_number">
                            Account number
                        </Label>
                        <Input
                            id="trust_account_number"
                            inputMode="numeric"
                            value={f.trust_account_number}
                            onChange={(e) =>
                                set('trust_account_number', e.target.value)
                            }
                        />
                        <InputError message={errors.trust_account_number} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="trust_branch_code">Branch code</Label>
                        <Input
                            id="trust_branch_code"
                            inputMode="numeric"
                            value={f.trust_branch_code}
                            onChange={(e) =>
                                set('trust_branch_code', e.target.value)
                            }
                        />
                        <span className="text-xs text-muted-foreground">
                            Filled in with the bank’s universal branch code;
                            change it if yours differs.
                        </span>
                        <InputError message={errors.trust_branch_code} />
                    </div>
                </section>

                <section
                    aria-labelledby="refund-h"
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 sm:grid-cols-2"
                >
                    <h2 id="refund-h" className="font-semibold sm:col-span-2">
                        Refunds
                    </h2>
                    <div className="grid gap-1.5">
                        <Label htmlFor="refund_business_days">
                            Refund unsuccessful bidders after (business days)
                        </Label>
                        <Input
                            id="refund_business_days"
                            inputMode="numeric"
                            value={String(f.refund_business_days)}
                            onChange={(e) =>
                                set(
                                    'refund_business_days',
                                    Number(e.target.value) || 0,
                                )
                            }
                        />
                        <InputError message={errors.refund_business_days} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="runner_up_hold_days">
                            Hold the runner-up’s deposit for (days)
                        </Label>
                        <Input
                            id="runner_up_hold_days"
                            inputMode="numeric"
                            value={String(f.runner_up_hold_days)}
                            onChange={(e) =>
                                set(
                                    'runner_up_hold_days',
                                    Number(e.target.value) || 0,
                                )
                            }
                        />
                        <InputError message={errors.runner_up_hold_days} />
                    </div>
                </section>

                <div>
                    <Button
                        disabled={saving}
                        onClick={() => {
                            setSaving(true);
                            router.put(
                                settingsRoutes.update().url,
                                f as never,
                                {
                                    preserveScroll: true,
                                    onFinish: () => setSaving(false),
                                },
                            );
                        }}
                    >
                        {saving ? 'Saving…' : 'Save'}
                    </Button>
                </div>
            </div>
        </>
    );
}

PaymentSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Trust account and settings', href: settingsRoute() },
    ],
};
