# Sprint 6 — Bidding engine: detailed specification

**Status:** Draft for approval · **Depends on:** Sprints 4 (approved bidders) and 5 (deposits, refunds) · **Design source:** approved "Listing" and "Bid confirmation" screens

## 1. Objective

Run fair, lawful online auctions: approved bidders with a confirmed deposit place increase-only bids at or above the minimum, with proxy (maximum) bidding, soft close and a live feed that shows bidder numbers, never names; Mining listings can run as sealed tenders. At close the winner and runner-up are determined and every other deposit is handed to the Sprint 5 refund process.

## 2. Legal frame (to confirm with Thabekhulu's attorney)

- Online auctions fall under section 45 of the Consumer Protection Act and its auction regulations (bidders' record, bidder numbers, letters of authority for bidding on behalf of others).
- **No bids by or for the seller.** Where a seller (or someone for the seller) bids without a published reservation of that right, the buyer may void the sale or buy at the last legitimate bid. Phase 1 therefore has **no vendor-bid feature at all**, and the minimum selling price is enforced as the lowest acceptable bid, so a "reserve not met" situation cannot arise.
- **Bidders' record:** every approved bidder's identity, bidder number and authority (for companies and trusts, the board resolution from Sprint 4) is held and exportable for inspection.
- The Rules of Auction must describe online bidding, proxy bidding, soft close, sealed tenders and how ties and system faults are handled.

## 3. Delivery: three sub-sprints, one PR each

| Sub-sprint                      | Contents                                                                                                                                                                                                                                                                                         |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **6a Bidding core**             | Bid eligibility, increment bands, increase-only bids at or above the minimum, **proxy (maximum) bids**, concurrency-safe bid processing, **soft close**, step-up OTP above a threshold, bid confirmation screen, staff voiding of an erroneous bid (with reason), bidders' record export         |
| **6b Live feed and alerts**     | Laravel Reverb WebSockets: public live feed per listing (bidder number + amount), private outbid and closing alerts; polling fallback; email and **WhatsApp** alerts (opt-in, POPIA) for outbid, closing soon and won/lost                                                                       |
| **6c Sealed tenders and close** | Sealed tender listings (one best offer per bidder, unreadable until the deadline, opened by two staff); auction close job: winner, runner-up, result notices; deposits of everyone else scheduled for refund (5c); runner-up hold; hand-off to settlement (Sprint 7); shill-bid detection report |

## 4. Decisions needing approval

| #   | Decision       | Recommendation                                                                                                                                                                                                                           | Alternative        |
| --- | -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| B1  | Who may bid    | Approved for this listing (Sprint 4 checklist complete, including deposit received); **staff, the seller and anyone linked to them can never bid** (linked = same ID or company controller, same refund bank account)                    | Approval only      |
| B2  | Bid rules      | Increase-only; first bid ≥ minimum; each later bid ≥ current bid + increment from the listing's **increment bands** (e.g. up to R1m: R10 000; R1m–R5m: R25 000; above: R50 000); **bids are binding and cannot be withdrawn** by bidders | Free amounts       |
| B3  | Proxy bidding  | A bidder may set a maximum; the system bids for them by the minimum needed, up to that maximum; on equal maximums the **earlier** maximum wins; maximums are private (never shown to staff during the auction, kept encrypted)           | No proxy           |
| B4  | Concurrency    | All bids for a listing are processed one at a time under a database row lock on the listing's auction state; the bid record is append-only with server timestamps (SAST shown)                                                           | Optimistic retries |
| B5  | Soft close     | Default on: any accepted bid in the last **5 minutes** moves the close to 5 minutes after that bid; no cap by default (setting per listing)                                                                                              | Fixed end          |
| B6  | Step-up OTP    | Bids above a threshold (setting, default R1 000 000 or 2× the minimum, whichever is lower) require a one-time code by SMS or authenticator app at the confirm step                                                                       | No OTP             |
| B7  | Live feed      | Reverb WebSockets; public channel shows bidder number, amount and time only; names never leave the server; polling fallback every 5 seconds                                                                                              | Polling only       |
| B8  | Sealed tenders | Per listing; one offer per bidder (may be raised, not lowered, before the deadline); offers encrypted and unreadable by anyone until the deadline; opening requires two staff; highest offer wins, ties to the earlier offer             | Open bidding only  |
| B9  | Errors         | A staff member with `auctions.void_bid` may void a bid placed in error (reason required, bidder notified, audit-logged); the auction then continues from the previous valid bid                                                          | No voiding         |

## 5. 6a — Bidding core

- **Eligibility check on every bid:** approval for this listing, deposit received, verification and screening still valid, not linked to staff or the seller (B1), listing live and before close.
- **Bid screen (approved design):** current bid, next minimum, bid or set a maximum, confirm step showing amount, deposit terms and the binding nature of the bid; OTP when required.
- **Engine:** single place that accepts a bid, applies proxy maximums, extends the close (soft close) and records every resulting bid (including automatic proxy bids, marked as such).
- **Staff:** auction monitor (live list of bids by bidder number, with names visible only to staff with `auctions.monitor`), pause/resume (existing `auctions.pause`), void bid (B9).
- **Bidders' record export:** CSV/PDF per listing for inspection.

## 6. 6b — Live feed and alerts

- Reverb server in Docker; channels: `listing.{ulid}` (public), `bidder.{id}` (private).
- Alerts: outbid (immediately), closing in 1 hour / 10 minutes (to bidders on that listing), won, lost (with refund timing).
- WhatsApp through the **Meta WhatsApp Business Cloud API** with pre-approved utility templates; only for buyers who opt in; email always.

## 7. 6c — Sealed tenders and close

- Tender offers stored encrypted with a per-listing key released only at the deadline; staff see counts, not amounts, before opening; opening is a two-person action recorded in the audit log.
- **Close job** (every minute, idempotent): when the (possibly extended) end passes, lock the listing, determine winner and runner-up, mark the listing "Sold — pending settlement" or "Not sold" (no valid bids), notify bidders, and **schedule refunds for every other deposit** (5c), keeping the runner-up's deposit on hold for the configured days.
- **Shill-bid report:** flags patterns for staff review (bids from shared IPs/devices, accounts linked by bank account or controllers, bidders who only ever bid against one other bidder).

## 8. Data model

`auction_states` (listing, current bid, leader approval, ends_at, extensions, paused), `bids` (append-only: listing, approval, bidder number, amount, kind manual/proxy, placed_at, voided_at/by/reason), `max_bids` (encrypted amount, set_at), `increment_bands` (per listing or category default), `tender_offers` (encrypted amount, submitted_at), `auction_results` (winner, runner-up, final price, closed_at), `notification_preferences` (WhatsApp opt-in).

## 9. Permissions

New: `auctions.void_bid`, `auctions.open_tender` (two people required). Existing: `auctions.monitor`, `auctions.pause`, `auctions.settle`.

## 10. Testing and performance

- Unit tests of the engine: increments, proxy resolution (including equal maximums), soft close boundaries, ties.
- **Concurrency tests:** many simultaneous bids at the deadline produce one consistent winner and correct extensions (MySQL row locks).
- Load test before launch: 500 concurrent bidders on one listing and 2 000 viewers on the live feed; bid accepted and broadcast in under 300 ms at the 95th percentile.
- Security: bids only through authenticated, rate-limited endpoints; names never in broadcast payloads (test).

## 11. Acceptance criteria

1. An approved bidder with a received deposit bids; an unapproved, unpaid, staff or linked account cannot.
2. Two bidders with maximums: the system bids correctly, the earlier maximum wins a tie, and the feed shows only bidder numbers.
3. A bid in the last five minutes extends the close; simultaneous last-second bids resolve to one winner.
4. A sealed tender's amounts stay hidden until the deadline and opening needs two staff.
5. At close the winner and runner-up are recorded, everyone else's deposit is scheduled for refund, and the runner-up's is held.
6. CI green on MySQL for all three PRs; the load test meets its target.

## 12. Needed from Thabekhulu

- **Attorney-approved Rules of Auction** covering online, proxy and sealed-tender bidding, soft close, binding bids, voided bids and system faults; confirmation of **no vendor bids**.
- Increment bands per category; soft-close minutes and any cap; OTP threshold.
- A **Meta WhatsApp Business** account (verified business, sender number) and approval of the alert templates.
- Hosting that supports WebSockets (VPS or cloud; already on the checklist).
