<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * The public live feed (listing.{ulid}) needs no authorisation: it carries
 * only bidder numbers and amounts. Private alerts go to their owner only,
 * keyed by the user's ulid (the browser never sees internal ids).
 */
Broadcast::channel('bidder.{ulid}', fn (User $user, string $ulid): bool => $user->ulid === $ulid && $user->isBuyer());
