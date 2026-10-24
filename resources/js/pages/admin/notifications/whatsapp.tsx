import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { whatsapp as page } from '@/routes/admin/notifications';
import whatsappRoutes from '@/routes/admin/notifications/whatsapp';

type S = {
    enabled: boolean;
    phone_number_id: string;
    token_hint: string | null;
    api_version: string;
    language: string;
    template_outbid: string;
    template_closing: string;
};

export default function WhatsAppSettingsPage({ settings }: { settings: S }) {
    const [f, setF] = useState({ ...settings, access_token: '' });
    const text = (k: keyof typeof f, label: string, hint?: string) => (
        <div className="grid gap-1.5">
            <Label htmlFor={k}>{label}</Label>
            <Input
                id={k}
                value={String(f[k] ?? '')}
                onChange={(e) => setF({ ...f, [k]: e.target.value })}
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );

    return (
        <>
            <Head title="WhatsApp alerts" />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">WhatsApp alerts</h1>
                    <p className="mt-1 text-muted-foreground">
                        Meta WhatsApp Business Cloud API. Messages go only to
                        buyers who opted in, using the templates approved in
                        Meta’s WhatsApp Manager (utility category). Email is
                        always sent as well.
                    </p>
                </div>
                <div className="grid gap-4 rounded-xl border border-border bg-card p-5 sm:grid-cols-2">
                    <label className="flex items-center gap-2 text-sm sm:col-span-2">
                        <input
                            type="checkbox"
                            className="size-4 accent-primary"
                            checked={f.enabled}
                            onChange={(e) =>
                                setF({ ...f, enabled: e.target.checked })
                            }
                        />
                        Send WhatsApp alerts
                    </label>
                    {text(
                        'phone_number_id',
                        'Phone number ID',
                        'From WhatsApp Manager → API setup',
                    )}
                    <div className="grid gap-1.5">
                        <Label htmlFor="access_token">Access token</Label>
                        <Input
                            id="access_token"
                            type="password"
                            autoComplete="off"
                            placeholder={f.token_hint ?? 'Not set'}
                            value={f.access_token}
                            onChange={(e) =>
                                setF({ ...f, access_token: e.target.value })
                            }
                        />
                        {f.token_hint && (
                            <p className="text-xs text-muted-foreground">
                                Saved. Leave empty to keep it.
                            </p>
                        )}
                    </div>
                    {text('api_version', 'Graph API version')}
                    {text('language', 'Template language code', 'e.g. en')}
                    {text(
                        'template_outbid',
                        'Outbid template name',
                        'Body: {{1}} listing, {{2}} new bid',
                    )}
                    {text(
                        'template_closing',
                        'Closing-soon template name',
                        'Body: {{1}} listing, {{2}} time left',
                    )}
                </div>
                <div>
                    <Button
                        onClick={() =>
                            router.put(
                                whatsappRoutes.update().url,
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

WhatsAppSettingsPage.layout = {
    breadcrumbs: [{ title: 'WhatsApp alerts', href: page() }],
};
