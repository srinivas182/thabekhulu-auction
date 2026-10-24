<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * The public live feed (listing.{ulid}) needs no authorisation: it carries
 * only bidder numbers and amounts. Private alerts go to their owner only.
 */
Broadcast::channel('bidder.{id}', fn (User $user, int $id): bool => $user->id === $id && $user->isBuyer());
