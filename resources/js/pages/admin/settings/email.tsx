import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    edit as settingsEdit,
    email as emailPage,
} from '@/routes/admin/settings';
import emailRoutes from '@/routes/admin/settings/email';

type Provider = {
    label: string;
    host: string;
    port: number;
    username: string;
    password: string;
    help: string;
};
type Props = {
    settings: {
        enabled: boolean;
        provider: string;
        host: string;
        port: number;
        username: string;
        password_hint: string | null;
        from_address: string;
        from_name: string;
    };
    sending: boolean;
    providers: Record<string, Provider>;
};

export default function EmailProvider({ settings, sending, providers }: Props) {
    const [enabled, setEnabled] = useState(settings.enabled);
    const [provider, setProvider] = useState(settings.provider);
    const [host, setHost] = useState(settings.host);
    const [port, setPort] = useState(String(settings.port));
    const p = providers[provider];

    const choose = (key: string) => {
        setProvider(key);
        setHost(providers[key].host);
        setPort(String(providers[key].port));
    };

    return (
        <>
            <Head title="Email provider" />
            <div className="flex max-w-3xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Email provider</h1>
                    <p
                        className={
                            sending
                                ? 'mt-1 font-medium text-status-live'
                                : 'mt-1 font-medium text-destructive'
                        }
                    >
                        {sending
                            ? `Emails are being sent through ${providers[settings.provider]?.label ?? 'your provider'}.`
                            : 'Email sending is off. No emails are sent, and buyers aren’t asked to verify their email address.'}
                    </p>
                </div>

                <Form
                    action={emailRoutes.update().url}
                    method="put"
                    options={{ preserveScroll: true }}
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 text-sm"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="enabled"
                                value={enabled ? '1' : '0'}
                            />
                            <label className="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    checked={enabled}
                                    onChange={(e) =>
                                        setEnabled(e.target.checked)
                                    }
                                    className="mt-0.5 size-4 accent-primary"
                                />
                                <span>
                                    <span className="font-medium">
                                        Send emails through this provider
                                    </span>
                                    <br />
                                    <span className="text-muted-foreground">
                                        While off, nothing is sent: registration
                                        receipts, alerts and notices are
                                        skipped.
                                    </span>
                                </span>
                            </label>

                            <label className="grid gap-1 font-medium">
                                Provider
                                <select
                                    name="provider"
                                    value={provider}
                                    onChange={(e) => choose(e.target.value)}
                                    className="h-10 rounded-md border border-input bg-background px-3 font-normal"
                                >
                                    {Object.entries(providers).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <p className="rounded-lg bg-muted px-3 py-2 text-muted-foreground">
                                {p?.help}
                            </p>

                            <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                                <label className="grid gap-1 font-medium">
                                    Server
                                    <Input
                                        name="host"
                                        value={host}
                                        onChange={(e) =>
                                            setHost(e.target.value)
                                        }
                                        placeholder="smtp.example.com"
                                    />
                                    {errors.host && (
                                        <span className="font-normal text-destructive">
                                            {errors.host}
                                        </span>
                                    )}
                                </label>
                                <label className="grid gap-1 font-medium">
                                    Port
                                    <select
                                        name="port"
                                        value={port}
                                        onChange={(e) =>
                                            setPort(e.target.value)
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 font-normal"
                                    >
                                        {['587', '465', '2525', '25'].map(
                                            (v) => (
                                                <option key={v} value={v}>
                                                    {v}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </label>
                            </div>
                            <label className="grid gap-1 font-medium">
                                {p?.username ?? 'Username'}
                                <Input
                                    name="username"
                                    defaultValue={settings.username}
                                    autoComplete="off"
                                />
                                {errors.username && (
                                    <span className="font-normal text-destructive">
                                        {errors.username}
                                    </span>
                                )}
                            </label>
                            <label className="grid gap-1 font-medium">
                                {p?.password ?? 'Password'}
                                <Input
                                    name="password"
                                    type="password"
                                    autoComplete="new-password"
                                    placeholder={
                                        settings.password_hint
                                            ? `Saved (${settings.password_hint}). Leave blank to keep it.`
                                            : ''
                                    }
                                />
                                {errors.password && (
                                    <span className="font-normal text-destructive">
                                        {errors.password}
                                    </span>
                                )}
                            </label>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="grid gap-1 font-medium">
                                    Send from (address)
                                    <Input
                                        name="from_address"
                                        type="email"
                                        defaultValue={settings.from_address}
                                        placeholder="noreply@yourdomain.co.za"
                                    />
                                    {errors.from_address && (
                                        <span className="font-normal text-destructive">
                                            {errors.from_address}
                                        </span>
                                    )}
                                </label>
                                <label className="grid gap-1 font-medium">
                                    Send from (name)
                                    <Input
                                        name="from_name"
                                        defaultValue={settings.from_name}
                                    />
                                </label>
                            </div>
                            <Button disabled={processing} className="w-fit">
                                Save
                            </Button>
                        </>
                    )}
                </Form>

                <Form
                    action={emailRoutes.test().url}
                    method="post"
                    options={{ preserveScroll: true }}
                    className="grid gap-3 rounded-xl border border-border bg-card p-5 text-sm"
                >
                    {({ processing, errors }) => (
                        <>
                            <h2 className="font-semibold">Send a test email</h2>
                            <p className="text-muted-foreground">
                                Save first, then send one email to check the
                                provider accepts it.
                            </p>
                            <div className="flex flex-wrap items-start gap-2">
                                <Input
                                    name="to"
                                    type="email"
                                    placeholder="you@example.com"
                                    className="max-w-xs"
                                    aria-label="Send the test to"
                                />
                                <Button
                                    variant="outline"
                                    disabled={processing || !sending}
                                >
                                    Send test email
                                </Button>
                            </div>
                            {errors.to && (
                                <p className="break-words text-destructive">
                                    {errors.to}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

EmailProvider.layout = {
    breadcrumbs: [
        { title: 'Settings', href: settingsEdit() },
        { title: 'Email provider', href: emailPage() },
    ],
};
