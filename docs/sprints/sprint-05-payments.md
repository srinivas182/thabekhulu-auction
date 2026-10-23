# Sprint 5 — Payments, deposits and refunds: detailed specification

**Status:** Draft for approval · **Depends on:** Sprint 4 (merged) · **Feeds:** Sprint 6 (bidding needs a confirmed deposit), Sprint 7 (settlement)

## 1. Objective

Collect each bidder's deposit for a listing, hold it safely, confirm it automatically where possible, and refund it to the bidder's verified account in their own name, with a complete, append-only money trail and reconciliation. Payment methods are **pluggable**, like verification providers: the super admin enables providers and chooses which handles each payment type, with **EFT to Thabekhulu's trust account** always available.

## 2. Delivery: three sub-sprints, one PR each

| Sub-sprint | Contents |
| --- | --- |
| **5a Deposits and the money trail** | Payment provider framework; deposit by EFT to the trust account with a unique reference; bank-statement import and matching; append-only ledger; receipts; **compliance gate** (no deposits until the listing's checklist is signed off); "deposit received" becomes a blocking approval item; refund-account lock |
| **5b Escrow and gateways** | TradeSafe escrow adapter for deposits; instant-EFT and card gateway adapters for small payments (event tickets, premium slots, quotations); signed, idempotent webhooks; payment status pages |
| **5c Refunds and reconciliation** | Refund rules by deposit terms; runner-up hold; refund schedule after close; finance approval (four eyes); refund batches and proof of payment; daily reconciliation report; finance exports |

## 3. Recommended payment methods

| | **EFT to the trust account** (always on) | **TradeSafe escrow** (recommended for deposits) | **Ozow** instant EFT (small payments) | **Paystack** cards (small payments) |
| --- | --- | --- | --- | --- |
| What it is | Bidder pays by bank transfer with a unique reference; matched from bank statements | South African digital escrow, backed by Standard Bank; GraphQL API with a sandbox; escrow-secured or direct ("Relay") flows; refunds handled by TradeSafe | Instant EFT from South African banks with instant confirmation | Card payments; owned by Stripe; developer-friendly API |
| Best for | Any deposit size; no provider fees | Large deposits held by an independent party; investor confidence | Tickets, premium slots, small fees | Card payers, international cards |
| Confirmation | Statement import (5a); bank API later if Thabekhulu's bank offers one | Webhook | Webhook | Webhook |
| Typical cost | Bank charges only | To confirm with TradeSafe | Around 1.5–2.5% per transaction (published comparisons; confirm) | Card rates (confirm) |

Instant EFT and cards are **not** used for large deposits: bank instant-EFT limits and card fees make them unsuitable for amounts like R1.2 million. Fees and limits must be confirmed directly with each provider before contracts are signed.

**Legal check for Thabekhulu's attorney:** under the Property Practitioners Act, money a property practitioner holds for others must be kept in a trust account. Confirm whether deposits may be held by a third-party escrow (TradeSafe) instead of, or alongside, Thabekhulu's own trust account, and how PPRA and Fidelity Fund rules apply.

## 4. Decisions needing approval

| # | Decision | Recommendation | Alternative |
| --- | --- | --- | --- |
| P1 | Provider model | **Pluggable adapters** with super-admin enable, keys (encrypted, write-only), sandbox/live and routing per payment type; trust-account EFT always available | Hard-wire one provider |
| P2 | Money trail | **Append-only ledger**: every movement is a new entry (received, held, applied, refunded, forfeited by decision); balances are calculated, never edited; amounts in cents | Editable balances |
| P3 | Matching EFTs | Unique reference per deposit (e.g. `TDG-D-4821-7Q3K`, with a check character); bank statement import (CSV / OFX) matches reference and amount; anything unmatched or short goes to a finance queue | Manual matching only |
| P4 | Compliance gate | A listing accepts deposits only when **every** compliance checklist item (Sprint 3) is signed off; enforced on the server | Warning only |
| P5 | Refunds | Only to the bidder's **verified** account in their own name; account **locked** from deposit until refund; refunds need two finance users (prepare + approve); never automatic forfeiture of security deposits | Single approver |
| P6 | Refund timing | Unsuccessful bidders: N days after close (setting, default 5 business days); runner-up: held until the winner completes or the hold period ends (setting); winner: per deposit terms in Sprint 7 | Manual timing |
| P7 | Receipts | PDF receipt for every confirmed deposit and refund, numbered (`TDG-R-2026-0001`), emailed and in the buyer's account | Email text only |

## 5. 5a — Deposits and the money trail

- **Deposit request:** an approved-pending or approved bidder starts a deposit for a listing; amount from the listing; method chosen from what's enabled (EFT always). EFT shows Thabekhulu's trust account details (settings) and the unique reference.
- **Compliance gate:** if the listing's compliance checklist isn't fully signed off, the deposit button explains that deposits open soon; the server refuses the request.
- **Statement import** (`deposits.confirm`): finance uploads a CSV/OFX export; lines matched by reference and amount; exact matches confirmed automatically (four-eyes not needed for confirmation); partial, over- or unmatched payments go to a queue to resolve or return. Every import kept with its file hash; duplicate lines ignored.
- **On confirmation:** ledger entry, receipt, buyer email, the approval checklist's "deposit received" item turns green, and the refund account is **locked**.
- **Bidder view:** deposit status on the listing bid panel and the dashboard (awaiting payment, received, refund scheduled, refunded).

## 6. 5b — Escrow and gateways

- TradeSafe: create the escrow transaction for the deposit, send the bidder to pay, confirm by webhook, and release or refund through TradeSafe's API (5c).
- Ozow and Paystack: hosted checkout for small payments; webhook confirmation; refunds through the provider.
- Webhooks verified by signature and processed once (same pattern as verification providers, ADR 0010).
- Adapters whose API details can't be confirmed without sandbox access are marked "not ready" and cannot be routed, as with VerifyID.

## 7. 5c — Refunds and reconciliation

- **Rules engine:** at auction close (Sprint 6) each deposit is classified: unsuccessful → refund after N days; runner-up → hold; winner → per deposit terms (Sprint 7). Until bidding exists, finance can schedule refunds manually (e.g. withdrawn listing).
- **Refund workflow:** finance prepares a refund batch (account verified and locked, amount from the ledger); a **second** finance user approves; payment made by bank (EFT batch file or manual) or through the provider; proof of payment recorded; receipt emailed; account unlocked.
- **Reconciliation:** daily report of deposits received, held, refunded and outstanding per listing, matched against the bank or provider; differences flagged.
- **Exports:** CSV for the accountant; audit log for every step.

## 8. Data model

`payment_providers`, `payment_routes`, `deposits` (listing, bidder, approval, amount, reference, method, status), `ledger_entries` (append-only: deposit, type, amount, balance after, source, reference, user, time), `bank_imports` and `bank_lines`, `refunds` and `refund_batches` (prepared by, approved by, proof), `receipts`, settings group `payments` (trust account details, refund days, runner-up hold days).

## 9. Permissions

New: `payments.providers` (super admin), `deposits.confirm` (finance: import statements, resolve the queue), `refunds.prepare`, `refunds.approve` (finance; never the same person for one batch), `finance.reports`. Existing `deposits.view` for auctioneers.

## 10. Tests (highlights)

- Compliance gate refuses deposits on unsigned listings; deposit only for applied/approved bidders.
- Reference uniqueness and check character; statement matching (exact, partial, duplicate line, unknown reference).
- Ledger is append-only (updates and deletes refused); balance = sum of entries; cents arithmetic.
- Deposit confirmation turns the checklist item green and locks the refund account.
- Refunds: only to verified own-name accounts; preparer cannot approve; batch amounts equal ledger balances; account unlocks after refund.
- Webhooks: signature, idempotency, provider adapters against recorded sandbox responses.
- Receipts numbered without gaps; PDFs contain the right figures.

## 11. Acceptance criteria

1. A listing with an unsigned compliance item refuses deposits; after sign-off, an approved bidder sees trust-account EFT details and a unique reference.
2. Finance imports a bank statement; the matching deposit is confirmed, a receipt emailed, the checklist item turns green and the refund account locks.
3. An unmatched line lands in the finance queue and is resolved with a reason.
4. A refund is prepared by one finance user and approved by another, paid, recorded with proof and receipted; the account unlocks.
5. The reconciliation report balances to the cent; CI green on MySQL for all three PRs.

## 12. Needed from Thabekhulu

- **Attorney's confirmation** on trust account vs escrow (section 3), and the Rules of Auction wording on deposits and refunds.
- **Trust account details** (bank, account name, number, branch) and a sample **bank statement export** (CSV/OFX) from that bank.
- Choice of providers and **sandbox keys** (TradeSafe; Ozow and/or Paystack).
- Refund timing (days after close) and runner-up hold period.
- Who in finance prepares and who approves refunds.

## 13. Risks

| Risk | Mitigation |
| --- | --- |
| Deposits held in the wrong kind of account (PPRA) | Attorney confirmation before go-live; trust-account EFT available regardless |
| Mismatched or partial EFTs | Unique references with check character; finance queue; never auto-confirm partial amounts |
| Refund paid to the wrong account | Verified own-name accounts only; lock from deposit to refund; two-person approval |
| Provider outage | Trust-account EFT always available; webhook retries; reconciliation catches gaps |
