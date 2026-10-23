import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Copy } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import providers from '@/routes/admin/finance/providers';

type Field = {
    name: string;
    label: string;
    secret: boolean;
    value: string | null;
    hint: string | null;
};
type P = {
    key: string;
    name: string;
    ready: boolean;
    note: string;
    supports: string[];
    enabled: boolean;
    mode: string;
    lastSuccess: string | null;
    lastError: string | null;
    fields: Field[];
    callbackUrl: string | null;
};
const opts = { preserveScroll: true };

function Card({ p }: { p: P }) {
    const [f, setF] = useState({
        enabled: p.enabled,
        mode: p.mode,
        credentials: Object.fromEntries(
            p.fields.map((x) => [x.name, x.value ?? '']),
        ) as Record<string, string>,
    });
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;

    return (
        <section
            aria-labelledby={`p-${p.key}`}
            className="grid gap-4 rounded-xl border border-border bg-card p-5 [&_*]:min-w-0"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id={`p-${p.key}`} className="text-lg font-semibold">
                        {p.name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {p.supports.includes('deposit')
                            ? 'Escrow for deposits'
                            : 'Small payments (tickets, fees)'}
                    </p>
                </div>
                <span
                    className={cn(
                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                        p.ready && p.enabled
                            ? 'bg-status-live/10 text-status-live'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    {!p.ready
                        ? 'Not ready'
                        : p.enabled
                          ? `Enabled · ${p.mode}`
                          : 'Off'}
                </span>
            </div>
            <p className="rounded-md bg-muted px-4 py-3 text-sm">{p.note}</p>
            {p.ready && (
                <>
                    <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                className="size-4 accent-primary"
                                checked={f.enabled}
                                onChange={(e) =>
                                    setF({ ...f, enabled: e.target.checked })
                                }
                            />
                            Enabled
                        </label>
                        <fieldset className="flex items-center gap-4">
                            <legend className="sr-only">Mode</legend>
                            {['sandbox', 'live'].map((m) => (
                                <label
                                    key={m}
                                    className="flex items-center gap-1.5"
                                >
                                    <input
                                        type="radio"
                                        name={`mode-${p.key}`}
                                        className="size-4 accent-primary"
                                        checked={f.mode === m}
                                        onChange={() => setF({ ...f, mode: m })}
                                    />
                                    {m === 'sandbox'
                                        ? 'Sandbox (testing)'
                                        : 'Live'}
                                </label>
                            ))}
                        </fieldset>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                        {p.fields.map((x) => (
                            <div key={x.name} className="grid gap-1.5">
                                <Label htmlFor={`${p.key}-${x.name}`}>
                                    {x.label}
                                </Label>
                                <Input
                                    id={`${p.key}-${x.name}`}
                                    type={x.secret ? 'password' : 'text'}
                                    autoComplete="off"
                                    value={f.credentials[x.name]}
                                    placeholder={
                                        x.hint ?? (x.secret ? 'Not set' : '')
                                    }
                                    onChange={(e) =>
                                        setF({
                                            ...f,
                                            credentials: {
                                                ...f.credentials,
                                                [x.name]: e.target.value,
                                            },
                                        })
                                    }
                                />
                                {x.secret && x.hint && (
                                    <p className="text-xs text-muted-foreground">
                                        Saved. Leave empty to keep it.
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Callback URL to give {p.name}</Label>
                        {p.callbackUrl ? (
                            <div className="flex gap-2">
                                <Input
                                    readOnly
                                    value={p.callbackUrl}
                                    className="font-mono text-xs"
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    aria-label="Copy callback URL"
                                    onClick={() =>
                                        void navigator.clipboard?.writeText(
                                            p.callbackUrl ?? '',
                                        )
                                    }
                                >
                                    <Copy />
                                </Button>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Save a callback secret first; it becomes part of
                                the URL.
                            </p>
                        )}
                    </div>
                    <InputError message={errors.provider} />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            onClick={() =>
                                router.put(
                                    providers.update(p.key).url,
                                    f as never,
                                    opts,
                                )
                            }
                        >
                            Save
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(providers.test(p.key).url, {}, opts)
                            }
                        >
                            Test connection
                        </Button>
                        {p.lastSuccess && (
                            <span className="inline-flex items-center gap-1 text-sm text-status-live">
                                <CheckCircle2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Connected {formatDateTime(p.lastSuccess)}
                            </span>
                        )}
                        {p.lastError && (
                            <span className="text-sm text-destructive">
                                {p.lastError}
                            </span>
                        )}
                    </div>
                </>
            )}
        </section>
    );
}

export default function PaymentProviders({
    providers: list,
    depositProvider,
}: {
    providers: P[];
    depositProvider: string;
}) {
    const [dp, setDp] = useState(depositProvider);
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const escrow = list.filter(
        (p) => p.ready && p.enabled && p.supports.includes('deposit'),
    );

    return (
        <>
            <Head title="Payment providers" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Payment providers</h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        EFT to Thabekhulu’s trust account is always available
                        for deposits. Enable an escrow provider to offer it as
                        well, and card or instant-EFT providers for smaller
                        payments.
                    </p>
                </div>
                <section
                    aria-labelledby="dep"
                    className="grid max-w-xl gap-2 rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="dep" className="text-lg font-semibold">
                        Deposits
                    </h2>
                    <Label htmlFor="dp">How bidders can pay deposits</Label>
                    <select
                        id="dp"
                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        value={dp}
                        onChange={(e) => setDp(e.target.value)}
                    >
                        <option value="none">
                            EFT to the trust account only
                        </option>
                        {escrow.map((p) => (
                            <option key={p.key} value={p.key}>
                                EFT, or {p.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.deposit_provider} />
                    <div>
                        <Button
                            size="sm"
                            onClick={() =>
                                router.put(
                                    providers.deposit().url,
                                    { deposit_provider: dp },
                                    opts,
                                )
                            }
                        >
                            Save
                        </Button>
                    </div>
                </section>
                {list.map((p) => (
                    <Card key={p.key} p={p} />
                ))}
            </div>
        </>
    );
}

PaymentProviders.layout = {
    breadcrumbs: [{ title: 'Payment providers', href: providers.index() }],
};
