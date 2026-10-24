<?php

namespace App\Domains\Bidding\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Public live feed for one listing (B7). Carries the whole public view so
 * browsers update in place instead of every viewer re-fetching the page.
 * Bidder numbers only; never names or maximums. Sent after the bid commits.
 */
class AuctionUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param array<string, mixed> $auction */
    public function __construct(public readonly string $listingUlid, public readonly array $auction) {}

    public function broadcastOn(): Channel
    {
        return new Channel('listing.'.$this->listingUlid);
    }

    public function broadcastAs(): string
    {
        return 'auction.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->auction;
    }
}
