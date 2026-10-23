import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { settings as settingsRoute } from '@/routes/admin/dataroom';
import settingsRoutes from '@/routes/admin/dataroom/settings';

type S = {
    nda_text: string;
    nda_version: number;
    daily_download_alert: number;
};

export default function DataRoomSettingsPage({ settings }: { settings: S }) {
    const [f, setF] = useState(settings);

    return (
        <>
            <Head title="Data room" />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Data room</h1>
                    <p className="mt-1 text-muted-foreground">
                        Bidders accept this NDA before opening a listing’s data
                        room. Changing the text publishes a new version (now
                        version {settings.nda_version}), which every bidder
                        accepts again.
                    </p>
                </div>
                <section
                    className="grid gap-2 rounded-xl border border-border bg-card p-5"
                    aria-label="NDA"
                >
                    <Label htmlFor="nda">Non-disclosure agreement</Label>
                    <RichTextEditor
                        id="nda"
                        label="NDA"
                        value={f.nda_text}
                        onChange={(nda_text) => setF({ ...f, nda_text })}
                    />
                </section>
                <section
                    className="grid max-w-sm gap-1.5 rounded-xl border border-border bg-card p-5"
                    aria-label="Alert"
                >
                    <Label htmlFor="alert">
                        Flag a bidder after this many downloads in 24 hours
                    </Label>
                    <Input
                        id="alert"
                        type="number"
                        min={1}
                        value={f.daily_download_alert}
                        onChange={(e) =>
                            setF({
                                ...f,
                                daily_download_alert: Number(e.target.value),
                            })
                        }
                    />
                </section>
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

DataRoomSettingsPage.layout = {
    breadcrumbs: [{ title: 'Data room', href: settingsRoute() }],
};
