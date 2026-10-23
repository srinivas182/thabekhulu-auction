# Sprint 3Q — Quotations: detailed specification

**Status:** Draft for approval · **Depends on:** Sprint 3 (merged) · **Independent of:** auctions, payments, KYC

## 1. Objective

A standalone quotation tool inside the back office. Thabekhulu staff create professional, branded quotations for buyers and investors (property, renovation, mining due diligence, development or services), get them approved, send them, and track them to acceptance. Clients view, download, accept or decline online. It does not depend on the auction modules, but a quote can optionally be linked to a listing.

## 2. Delivery: two sub-sprints, one PR each

| Sub-sprint        | Contents                                                                                                                                                |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **3Q-a Builder**  | Clients, price catalogue, templates, quote builder with all sections, totals and VAT, numbering, versions, branded PDF                                  |
| **3Q-b Workflow** | Internal approval, sending by email with a secure link, view tracking, online acceptance and decline, expiry and reminders, buyer-account view, reports |

## 3. Decisions needing approval

| #   | Decision       | Recommendation                                                                                                                                  | Alternative                                                |
| --- | -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| Q1  | PDF generation | **barryvdh/laravel-dompdf**: pure PHP, works on any host and in CI                                                                              | Browsershot (needs a headless Chrome on the server)        |
| Q2  | Client access  | **Signed, expiring link** per quote (no account needed); registered buyers also see their quotes in their account                               | Require every client to register                           |
| Q3  | Acceptance     | Typed full name + "I accept" tick box; record time, IP address, browser and a **SHA-256 fingerprint of the exact version accepted**             | Third-party e-signature service (cost; can be added later) |
| Q4  | Money          | Whole cents (integer) for quote amounts, VAT at a configurable rate (default 15%), rounded per line                                             | Floating point (rounding errors)                           |
| Q5  | Numbering      | `TDG-Q-2026-0001`, sequential per year, gap-free, assigned when a draft is first saved; revisions keep the number with a version suffix (`-v2`) | Random references                                          |

## 4. 3Q-a — Builder

### 4.1 Clients

Name, company (optional), email, phone, physical address, VAT number (optional), notes. Can be linked to a registered buyer account (matched by email). Clients are never deleted once a quote has been sent (audit trail).

### 4.2 Price catalogue

Standard items staff pick instead of retyping: name, description, unit (each, hour, day, m², ha, month, year), unit price, VAT applicable (yes/no), type (**once-off** or **recurring monthly / annual**), active. Price changes never alter quotes already created.

### 4.3 Templates

Named starting points (e.g. "Renovation", "Mining due diligence", "Development project") that pre-fill: sections enabled, line items, scope, assumptions, exclusions, terms, payment schedule and validity period. Seeded with three templates whose wording is marked as needing Thabekhulu's review.

### 4.4 Quote builder

| Section                               | Contents                                                                                                                          |
| ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Header                                | Number, version, date, valid until (default 30 days, setting), client, prepared by, optional linked listing, title                |
| Line items                            | Description, quantity, unit, unit price, discount (% or amount), VAT on/off; from the catalogue or typed; reorder; group headings |
| Ongoing costs                         | Recurring lines (monthly / annual) kept **separate from once-off totals**                                                         |
| Delivery schedule                     | Milestones with a start date and duration or end date; shown as a simple timeline                                                 |
| Payment schedule                      | Deposit % and milestone payments (percentages must total 100%), each with a due description or date                               |
| Payment method                        | Bank details (from settings), escrow instructions, or other text                                                                  |
| Scope, assumptions, exclusions, terms | Rich text (same editor and HTML cleaning as the CMS)                                                                              |
| Internal notes                        | Staff only; never on the PDF or client page                                                                                       |

Totals: once-off subtotal, discount, VAT, total; recurring per month and per year with VAT, shown separately. All in ZAR.

### 4.5 Versions

Editing a sent quote creates a new version (v2, v3…). Earlier versions stay readable, are marked "Superseded", and can no longer be accepted.

### 4.6 Branded PDF

Thabekhulu letterhead from the published website theme (logo, colours, fonts), company registration number, VAT number, address, contact details and bank details from settings; page numbers; "Valid until" prominent; the acceptance fingerprint printed on accepted copies. A4.

### 4.7 Settings (new "Quotations" group)

Company legal name, registration number, VAT number, address, bank details, VAT rate (15%), default validity (30 days), approval threshold (R), reminder days before expiry (5), number prefix (`TDG-Q`).

## 5. 3Q-b — Workflow

| Status              | Meaning                                                                   |
| ------------------- | ------------------------------------------------------------------------- |
| Draft               | Being prepared                                                            |
| Awaiting approval   | Total is above the approval threshold, or the creator lacks `quotes.send` |
| Approved            | Ready to send                                                             |
| Sent                | Emailed with PDF and secure link                                          |
| Viewed              | Client opened the link (first and latest view times recorded)             |
| Accepted / Declined | Client's decision, with optional decline reason                           |
| Expired             | Past "valid until" without a decision (scheduled job)                     |
| Superseded          | A newer version was sent                                                  |

- **Approval:** quotes above the threshold need someone with `quotes.approve` (approver cannot approve their own quote).
- **Sending:** email with the PDF attached and a signed link valid until the expiry date; cc option; message text editable.
- **Client page:** branded, mobile-friendly view of the quote with Download PDF, Accept (typed name + tick box) and Decline (optional reason). After acceptance the page shows the acceptance record.
- **Notifications:** staff are emailed on view, acceptance and decline; the client receives a confirmation copy of the accepted PDF.
- **Reminders:** an email to the client a set number of days before expiry (once).
- **Buyer accounts:** registered buyers see their quotes under "My account → Quotes".
- **Reports:** quotes by status, total value in progress (sent + viewed), acceptance rate by month, quotes expiring in the next 7 days; CSV export.
- **Audit:** every create, edit, approval, send, view, acceptance, decline and expiry is in the audit log.

## 6. Permissions

New: `quotes.create`, `quotes.approve`, `quotes.send`, `quotes.view_all` (without it, staff see only their own quotes), `quotes.settings`.
Default roles: Super admin (all); Finance (create, approve, send, view all); Auctioneer and Listings manager (create, send); others none. Configurable through custom roles.

## 7. Data model

`clients`, `catalogue_items`, `quote_templates`, `quotes` (number, version, status, client, listing, totals in cents, valid_until, fingerprint, accepted name/IP/agent/time, decline reason), `quote_lines` (once-off and recurring), `quote_milestones`, `quote_payments`, `quote_events` (sent, viewed, reminded…), settings group `quotations`.

## 8. Tests (highlights)

- Totals and VAT per line with discounts; recurring kept separate; payment percentages must total 100%.
- Gap-free numbering under concurrent creation; versions keep the number.
- Approval threshold and self-approval blocked; permissions per role; staff see only their own quotes without `quotes.view_all`.
- Signed links: tampered or expired links refused; superseded or expired quotes cannot be accepted; acceptance records name, time, IP and fingerprint; fingerprint changes if any figure changes.
- PDF generated with letterhead, totals and terms; internal notes never on the PDF or client page.
- Expiry job and single reminder; notifications to staff and client.
- HTML cleaning on all rich text.

## 9. Acceptance criteria

1. Staff build a mining due-diligence quote from a template with catalogue items, ongoing costs, a delivery schedule and a 50/50 payment schedule; totals and VAT are correct.
2. A quote above the threshold goes to approval; the creator cannot approve it; Finance approves.
3. The client receives the email, opens the link on a phone, downloads the PDF and accepts; staff are notified; the acceptance record and fingerprint appear on the PDF.
4. Editing a sent quote creates v2; v1 can no longer be accepted.
5. Reports show the pipeline; every step is in the audit log; CI green on MySQL for both PRs.

## 10. Needed from Thabekhulu

Company legal name, registration and VAT numbers, address and bank details for the letterhead; standard terms and conditions (reviewed by their attorney); the approval threshold; which staff may create, approve and send; wording for the three starter templates.

## 11. Risks

| Risk                       | Mitigation                                                                                             |
| -------------------------- | ------------------------------------------------------------------------------------------------------ |
| Acceptance disputed        | Fingerprint of the exact version, time, IP and browser recorded and printed on the PDF; audit log      |
| Quote emails land in spam  | Sent from the platform domain with SPF/DKIM (Sprint 9 hosting); link also visible in the buyer account |
| Terms not legally reviewed | Templates and terms flagged "needs review" until replaced                                              |
