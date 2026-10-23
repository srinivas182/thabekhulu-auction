import { Form, Head, Link } from '@inertiajs/react';
import { Download, Lock } from 'lucide-react';
import { nda as ndaRoute } from '@/routes/dataroom';
import { download } from '@/routes/dataroom';

type Doc = {
    id: number;
    type: string;
    name: string;
    size: number;
    level: string;
    open: boolean;
};
type Props = {
    listing: { ulid: string; title: string; url: string };
    documents: Doc[];
    state: {
        verified: boolean;
        approval: string | null;
        bidderNumber: string | null;
        ndaAccepted: boolean;
        canSignNda: boolean;
        hasDataRoom: boolean;
    };
    nda: { text: string; version: number };
    name: string;
};

const mb = (b: number) =>
    b < 1024 * 1024
        ? `${Math.max(1, Math.round(b / 1024))} KB`
        : `${(b / 1024 / 1024).toFixed(1)} MB`;
const field =
    'h-12 w-full min-w-0 rounded-[10px] border border-input bg-card px-3 text-base';

export default function DataRoom({
    listing,
    documents,
    state,
    nda,
    name,
}: Props) {
    const lockReason = (d: Doc) =>
        !state.verified
            ? 'Verify your identity first'
            : d.level === 'Data room'
              ? state.approval !== 'approved'
                  ? 'Available once you’re approved to bid'
                  : 'Sign the NDA above to open'
              : 'Locked';

    return (
        <>
            <Head title={`Documents: ${listing.title}`}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <section className="mx-auto flex max-w-[900px] flex-col gap-7 px-5 py-12 sm:px-8">
                <div>
                    <Link href={listing.url} className="text-sm underline">
                        {listing.title}
                    </Link>
                    <h1 className="mt-1 font-display text-4xl">Documents</h1>
                    <p className="mt-2 text-muted-foreground">
                        Every download is stamped with your name
                        {state.bidderNumber
                            ? ` and bidder number #${state.bidderNumber}`
                            : ''}{' '}
                        and recorded.
                    </p>
                </div>

                {state.hasDataRoom &&
                    state.canSignNda &&
                    !state.ndaAccepted && (
                        <section
                            aria-labelledby="nda-h"
                            className="grid gap-4 rounded-[18px] border border-border bg-card p-6 [&_*]:min-w-0"
                        >
                            <h2 id="nda-h" className="font-display text-2xl">
                                Non-disclosure agreement (version {nda.version})
                            </h2>
                            <div
                                className="max-h-72 overflow-y-auto rounded-[10px] border border-border p-4 text-sm leading-relaxed [&_p]:mt-2"
                                dangerouslySetInnerHTML={{ __html: nda.text }}
                            />
                            <Form
                                action={ndaRoute(listing.ulid).url}
                                method="post"
                                options={{ preserveScroll: true }}
                                className="grid gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                                            Type your full name
                                            <input
                                                name="typed_name"
                                                defaultValue={name}
                                                className={field}
                                            />
                                            {errors.typed_name && (
                                                <span className="text-destructive">
                                                    {errors.typed_name}
                                                </span>
                                            )}
                                        </label>
                                        <label className="flex items-start gap-3 text-sm">
                                            <input
                                                type="checkbox"
                                                name="agree"
                                                value="1"
                                                className="mt-0.5 size-5 accent-primary"
                                            />
                                            <span>
                                                I accept this non-disclosure
                                                agreement.
                                            </span>
                                        </label>
                                        {errors.agree && (
                                            <p className="text-sm text-destructive">
                                                {errors.agree}
                                            </p>
                                        )}
                                        <button
                                            disabled={processing}
                                            className="min-h-12 w-fit rounded-[10px] bg-primary px-6 font-semibold text-primary-foreground"
                                        >
                                            Accept and open the data room
                                        </button>
                                    </>
                                )}
                            </Form>
                        </section>
                    )}

                <ul className="divide-y divide-border rounded-[18px] border border-border bg-card">
                    {documents.map((d) => (
                        <li
                            key={d.id}
                            className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                        >
                            <div>
                                <p className="font-semibold">{d.type}</p>
                                <p className="text-sm text-muted-foreground">
                                    {d.name} · {mb(d.size)} · {d.level}
                                </p>
                            </div>
                            {d.open ? (
                                <a
                                    href={
                                        download({
                                            listing: listing.ulid,
                                            document: d.id,
                                        }).url
                                    }
                                    className="inline-flex min-h-11 items-center gap-1.5 rounded-[10px] bg-primary px-4 font-semibold text-primary-foreground"
                                >
                                    <Download
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Download
                                </a>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <Lock
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {lockReason(d)}
                                </span>
                            )}
                        </li>
                    ))}
                    {documents.length === 0 && (
                        <li className="px-5 py-8 text-center text-muted-foreground">
                            No documents yet.
                        </li>
                    )}
                </ul>
            </section>
        </>
    );
}
