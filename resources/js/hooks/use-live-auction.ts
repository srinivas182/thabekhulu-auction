import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { AuctionLive } from '@/components/auctions/bid-form';

type PublicUpdate = Omit<AuctionLive, 'mine'> & { leaderNumber: string | null };

/**
 * Live auction state (B7): applies Reverb events in place, so viewers don't
 * all re-fetch the page on every bid. Falls back to refreshing every
 * 5 seconds whenever the WebSocket isn't connected. Echo is only touched in
 * the browser (effects never run during server rendering).
 */
export function useLiveAuction(
    ulid: string,
    initial: AuctionLive | null,
    userUlid?: string | null,
) {
    const [auction, setAuction] = useState(initial);
    const [live, setLive] = useState(false);
    const [outbid, setOutbid] = useState<number | null>(null);

    useEffect(() => setAuction(initial), [initial]);

    useEffect(() => {
        if (!initial || initial.closed) return;
        let stopped = false;
        let cleanup = () => {};

        import('@laravel/echo-react')
            .then(({ echo }) => {
                if (stopped) return;
                const e = echo();
                e.channel(`listing.${ulid}`).listen(
                    '.auction.updated',
                    (data: PublicUpdate) =>
                        setAuction((prev) =>
                            prev
                                ? {
                                      ...prev,
                                      ...data,
                                      mine: prev.mine
                                          ? {
                                                ...prev.mine,
                                                leading:
                                                    data.leaderNumber ===
                                                    prev.mine.bidderNumber,
                                                hasBid:
                                                    prev.mine.hasBid ||
                                                    data.leaderNumber ===
                                                        prev.mine.bidderNumber,
                                            }
                                          : null,
                                  }
                                : prev,
                        ),
                );
                if (userUlid) {
                    e.private(`bidder.${userUlid}`).notification(
                        (n: {
                            kind?: string;
                            listing?: string;
                            current?: number;
                        }) => {
                            if (n.kind === 'outbid' && n.listing === ulid)
                                setOutbid(n.current ?? null);
                        },
                    );
                }
                const connection = (
                    e.connector as {
                        pusher?: {
                            connection?: {
                                state?: string;
                                bind: (ev: string, cb: () => void) => void;
                                unbind: (ev: string, cb: () => void) => void;
                            };
                        };
                    }
                ).pusher?.connection;
                const update = () => setLive(connection?.state === 'connected');
                connection?.bind('state_change', update);
                update();
                cleanup = () => {
                    connection?.unbind('state_change', update);
                    e.leave(`listing.${ulid}`);
                    if (userUlid) e.leave(`bidder.${userUlid}`);
                };
            })
            .catch(() => setLive(false)); // no WebSocket: polling below keeps the page current

        return () => {
            stopped = true;
            cleanup();
        };
    }, [ulid, userUlid, initial?.closed]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (live || !initial || initial.closed) return;
        const timer = setInterval(
            () => router.reload({ only: ['auction'] }),
            5000,
        );

        return () => clearInterval(timer);
    }, [live, initial?.closed]); // eslint-disable-line react-hooks/exhaustive-deps

    return { auction, live, outbid, clearOutbid: () => setOutbid(null) };
}
