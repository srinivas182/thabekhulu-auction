# PRD summary — agreed business rules (v1.0)

The full PRD is maintained with the client. This file keeps the rules developers build against. If this file and the PRD disagree, raise it before building.

## Scope

- **Phase 1:** public site and CMS, admin-created listings, buyer registration with automated KYC, deposits, live bidding, settlement, refunds, events with QR tickets, admin-assigned premium slots, growth and trust features. Seller module **built but switched off** (feature flag `sellers`).
- **Phase 2:** seller go-live, consortium bidding, Investor Club, post-auction offers, comparison, virtual tours, AI listing assistant, more currencies and languages.
- Currency ZAR, language English. Domain: thabekhuludevelopment.org.za (main domain).

## Pricing and deal types

- Internal per listing: seller price, re-innovation (renovation) cost. Public: **minimum selling price** = the lowest allowed bid.
- **Commission sale:** seller receives winning bid − platform fee %. Suggested minimum = seller net ÷ (1 − fee %). Admin may set higher, never lower.
- **Thabekhulu-renovated:** seller receives the fixed seller price; Thabekhulu keeps the rest. Minimum ≥ seller price + renovation cost.
- Platform fee %: default per category, editable per listing. VAT (15%) applies to the platform fee invoice. Buyer pays only the winning bid.
- Seller payouts decided and recorded by finance.

## Deposits

- Fixed per category in settings; editable per listing. Paid by every bidder before their first bid on that listing.
- Winner: deposit counts toward price (balance = bid − deposit).
- Runner-up: deposit held until the winner completes (admin-set limit). Others refunded N days after close (admin setting).
- Refunds only to a verified bank account in the bidder's own name. Bank details locked while a refund is pending.
- Forfeit on default capped at the lower of 10% of price or actual auction cost (CPA Reg. 24); excess auto-refunded. Admin warned when deposit > 10% of minimum.

## Bidding

- Bids ≥ minimum selling price, increase-only, admin-set increments. Proxy (max) bidding.
- Public feed shows bidder number + amount, never names.
- Server time is authoritative; bids processed under a Redis atomic lock; append-only bid ledger.
- Default duration 30 days (setting, overridable per listing). Optional soft close: bid in last 5 min extends 5 min.
- Step-up OTP above an admin-set bid amount. Staff and linked IDs cannot bid.

## Lifecycle

Draft → In review → Coming soon → Live → Closing soon (default 72 h window) → Processing → Sold, or No bids (hidden, relistable).
Sold shows price, date, bidder number — never the buyer's name. Extra badges: Premium, New (7 days), Hot (bid threshold), Renovated.

## Default

Winner misses the deadline → deposit forfeited (within cap) → property offered to runner-up → defaulter's bidding suspended 12 months. One account per SA ID / passport number.

## Other rules

- Seller identity hidden; listings say "Listed by Thabekhulu Development Group · Verified Seller". Book a call uses the Thabekhulu office calendar or phone.
- Documents per category: mandatory/optional; internal or verified-bidders-only; never public. Downloads watermarked and logged.
- Premium slots: per category + city + dates, three positions. Paid bookings win; admin fills unsold. 15-minute checkout hold.
- Seller packages: auction end date may not pass package expiry.
- Events: free or paid, online or in person; paid → gateway → QR ticket; staff QR check-in.
- Roles: super admin, auctioneer, listings manager, KYC officer, finance, content editor, support, buyer (unverified/verified), seller.
- Theme: super admin can change colours, logos, fonts and corner style for the website and the back office separately (draft → preview → publish, contrast check, version history, reset to brand defaults). Auction status colours are locked.
