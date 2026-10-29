import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rand } from '@/lib/money';
import { cn } from '@/lib/utils';
import { auctionRules as auctionRulesRoute } from '@/routes/admin/settings';
import auctionRules from '@/routes/admin/settings/auction-rules';

type Band = { upTo: number | null; step: number };
type Rules = {
    step_up_mode: 'off' | 'first' | 'every' | 'threshold';
    step_up_threshold: number;
    increment_bands: Band[];
    auto_bid_enabled: boolean;
    soft_close_enabled: boolean;
    soft_close_window_minutes: number;
    soft_close_extension_minutes: number;
    closing_soon_hours: number;
};

const modes: { key: Rules['step_up_mode']; title: string; text: string }[] = [
    {
        key: 'off',
        title: 'Off',
        text: 'No codes. For testing only; security:check flags this before launch.',
    },
    {
        key: 'first',
        title: 'First bid in each auction (recommended)',
        text: 'The bidder confirms it’s really them once per auction; later bids go through instantly, so email delays never cost anyone a winning bid.',
    },
    {
        key: 'every',
        title: 'Every bid',
        text: 'Strongest, but every bid waits for an email, which can be slow in the final minutes.',
    },
    {
        key: 'threshold',
        title: 'Bids above an amount',
        text: 'Only larger bids need a code.',
    },
];
const num = (v: string) => Number(v.replace(/\D/g, '')) || 0;

/** Bid confirmation codes, increment bands, soft close and the closing-soon reminder. */
export default function AuctionRules({ rules }: { rules: Rules }) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [f, setF] = useState(rules);
    const [saving, setSaving] = useState(false);
    const setBand = (i: number, b: Partial<Band>) =>
        setF({
            ...f,
            increment_bands: f.increment_bands.map((x, j) =>
                j === i ? { ...x, ...b } : x,
            ),
        });

    return (
        <>
            <Head title="Auction rules" />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Auction rules</h1>
                    <p className="mt-1 text-muted-foreground">
                        How bidding works on every auction. Changes apply to
                        bids from now on.
                    </p>
                </div>

                <section
                    aria-labelledby="code-h"
                    className="grid gap-3 rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="code-h" className="font-semibold">
                        Bid confirmation code
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        An emailed 6-digit code that proves the bid comes from
                        the account holder, blocking anyone using a stolen
                        password.
                    </p>
                    <div
                        className="grid gap-2"
                        role="radiogroup"
                        aria-labelledby="code-h"
                    >
                        {modes.map((m) => (
                            <label
                                key={m.key}
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-[10px] border p-3',
                                    f.step_up_mode === m.key
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="step_up_mode"
                                    checked={f.step_up_mode === m.key}
                                    onChange={() =>
                                        setF({ ...f, step_up_mode: m.key })
                                    }
                                    className="mt-0.5 size-5 accent-primary"
                                />
                                <span className="grid gap-0.5 text-sm">
                                    <span className="font-semibold">
                                        {m.title}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {m.text}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {f.step_up_mode === 'threshold' && (
                        <div className="grid max-w-xs gap-1.5">
                            <Label htmlFor="threshold">Bids from (R)</Label>
                            <Input
                                id="threshold"
                                inputMode="numeric"
                                value={String(f.step_up_threshold)}
                                onChange={(e) =>
                                    setF({
                                        ...f,
                                        step_up_threshold: num(e.target.value),
                                    })
                                }
                            />
                            <span className="text-xs text-muted-foreground">
                                Also always from twice the listing’s minimum
                                price.
                            </span>
                            <InputError message={errors.step_up_threshold} />
                        </div>
                    )}
                </section>

                <section
                    aria-labelledby="inc-h"
                    className="grid gap-3 rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="inc-h" className="font-semibold">
                        Bid increments
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        The smallest step between bids, by the current bid.
                        Automatic bids rise by these steps too.
                    </p>
                    <div className="grid gap-2">
                        {f.increment_bands.map((b, i) => {
                            const last = i === f.increment_bands.length - 1;

                            return (
                                <div
                                    key={i}
                                    className="flex flex-wrap items-center gap-2 text-sm"
                                >
                                    {last ? (
                                        <span className="w-48">
                                            {i === 0
                                                ? 'Any bid'
                                                : `From ${rand(f.increment_bands[i - 1].upTo ?? 0)} and above`}
                                        </span>
                                    ) : (
                                        <span className="flex w-48 items-center gap-1.5">
                                            Below R
                                            <Input
                                                aria-label={`Band ${i + 1} up to`}
                                                inputMode="numeric"
                                                value={String(b.upTo ?? '')}
                                                onChange={(e) =>
                                                    setBand(i, {
                                                        upTo: num(
                                                            e.target.value,
                                                        ),
                                                    })
                                                }
                                                className="h-8"
                                            />
                                        </span>
                                    )}
                                    <span>step R</span>
                                    <Input
                                        aria-label={`Band ${i + 1} step`}
                                        inputMode="numeric"
                                        value={String(b.step)}
                                        onChange={(e) =>
                                            setBand(i, {
                                                step: num(e.target.value),
                                            })
                                        }
                                        className="h-8 w-32"
                                    />
                                    {f.increment_bands.length > 1 && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                const next =
                                                    f.increment_bands.filter(
                                                        (_, j) => j !== i,
                                                    );
                                                next[next.length - 1] = {
                                                    ...next[next.length - 1],
                                                    upTo: null,
                                                };
                                                setF({
                                                    ...f,
                                                    increment_bands: next,
                                                });
                                            }}
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    <div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                const bands = [...f.increment_bands];
                                const lastBand = bands[bands.length - 1];
                                const prevTop =
                                    bands.length > 1
                                        ? (bands[bands.length - 2].upTo ?? 0)
                                        : 0;
                                bands[bands.length - 1] = {
                                    ...lastBand,
                                    upTo: prevTop + 1000000,
                                };
                                bands.push({
                                    upTo: null,
                                    step: lastBand.step * 2,
                                });
                                setF({ ...f, increment_bands: bands });
                            }}
                        >
                            Add a band
                        </Button>
                    </div>
                    <InputError message={errors.increment_bands} />
                </section>
                <section
                    aria-labelledby="auto-h"
                    className="grid gap-3 rounded-xl border border-border bg-card p-5"
                >
                    <h2 id="auto-h" className="font-semibold">
                        Automatic bidding
                    </h2>
                    <label className="flex cursor-pointer items-start gap-3 text-sm">
                        <input
                            type="checkbox"
                            checked={f.auto_bid_enabled}
                            onChange={(e) =>
                                setF({
                                    ...f,
                                    auto_bid_enabled: e.target.checked,
                                })
                            }
                            className="mt-0.5 size-5 accent-primary"
                        />
                        <span>
                            <span className="font-semibold">
                                Let buyers set a maximum and bid automatically
                            </span>
                            <span className="block text-muted-foreground">
                                Recommended: buyers who can’t watch every minute
                                still compete fairly. If switched off, buyers
                                can’t set new maximums; maximums already set
                                keep working until those auctions close.
                            </span>
                        </span>
                    </label>
                </section>

                <section
                    aria-labelledby="close-h"
                    className="grid gap-4 rounded-xl border border-border bg-card p-5 sm:grid-cols-2"
                >
                    <h2 id="close-h" className="font-semibold sm:col-span-2">
                        Closing
                    </h2>
                    <label className="flex items-start gap-3 text-sm sm:col-span-2">
                        <input
                            type="checkbox"
                            checked={f.soft_close_enabled}
                            onChange={(e) =>
                                setF({
                                    ...f,
                                    soft_close_enabled: e.target.checked,
                                })
                            }
                            className="mt-0.5 size-5 accent-primary"
                        />
                        <span>
                            <span className="font-semibold">
                                Extend the auction after a late bid (soft close)
                            </span>
                            <span className="block text-muted-foreground">
                                Stops last-second “sniping”: everyone gets a
                                fair chance to respond.
                            </span>
                        </span>
                    </label>
                    {f.soft_close_enabled && (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="window">
                                    A bid in the final (minutes)
                                </Label>
                                <Input
                                    id="window"
                                    inputMode="numeric"
                                    value={String(f.soft_close_window_minutes)}
                                    onChange={(e) =>
                                        setF({
                                            ...f,
                                            soft_close_window_minutes: num(
                                                e.target.value,
                                            ),
                                        })
                                    }
                                />
                                <InputError
                                    message={errors.soft_close_window_minutes}
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ext">
                                    extends the close by (minutes)
                                </Label>
                                <Input
                                    id="ext"
                                    inputMode="numeric"
                                    value={String(
                                        f.soft_close_extension_minutes,
                                    )}
                                    onChange={(e) =>
                                        setF({
                                            ...f,
                                            soft_close_extension_minutes: num(
                                                e.target.value,
                                            ),
                                        })
                                    }
                                />
                                <InputError
                                    message={
                                        errors.soft_close_extension_minutes
                                    }
                                />
                            </div>
                        </>
                    )}
                    <div className="grid gap-1.5">
                        <Label htmlFor="soon">
                            “Closing soon” reminder (hours before)
                        </Label>
                        <Input
                            id="soon"
                            inputMode="numeric"
                            value={String(f.closing_soon_hours)}
                            onChange={(e) =>
                                setF({
                                    ...f,
                                    closing_soon_hours: num(e.target.value),
                                })
                            }
                        />
                        <InputError message={errors.closing_soon_hours} />
                    </div>
                </section>

                <div>
                    <Button
                        disabled={saving}
                        onClick={() => {
                            setSaving(true);
                            router.put(auctionRules.update().url, f as never, {
                                preserveScroll: true,
                                onFinish: () => setSaving(false),
                            });
                        }}
                    >
                        {saving ? 'Saving…' : 'Save auction rules'}
                    </Button>
                </div>
            </div>
        </>
    );
}

AuctionRules.layout = {
    breadcrumbs: [{ title: 'Auction rules', href: auctionRulesRoute() }],
};
