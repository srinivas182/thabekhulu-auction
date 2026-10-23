# Sprint 4 — Investors and KYC: detailed specification

**Status:** Draft for approval · **Depends on:** Sprint 3 and 3Q (merged) · **Design source:** approved "Register to bid: identity verification" and "Buyer account" screens

## 1. Objective

Turn a registered buyer into an **approved bidder for a specific listing**: verified identity (or company), screened against sanctions and politically exposed persons, proof of funds where required, a verified refund bank account in their own name, signed data-room NDA, and staff approval per listing. Verification providers are **pluggable**: the super admin enables a provider, enters its keys and chooses which provider runs each check, with manual review always available as a fallback.

## 2. Delivery: three sub-sprints, one PR each

| Sub-sprint                                                  | Contents                                                                                                                                                                                                                                       |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **4a Verification framework and identity**                  | Provider framework (enable, keys, test, routing per check, webhooks), three provider adapters, manual-review fallback; buyer verification flow (SA ID or passport, selfie liveness, proof of address, marital status and spouse consent)       |
| **4b Companies, screening, funds, bank accounts, approval** | Company and trust bidders (CIPC, directors, beneficial owners, authority resolution); sanctions/PEP screening with re-screening; proof of funds; refund bank account verification and lock; KYC review queue; **per-listing bidding approval** |
| **4c Data room and buyer dashboard**                        | NDA click-through per listing, document access for verified / approved bidders, watermarked downloads, download log; buyer dashboard from the approved design                                                                                  |

## 3. Recommended verification providers

All three are built in from the start, off until the super admin enables them. One provider is enough to go live; the others give choice on price and cover.

|                                  | **Smile ID** (recommended primary)                                                         | **VerifyID / OmniCheck** (recommended SA alternative)                                     | **Sumsub** (recommended for foreign investors)      |
| -------------------------------- | ------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------- | --------------------------------------------------- |
| Focus                            | Pan-African, optimised for low-end phones and weak connections                             | South African, single API for local checks                                                | Global KYC, KYB and AML platform                    |
| SA ID against government records | Yes: ID number and photo from SA government sources, selfie matched to the photo on record | Yes: live Home Affairs status and DHA ID photo, with an offline fallback when DHA is down | Document and data checks; SA support stated         |
| Liveness / selfie                | Yes                                                                                        | Biometric options (fingerprint; facial via partners)                                      | Yes (document photo + video selfie)                 |
| Foreign passports                | Documents in 220+ countries                                                                | Limited (SA-focused)                                                                      | Worldwide                                           |
| Company (CIPC)                   | Yes: SA company records, directors and beneficial owners                                   | Yes: CIPC company checks                                                                  | Yes: KYB                                            |
| Sanctions / PEP                  | AML check                                                                                  | AML/PEP/crime screening                                                                   | AML screening **and ongoing monitoring**            |
| Bank account verification        | Yes                                                                                        | Yes (banking verification)                                                                | Yes                                                 |
| Typical use here                 | Default for SA citizens and SA companies                                                   | Lower-cost SA checks; backup when another provider is down                                | Foreign mining investors and ongoing AML monitoring |

Prices, contract terms and FICA/POPIA data-processing terms must be confirmed directly with each provider before go-live; published figures change. Thabekhulu holds the contract and keys with whichever providers they choose; the platform only stores the provider's reference and result.

## 4. Decisions needing approval

| #   | Decision                  | Recommendation                                                                                                                                                                                                                                                                          | Alternative                                                |
| --- | ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| K1  | Provider model            | **Pluggable adapters** behind one interface; super admin enables providers and routes each check type (SA identity, passport identity, liveness, company, sanctions/PEP, bank account) to one enabled provider; **manual review** always available                                      | Hard-wire one provider                                     |
| K2  | Credentials               | Stored **encrypted** in the database, entered by the super admin, never shown again after saving (only the last 4 characters); sandbox / live mode per provider; "Test connection" button                                                                                               | Keys in server `.env` (needs a developer for every change) |
| K3  | Data minimisation (POPIA) | Store the provider's reference, outcome, score and the fields needed for FICA records; **no raw selfie video or fingerprint data** kept on our servers; ID document images kept privately, encrypted, for the FICA retention period (5 years after the relationship ends), then deleted | Store everything                                           |
| K4  | Results                   | Every result normalised to **Passed / Needs review / Failed** with reasons; anything not a clear pass goes to the KYC review queue; staff decisions recorded with a reason                                                                                                              | Trust provider results blindly                             |
| K5  | Webhooks                  | Provider callbacks verified by signature, idempotent (each event processed once), queued                                                                                                                                                                                                | Polling only                                               |
| K6  | Bidding eligibility       | **Per listing**: verified + screened + bank account verified + deposit received (Sprint 5) + NDA (if data room) + staff approval → "Approved bidder" for that listing only                                                                                                              | One global approval                                        |

## 5. 4a — Verification framework and identity

### 5.1 Provider framework

- Back office → Settings → **Verification providers** (permission `verification.providers`, super admin by default).
- Per provider: enable/disable, mode (sandbox / live), API keys (encrypted), webhook secret, "Test connection", last successful call.
- **Routing table:** for each check type choose the provider (only enabled providers that support the check are offered) or **Manual review**.
- Health: if a provider fails or times out, the check is queued for retry, then falls back to manual review; staff are alerted.
- Every provider call and result is logged (no personal documents in logs) and audit-logged.

### 5.2 Buyer verification flow (approved design, step 2 of 4)

1. Document type: SA ID or passport (non-SA).
2. ID number (validated: date of birth and checksum) or passport details; **one account per ID / passport** (already a rule).
3. Document upload (front/back) or camera capture.
4. **Selfie liveness** in the provider's secure web component (or upload for manual review).
5. Proof of address (under 3 months).
6. Marital status; **married in community of property → spouse details and signed consent** required before bidding.
7. Submit → provider checks → result. Clear pass: verified. Otherwise: KYC review queue.

Statuses: Not started → In progress → Submitted → Verified / Needs review / Rejected (with reason the buyer can see) → Expired (re-verify after a set period, default 24 months).

### 5.3 Data model (4a)

`verification_providers` (key, enabled, mode, encrypted credentials, health), `verification_routes` (check type → provider), `verifications` (user, type, provider, provider reference, status, outcome, score, reasons, reviewed by, reviewed at), `verification_documents` (private, encrypted, retention date), `spouse_consents`.

## 6. 4b — Companies, screening, funds, bank accounts, approval

- **Company or trust bidders:** registration number → CIPC lookup (company) or trust deed and letters of authority (trust, manual); directors and **beneficial owners (25%+)**; each controlling person verified; signed board resolution authorising the bidder.
- **Sanctions and PEP screening** (including the FIC targeted financial sanctions list via the provider): at verification, before each approval, and on a schedule (default monthly) for active bidders; a hit suspends bidding until reviewed.
- **Proof of funds:** required where a listing's minimum is above a setting (default R5 million) or for all Mining listings; bank letter or statement uploaded; reviewed by a KYC officer; valid 90 days.
- **Refund bank account:** account holder name must match the verified person or company (provider verification, or bank letter for manual review); **locked while a refund or deposit is pending** (rule from the PRD).
- **KYC review queue** (`kyc.review`): side-by-side document view, provider result and reasons, approve / reject / request more information (buyer notified by email), four-eyes for rejections of company bidders.
- **Per-listing bidding approval** (`bidders.approve`): checklist for the listing (verified, screened in the last 30 days, bank account verified, proof of funds if required, NDA if data room, deposit received from Sprint 5), then approve; approval is listed on the buyer's dashboard and in the listing's bidder register.

## 7. 4c — Data room and buyer dashboard

- **NDA click-through** per listing (text from settings, versioned, attorney-reviewed), recorded with name, time, IP and NDA version.
- Document access by visibility: verified bidders see "Verified bidders" documents; approved bidders who signed the NDA see the data room.
- **Watermarked downloads:** each PDF page stamped with the bidder's name, bidder number, date and "Confidential"; images watermarked; files streamed, never public URLs.
- **Download log** per document and bidder, visible to staff; unusual volume flagged.
- **Buyer dashboard** (approved design): needs-your-attention items, my auctions, verification status, bank account, quotes (from 3Q), documents.

## 8. Permissions

New: `verification.providers`, `kyc.review`, `kyc.approve_company`, `bidders.approve`, `dataroom.view_logs`. Defaults: Super admin (all); KYC officer (`kyc.review`, `kyc.approve_company`, `dataroom.view_logs`); Auctioneer (`bidders.approve`, `dataroom.view_logs`).

## 9. Tests (highlights)

- Provider adapters against recorded sandbox responses (no live calls in CI); routing respects enabled providers and capabilities; fallback to manual review on failure.
- Credentials encrypted at rest and never returned to the browser; only the last 4 characters shown.
- Webhook signature verification and idempotency.
- SA ID checksum and date of birth; duplicate ID refused; spouse consent required in community of property.
- Screening hit suspends bidding; scheduled re-screening.
- Bank account name mismatch rejected; account locked while a refund is pending.
- Per-listing approval checklist; approval for one listing does not allow bidding on another.
- NDA required before data-room documents; watermark contains bidder details; every download logged.
- Documents and selfies never reachable by URL without authorisation; retention deletion job.

## 10. Acceptance criteria

1. Super admin enables Smile ID in sandbox, enters keys, tests the connection and routes SA identity to it; disabling it moves checks to manual review.
2. An SA buyer completes verification on a phone and is verified automatically; a doubtful case lands in the review queue and a KYC officer decides with a reason.
3. A company bidder is verified with directors and beneficial owners.
4. A screening hit blocks approval until reviewed.
5. An approved bidder for one mine signs the NDA, downloads a watermarked data-room document, and the download appears in the log; the same bidder cannot open another mine's data room.
6. CI green on MySQL for all three PRs.

## 11. Needed from Thabekhulu

- Choice of provider(s) and signed agreements; **sandbox API keys** for development, live keys before launch (entered by their super admin).
- Confirmation from their compliance adviser of Thabekhulu's **FICA obligations** (estate agents are accountable institutions under FICA: FIC registration and a Risk Management and Compliance Programme) and the checks that programme requires.
- NDA text and spouse-consent form (attorney-reviewed).
- Proof-of-funds threshold and re-verification period.

## 12. Risks

| Risk                                         | Mitigation                                                                                                                             |
| -------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Home Affairs or a provider is unavailable    | Retry, then manual review; a second provider can be routed for the same check                                                          |
| Personal data breach                         | Encryption at rest, private storage, no raw biometrics, access logs, retention deletion, POPIA-aligned processing terms with providers |
| Provider lock-in                             | One interface; switching provider is a routing change in settings                                                                      |
| Foreign investors cannot pass SA-only checks | Passport route via a global provider (Sumsub) or manual review                                                                         |
