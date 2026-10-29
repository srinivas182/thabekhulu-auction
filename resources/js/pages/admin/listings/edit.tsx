import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Download,
    Lock,
    TriangleAlert,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { rand } from '@/lib/money';
import { cn } from '@/lib/utils';
import {
    approve,
    destroy,
    index,
    reopen,
    requestChanges,
    submit,
    update,
    withdraw,
    bidders,
    auction as auctionMonitor,
    relist,
} from '@/routes/admin/listings';
import compliance from '@/routes/admin/listings/compliance';
import documentsRoutes from '@/routes/admin/listings/documents';
import media from '@/routes/admin/listings/media';
import { statusTone } from './index';

type Option = { value: string; label: string };
type MediaItem = { id: number; url: string; alt: string; name: string };
type Field = {
    id: number;
    label: string;
    type: string;
    unit: string | null;
    options: string[];
    required: boolean;
    value: string | null;
};
type DocType = {
    id: number;
    name: string;
    mandatory: boolean;
    visibility: string;
    formats: string[];
    maxSizeMb: number;
    files: {
        id: number;
        name: string;
        size: number;
        uploadedAt: string | null;
    }[];
};
type Listing = {
    ulid: string;
    title: string;
    slug: string;
    category: { name: string; slug: string; notice: boolean };
    status: string;
    statusLabel: string;
    reviewNote: string | null;
    cityId: number | null;
    suburb: string | null;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    summary: string | null;
    description: string | null;
    videoUrl: string | null;
    dealType: string;
    sellerNetPrice: number | null;
    sellerPrice: number | null;
    renovationCost: number;
    feePercent: number;
    minimumPrice: number | null;
    depositAmount: number;
    depositTerms: string;
    auctionType: string;
    biddingTerms: string | null;
    startsAt: string | null;
    endsAt: string | null;
    featured: boolean;
    seoTitle: string | null;
    seoDescription: string | null;
    updatedAt: string | null;
    updatedBy: string | null;
};
type Props = {
    listing: Listing;
    fields: Field[];
    photos: MediaItem[];
    floorPlans: MediaItem[];
    documentTypes: DocType[];
    compliance: {
        item: string;
        label: string;
        signedBy: string | null;
        signedAt: string | null;
        note: string | null;
        attachments: { id: number; name: string; url: string }[];
    }[];
    pricing: { floor: number | null };
    readiness: string[];
    history: {
        from: string | null;
        to: string;
        by: string;
        note: string | null;
        at: string;
    }[];
    cities: { id: number; name: string }[];
    options: {
        dealTypes: Option[];
        auctionTypes: Option[];
        depositTerms: Option[];
        durationDays: number;
    };
    can: {
        edit: boolean;
        editPricing: boolean;
        submit: boolean;
        publish: boolean;
        signOff: boolean;
        delete: boolean;
    };
};

const TABS = [
    'Details',
    'Key facts',
    'Photos and plans',
    'Documents',
    'Pricing',
    'Auction',
    'Compliance',
    'Search',
    'History',
] as const;
const select =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm disabled:opacity-60';
const opts = { preserveScroll: true };
const num = (v: string) => (v === '' ? null : Number(v.replace(/[^\d.]/g, '')));

function Section({
    title,
    children,
    description,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section
            className="grid gap-4 rounded-xl border border-border bg-card p-5"
            aria-label={title}
        >
            <div>
                <h2 className="font-semibold">{title}</h2>
                {description && (
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

function Internal() {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-brand-accent-soft px-2 py-0.5 text-xs font-semibold text-brand-accent">
            <Lock className="size-3" aria-hidden="true" />
            Internal
        </span>
    );
}

export default function ListingEdit(props: Props) {
    const { listing: l, can } = props;
    const [tab, setTab] = useState<(typeof TABS)[number]>('Details');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const save = (section: string, data: Record<string, unknown>) =>
        router.put(update({ listing: l.ulid, section }).url, data as never, {
            ...opts,
            onError: setErrors,
            onSuccess: () => setErrors({}),
        });
    const live = l.status === 'live';

    const [d, setD] = useState({
        title: l.title,
        city_id: l.cityId ?? '',
        suburb: l.suburb ?? '',
        address: l.address ?? '',
        latitude: l.latitude ?? '',
        longitude: l.longitude ?? '',
        summary: l.summary ?? '',
        description: l.description ?? '',
        video_url: l.videoUrl ?? '',
    });
    const [facts, setFacts] = useState<Record<number, string>>(
        Object.fromEntries(props.fields.map((f) => [f.id, f.value ?? ''])),
    );
    const [p, setP] = useState({
        deal_type: l.dealType,
        seller_net_price: l.sellerNetPrice,
        seller_price: l.sellerPrice,
        renovation_cost: l.renovationCost,
        fee_percent: l.feePercent,
        minimum_price: l.minimumPrice,
        deposit_amount: l.depositAmount,
        deposit_terms: l.depositTerms,
        reason: '',
    });
    const [a, setA] = useState({
        starts_at: l.startsAt ?? '',
        ends_at: l.endsAt ?? '',
        auction_type: l.auctionType,
        bidding_terms: l.biddingTerms ?? '',
        reason: '',
    });
    const [s, setS] = useState({
        seo_title: l.seoTitle ?? '',
        seo_description: l.seoDescription ?? '',
        is_featured: l.featured,
    });

    // Same rules as app/Domains/Listings/Support/Pricing.php, for instant feedback.
    const fee = Number(p.fee_percent) * 100;
    const floor =
        p.deal_type === 'commission'
            ? p.seller_net_price && fee < 10000
                ? Math.ceil((p.seller_net_price * 10000) / (10000 - fee))
                : null
            : p.seller_price
              ? p.seller_price + (p.renovation_cost || 0)
              : null;
    const payout = (bid: number) =>
        p.deal_type === 'commission'
            ? {
                  fee: Math.round((bid * fee) / 10000),
                  seller: bid - Math.round((bid * fee) / 10000),
              }
            : { fee: bid - (p.seller_price ?? 0), seller: p.seller_price ?? 0 };
    const depositPercent = p.minimum_price
        ? Math.round((p.deposit_amount / p.minimum_price) * 1000) / 10
        : null;
    const belowFloor =
        floor !== null && p.minimum_price !== null && p.minimum_price < floor;

    // Branded dialogs instead of the browser's prompt box.
    type Dlg = {
        title: string;
        label: string;
        hint: string;
        confirm: string;
        danger?: boolean;
        files?: boolean;
        run: (text: string, files: File[]) => void;
    };
    const [dlg, setDlg] = useState<Dlg | null>(null);
    const [dlgText, setDlgText] = useState('');
    const [dlgFiles, setDlgFiles] = useState<File[]>([]);
    const dialogs: Record<string, Omit<Dlg, 'run'>> = {
        'What needs to change?': {
            title: 'Request changes',
            label: 'What needs to change?',
            hint: 'The listing goes back to its author with this note.',
            confirm: 'Send back',
        },
        'Reason for withdrawing this listing': {
            title: 'Withdraw this listing',
            label: 'Reason',
            hint: 'The listing is taken off the website. The reason is recorded.',
            confirm: 'Withdraw listing',
            danger: true,
        },
        'Why is this sign-off being withdrawn?': {
            title: 'Withdraw sign-off',
            label: 'Reason',
            hint: 'Deposits close again until this check is signed off. Its attachments are kept on record.',
            confirm: 'Withdraw sign-off',
            danger: true,
        },
        'What did you check? (recorded with your sign-off)': {
            title: 'Sign off this check',
            label: 'What did you check?',
            hint: 'Recorded with your name and the time. Attach the supporting documents, such as the seller’s mandate, title deed, approvals or escrow agreement.',
            confirm: 'Sign off',
            files: true,
        },
    };
    const prompted = (
        message: string,
        action: (text: string, files: File[]) => void,
    ) => {
        setDlgText('');
        setDlgFiles([]);
        setDlg({
            ...(dialogs[message] ?? {
                title: message,
                label: 'Details',
                hint: '',
                confirm: 'Continue',
            }),
            run: action,
        });
    };

    const upload = (
        url: string,
        files: FileList | null,
        field: 'files' | 'file',
    ) => {
        if (!files || files.length === 0) return;
        const data =
            field === 'files'
                ? { files: Array.from(files) }
                : { file: files[0] };
        router.post(url, data, {
            ...opts,
            forceFormData: true,
            onError: setErrors,
            onSuccess: () => setErrors({}),
        });
    };

    const move = (
        list: MediaItem[],
        collection: string,
        i: number,
        dir: -1 | 1,
    ) => {
        const ids = list.map((m) => m.id);
        [ids[i], ids[i + dir]] = [ids[i + dir], ids[i]];
        router.put(
            media.order({ listing: l.ulid, collection }).url,
            { ids },
            opts,
        );
    };

    const mediaGrid = (
        list: MediaItem[],
        collection: string,
        withAlt: boolean,
    ) => (
        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {list.map((m, i) => (
                <li
                    key={m.id}
                    className="overflow-hidden rounded-lg border border-border"
                >
                    <div className="relative aspect-[3/2] bg-muted">
                        <img
                            src={m.url}
                            alt={m.alt || ''}
                            className="size-full object-cover"
                        />
                        {i === 0 && collection === 'photos' && (
                            <span className="absolute top-2 left-2 rounded-full bg-card px-2 py-0.5 text-xs font-semibold">
                                Cover
                            </span>
                        )}
                    </div>
                    <div className="grid gap-2 p-3">
                        {withAlt && (
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`alt-${m.id}`}
                                    className={cn(!m.alt && 'text-destructive')}
                                >
                                    Describe this photo
                                </Label>
                                <Input
                                    id={`alt-${m.id}`}
                                    defaultValue={m.alt}
                                    disabled={!can.edit}
                                    placeholder="e.g. Aerial view of the river frontage"
                                    onBlur={(e) =>
                                        e.target.value !== m.alt &&
                                        e.target.value.trim() &&
                                        router.put(
                                            media.update({
                                                listing: l.ulid,
                                                media: m.id,
                                            }).url,
                                            { alt: e.target.value },
                                            opts,
                                        )
                                    }
                                />
                            </div>
                        )}
                        <div className="flex gap-1">
                            <Button
                                size="sm"
                                variant="ghost"
                                disabled={!can.edit || i === 0}
                                aria-label="Move earlier"
                                onClick={() => move(list, collection, i, -1)}
                            >
                                <ArrowLeft />
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                disabled={!can.edit || i === list.length - 1}
                                aria-label="Move later"
                                onClick={() => move(list, collection, i, 1)}
                            >
                                <ArrowRight />
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                disabled={!can.edit}
                                aria-label={`Remove ${m.name}`}
                                onClick={() =>
                                    window.confirm('Remove this file?') &&
                                    router.delete(
                                        media.destroy({
                                            listing: l.ulid,
                                            media: m.id,
                                        }).url,
                                        opts,
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>
                </li>
            ))}
        </ul>
    );

    return (
        <>
            <Head title={l.title} />
            <Dialog
                open={dlg !== null}
                onOpenChange={(open) => !open && setDlg(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display text-2xl">
                            {dlg?.title}
                        </DialogTitle>
                        {dlg?.hint && (
                            <DialogDescription>{dlg.hint}</DialogDescription>
                        )}
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="dlg-text">{dlg?.label}</Label>
                        <textarea
                            id="dlg-text"
                            rows={4}
                            value={dlgText}
                            onChange={(e) => setDlgText(e.target.value)}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    {dlg?.files && (
                        <div className="grid gap-2">
                            <Label htmlFor="dlg-files">
                                Attachments (optional, up to 10)
                            </Label>
                            <input
                                id="dlg-files"
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                                onChange={(e) =>
                                    setDlgFiles(
                                        Array.from(e.target.files ?? []).slice(
                                            0,
                                            10,
                                        ),
                                    )
                                }
                                className="text-sm"
                            />
                            {dlgFiles.length > 0 && (
                                <ul className="grid gap-0.5 text-xs text-muted-foreground">
                                    {dlgFiles.map((f) => (
                                        <li key={f.name}>
                                            {f.name} (
                                            {Math.max(
                                                1,
                                                Math.round(f.size / 1024),
                                            )}{' '}
                                            KB)
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <p className="text-xs text-muted-foreground">
                                PDF, images, Word or Excel; up to 10 MB each.
                                Stored encrypted.
                            </p>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDlg(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant={dlg?.danger ? 'destructive' : 'default'}
                            disabled={dlgText.trim() === ''}
                            onClick={() => {
                                dlg?.run(dlgText.trim(), dlgFiles);
                                setDlg(null);
                            }}
                        >
                            {dlg?.confirm}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span
                                className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusTone[l.status] ?? ''}`}
                            >
                                {l.statusLabel}
                            </span>
                            {l.category.name}
                            {l.updatedAt && (
                                <span>
                                    · last saved {formatDateTime(l.updatedAt)}
                                    {l.updatedBy ? ` by ${l.updatedBy}` : ''}
                                </span>
                            )}
                        </p>
                        <h1 className="mt-2 font-display text-3xl">
                            {l.title}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {['draft', 'changes_requested'].includes(l.status) &&
                            can.submit && (
                                <Button
                                    disabled={props.readiness.length > 0}
                                    onClick={() =>
                                        router.post(
                                            submit(l.ulid).url,
                                            {},
                                            { ...opts, onError: setErrors },
                                        )
                                    }
                                >
                                    Submit for review
                                </Button>
                            )}
                        {l.status === 'in_review' && can.publish && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        prompted(
                                            'What needs to change?',
                                            (note) =>
                                                router.post(
                                                    requestChanges(l.ulid).url,
                                                    { note },
                                                    opts,
                                                ),
                                        )
                                    }
                                >
                                    Request changes
                                </Button>
                                <Button
                                    disabled={props.readiness.length > 0}
                                    onClick={() =>
                                        window.confirm(
                                            'Approve and publish this listing?',
                                        ) &&
                                        router.post(
                                            approve(l.ulid).url,
                                            {},
                                            { ...opts, onError: setErrors },
                                        )
                                    }
                                >
                                    Approve and publish
                                </Button>
                            </>
                        )}
                        {['in_review', 'scheduled', 'live'].includes(
                            l.status,
                        ) &&
                            can.publish && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        prompted(
                                            'Reason for withdrawing this listing',
                                            (reason) =>
                                                router.post(
                                                    withdraw(l.ulid).url,
                                                    { reason },
                                                    opts,
                                                ),
                                        )
                                    }
                                >
                                    Withdraw
                                </Button>
                            )}
                        {l.status === 'withdrawn' && can.edit && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(reopen(l.ulid).url, {}, opts)
                                }
                            >
                                Reopen as draft
                            </Button>
                        )}
                        {(
                            (usePage().props.auth as { permissions?: string[] })
                                .permissions ?? []
                        ).includes('bidders.approve') &&
                            ['scheduled', 'live'].includes(l.status) && (
                                <Link
                                    href={bidders(l.ulid)}
                                    className="inline-flex h-9 items-center rounded-md border border-input px-3 text-sm font-medium"
                                >
                                    Bidders
                                </Link>
                            )}
                        {(
                            (usePage().props.auth as { permissions?: string[] })
                                .permissions ?? []
                        ).includes('auctions.monitor') &&
                            l.status === 'live' && (
                                <Link
                                    href={auctionMonitor(l.ulid)}
                                    className="inline-flex h-9 items-center rounded-md border border-input px-3 text-sm font-medium"
                                >
                                    Auction
                                </Link>
                            )}
                        {(l.status === 'no_bids' ||
                            l.status === 'not_sold') && (
                            <button
                                type="button"
                                onClick={() =>
                                    window.confirm(
                                        'Relist this listing? Old bids are voided, earlier applicants must apply again, and it returns to Draft for new dates.',
                                    ) &&
                                    router.post(
                                        relist(l.ulid).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                className="inline-flex h-9 items-center rounded-md border border-input px-3 text-sm font-medium"
                            >
                                Relist
                            </button>
                        )}
                        {can.delete && (
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    window.confirm(
                                        'Delete this draft permanently?',
                                    ) && router.delete(destroy(l.ulid).url)
                                }
                            >
                                Delete draft
                            </Button>
                        )}
                    </div>
                </div>

                {errors.listing && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive px-4 py-3 text-sm text-destructive"
                    >
                        {errors.listing}
                    </p>
                )}
                {l.status === 'changes_requested' && l.reviewNote && (
                    <p className="rounded-md bg-brand-accent-soft px-4 py-3 text-sm">
                        <strong>Changes requested:</strong> {l.reviewNote}
                    </p>
                )}
                {l.category.notice && (
                    <p className="rounded-md bg-brand-accent-soft px-4 py-3 text-sm">
                        This listing will show: “This listing is subject to
                        verification. A winning bid does not by itself transfer
                        a mining right or guarantee regulatory approval.”
                    </p>
                )}
                {props.readiness.length > 0 &&
                    ['draft', 'changes_requested', 'in_review'].includes(
                        l.status,
                    ) && (
                        <section
                            aria-labelledby="ready-h"
                            className="rounded-xl border border-status-closing/50 bg-card p-4"
                        >
                            <h2
                                id="ready-h"
                                className="flex items-center gap-2 text-sm font-semibold"
                            >
                                <TriangleAlert
                                    className="size-4 text-status-closing"
                                    aria-hidden="true"
                                />
                                Before this listing can be submitted
                            </h2>
                            <ul className="mt-2 list-disc pl-5 text-sm text-muted-foreground">
                                {props.readiness.map((r) => (
                                    <li key={r}>{r}</li>
                                ))}
                            </ul>
                        </section>
                    )}

                <div
                    role="tablist"
                    aria-label="Listing sections"
                    className="flex flex-wrap gap-x-6 gap-y-1 border-b border-border text-sm"
                >
                    {TABS.map((t) => (
                        <button
                            key={t}
                            role="tab"
                            aria-selected={tab === t}
                            onClick={() => setTab(t)}
                            className={cn(
                                '-mb-px border-b-2 py-2.5',
                                tab === t
                                    ? 'border-primary font-semibold text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t}
                        </button>
                    ))}
                </div>

                {tab === 'Details' && (
                    <Section
                        title="Details"
                        description="The summary is the first thing buyers and search engines read: say what, where, size, minimum price and closing date."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-1.5 md:col-span-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    disabled={!can.edit}
                                    value={d.title}
                                    onChange={(e) =>
                                        setD({ ...d, title: e.target.value })
                                    }
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="city">City</Label>
                                <select
                                    id="city"
                                    className={select}
                                    disabled={!can.edit}
                                    value={d.city_id}
                                    onChange={(e) =>
                                        setD({ ...d, city_id: e.target.value })
                                    }
                                >
                                    <option value="">Choose a city</option>
                                    {props.cities.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.city_id} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="suburb">Suburb or area</Label>
                                <Input
                                    id="suburb"
                                    disabled={!can.edit}
                                    value={d.suburb}
                                    onChange={(e) =>
                                        setD({ ...d, suburb: e.target.value })
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5 md:col-span-2">
                                <Label
                                    htmlFor="address"
                                    className="flex items-center gap-2"
                                >
                                    Street address <Internal />
                                </Label>
                                <Input
                                    id="address"
                                    disabled={!can.edit}
                                    value={d.address}
                                    onChange={(e) =>
                                        setD({ ...d, address: e.target.value })
                                    }
                                />
                                <p className="text-sm text-muted-foreground">
                                    Never shown publicly. The website shows the
                                    suburb only.
                                </p>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="lat">Map latitude</Label>
                                <Input
                                    id="lat"
                                    disabled={!can.edit}
                                    inputMode="decimal"
                                    value={d.latitude}
                                    onChange={(e) =>
                                        setD({ ...d, latitude: e.target.value })
                                    }
                                    placeholder="-23.904"
                                />
                                <InputError message={errors.latitude} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="lng">Map longitude</Label>
                                <Input
                                    id="lng"
                                    disabled={!can.edit}
                                    inputMode="decimal"
                                    value={d.longitude}
                                    onChange={(e) =>
                                        setD({
                                            ...d,
                                            longitude: e.target.value,
                                        })
                                    }
                                    placeholder="29.468"
                                />
                                <InputError message={errors.longitude} />
                            </div>
                            <div className="grid gap-1.5 md:col-span-2">
                                <Label htmlFor="summary">Summary</Label>
                                <textarea
                                    id="summary"
                                    disabled={!can.edit}
                                    maxLength={600}
                                    value={d.summary}
                                    onChange={(e) =>
                                        setD({ ...d, summary: e.target.value })
                                    }
                                    className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                />
                                <p className="tabular text-right text-xs text-muted-foreground">
                                    {d.summary.length}/600
                                </p>
                            </div>
                            <div className="grid gap-1.5 md:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                {can.edit ? (
                                    <RichTextEditor
                                        id="description"
                                        label="Description"
                                        value={d.description}
                                        onChange={(html) =>
                                            setD({ ...d, description: html })
                                        }
                                    />
                                ) : (
                                    <div
                                        className="rounded-md border border-border p-3 text-sm"
                                        dangerouslySetInnerHTML={{
                                            __html: d.description,
                                        }}
                                    />
                                )}
                            </div>
                            <div className="grid gap-1.5 md:col-span-2">
                                <Label htmlFor="video">
                                    Video tour link (YouTube or Vimeo)
                                </Label>
                                <Input
                                    id="video"
                                    disabled={!can.edit}
                                    value={d.video_url}
                                    onChange={(e) =>
                                        setD({
                                            ...d,
                                            video_url: e.target.value,
                                        })
                                    }
                                    placeholder="https://www.youtube.com/watch?v=…"
                                />
                                <InputError message={errors.video_url} />
                            </div>
                        </div>
                        {can.edit && (
                            <div>
                                <Button onClick={() => save('details', d)}>
                                    Save details
                                </Button>
                            </div>
                        )}
                    </Section>
                )}

                {tab === 'Key facts' && (
                    <Section
                        title="Key facts"
                        description={`Fields set for ${l.category.name}. Required ones must be filled before submission.`}
                    >
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {props.fields.map((f) => (
                                <div key={f.id} className="grid gap-1.5">
                                    <Label htmlFor={`fact-${f.id}`}>
                                        {f.label}
                                        {f.unit ? ` (${f.unit})` : ''}
                                        {f.required && (
                                            <span className="text-destructive">
                                                {' '}
                                                *
                                            </span>
                                        )}
                                    </Label>
                                    {f.type === 'select' ||
                                    f.type === 'boolean' ? (
                                        <select
                                            id={`fact-${f.id}`}
                                            className={select}
                                            disabled={!can.edit}
                                            value={facts[f.id] ?? ''}
                                            onChange={(e) =>
                                                setFacts({
                                                    ...facts,
                                                    [f.id]: e.target.value,
                                                })
                                            }
                                        >
                                            <option value="">Not set</option>
                                            {(f.type === 'boolean'
                                                ? ['yes', 'no']
                                                : f.options
                                            ).map((o) => (
                                                <option key={o} value={o}>
                                                    {f.type === 'boolean'
                                                        ? o === 'yes'
                                                            ? 'Yes'
                                                            : 'No'
                                                        : o}
                                                </option>
                                            ))}
                                        </select>
                                    ) : (
                                        <Input
                                            id={`fact-${f.id}`}
                                            disabled={!can.edit}
                                            type={
                                                f.type === 'date'
                                                    ? 'date'
                                                    : 'text'
                                            }
                                            inputMode={
                                                f.type === 'number'
                                                    ? 'decimal'
                                                    : undefined
                                            }
                                            value={facts[f.id] ?? ''}
                                            onChange={(e) =>
                                                setFacts({
                                                    ...facts,
                                                    [f.id]: e.target.value,
                                                })
                                            }
                                        />
                                    )}
                                    <InputError
                                        message={errors[`facts.${f.id}`]}
                                    />
                                </div>
                            ))}
                        </div>
                        {can.edit && (
                            <div>
                                <Button
                                    onClick={() => save('facts', { facts })}
                                >
                                    Save key facts
                                </Button>
                            </div>
                        )}
                    </Section>
                )}

                {tab === 'Photos and plans' && (
                    <>
                        <Section
                            title={`Photos (${props.photos.length})`}
                            description="At least 3. The first photo is the cover. Landscape, at least 2400 px wide, JPG, PNG or WebP up to 15 MB."
                        >
                            {can.edit && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="photos-upload">
                                        Add photos
                                    </Label>
                                    <input
                                        id="photos-upload"
                                        type="file"
                                        multiple
                                        accept=".jpg,.jpeg,.png,.webp"
                                        onChange={(e) =>
                                            upload(
                                                media.store({
                                                    listing: l.ulid,
                                                    collection: 'photos',
                                                }).url,
                                                e.target.files,
                                                'files',
                                            )
                                        }
                                        className="text-sm"
                                    />
                                    <InputError
                                        message={
                                            errors['files.0'] ?? errors.files
                                        }
                                    />
                                </div>
                            )}
                            {mediaGrid(props.photos, 'photos', true)}
                        </Section>
                        <Section
                            title={`Floor plans and site maps (${props.floorPlans.length})`}
                        >
                            {can.edit && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="plans-upload">
                                        Add plans
                                    </Label>
                                    <input
                                        id="plans-upload"
                                        type="file"
                                        multiple
                                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                                        onChange={(e) =>
                                            upload(
                                                media.store({
                                                    listing: l.ulid,
                                                    collection: 'floor_plans',
                                                }).url,
                                                e.target.files,
                                                'files',
                                            )
                                        }
                                        className="text-sm"
                                    />
                                </div>
                            )}
                            {mediaGrid(props.floorPlans, 'floor_plans', false)}
                        </Section>
                    </>
                )}

                {tab === 'Documents' && (
                    <Section
                        title="Documents"
                        description="Stored privately. Who can open each type is set per category; nothing here is ever public."
                    >
                        <ul className="grid gap-3">
                            {props.documentTypes.map((t) => (
                                <li
                                    key={t.id}
                                    className="rounded-lg border border-border p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-medium">
                                            {t.name}
                                            {t.mandatory && (
                                                <span className="ml-2 text-xs font-semibold text-destructive">
                                                    Mandatory
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {t.visibility} ·{' '}
                                            {t.formats.join(', ').toUpperCase()}{' '}
                                            · up to {t.maxSizeMb} MB
                                        </p>
                                    </div>
                                    <ul className="mt-2 grid gap-1 text-sm">
                                        {t.files.map((f) => (
                                            <li
                                                key={f.id}
                                                className="flex items-center justify-between gap-2"
                                            >
                                                <a
                                                    href={
                                                        documentsRoutes.download(
                                                            {
                                                                listing: l.ulid,
                                                                document: f.id,
                                                            },
                                                        ).url
                                                    }
                                                    className="inline-flex items-center gap-1.5 underline-offset-4 hover:underline"
                                                >
                                                    <Download
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    {f.name}
                                                </a>
                                                {can.edit && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        aria-label={`Remove ${f.name}`}
                                                        onClick={() =>
                                                            window.confirm(
                                                                'Remove this document?',
                                                            ) &&
                                                            router.delete(
                                                                documentsRoutes.destroy(
                                                                    {
                                                                        listing:
                                                                            l.ulid,
                                                                        document:
                                                                            f.id,
                                                                    },
                                                                ).url,
                                                                opts,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                    {can.edit && (
                                        <div className="mt-2">
                                            <Label
                                                htmlFor={`doc-${t.id}`}
                                                className="sr-only"
                                            >
                                                Upload {t.name}
                                            </Label>
                                            <input
                                                id={`doc-${t.id}`}
                                                type="file"
                                                accept={t.formats
                                                    .map((x) => `.${x}`)
                                                    .join(',')}
                                                onChange={(e) =>
                                                    upload(
                                                        documentsRoutes.store({
                                                            listing: l.ulid,
                                                            type: t.id,
                                                        }).url,
                                                        e.target.files,
                                                        'file',
                                                    )
                                                }
                                                className="text-sm"
                                            />
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                        <InputError message={errors.file} />
                    </Section>
                )}

                {tab === 'Pricing' && (
                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
                        <div className="grid gap-5">
                            <Section title="Deal type">
                                <div className="grid gap-3 md:grid-cols-2">
                                    {props.options.dealTypes.map((t) => (
                                        <label
                                            key={t.value}
                                            className={cn(
                                                'flex gap-3 rounded-lg border p-4',
                                                p.deal_type === t.value
                                                    ? 'border-2 border-primary'
                                                    : 'border-input',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="deal"
                                                className="mt-1 size-4 accent-primary"
                                                disabled={!can.editPricing}
                                                checked={
                                                    p.deal_type === t.value
                                                }
                                                onChange={() =>
                                                    setP({
                                                        ...p,
                                                        deal_type: t.value,
                                                    })
                                                }
                                            />
                                            <span>
                                                <span className="block font-medium">
                                                    {t.label}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {t.value === 'commission'
                                                        ? 'Seller receives the winning bid minus the platform fee.'
                                                        : 'Seller receives a fixed price. Thabekhulu keeps everything above it.'}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </Section>
                            <Section title="Seller terms">
                                <div className="-mt-2">
                                    <Internal />
                                </div>
                                <div className="grid gap-4 md:grid-cols-3">
                                    {p.deal_type === 'commission' ? (
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="net">
                                                Seller’s net price (R)
                                            </Label>
                                            <Input
                                                id="net"
                                                className="tabular"
                                                inputMode="numeric"
                                                disabled={!can.editPricing}
                                                value={p.seller_net_price ?? ''}
                                                onChange={(e) =>
                                                    setP({
                                                        ...p,
                                                        seller_net_price: num(
                                                            e.target.value,
                                                        ),
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors.seller_net_price
                                                }
                                            />
                                        </div>
                                    ) : (
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="sp">
                                                Seller price (R)
                                            </Label>
                                            <Input
                                                id="sp"
                                                className="tabular"
                                                inputMode="numeric"
                                                disabled={!can.editPricing}
                                                value={p.seller_price ?? ''}
                                                onChange={(e) =>
                                                    setP({
                                                        ...p,
                                                        seller_price: num(
                                                            e.target.value,
                                                        ),
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={errors.seller_price}
                                            />
                                        </div>
                                    )}
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="reno">
                                            Re-innovation cost (R)
                                        </Label>
                                        <Input
                                            id="reno"
                                            className="tabular"
                                            inputMode="numeric"
                                            disabled={!can.editPricing}
                                            value={p.renovation_cost ?? 0}
                                            onChange={(e) =>
                                                setP({
                                                    ...p,
                                                    renovation_cost:
                                                        num(e.target.value) ??
                                                        0,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="fee">
                                            Platform fee (%)
                                        </Label>
                                        <Input
                                            id="fee"
                                            className="tabular"
                                            inputMode="decimal"
                                            disabled={!can.editPricing}
                                            value={p.fee_percent}
                                            onChange={(e) =>
                                                setP({
                                                    ...p,
                                                    fee_percent: Number(
                                                        e.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                        <InputError
                                            message={errors.fee_percent}
                                        />
                                    </div>
                                </div>
                            </Section>
                            <Section title="Public price and deposit">
                                {floor !== null && (
                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted px-4 py-3">
                                        <p className="text-sm">
                                            Lowest allowed minimum:{' '}
                                            <strong className="tabular">
                                                {rand(floor)}
                                            </strong>
                                            <br />
                                            <span className="text-muted-foreground">
                                                {p.deal_type === 'commission'
                                                    ? `${rand(p.seller_net_price)} ÷ (1 − ${p.fee_percent}%), so the seller nets their price.`
                                                    : 'Seller price plus re-innovation cost.'}
                                            </span>
                                        </p>
                                        {can.editPricing && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setP({
                                                        ...p,
                                                        minimum_price: floor,
                                                    })
                                                }
                                            >
                                                Use this minimum
                                            </Button>
                                        )}
                                    </div>
                                )}
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="min">
                                            Minimum selling price (R, public)
                                        </Label>
                                        <Input
                                            id="min"
                                            className="tabular"
                                            inputMode="numeric"
                                            aria-invalid={belowFloor}
                                            disabled={!can.editPricing}
                                            value={p.minimum_price ?? ''}
                                            onChange={(e) =>
                                                setP({
                                                    ...p,
                                                    minimum_price: num(
                                                        e.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.minimum_price ??
                                                (belowFloor
                                                    ? `Can’t be below ${rand(floor)}.`
                                                    : undefined)
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="dep">
                                            Deposit to bid (R)
                                        </Label>
                                        <Input
                                            id="dep"
                                            className="tabular"
                                            inputMode="numeric"
                                            disabled={!can.editPricing}
                                            value={p.deposit_amount}
                                            onChange={(e) =>
                                                setP({
                                                    ...p,
                                                    deposit_amount:
                                                        num(e.target.value) ??
                                                        0,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="terms">
                                            Deposit terms
                                        </Label>
                                        <select
                                            id="terms"
                                            className={select}
                                            disabled={!can.editPricing}
                                            value={p.deposit_terms}
                                            onChange={(e) =>
                                                setP({
                                                    ...p,
                                                    deposit_terms:
                                                        e.target.value,
                                                })
                                            }
                                        >
                                            {props.options.depositTerms.map(
                                                (t) => (
                                                    <option
                                                        key={t.value}
                                                        value={t.value}
                                                    >
                                                        {t.label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </div>
                                </div>
                                {depositPercent !== null && (
                                    <p
                                        className={cn(
                                            'rounded-md px-4 py-3 text-sm',
                                            depositPercent > 10
                                                ? 'border border-status-closing text-status-closing'
                                                : 'bg-status-live/10 text-status-live',
                                        )}
                                    >
                                        The deposit is {depositPercent}% of the
                                        minimum.{' '}
                                        {depositPercent > 10
                                            ? 'That’s above 10%: if a winner defaults, no more than 10% of the price, or the actual auction cost if lower, can be kept (CPA Regulation 24).'
                                            : 'Within the legal limit.'}
                                        {p.deposit_terms === 'security' &&
                                            ' As a security deposit it is refunded in full and never applied or forfeited automatically.'}
                                    </p>
                                )}
                                {live && can.editPricing && (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="p-reason">
                                            Reason for changing a live auction
                                        </Label>
                                        <Input
                                            id="p-reason"
                                            value={p.reason}
                                            onChange={(e) =>
                                                setP({
                                                    ...p,
                                                    reason: e.target.value,
                                                })
                                            }
                                        />
                                        <InputError message={errors.reason} />
                                    </div>
                                )}
                                {can.editPricing ? (
                                    <div>
                                        <Button
                                            disabled={belowFloor}
                                            onClick={() => save('pricing', p)}
                                        >
                                            Save pricing
                                        </Button>
                                    </div>
                                ) : (
                                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Lock
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Pricing is locked at this stage.
                                    </p>
                                )}
                            </Section>
                        </div>
                        <aside
                            className="h-fit rounded-xl border border-border bg-card p-5"
                            aria-labelledby="payout-h"
                        >
                            <h2 id="payout-h" className="font-semibold">
                                Payout preview
                            </h2>
                            {p.minimum_price ? (
                                [
                                    p.minimum_price,
                                    Math.round(p.minimum_price * 1.3),
                                ].map((bid, i) => (
                                    <dl
                                        key={bid}
                                        className="mt-4 grid gap-1 text-sm"
                                    >
                                        <p className="text-muted-foreground">
                                            {i === 0
                                                ? 'At the minimum'
                                                : 'If bidding reaches 30% more'}
                                        </p>
                                        <div className="flex justify-between">
                                            <dt>Winning bid</dt>
                                            <dd className="tabular">
                                                {rand(bid)}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt>
                                                {p.deal_type === 'commission'
                                                    ? `Platform fee (${p.fee_percent}%)`
                                                    : 'Thabekhulu keeps'}
                                            </dt>
                                            <dd className="tabular">
                                                {rand(payout(bid).fee)}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between font-semibold">
                                            <dt>Seller receives</dt>
                                            <dd className="tabular">
                                                {rand(payout(bid).seller)}
                                            </dd>
                                        </div>
                                    </dl>
                                ))
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Enter the minimum to see the payout.
                                </p>
                            )}
                            <p className="mt-4 text-xs text-muted-foreground">
                                Buyers pay only their winning bid. VAT on the
                                platform fee: to confirm with Thabekhulu’s
                                accountant.
                            </p>
                        </aside>
                    </div>
                )}

                {tab === 'Auction' && (
                    <Section
                        title="Auction"
                        description={`Times are South African. If you leave the end empty it is set to ${props.options.durationDays} days after the start.`}
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="start">Opens</Label>
                                <Input
                                    id="start"
                                    type="datetime-local"
                                    disabled={!can.editPricing}
                                    value={a.starts_at}
                                    onChange={(e) =>
                                        setA({
                                            ...a,
                                            starts_at: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="end">Closes</Label>
                                <Input
                                    id="end"
                                    type="datetime-local"
                                    disabled={!can.editPricing}
                                    value={a.ends_at}
                                    onChange={(e) =>
                                        setA({ ...a, ends_at: e.target.value })
                                    }
                                />
                                <InputError message={errors.ends_at} />
                            </div>
                        </div>
                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                Auction type
                            </legend>
                            {props.options.auctionTypes.map((t) => (
                                <label
                                    key={t.value}
                                    className="flex items-start gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="atype"
                                        className="mt-0.5 size-4 accent-primary"
                                        disabled={!can.editPricing}
                                        checked={a.auction_type === t.value}
                                        onChange={() =>
                                            setA({
                                                ...a,
                                                auction_type: t.value,
                                            })
                                        }
                                    />
                                    <span>
                                        <strong>{t.label}</strong>:{' '}
                                        {t.value === 'open'
                                            ? 'bids and bidder numbers are public; highest bid at the close wins.'
                                            : 'bids stay confidential until the close; often used for mining assets.'}
                                    </span>
                                </label>
                            ))}
                        </fieldset>
                        <div className="grid gap-1.5">
                            <Label htmlFor="bterms">
                                Bidding and sale terms for this listing
                                (optional)
                            </Label>
                            {can.editPricing ? (
                                <RichTextEditor
                                    id="bterms"
                                    label="Bidding terms"
                                    value={a.bidding_terms}
                                    onChange={(html) =>
                                        setA({ ...a, bidding_terms: html })
                                    }
                                />
                            ) : (
                                <div
                                    className="rounded-md border border-border p-3 text-sm"
                                    dangerouslySetInnerHTML={{
                                        __html: a.bidding_terms,
                                    }}
                                />
                            )}
                            <p className="text-sm text-muted-foreground">
                                Leave empty to use the standard terms. Terms
                                must be reviewed by Thabekhulu’s attorney.
                            </p>
                        </div>
                        {live && can.editPricing && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="a-reason">
                                    Reason for changing a live auction
                                </Label>
                                <Input
                                    id="a-reason"
                                    value={a.reason}
                                    onChange={(e) =>
                                        setA({ ...a, reason: e.target.value })
                                    }
                                />
                                <InputError message={errors.reason} />
                            </div>
                        )}
                        {can.editPricing && (
                            <div>
                                <Button onClick={() => save('auction', a)}>
                                    Save auction
                                </Button>
                            </div>
                        )}
                    </Section>
                )}

                {tab === 'Compliance' && (
                    <Section
                        title="Compliance checklist"
                        description="Deposits stay switched off for this listing until every item is signed off (enforced when payments go live)."
                    >
                        <ul className="grid gap-3">
                            {props.compliance.map((c) => (
                                <li
                                    key={c.item}
                                    className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border p-4"
                                >
                                    <div className="flex gap-3">
                                        <span
                                            className={cn(
                                                'mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full border',
                                                c.signedBy
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-input',
                                            )}
                                        >
                                            {c.signedBy && (
                                                <Check
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </span>
                                        <div>
                                            <p className="font-medium">
                                                {c.label}
                                            </p>
                                            {c.signedBy ? (
                                                <p className="text-sm text-muted-foreground">
                                                    Signed off by {c.signedBy},{' '}
                                                    {formatDateTime(c.signedAt)}
                                                    {c.note
                                                        ? `: ${c.note}`
                                                        : ''}
                                                </p>
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    Not signed off
                                                </p>
                                            )}
                                            {c.attachments.length > 0 && (
                                                <ul className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                                                    {c.attachments.map((a) => (
                                                        <li key={a.id}>
                                                            <a
                                                                href={a.url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="underline"
                                                            >
                                                                {a.name}
                                                            </a>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                    </div>
                                    {can.signOff &&
                                        (c.signedBy ? (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    prompted(
                                                        'Why is this sign-off being withdrawn?',
                                                        (reason) =>
                                                            router.delete(
                                                                compliance.withdraw(
                                                                    {
                                                                        listing:
                                                                            l.ulid,
                                                                        item: c.item,
                                                                    },
                                                                ).url,
                                                                {
                                                                    ...opts,
                                                                    data: {
                                                                        reason,
                                                                    },
                                                                },
                                                            ),
                                                    )
                                                }
                                            >
                                                Withdraw
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    prompted(
                                                        'What did you check? (recorded with your sign-off)',
                                                        (note, files) =>
                                                            router.post(
                                                                compliance.signoff(
                                                                    {
                                                                        listing:
                                                                            l.ulid,
                                                                        item: c.item,
                                                                    },
                                                                ).url,
                                                                { note, files },
                                                                {
                                                                    ...opts,
                                                                    forceFormData: true,
                                                                },
                                                            ),
                                                    )
                                                }
                                            >
                                                Sign off
                                            </Button>
                                        ))}
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}

                {tab === 'Search' && (
                    <Section
                        title="Search and sharing"
                        description="Leave empty to use the title and summary."
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="seot">Search title</Label>
                            <Input
                                id="seot"
                                maxLength={120}
                                disabled={!can.edit}
                                value={s.seo_title}
                                onChange={(e) =>
                                    setS({ ...s, seo_title: e.target.value })
                                }
                            />
                            <p className="tabular text-right text-xs text-muted-foreground">
                                {s.seo_title.length}/120
                            </p>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="seod">Search description</Label>
                            <textarea
                                id="seod"
                                maxLength={300}
                                disabled={!can.edit}
                                value={s.seo_description}
                                onChange={(e) =>
                                    setS({
                                        ...s,
                                        seo_description: e.target.value,
                                    })
                                }
                                className="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <p className="tabular text-right text-xs text-muted-foreground">
                                {s.seo_description.length}/300
                            </p>
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="size-4 accent-primary"
                                disabled={!can.edit}
                                checked={s.is_featured}
                                onChange={(e) =>
                                    setS({
                                        ...s,
                                        is_featured: e.target.checked,
                                    })
                                }
                            />
                            Feature on the home page
                        </label>
                        <div className="rounded-lg border border-border p-4">
                            <p className="text-xs text-muted-foreground">
                                Preview
                            </p>
                            <p className="mt-1 text-lg text-[#1a0dab] dark:text-[#8ab4f8]">
                                {s.seo_title || d.title}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {s.seo_description ||
                                    d.summary ||
                                    'Add a summary.'}
                            </p>
                        </div>
                        {can.edit && (
                            <div>
                                <Button onClick={() => save('seo', s)}>
                                    Save
                                </Button>
                            </div>
                        )}
                    </Section>
                )}

                {tab === 'History' && (
                    <Section title="Status history">
                        <ol className="grid gap-2 text-sm">
                            {props.history.map((h, i) => (
                                <li
                                    key={i}
                                    className="rounded-lg border border-border p-3"
                                >
                                    <p>
                                        <strong>
                                            {h.to.replace('_', ' ')}
                                        </strong>{' '}
                                        <span className="text-muted-foreground">
                                            by {h.by}, {formatDateTime(h.at)}
                                        </span>
                                    </p>
                                    {h.note && (
                                        <p className="text-muted-foreground">
                                            {h.note}
                                        </p>
                                    )}
                                </li>
                            ))}
                            {props.history.length === 0 && (
                                <li className="text-muted-foreground">
                                    No status changes yet.
                                </li>
                            )}
                        </ol>
                    </Section>
                )}

                <p className="text-sm">
                    <Link
                        href={index()}
                        className="underline-offset-4 hover:underline"
                    >
                        Back to listings
                    </Link>
                </p>
            </div>
        </>
    );
}

ListingEdit.layout = {
    breadcrumbs: [{ title: 'Listings', href: index() }],
};
