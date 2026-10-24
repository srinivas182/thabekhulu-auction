# Sprint 8 — Seller module (off), SEO/AEO, growth, investor register and reports: detailed specification

**Status:** Draft for approval · **Depends on:** Sprints 3–7 (merged) · **Source:** sprint plan line 8 and PRD summary

## 1. Objective

Finish Phase 1's remaining scope: build the seller module but keep it **switched off**; make listings and events easy to find in search engines and AI answer engines; add the growth features Thabekhulu chooses; and give staff an investor register, financial reports for the accountant, and one place to review suspicious activity.

## 2. Delivery: three sub-sprints, one PR each

| Sub-sprint                                             | Contents                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **8a Seller module (off)**                             | Seller accounts (role "seller"), seller verification (identity plus proof of ownership or authority to sell), property submission that becomes a lead and then a draft listing, seller dashboard (status, bid count, result, payout status), seller packages with expiry (an auction may not end after its package expires). Everything behind the `seller_module` feature flag, **off by default**; tests run with it on                                     |
| **8b SEO/AEO and growth**                              | Structured data audit and additions (listings, events, organisation, breadcrumbs, FAQ blocks); sitemap for events; Open Graph images per listing; concise answer blocks for answer engines; plus the growth features chosen in section 4 (G1)                                                                                                                                                                                                                 |
| **8c Investor register, reports, suspicious activity** | Investor register (verified buyers and entities with verification, screening, deposits, bids and wins); financial reports per period (deposits held, refunds, forfeitures, fees invoiced, VAT, payouts) with CSV for the accountant, reconciling with Sprint 5c; one suspicious-activity view that brings together integrity flags, screening matches, unusual downloads, payment mismatches, defaults and repeated failed sign-ins or codes, with case notes |

## 3. Rules from the PRD

- Seller module **built but switched off** in Phase 1; seller go-live is Phase 2.
- Seller identity stays hidden on the website: "Listed by Thabekhulu Development Group · Verified Seller".
- Seller packages: the auction end date may not pass the package expiry.
- Roles include seller.

## 4. Decisions needing approval

| #   | Decision                 | Recommendation                                                                                                                                                                                                                            | Alternative           |
| --- | ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------- |
| G1  | Growth features (choose) | **Saved searches with email alerts** (new matching listings), **watchlist** (follow a listing; closing reminders), **share links** with campaign tags, **newsletter sign-up** with POPIA double opt-in. Referral rewards left for Phase 2 | Different set         |
| T1  | Seller flag              | `seller_module` off; with it off, seller routes return 404 and no seller UI shows; staff can still test it in a staging environment                                                                                                       | Not built             |
| T2  | Seller verification      | Reuses the Sprint 4 identity flow plus documents proving ownership or authority (title deed, mining right, board resolution), reviewed in the KYC queue                                                                                   | Separate flow         |
| T3  | Seller submissions       | A seller's submission becomes a "Sell with us" lead (Sprint 3c) that staff convert into a draft listing; sellers never publish directly                                                                                                   | Self-publish          |
| T4  | Structured data          | `RealEstateListing`/`Offer` for listings, `Event` with offers for events, `Organization`, `BreadcrumbList`, `FAQPage` for Q&A blocks; never internal prices or seller details (existing PublicListing allow-list)                         | Minimal               |
| T5  | Investor register        | Staff with `buyers.view` see it; exports are audit-logged; POPIA: only what each role needs (KYC officers see verification detail, finance sees money)                                                                                    | One full view for all |
| T6  | Financial reports        | Period reports from the ledger, invoices and payouts; totals must reconcile with Sprint 5c's reconciliation; CSV per report                                                                                                               | Spreadsheet only      |
| T7  | Suspicious activity      | One review queue with notes and outcomes; **the platform does not file reports**: suspicious transaction reports go to the FIC through Thabekhulu's compliance officer, and the platform provides an evidence export                      | Automatic reporting   |

## 5. Tests (highlights)

Flag off hides every seller route and screen; flag on: seller verification, submission to lead to draft, package expiry enforced on auction dates; structured data valid and free of internal fields; saved-search alerts sent once per new match; watchlist reminders; double opt-in; register exports logged; report totals equal the reconciliation; suspicious-activity items created from each source.

## 6. Needed from Thabekhulu

- **Choice of growth features (G1)** and newsletter tool preference (built-in email or an external service).
- Seller packages: names, prices, durations (for Phase 2, but the rules are built now).
- Which reports the accountant needs and in what format; the financial year end.
- The compliance officer who reviews suspicious activity and files with the FIC.
