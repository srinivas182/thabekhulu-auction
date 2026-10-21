import { Head, Link, router } from '@inertiajs/react';
import { Check, TriangleAlert } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { isHex, ratio, suggest } from '@/lib/contrast';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { edit, publish, reset, restore } from '@/routes/admin/theme';
import draft from '@/routes/admin/theme/draft';
// Load every pairing on this page only, so previews show real fonts.
import '../../../../css/fonts/thabekhulu.css';
import '../../../../css/fonts/classic.css';
import '../../../../css/fonts/modern.css';
import '../../../../css/fonts/heritage.css';
import '../../../../css/fonts/studio.css';
import '../../../../css/fonts/bold.css';
import '../../../../css/fonts/geometric.css';
import '../../../../css/fonts/humanist.css';

type Mode = 'light' | 'dark';
type Colours = Record<string, string>;
type Values = {
    light: Colours;
    dark: Colours;
    fonts: string;
    radius: string;
    logo_light_url: string | null;
    logo_dark_url: string | null;
    favicon_url: string | null;
};
type Pairing = { key: string; label: string; display: string; ui: string };
type Version = {
    id: number;
    live: boolean;
    publishedAt: string | null;
    publishedBy: string | null;
    note: string | null;
};

type Props = {
    scope: 'website' | 'admin';
    values: Values;
    hasDraft: boolean;
    defaults: Omit<Values, 'logo_light_url' | 'logo_dark_url' | 'favicon_url'>;
    colours: Record<string, string>;
    status: Record<Mode, Record<string, string>>;
    pairings: Pairing[];
    radii: string[];
    history: Version[];
};

// Same pairs as app/Domains/Theme/Support/ThemeChecks.php.
const PAIRS: [string, string, number, string][] = [
    ['text', 'background', 4.5, 'Text on the page background'],
    ['text', 'surface', 4.5, 'Text on cards and panels'],
    ['muted_text', 'background', 4.5, 'Secondary text on the page background'],
    ['muted_text', 'surface', 4.5, 'Secondary text on cards and panels'],
    ['primary_foreground', 'primary', 4.5, 'Button text on primary buttons'],
    ['primary', 'background', 3, 'Primary buttons against the page'],
    ['accent', 'background', 3, 'Accent and focus ring against the page'],
    ['accent', 'surface', 3, 'Accent and focus ring on cards'],
];

const RADIUS: Record<string, string> = {
    sharp: '0.125rem',
    soft: '0.625rem',
    round: '1rem',
};

type Check = {
    mode: Mode;
    field: string;
    label: string;
    value: number;
    min: number;
    pass: boolean;
    fix: string;
};

function runChecks(values: Values, status: Props['status']): Check[] {
    const out: Check[] = [];

    (['light', 'dark'] as Mode[]).forEach((mode) => {
        const c = values[mode];
        if (!Object.values(c).every(isHex)) return;

        PAIRS.forEach(([fg, bg, min, label]) => {
            const value = ratio(c[fg], c[bg]);
            out.push({
                mode,
                field: fg,
                label,
                value,
                min,
                pass: value >= min,
                fix: suggest(c[fg], c[bg], min),
            });
        });

        Object.entries(status[mode]).forEach(([name, colour]) => {
            (['background', 'surface'] as const).forEach((bg) => {
                const value = ratio(colour, c[bg]);
                out.push({
                    mode,
                    field: bg,
                    label: `Fixed “${name}” badge on ${bg === 'surface' ? 'cards' : 'the page background'}`,
                    value,
                    min: 4.5,
                    pass: value >= 4.5,
                    fix: suggest(c[bg], colour, 4.5),
                });
            });
        });
    });

    return out;
}

export default function ThemeEdit(props: Props) {
    const { scope, hasDraft, colours, status, pairings, radii, history } =
        props;
    const [values, setValues] = useState<Values>(props.values);
    const [mode, setMode] = useState<Mode>('light');
    const [files, setFiles] = useState<Record<string, File | null>>({});
    const [remove, setRemove] = useState<string[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const checks = useMemo(() => runChecks(values, status), [values, status]);
    const failures = checks.filter((c) => !c.pass);
    const dirty =
        JSON.stringify(values) !== JSON.stringify(props.values) ||
        Object.values(files).some(Boolean) ||
        remove.length > 0;

    const setColour = (m: Mode, key: string, value: string) =>
        setValues((v) => ({
            ...v,
            [m]: { ...v[m], [key]: value.toLowerCase() },
        }));

    const saveDraft = () => {
        setBusy(true);
        router.post(
            draft.save(scope).url,
            {
                light: values.light,
                dark: values.dark,
                fonts: values.fonts,
                radius: values.radius,
                remove,
                ...Object.fromEntries(
                    Object.entries(files).filter(([, f]) => f),
                ),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (e) => setErrors(e),
                onSuccess: () => {
                    setErrors({});
                    setFiles({});
                    setRemove([]);
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    const confirmThen = (message: string, action: () => void) => {
        if (window.confirm(message)) action();
    };

    const pairing = pairings.find((p) => p.key === values.fonts) ?? pairings[0];
    const c = values[mode];
    const previewStyle = {
        '--background': c.background,
        '--foreground': c.text,
        '--card': c.surface,
        '--card-foreground': c.text,
        '--primary': c.primary,
        '--primary-foreground': c.primary_foreground,
        '--muted-foreground': c.muted_text,
        '--border': c.border,
        '--ring': c.accent,
        '--brand-accent': c.accent,
        '--radius': RADIUS[values.radius],
        fontFamily: `'${pairing.ui}', system-ui, sans-serif`,
    } as CSSProperties;
    const displayFont = {
        fontFamily: `'${scope === 'admin' ? 'Gloock' : pairing.display}', Georgia, serif`,
    };

    return (
        <>
            <Head title="Theme" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-3xl">Theme</h1>
                        <p className="mt-1 max-w-2xl text-muted-foreground">
                            Save changes as a draft, check the preview, then
                            publish. Colours that are hard to read can’t be
                            saved. Auction status colours are fixed.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {hasDraft && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    confirmThen('Discard the draft?', () =>
                                        router.delete(
                                            draft.discard(scope).url,
                                            { preserveScroll: true },
                                        ),
                                    )
                                }
                            >
                                Discard draft
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            disabled={busy || failures.length > 0 || !dirty}
                            onClick={saveDraft}
                        >
                            Save draft
                        </Button>
                        <Button
                            disabled={!hasDraft || dirty || failures.length > 0}
                            onClick={() =>
                                confirmThen(
                                    `Publish this theme to the ${scope === 'admin' ? 'back office' : 'website'}?`,
                                    () =>
                                        router.post(
                                            publish(scope).url,
                                            {},
                                            { preserveScroll: true },
                                        ),
                                )
                            }
                        >
                            Publish
                        </Button>
                    </div>
                </div>

                <nav
                    aria-label="Theme area"
                    className="flex gap-1 border-b border-border"
                >
                    {(['website', 'admin'] as const).map((s) => (
                        <Link
                            key={s}
                            href={edit(s)}
                            className={cn(
                                '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
                                s === scope
                                    ? 'border-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                            aria-current={s === scope ? 'page' : undefined}
                        >
                            {s === 'website' ? 'Website' : 'Back office'}
                        </Link>
                    ))}
                </nav>

                {hasDraft && !dirty && (
                    <p className="rounded-md border border-brand-accent/40 bg-brand-accent-soft px-4 py-2 text-sm">
                        You’re editing an unpublished draft. The live{' '}
                        {scope === 'admin' ? 'back office' : 'website'} still
                        uses the published theme.
                    </p>
                )}
                {(errors.contrast || errors.file) && (
                    <p className="text-sm text-destructive">
                        {errors.contrast ?? errors.file}
                    </p>
                )}

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                    <div className="flex flex-col gap-6">
                        <section className="rounded-xl border border-border bg-card p-5">
                            <div className="flex items-center justify-between">
                                <h2 className="font-semibold">Colours</h2>
                                <div
                                    role="tablist"
                                    className="flex rounded-md border border-border p-0.5 text-sm"
                                >
                                    {(['light', 'dark'] as Mode[]).map((m) => (
                                        <button
                                            key={m}
                                            role="tab"
                                            aria-selected={mode === m}
                                            onClick={() => setMode(m)}
                                            className={cn(
                                                'rounded px-3 py-1',
                                                mode === m
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {m === 'light'
                                                ? 'Light mode'
                                                : 'Dark mode'}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                {Object.entries(colours).map(([key, label]) => {
                                    const id = `colour-${mode}-${key}`;
                                    const value = values[mode][key];
                                    const failing = failures.some(
                                        (f) =>
                                            f.mode === mode && f.field === key,
                                    );

                                    return (
                                        <div key={key} className="grid gap-1.5">
                                            <Label
                                                htmlFor={id}
                                                className={cn(
                                                    failing &&
                                                        'text-destructive',
                                                )}
                                            >
                                                {label}
                                            </Label>
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="color"
                                                    aria-label={`${label} colour picker`}
                                                    value={
                                                        isHex(value)
                                                            ? value
                                                            : '#000000'
                                                    }
                                                    onChange={(e) =>
                                                        setColour(
                                                            mode,
                                                            key,
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-9 w-11 cursor-pointer rounded border border-input bg-transparent"
                                                />
                                                <input
                                                    id={id}
                                                    value={value}
                                                    onChange={(e) =>
                                                        setColour(
                                                            mode,
                                                            key,
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={cn(
                                                        'h-9 w-28 rounded-md border bg-background px-2 font-mono text-sm',
                                                        isHex(value)
                                                            ? 'border-input'
                                                            : 'border-destructive',
                                                    )}
                                                    spellCheck={false}
                                                />
                                            </div>
                                            {errors[`${mode}.${key}`] && (
                                                <p className="text-xs text-destructive">
                                                    {errors[`${mode}.${key}`]}
                                                </p>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </section>

                        <section
                            className="rounded-xl border border-border bg-card p-5"
                            aria-live="polite"
                        >
                            <h2 className="flex items-center gap-2 font-semibold">
                                Readability check
                                {failures.length === 0 ? (
                                    <span className="inline-flex items-center gap-1 text-sm font-normal text-status-live">
                                        <Check
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        All {checks.length} pass
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1 text-sm font-normal text-destructive">
                                        <TriangleAlert
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {failures.length} to fix
                                    </span>
                                )}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                WCAG AA: 4.5:1 for text, 3:1 for buttons and
                                focus rings.
                            </p>
                            {failures.length > 0 && (
                                <ul className="mt-3 divide-y divide-border">
                                    {failures.map((f, i) => (
                                        <li
                                            key={i}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                                        >
                                            <span>
                                                <span className="text-muted-foreground">
                                                    {f.mode === 'light'
                                                        ? 'Light'
                                                        : 'Dark'}
                                                    :
                                                </span>{' '}
                                                {f.label}{' '}
                                                <span className="tabular text-destructive">
                                                    {f.value.toFixed(2)}:1
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    (needs {f.min}:1)
                                                </span>
                                            </span>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setColour(
                                                        f.mode,
                                                        f.field,
                                                        f.fix,
                                                    )
                                                }
                                            >
                                                <span
                                                    className="inline-block size-3 rounded-full border"
                                                    style={{
                                                        background: f.fix,
                                                    }}
                                                    aria-hidden="true"
                                                />
                                                Use {f.fix}
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="grid gap-4 rounded-xl border border-border bg-card p-5">
                            <h2 className="font-semibold">Type and shape</h2>
                            <div className="grid gap-1.5">
                                <Label htmlFor="fonts">
                                    {scope === 'admin'
                                        ? 'Interface font'
                                        : 'Font pairing'}
                                </Label>
                                <select
                                    id="fonts"
                                    value={values.fonts}
                                    onChange={(e) =>
                                        setValues((v) => ({
                                            ...v,
                                            fonts: e.target.value,
                                        }))
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {pairings.map((p) => (
                                        <option key={p.key} value={p.key}>
                                            {scope === 'admin'
                                                ? `${p.ui.replace(' Variable', '')}`
                                                : `${p.label}: ${p.display} with ${p.ui.replace(' Variable', '')}`}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <fieldset className="grid gap-1.5">
                                <legend className="text-sm font-medium">
                                    Corners
                                </legend>
                                <div className="flex gap-2">
                                    {radii.map((r) => (
                                        <label
                                            key={r}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-2 border px-3 py-2 text-sm capitalize',
                                                values.radius === r
                                                    ? 'border-primary'
                                                    : 'border-border',
                                            )}
                                            style={{ borderRadius: RADIUS[r] }}
                                        >
                                            <input
                                                type="radio"
                                                name="radius"
                                                value={r}
                                                checked={values.radius === r}
                                                onChange={() =>
                                                    setValues((v) => ({
                                                        ...v,
                                                        radius: r,
                                                    }))
                                                }
                                                className="accent-primary"
                                            />
                                            {r}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        </section>

                        <section className="grid gap-4 rounded-xl border border-border bg-card p-5">
                            <h2 className="font-semibold">Logos</h2>
                            <p className="-mt-2 text-sm text-muted-foreground">
                                SVG, PNG or WebP, up to 1 MB. SVGs are cleaned
                                of scripts. Favicons are resized automatically.
                            </p>
                            {(
                                [
                                    [
                                        'logo_light',
                                        'Logo for light backgrounds',
                                        values.logo_light_url,
                                    ],
                                    [
                                        'logo_dark',
                                        'Logo for dark backgrounds',
                                        values.logo_dark_url,
                                    ],
                                    [
                                        'favicon',
                                        'Favicon (square)',
                                        values.favicon_url,
                                    ],
                                ] as const
                            ).map(([kind, label, url]) => (
                                <div
                                    key={kind}
                                    className="flex flex-wrap items-center gap-3"
                                >
                                    <div
                                        className={cn(
                                            'flex h-12 w-24 items-center justify-center rounded border border-border',
                                            kind === 'logo_dark'
                                                ? 'bg-[#0e1d19]'
                                                : 'bg-white',
                                        )}
                                    >
                                        {url && !remove.includes(kind) ? (
                                            <img
                                                src={url}
                                                alt=""
                                                className="max-h-10 max-w-20 object-contain"
                                            />
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                Default
                                            </span>
                                        )}
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor={kind}>{label}</Label>
                                        <input
                                            id={kind}
                                            type="file"
                                            accept=".svg,.png,.webp"
                                            className="text-sm"
                                            onChange={(e) =>
                                                setFiles((f) => ({
                                                    ...f,
                                                    [kind]:
                                                        e.target.files?.[0] ??
                                                        null,
                                                }))
                                            }
                                        />
                                        {errors[kind] && (
                                            <p className="text-xs text-destructive">
                                                {errors[kind]}
                                            </p>
                                        )}
                                    </div>
                                    {url && (
                                        <label className="flex items-center gap-1.5 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={remove.includes(kind)}
                                                onChange={(e) =>
                                                    setRemove((r) =>
                                                        e.target.checked
                                                            ? [...r, kind]
                                                            : r.filter(
                                                                  (k) =>
                                                                      k !==
                                                                      kind,
                                                              ),
                                                    )
                                                }
                                            />
                                            Use default
                                        </label>
                                    )}
                                </div>
                            ))}
                        </section>
                    </div>

                    <div className="flex flex-col gap-6">
                        <section
                            aria-label="Preview"
                            className="sticky top-4 overflow-hidden rounded-xl border border-border"
                        >
                            <p className="border-b border-border bg-card px-4 py-2 text-xs text-muted-foreground">
                                Preview:{' '}
                                {scope === 'admin' ? 'back office' : 'website'},{' '}
                                {mode} mode
                            </p>
                            <div
                                style={previewStyle}
                                className="bg-background text-foreground"
                            >
                                {scope === 'website' ? (
                                    <>
                                        <div className="flex items-center justify-between border-b border-border bg-card px-5 py-3">
                                            <span
                                                style={displayFont}
                                                className="text-lg"
                                            >
                                                Thabekhulu
                                            </span>
                                            <span
                                                className="bg-primary px-3 py-1.5 text-sm font-semibold text-primary-foreground"
                                                style={{
                                                    borderRadius:
                                                        'var(--radius)',
                                                }}
                                            >
                                                Register to bid
                                            </span>
                                        </div>
                                        <div
                                            className="px-5 py-8"
                                            style={{
                                                background:
                                                    values.light.primary,
                                                color: values.light
                                                    .primary_foreground,
                                            }}
                                        >
                                            <p
                                                style={displayFont}
                                                className="text-3xl leading-tight"
                                            >
                                                Bid on South African property,
                                                from anywhere.
                                            </p>
                                        </div>
                                        <div className="grid gap-4 p-5 sm:grid-cols-2">
                                            <article
                                                className="overflow-hidden border border-border bg-card"
                                                style={{
                                                    borderRadius:
                                                        'var(--radius)',
                                                }}
                                            >
                                                <div
                                                    className="relative h-24"
                                                    style={{
                                                        background: `linear-gradient(160deg, ${c.border}, ${c.muted_text})`,
                                                    }}
                                                >
                                                    <span
                                                        className="absolute top-2 left-2 rounded-full border bg-card px-2 py-0.5 text-xs font-semibold"
                                                        style={{
                                                            color: status[mode][
                                                                'Closing soon'
                                                            ],
                                                        }}
                                                    >
                                                        ● Closing in 5h
                                                    </span>
                                                </div>
                                                <div className="p-3">
                                                    <p className="text-xs text-muted-foreground">
                                                        Land · Polokwane
                                                    </p>
                                                    <p
                                                        style={displayFont}
                                                        className="text-lg"
                                                    >
                                                        10 ha farm portion
                                                    </p>
                                                    <p className="tabular font-semibold">
                                                        R 2 450 000
                                                    </p>
                                                    <span
                                                        className="mt-2 block bg-primary py-1.5 text-center text-sm font-semibold text-primary-foreground"
                                                        style={{
                                                            borderRadius:
                                                                'var(--radius)',
                                                        }}
                                                    >
                                                        Place a bid
                                                    </span>
                                                </div>
                                            </article>
                                            <div className="grid content-start gap-2 text-sm">
                                                <label className="grid gap-1">
                                                    Email address
                                                    <span
                                                        className="h-9 border bg-card px-2 leading-9 text-muted-foreground outline-2 outline-offset-2"
                                                        style={{
                                                            borderColor:
                                                                c.border,
                                                            borderRadius:
                                                                'var(--radius)',
                                                            outlineColor:
                                                                c.accent,
                                                            outlineStyle:
                                                                'solid',
                                                        }}
                                                    >
                                                        focus ring
                                                    </span>
                                                </label>
                                                <p className="text-muted-foreground">
                                                    Secondary text looks like
                                                    this.
                                                </p>
                                                <span
                                                    className="w-fit px-2 py-0.5 text-xs font-semibold"
                                                    style={{
                                                        color: c.accent,
                                                        border: `1px solid ${c.accent}`,
                                                        borderRadius: '999px',
                                                    }}
                                                >
                                                    Premium
                                                </span>
                                                <span
                                                    className="w-fit rounded-full border px-2 py-0.5 text-xs font-semibold"
                                                    style={{
                                                        color: status[mode][
                                                            'Live'
                                                        ],
                                                    }}
                                                >
                                                    ● Live
                                                </span>
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    <div className="grid grid-cols-[9rem_1fr]">
                                        <div className="min-h-64 border-r border-border bg-card p-3 text-sm">
                                            <p
                                                style={displayFont}
                                                className="mb-3 text-base"
                                            >
                                                Thabekhulu
                                            </p>
                                            <p
                                                className="rounded bg-primary px-2 py-1 text-primary-foreground"
                                                style={{
                                                    borderRadius:
                                                        'var(--radius)',
                                                }}
                                            >
                                                Dashboard
                                            </p>
                                            <p className="px-2 py-1 text-muted-foreground">
                                                Staff
                                            </p>
                                            <p className="px-2 py-1 text-muted-foreground">
                                                Audit log
                                            </p>
                                        </div>
                                        <div className="p-4 text-sm">
                                            <p
                                                style={displayFont}
                                                className="text-2xl"
                                            >
                                                Staff
                                            </p>
                                            <p className="text-muted-foreground">
                                                Back-office accounts.
                                            </p>
                                            <div
                                                className="mt-3 border border-border bg-card p-3"
                                                style={{
                                                    borderRadius:
                                                        'var(--radius)',
                                                }}
                                            >
                                                <div className="flex items-center justify-between">
                                                    <span>Nomsa Khumalo</span>
                                                    <span
                                                        className="font-medium"
                                                        style={{
                                                            color: status[mode][
                                                                'Live'
                                                            ],
                                                        }}
                                                    >
                                                        2FA on
                                                    </span>
                                                </div>
                                            </div>
                                            <span
                                                className="mt-3 inline-block bg-primary px-3 py-1.5 font-semibold text-primary-foreground"
                                                style={{
                                                    borderRadius:
                                                        'var(--radius)',
                                                }}
                                            >
                                                Send invitation
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="rounded-xl border border-border bg-card p-5">
                            <div className="flex items-center justify-between">
                                <h2 className="font-semibold">
                                    Published versions
                                </h2>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        confirmThen(
                                            'Reset to the Thabekhulu brand defaults and publish now?',
                                            () =>
                                                router.post(
                                                    reset(scope).url,
                                                    {},
                                                    { preserveScroll: true },
                                                ),
                                        )
                                    }
                                >
                                    Reset to brand defaults
                                </Button>
                            </div>
                            <ul className="mt-3 divide-y divide-border text-sm">
                                {history.map((v) => (
                                    <li
                                        key={v.id}
                                        className="flex items-center justify-between gap-3 py-2"
                                    >
                                        <span>
                                            {formatDateTime(v.publishedAt)} by{' '}
                                            {v.publishedBy ?? 'unknown'}
                                            {v.note && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {v.note}
                                                </span>
                                            )}
                                        </span>
                                        {v.live ? (
                                            <span className="text-status-live">
                                                Live
                                            </span>
                                        ) : (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    confirmThen(
                                                        'Restore this version and publish it now?',
                                                        () =>
                                                            router.post(
                                                                restore({
                                                                    scope,
                                                                    version:
                                                                        v.id,
                                                                }).url,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            ),
                                                    )
                                                }
                                            >
                                                Restore
                                            </Button>
                                        )}
                                    </li>
                                ))}
                                {history.length === 0 && (
                                    <li className="py-2 text-muted-foreground">
                                        Nothing published yet. The site uses the
                                        brand defaults.
                                    </li>
                                )}
                            </ul>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

ThemeEdit.layout = {
    breadcrumbs: [{ title: 'Theme', href: edit('website') }],
};
