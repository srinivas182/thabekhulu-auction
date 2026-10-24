# Sprint 7 — Settlement, events and premium: detailed specification

**Status:** Draft for approval · **Depends on:** Sprints 5 (ledger, refunds) and 6 (results, runner-up) · **Source:** PRD summary (settlement, default, events, premium) and sprint plan

## 1. Objective

Take every closed auction to a recorded outcome: the winner completes (deposit applied or refunded per its terms, seller paid, fee invoiced, transfer tracked) or defaults (capped forfeiture by decision, excess refunded, property offered to the runner-up, defaulter suspended). Add auction events with QR tickets and admin-assigned premium slots.

## 2. Rules from the PRD

- **Public result:** Sold shows price, date and **bidder number, never the buyer's name**. No-bids listings are hidden from browsing and can be relisted. (Sprint 6c hides the price publicly; 7d corrects this.)
- **Mining:** the outcome reads **"Preferred bidder: subject to approvals"**; a winning bid does not by itself transfer a mining right (the transfer of a mining right needs the Minister's consent under section 11 of the MPRDA; to be confirmed by Thabekhulu's attorney).
- **Runner-up:** deposit held until the winner completes (admin-set limit, Sprint 5/6).
- **Default:** winner misses the deadline → deposit forfeited **within the cap: the lower of 10% of the price or the actual auction cost**; excess refunded → property offered to the runner-up → defaulter's bidding suspended 12 months (setting).
- **Security deposits** (Mining default) are **never forfeited automatically**: only by a recorded manual decision under the legally reviewed sale terms.
- **Money:** buyer pays only the winning bid; seller receives the bid less the platform fee (commission sale) or the fixed seller price (Thabekhulu-renovated); **VAT (15%) applies to the platform-fee invoice**; seller payouts are decided and recorded by finance.

## 3. Delivery: four sub-sprints, one PR each

| Sub-sprint | Contents |
| --- | --- |
| **7a Settlement and default** | Settlement case per sold listing with deadlines; winner steps (sale agreement signed, balance or guarantees, transfer attorney appointed); reminders; default declaration with capped forfeiture, excess refund and 12-month suspension; runner-up offer and acceptance; deposit applied or released at completion |
| **7b Payouts, invoices and transfer tracker** | Seller payout (two-person approval, proof); numbered platform-fee tax invoices with VAT; transfer tracker (residential and mining stages) visible to staff and the buyer |
| **7c Events and QR tickets** | Events (free or paid, online or in person, capacity); paid tickets via the small-payments provider (Paystack); signed QR tickets by email; staff check-in by camera scan or code; no double entry |
| **7d Premium slots, badges and public results** | Admin-assigned premium slots (category + city + dates, three positions); badges New, Hot, Renovated, Premium; public Sold display (price, date, bidder number); No-bids hidden and relistable |

## 4. Decisions needing approval

| # | Decision | Recommendation | Alternative |
| --- | --- | --- | --- |
| S1 | Outcome labels | Residential and land: **"Sold, pending transfer"**; Mining: **"Preferred bidder: subject to approvals"**; public shows price, date and bidder number only | One label |
| S2 | Settlement deadlines | Settings with per-listing override: sale agreement signed within 5 business days; balance paid or guarantees delivered within 30 days (Mining: per the sale terms); staff record each step with a document and date; reminders at 7 and 2 days | Free-form follow-up |
| S3 | Default | Declared by a person (`auctions.settle`) with a reason after a missed deadline; proposed forfeiture = min(10% of price, actual auction cost entered by finance, deposit held); **security deposits need an extra "forfeiture approved under the sale terms" sign-off**; a second person confirms; forfeiture is a ledger entry; any excess is refunded through the Sprint 5 refund batches; the defaulter is suspended for 12 months (blocks applying and bidding) | Automatic forfeiture |
| S4 | Runner-up | After a default the runner-up is offered the property at **their own highest bid**, with N days to accept (setting, default 5); acceptance cancels their scheduled refund and opens a new settlement case; decline or expiry releases their refund | Re-auction only |
| S5 | Deposit at completion | **Applied** terms: a ledger "applied" entry moves the deposit to the purchase price; **security** terms: refunded through the normal refund batch once completion is recorded | Manual only |
| S6 | Seller payout | Finance prepares the payout (commission sale: price − fee; renovated: fixed seller price), a different finance user approves, proof of payment uploaded; payout and fee invoice linked to the settlement case | Single approver |
| S7 | Fee invoice | Numbered tax invoice (`TDG-INV-YYYY-NNNN`) to the seller for the platform fee plus 15% VAT, from the company details in settings; VAT treatment of renovated sales to be confirmed by the accountant before go-live | Manual invoices |
| S8 | Transfer tracker | Stages, each with date and optional document: residential/land — agreement signed, balance or guarantees, transfer attorney instructed, lodged at the Deeds Office, registered; Mining adds section 11 consent applied for and granted; buyer sees progress in their dashboard | Notes only |
| S9 | Events and tickets | Free or paid events; paid via Paystack (Sprint 5b); each ticket a signed, single-use QR code; staff check-in page scans with the phone camera or accepts the code; capacity enforced | Paper lists |
| S10 | Premium slots | Admin-assigned in Phase 1 (paid booking is Phase 2): per category + city + date range, positions 1–3, no overlaps; badges computed (New: 7 days; Hot: bid threshold; Renovated: deal type; Premium: active slot) | Manual "featured" only |

## 5. Data model (outline)

`settlement_cases` (listing, approval, kind sale/runner-up offer, status, deadlines), `settlement_steps` (case, step, done_at, by, document), `defaults` (case, reason, auction cost, proposed and confirmed forfeiture, decided/confirmed by), `suspensions` (user, until, reason), `payouts` (case, amount, prepared/approved by, proof), `invoices` + sequence, `transfer_stages`, `events`, `tickets` (event, holder, token hash, payment ref, checked_in_at/by), `premium_slots`.

## 6. Permissions

New: `settlement.manage` (steps, deadlines), `defaults.confirm` (second person), `payouts.approve` (never the preparer), `events.manage`, `events.check_in`. Existing: `auctions.settle`, `payouts.view`, `payouts.record`, `premium.manage`.

## 7. Tests (highlights)

Deadlines and reminders; default cap arithmetic (10% vs auction cost vs deposit held) in cents; security deposits cannot be forfeited without the extra sign-off; excess refund scheduled; suspension blocks applying and bidding; runner-up acceptance cancels their refund and expiry releases it; applied deposit ledger entry; payout two-person rule; invoice VAT and gap-free numbers; QR tickets single-use and capacity; premium slot overlap refused; public Sold shows price and bidder number but never the name.

## 8. Acceptance criteria

1. A sold residential listing reaches "Registered" with the applied deposit counted, the seller paid by two finance users, and a VAT invoice issued.
2. A winner who misses the deadline is declared in default; the forfeiture never exceeds the cap; the excess is refunded; the runner-up accepts and a new case opens; the defaulter cannot bid for 12 months.
3. A Mining listing shows "Preferred bidder: subject to approvals" and tracks section 11 consent.
4. A paid event ticket is bought by card, emailed as a QR code, scanned once at the door, and refused on a second scan.
5. Premium slots show in their category and city for their dates only. CI green on MySQL for all four PRs.

## 9. Needed from Thabekhulu

- **Attorney:** conditions of sale (deadlines, guarantees, default and forfeiture clause including "actual auction cost"), the Mining sale terms, and confirmation of the section 11 process wording.
- **Accountant:** VAT treatment of the platform fee and of Thabekhulu-renovated sales; invoice requirements; company VAT number.
- Settlement deadlines; runner-up acceptance period; suspension period (default 12 months).
- First events planned (dates, venues, prices, capacity); premium slot plan per category and city.
