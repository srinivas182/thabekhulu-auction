import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { bidders } from '@/routes/admin/listings';
import { decide, queue } from '@/routes/admin/bidders';

type Application = {
    id: number;
    applied: string | null;
    waitingDays: number;
    buyer: string;
    email: string;
    biddingAs: string | null;
    listing: string;
    listingUlid: string;
    closes: string | null;
    ready: boolean;
    checksSigned: number;
    checksTotal: number;
    missing: string[];
};

/** Every application waiting for a decision, across all auctions, oldest first. */
export default function BidderQueue({
    applications,
}: {
    applications: Application[];
}) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [declining, setDeclining] = useState<Application | null>(null);
    const [reason, setReason] = useState('');
    const opts = { preserveScroll: true };

    return (
        <>
            <Head title="Bidder approvals" />
            <div className="flex max-w-5xl flex-1 flex-col gap-5 p-4 sm:p-6">
                <div>
                    <h1 className="font-display text-3xl">Bidder approvals</h1>
                    <p className="mt-1 text-muted-foreground">
                        {applications.length === 0
                            ? 'Nobody is waiting. New applications appear here as buyers apply.'
                            : `${applications.length} ${applications.length === 1 ? 'application is' : 'applications are'} waiting, oldest first.`}
                    </p>
                    {errors.decision && (
                        <p className="mt-2 text-sm text-destructive">
                            {errors.decision}
                        </p>
                    )}
                </div>

                <ul className="grid gap-3">
                    {applications.map((a) => (
                        <li
                            key={a.id}
                            className="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                        >
                            <div className="grid gap-1 text-sm">
                                <p>
                                    <span className="font-semibold">
                                        {a.buyer}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {a.email}
                                        {a.biddingAs
                                            ? ` · for ${a.biddingAs}`
                                            : ''}
                                    </span>
                                </p>
                                <p>
                                    {a.listing}
                                    <span className="text-muted-foreground">
                                        {a.closes
                                            ? ` · closes ${a.closes}`
                                            : ''}{' '}
                                        · applied {a.applied}
                                    </span>
                                    {a.waitingDays >= 2 && (
                                        <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                                            waiting {a.waitingDays} days
                                        </span>
                                    )}
                                </p>
                                {a.checksSigned < a.checksTotal && (
                                    <p className="font-medium text-amber-700 dark:text-amber-400">
                                        Deposits not open yet: {a.checksSigned}{' '}
                                        of {a.checksTotal} legal checks signed
                                        off on this listing.
                                    </p>
                                )}
                                {a.ready ? (
                                    <p className="font-medium text-status-live">
                                        Ready to approve: every requirement is
                                        met.
                                    </p>
                                ) : (
                                    <p className="text-muted-foreground">
                                        Still missing: {a.missing.join(', ')}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    disabled={!a.ready}
                                    onClick={() =>
                                        router.post(
                                            decide(a.id).url,
                                            { decision: 'approve' },
                                            opts,
                                        )
                                    }
                                >
                                    Approve
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        setReason('');
                                        setDeclining(a);
                                    }}
                                >
                                    Decline
                                </Button>
                                <Button size="sm" variant="ghost" asChild>
                                    <Link href={bidders(a.listingUlid)}>
                                        Review in detail
                                    </Link>
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>

            <Dialog
                open={declining !== null}
                onOpenChange={(open) => !open && setDeclining(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="font-display text-2xl">
                            Decline this application
                        </DialogTitle>
                        <DialogDescription>
                            {declining
                                ? `${declining.buyer} for ${declining.listing}. `
                                : ''}
                            The buyer sees the reason in their account.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="reason">Reason</Label>
                        <textarea
                            id="reason"
                            rows={4}
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeclining(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={reason.trim() === ''}
                            onClick={() => {
                                if (declining)
                                    router.post(
                                        decide(declining.id).url,
                                        {
                                            decision: 'reject',
                                            reason: reason.trim(),
                                        },
                                        opts,
                                    );
                                setDeclining(null);
                            }}
                        >
                            Decline application
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

BidderQueue.layout = {
    breadcrumbs: [{ title: 'Bidder approvals', href: queue() }],
};
