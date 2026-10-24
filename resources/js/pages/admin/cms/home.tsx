import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { exportMethod as newsletterExport } from '@/routes/admin/newsletter';
import CmsNav from '@/components/cms-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { pages } from '@/routes/admin/cms';
import homeRoutes from '@/routes/admin/cms/home';

type Home = {
    hero_title: string;
    hero_text: string;
    steps: { title: string; text: string }[];
    ffc_number: string;
    show_calendar: boolean;
    featured_listing: string | null;
};

export default function HomeSettings({
    home,
    listings,
}: {
    home: Home;
    listings: { ulid: string; title: string }[];
}) {
    const [h, setH] = useState(home);
    const [errors, setErrors] = useState<Record<string, string>>({});

    return (
        <>
            <Head title="Home page" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <h1 className="font-display text-3xl">Website content</h1>
                <a href={newsletterExport().url} className="text-sm underline">
                    Download newsletter subscribers (CSV, confirmed only)
                </a>
                <CmsNav current="Home page" />
                <section
                    className="grid gap-4 rounded-xl border border-border bg-card p-5"
                    aria-label="Hero"
                >
                    <h2 className="font-semibold">Hero</h2>
                    <div className="grid gap-1.5">
                        <Label htmlFor="featured">Featured auction</Label>
                        <select
                            id="featured"
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            value={h.featured_listing ?? ''}
                            onChange={(e) =>
                                setH({
                                    ...h,
                                    featured_listing: e.target.value || null,
                                })
                            }
                        >
                            <option value="">
                                None: show the headline below
                            </option>
                            {listings.map((l) => (
                                <option key={l.ulid} value={l.ulid}>
                                    {l.title}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="ht">
                            Headline (when no featured auction)
                        </Label>
                        <Input
                            id="ht"
                            value={h.hero_title}
                            onChange={(e) =>
                                setH({ ...h, hero_title: e.target.value })
                            }
                        />
                        <InputError message={errors.hero_title} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="hx">Introduction</Label>
                        <Input
                            id="hx"
                            value={h.hero_text}
                            onChange={(e) =>
                                setH({ ...h, hero_text: e.target.value })
                            }
                        />
                    </div>
                </section>
                <section
                    className="grid gap-3 rounded-xl border border-border bg-card p-5"
                    aria-label="How bidding works"
                >
                    <h2 className="font-semibold">How bidding works</h2>
                    {h.steps.map((s, i) => (
                        <div
                            key={i}
                            className="grid gap-2 md:grid-cols-[220px_1fr]"
                        >
                            <Input
                                aria-label={`Step ${i + 1} title`}
                                value={s.title}
                                onChange={(e) =>
                                    setH({
                                        ...h,
                                        steps: h.steps.map((x, k) =>
                                            k === i
                                                ? {
                                                      ...x,
                                                      title: e.target.value,
                                                  }
                                                : x,
                                        ),
                                    })
                                }
                            />
                            <Input
                                aria-label={`Step ${i + 1} text`}
                                value={s.text}
                                onChange={(e) =>
                                    setH({
                                        ...h,
                                        steps: h.steps.map((x, k) =>
                                            k === i
                                                ? { ...x, text: e.target.value }
                                                : x,
                                        ),
                                    })
                                }
                            />
                        </div>
                    ))}
                </section>
                <section
                    className="grid gap-3 rounded-xl border border-border bg-card p-5"
                    aria-label="Trust and calendar"
                >
                    <div className="grid max-w-sm gap-1.5">
                        <Label htmlFor="ffc">
                            PPRA Fidelity Fund Certificate number
                        </Label>
                        <Input
                            id="ffc"
                            value={h.ffc_number}
                            onChange={(e) =>
                                setH({ ...h, ffc_number: e.target.value })
                            }
                            placeholder="Shown in the trust band and footer"
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="size-4 accent-primary"
                            checked={h.show_calendar}
                            onChange={(e) =>
                                setH({ ...h, show_calendar: e.target.checked })
                            }
                        />
                        Show the auction calendar
                    </label>
                </section>
                <div>
                    <Button
                        onClick={() =>
                            router.put(homeRoutes.update().url, h as never, {
                                preserveScroll: true,
                                onError: setErrors,
                                onSuccess: () => setErrors({}),
                            })
                        }
                    >
                        Save home page
                    </Button>
                </div>
            </div>
        </>
    );
}

HomeSettings.layout = {
    breadcrumbs: [{ title: 'Website content', href: pages() }],
};
