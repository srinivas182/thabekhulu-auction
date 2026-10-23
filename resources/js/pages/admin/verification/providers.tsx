import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Copy } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    providers as providersRoute,
    routes as routesRoute,
} from '@/routes/admin/verification';
import providerRoutes from '@/routes/admin/verification/providers';

type Field = {
    name: string;
    label: string;
    secret: boolean;
    value: string | null;
    hint: string | null;
};
type Provider = {
    key: string;
    name: string;
    ready: boolean;
    note: string | null;
    supports: string[];
    enabled: boolean;
    mode: string;
    fields: Field[];
    lastSuccess: string | null;
    lastError: string | null;
    webhookUrl: string;
};
type Props = {
    providers: Provider[];
    checks: { value: string; label: string }[];
    routes: Record<string, string>;
};
const opts = { preserveScroll: true };

function ProviderCard({ p, checks }: { p: Provider; checks: Props['checks'] }) {
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
            className="grid gap-4 rounded-xl border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id={`p-${p.key}`} className="text-lg font-semibold">
                        {p.name}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {p.supports
                            .map(
                                (s) => checks.find((c) => c.value === s)?.label,
                            )
                            .join(' · ')}
                    </p>
                </div>
                <span
                    className={cn(
                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                        !p.ready
                            ? 'bg-muted text-muted-foreground'
                            : p.enabled
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
            {p.note && (
                <p className="rounded-md bg-muted px-4 py-3 text-sm">
                    {p.note}
                </p>
            )}

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
                        <Label>Webhook address to give {p.name}</Label>
                        <div className="flex gap-2">
                            <Input
                                readOnly
                                value={p.webhookUrl}
                                className="font-mono text-xs"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                aria-label="Copy webhook address"
                                onClick={() =>
                                    navigator.clipboard?.writeText(p.webhookUrl)
                                }
                            >
                                <Copy />
                            </Button>
                        </div>
                    </div>
                    <InputError message={errors.provider} />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            onClick={() =>
                                router.put(
                                    providerRoutes.update(p.key).url,
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
                                router.post(
                                    providerRoutes.test(p.key).url,
                                    {},
                                    opts,
                                )
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

export default function Providers({ providers, checks, routes }: Props) {
    const [r, setR] = useState(routes);
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const usable = (check: string) =>
        providers.filter(
            (p) => p.ready && p.enabled && p.supports.includes(check),
        );

    return (
        <>
            <Head title="Verification providers" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">
                        Verification providers
                    </h1>
                    <p className="mt-1 max-w-3xl text-muted-foreground">
                        Enable the providers Thabekhulu has contracts with,
                        enter their keys and choose which one runs each check.
                        Anything a provider can’t decide, or any check routed to
                        manual review, goes to the KYC review queue.
                    </p>
                </div>

                <section
                    aria-labelledby="routing"
                    className="grid gap-3 rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="routing" className="text-lg font-semibold">
                        Who runs each check
                    </h2>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {checks.map((c) => (
                            <div key={c.value} className="grid gap-1.5">
                                <Label htmlFor={`r-${c.value}`}>
                                    {c.label}
                                </Label>
                                <select
                                    id={`r-${c.value}`}
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    value={r[c.value]}
                                    onChange={(e) =>
                                        setR({
                                            ...r,
                                            [c.value]: e.target.value,
                                        })
                                    }
                                >
                                    <option value="manual">
                                        Manual review
                                    </option>
                                    {usable(c.value).map((p) => (
                                        <option key={p.key} value={p.key}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors[`routes.${c.value}`]}
                                />
                            </div>
                        ))}
                    </div>
                    <div>
                        <Button
                            onClick={() =>
                                router.put(
                                    routesRoute().url,
                                    { routes: r },
                                    opts,
                                )
                            }
                        >
                            Save routing
                        </Button>
                    </div>
                </section>

                {providers.map((p) => (
                    <ProviderCard key={p.key} p={p} checks={checks} />
                ))}
            </div>
        </>
    );
}

Providers.layout = {
    breadcrumbs: [{ title: 'Verification providers', href: providersRoute() }],
};
