import { Head, Link } from '@inertiajs/react';
import { FileText, Lock } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import type { BidderState } from '@/components/auctions/bidder-panel';
import BidderPanel from '@/components/auctions/bidder-panel';
import Countdown from '@/components/auctions/countdown';
import StatusBadge from '@/components/auctions/status-badge';
import type { Fact, ListingCardData } from '@/components/auctions/types';
import { useMounted } from '@/hooks/use-mounted';
import { rand } from '@/lib/money';

const ListingMap = lazy(() => import('@/components/auctions/listing-map'));

type Detail = ListingCardData & {
    summary: string | null;
    description: string | null;
    videoEmbed: string | null;
    gallery: { src: string | null; thumb: string | null; alt: string }[];
    floorPlans: { src: string; pdf: boolean; name: string }[];
    keyFacts: Fact[];
    deposit: number;
    depositTerms: 'security' | 'applied';
    biddingTerms: string | null;
    notice: string | null;
    documents: { name: string; access: string }[];
};
type Props = {
    listing: Detail;
    bidder: BidderState;
    office: { phone: string; email: string };
    seo: {
        title: string;
        description: string;
        image: string | null;
        canonical: string;
    };
};

export default function AuctionShow({
    bidder,
    listing: l,
    office,
    seo,
}: Props) {
    const [photo, setPhoto] = useState(0);
    const mounted = useMounted();
    const place = [l.suburb, l.city, l.province].filter(Boolean).join(', ');
    const open = l.status.key !== 'soon';

    return (
        <>
            <Head title={seo.title}>
                <meta name="description" content={seo.description} />
                <link rel="canonical" href={seo.canonical} />
                <meta property="og:title" content={seo.title} />
                <meta property="og:description" content={seo.description} />
                {seo.image && <meta property="og:image" content={seo.image} />}
            </Head>

            <nav
                aria-label="Breadcrumb"
                className="mx-auto max-w-[1280px] px-5 pt-6 text-sm sm:px-8"
            >
                <Link href="/auctions">Auctions</Link>{' '}
                <span className="text-muted-foreground">/</span>{' '}
                <Link href={`/auctions/${l.categorySlug}`}>{l.category}</Link>{' '}
                <span className="text-muted-foreground">/</span>{' '}
                <span className="text-muted-foreground">
                    {l.city ?? 'South Africa'}
                </span>
            </nav>

            <section
                className="mx-auto max-w-[1280px] px-5 pt-4 sm:px-8"
                aria-labelledby="title"
            >
                <div className="flex flex-wrap gap-2">
                    <StatusBadge status={l.status.key} label={l.status.label} />
                    {l.auctionType === 'sealed' && (
                        <span className="rounded-full bg-brand-accent-soft px-2.5 py-1 text-[13px] font-semibold text-brand-accent">
                            Sealed tender
                        </span>
                    )}
                    {l.renovated && (
                        <span className="rounded-full bg-brand-accent-soft px-2.5 py-1 text-[13px] font-semibold text-brand-accent">
                            Renovated
                        </span>
                    )}
                </div>
                <h1
                    id="title"
                    className="mt-3 font-display text-4xl leading-[1.05] sm:text-5xl"
                >
                    {l.title}
                </h1>
                <p className="mt-2 text-lg text-muted-foreground">
                    {place || 'South Africa'} · Listed by Thabekhulu Development
                    Group · Verified seller
                </p>
            </section>

            {l.gallery.length > 0 && (
                <section
                    aria-label="Photos"
                    className="mx-auto mt-6 max-w-[1280px] px-5 sm:px-8"
                >
                    <div className="aspect-[16/9] overflow-hidden rounded-[18px] bg-muted">
                        {l.gallery[photo]?.src && (
                            <img
                                src={l.gallery[photo].src!}
                                alt={l.gallery[photo].alt}
                                className="size-full object-cover"
                            />
                        )}
                    </div>
                    {l.gallery.length > 1 && (
                        <ul className="mt-3 flex gap-2 overflow-x-auto pb-1">
                            {l.gallery.map((g, i) => (
                                <li key={i} className="shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => setPhoto(i)}
                                        aria-label={`Show photo ${i + 1}: ${g.alt}`}
                                        aria-current={i === photo}
                                        className="block h-20 w-28 overflow-hidden rounded-[10px] border-2 border-transparent aria-[current=true]:border-primary"
                                    >
                                        {g.thumb && (
                                            <img
                                                src={g.thumb}
                                                alt=""
                                                className="size-full object-cover"
                                                loading="lazy"
                                            />
                                        )}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            )}

            <div className="mx-auto flex max-w-[1280px] flex-wrap items-start gap-10 px-5 pt-10 pb-24 sm:px-8">
                <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-14">
                    {l.notice && (
                        <p className="rounded-[10px] bg-brand-accent-soft px-4 py-3 text-brand-accent">
                            {l.notice}
                        </p>
                    )}

                    <section aria-labelledby="glance">
                        <h2 id="glance" className="mb-4 font-display text-3xl">
                            At a glance
                        </h2>
                        {l.summary && (
                            <p className="mb-5 max-w-[720px] text-lg leading-relaxed">
                                {l.summary}
                            </p>
                        )}
                        {l.keyFacts.length > 0 && (
                            <dl className="grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-2.5">
                                {l.keyFacts.map((f) => (
                                    <div
                                        key={f.label}
                                        className="rounded-[14px] border border-border bg-card px-4 py-3.5"
                                    >
                                        <dt className="text-[13px] text-muted-foreground">
                                            {f.label}
                                        </dt>
                                        <dd className="mt-0.5 text-lg font-bold">
                                            {f.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </section>

                    {l.description && (
                        <section aria-labelledby="about">
                            <h2
                                id="about"
                                className="mb-4 font-display text-3xl"
                            >
                                About this property
                            </h2>
                            <div
                                className="max-w-[720px] text-[17px] leading-relaxed text-foreground/90 [&_a]:underline [&_h2]:mt-6 [&_h2]:font-display [&_h2]:text-2xl [&_h3]:mt-4 [&_h3]:font-semibold [&_li]:mt-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-3 [&_ul]:list-disc [&_ul]:pl-6"
                                dangerouslySetInnerHTML={{
                                    __html: l.description,
                                }}
                            />
                        </section>
                    )}

                    {l.videoEmbed && (
                        <section aria-labelledby="video">
                            <h2
                                id="video"
                                className="mb-4 font-display text-3xl"
                            >
                                Video tour
                            </h2>
                            <div className="aspect-video overflow-hidden rounded-[18px] bg-muted">
                                <iframe
                                    src={l.videoEmbed}
                                    title={`Video tour of ${l.title}`}
                                    className="size-full"
                                    allow="fullscreen; picture-in-picture"
                                    loading="lazy"
                                    referrerPolicy="strict-origin-when-cross-origin"
                                />
                            </div>
                        </section>
                    )}

                    {l.floorPlans.length > 0 && (
                        <section aria-labelledby="plans">
                            <h2
                                id="plans"
                                className="mb-4 font-display text-3xl"
                            >
                                Plans and maps
                            </h2>
                            <ul className="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-3">
                                {l.floorPlans.map((f) => (
                                    <li key={f.src}>
                                        <a
                                            href={f.src}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-2 rounded-[14px] border border-border bg-card px-4 py-3 font-semibold"
                                        >
                                            <FileText
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                            {f.name}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {l.documents.length > 0 && (
                        <section aria-labelledby="docs">
                            <h2
                                id="docs"
                                className="mb-2 font-display text-3xl"
                            >
                                Documents
                            </h2>
                            <p className="mb-3 text-muted-foreground">
                                Available once you register and verify your
                                identity. Every download is watermarked with
                                your name.
                            </p>
                            <ul className="divide-y divide-border rounded-[14px] border border-border bg-card">
                                {l.documents.map((d) => (
                                    <li
                                        key={d.name}
                                        className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
                                    >
                                        <span className="inline-flex items-center gap-2">
                                            <Lock
                                                className="size-4 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            {d.name}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {d.access}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {l.position && (
                        <section aria-labelledby="location">
                            <h2
                                id="location"
                                className="mb-4 font-display text-3xl"
                            >
                                Location
                            </h2>
                            <p className="mb-3 text-muted-foreground">
                                Approximate area. The exact address is shared
                                with verified bidders.
                            </p>
                            <Suspense
                                fallback={
                                    <div className="h-80 rounded-[18px] bg-muted" />
                                }
                            >
                                {mounted && (
                                    <ListingMap
                                        listings={[l]}
                                        zoom={11}
                                        className="h-80 w-full overflow-hidden rounded-[18px] border border-border"
                                        label={`Approximate location of ${l.title}`}
                                    />
                                )}
                            </Suspense>
                        </section>
                    )}

                    {l.biddingTerms && (
                        <section aria-labelledby="terms">
                            <h2
                                id="terms"
                                className="mb-4 font-display text-3xl"
                            >
                                Bidding and sale terms
                            </h2>
                            <div
                                className="max-w-[720px] leading-relaxed [&_li]:mt-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mt-3 [&_ul]:list-disc [&_ul]:pl-6"
                                dangerouslySetInnerHTML={{
                                    __html: l.biddingTerms,
                                }}
                            />
                        </section>
                    )}

                    <section
                        aria-labelledby="visit"
                        className="flex flex-wrap items-center justify-between gap-5 rounded-[18px] border border-border bg-card p-7"
                    >
                        <div>
                            <h2 id="visit" className="font-display text-2xl">
                                View before you bid
                            </h2>
                            <p className="mt-1 text-muted-foreground">
                                Book a viewing or a call with the Thabekhulu
                                office.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2.5">
                            {office.phone && (
                                <a
                                    href={`tel:${office.phone.replace(/\s/g, '')}`}
                                    className="inline-flex min-h-12 items-center rounded-[10px] bg-primary px-5 font-semibold text-primary-foreground"
                                >
                                    Call {office.phone}
                                </a>
                            )}
                            <a
                                href={`mailto:${office.email}?subject=${encodeURIComponent(`Viewing: ${l.title}`)}`}
                                className="inline-flex min-h-12 items-center rounded-[10px] border border-input px-5 font-semibold"
                            >
                                Email the office
                            </a>
                        </div>
                    </section>
                </div>

                <aside
                    aria-labelledby="bid-h"
                    className="flex flex-[1_1_360px] flex-col gap-4 rounded-[18px] border border-border bg-card p-6 shadow-[0_12px_32px_rgba(14,29,25,0.08)] lg:sticky lg:top-4"
                >
                    <h2
                        id="bid-h"
                        className="text-[15px] font-semibold text-muted-foreground"
                    >
                        {open
                            ? `Auction closes ${l.closesLabel ?? '—'} SAST`
                            : `Bidding opens ${l.opensLabel ?? '—'} SAST`}
                    </h2>
                    {(open ? l.endsAt : l.startsAt) && (
                        <Countdown
                            to={(open ? l.endsAt : l.startsAt)!}
                            label={
                                open
                                    ? 'Time left in this auction'
                                    : 'Time until bidding opens'
                            }
                        />
                    )}
                    <div>
                        <p className="text-[13px] text-muted-foreground">
                            {l.auctionType === 'sealed'
                                ? 'Sealed tender: bids stay confidential until the close'
                                : 'Minimum bid'}
                        </p>
                        <p className="tabular mt-0.5 text-4xl font-bold">
                            {rand(l.minimum)}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Deposit to bid:{' '}
                            <strong className="tabular text-foreground">
                                {rand(l.deposit)}
                            </strong>
                        </p>
                    </div>
                    <p className="rounded-[10px] bg-muted px-3.5 py-3 text-sm leading-relaxed">
                        {l.depositTerms === 'security'
                            ? 'Your deposit is a refundable security deposit, held separately and refunded in full if you don’t win. It is not part of the purchase price.'
                            : 'Your deposit is refunded if you don’t win. If you win, it counts toward the purchase price.'}
                    </p>
                    <BidderPanel
                        state={bidder}
                        ulid={l.ulid}
                        minimum={l.minimum}
                    />
                </aside>
            </div>
        </>
    );
}
