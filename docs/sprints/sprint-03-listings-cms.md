# Sprint 3 — Listings and CMS: detailed specification

**Status:** Approved (revision 2, after the investor brief) · **Depends on:** Sprint 2 (merged), Sprint 1 designs (approved) · **Design source:** Thabekhulu design system + approved canvas screens (Home, Auctions, Listing, Admin listing pricing)

## 1. Objective

Thabekhulu staff can set up categories, create listings with full pricing rules, media and documents, take them through review, and publish them to a fast, server-rendered public website with a CMS for pages, blog, gallery and menus. Bidding, deposits and KYC are not in this sprint: the bid panel shows a "Bidding opens soon" state.

## 2. Delivery: three sub-sprints, one PR each

| Sub-sprint             | Contents                                                                                                                                 |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| **3a Catalogue setup** | Design-system alignment (incl. input border fix), categories with defaults, category attributes, document types, provinces and cities    |
| **3b Listings**        | Listing model, deal types and pricing rules, media, documents, auction dates, review workflow, public Auctions and Listing pages, search |
| **3c CMS and leads**   | Home page sections, pages, blog, gallery, menus, Sell with us leads, contact enquiries, Book a call                                      |

Each PR: CI green on MySQL, merged before the next starts (ADR 0002).

## 3. Technology decisions (approved)

| #   | Decision                              | Recommendation                                                                                                                                                    | Alternatives                                                 |
| --- | ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| D1  | Images and files                      | **spatie/laravel-medialibrary**: responsive WebP/AVIF conversions (400, 800, 1600, 2400 px), ordered collections; local disk now, S3-compatible storage at launch | Hand-built uploads                                           |
| D2  | Maps                                  | **Leaflet + OpenStreetMap tiles** (free, attribution shown); MapTiler or Google Maps later if needed                                                              | Google Maps (paid, needs billing account)                    |
| D3  | Video tours                           | **YouTube/Vimeo links**, embedded privacy-enhanced (`youtube-nocookie`), CSP `frame-src` limited to those two hosts                                               | Uploading video files (heavy storage and bandwidth)          |
| D4  | Rich text (descriptions, pages, blog) | **TipTap editor** in React; HTML cleaned server-side with **symfony/html-sanitizer** before saving                                                                | Markdown only                                                |
| D5  | Search                                | **Laravel Scout + Meilisearch** (already in Docker) for filters, sort and location; database driver in tests                                                      | SQL queries only (fine below ~1,000 listings, slower beyond) |

## 4. 3a — Catalogue setup

### 4.1 Design-system alignment

- Form-field border token changed to `#7d9084` (WCAG 3:1; was 1.56:1) in app CSS and theme defaults; theme manager checks gain "Field borders on cards / page" (3:1).
- Shared React components matching the design system: Button, Field, StatusBadge, chips, ListingCard, Stepper, Notice, Countdown.
- Brand guidelines page republished with the corrected status colours.

### 4.2 Categories

- Seeded: Homes, Land, Mining, Malls. Admin can add, rename, reorder, hide (never delete one that has listings).
- Per category: name, slug, description, icon, cover image, **default deposit (R)**, **default platform fee (%)**, SEO title/description.
- Permission: `categories.manage`. All changes audit-logged.

### 4.3 Category attributes (key facts)

Admin-defined fields per category, so new property types need no code:

| Field      | Options                                  |
| ---------- | ---------------------------------------- |
| Label, key | e.g. "Erf size", `erf_size`              |
| Type       | number, text, select, yes/no, date       |
| Unit       | m², ha, R/month, …                       |
| Required   | yes/no                                   |
| Shown      | on cards, on key-facts strip, in filters |
| Order      | drag to reorder                          |

Seeded defaults: **Homes** (bedrooms, bathrooms, garages, erf size m², floor area m², levies, rates); **Land** (hectares, zoning, water rights, services, access); **Mining** (commodity, right type, area ha, right status, ministerial consent required); **Malls** (GLA m², tenants, occupancy %, net operating income, anchor tenant).

### 4.4 Document types

Per category: name, **mandatory or optional**, **visibility: internal or verified bidders** (never public), accepted formats (PDF, JPG, PNG), max size. Seeded from the PRD (title deed, SG diagram, zoning certificate, building plans, compliance certificates; rent roll and leases for malls; mining or prospecting right and environmental plan for mining). Mandatory documents block "Submit for review" until uploaded.

### 4.5 Locations

South Africa's 9 provinces and their main cities and towns seeded; suburb as free text; optional map pin (latitude/longitude) set by clicking the map. City is the unit for premium slots (Sprint 7).

## 5. 3b — Listings

### 5.1 Listing record

Category, title, slug, location (province, city, suburb, address — address internal), map pin, summary (answer-first, for SEO/AEO), description (rich text), category attributes, badges (Renovated is automatic when renovation cost > 0; Premium comes in Sprint 7), video tour link, SEO title/description, created/updated by.

### 5.2 Deal types and pricing (agreed rules)

- **Commission sale:** seller receives winning bid − platform fee. **Suggested minimum = seller net ÷ (1 − fee %).** Admin may set the minimum higher, never lower.
- **Thabekhulu-renovated:** seller receives the fixed seller price; Thabekhulu keeps the rest. Minimum ≥ seller price + renovation cost.
- Seller price, renovation cost and fee % are **internal**: never sent to public pages or APIs (enforced by resource classes and a test).
- Deposit: category default, editable per listing; warning when > 10% of the minimum (CPA Regulation 24).
- **Payout preview** on the form (at minimum and at an example bid). VAT on the fee shown as "to confirm" until Thabekhulu answers.
- Pricing fields lock once the auction is live; changes after that need a super admin and are audit-logged with a reason.

### 5.3 Media and documents

- Photos: drag-and-drop upload (JPG/PNG/WebP, ≤ 15 MB each), reorder, choose cover, alt text required; min 2400 px wide recommended (warning, not block). Floor plans as a separate collection. Responsive images generated in the queue.
- Documents: uploaded to **private storage**, served only through an authorised download route (verified-bidder access and watermarking arrive in Sprint 4; in Sprint 3 only staff can download).
- Brochure PDF generation: Sprint 8.

### 5.4 Auction dates

Start date and time; end defaults to start + auction duration from settings (30 days), editable per listing; times stored UTC, entered and shown in SAST.

### 5.5 Workflow

| Status                               | Who moves it                                                      | Public                                                                   |
| ------------------------------------ | ----------------------------------------------------------------- | ------------------------------------------------------------------------ |
| Draft                                | Listings manager creates and edits                                | No                                                                       |
| In review                            | Listings manager submits (mandatory documents and photos present) | No                                                                       |
| Changes requested                    | Auctioneer sends back with a comment                              | No                                                                       |
| Scheduled ("Coming soon")            | Auctioneer approves                                               | Yes, with opening date; documents list shown locked                      |
| Live                                 | Scheduler at start time                                           | Yes (bid panel: "Bidding opens with buyer registration", until Sprint 6) |
| Closed / Processing / Sold / No bids | Sprints 6–7                                                       | —                                                                        |

Every transition is audit-logged with who and why. Withdrawal (unpublish) needs `listings.publish` and a reason.

### 5.6 Public pages (from the approved designs)

- **Auctions** (`/auctions`, `/auctions/{category}`, `/auctions/{category}/{city}`): filters (category, province/city, price range, closing within, status, renovated), sort, grid of ListingCards, Leaflet map with price pins, list/map toggle on phones, pagination, empty states.
- **Listing** (`/auctions/{category}/{city}/{slug}`): gallery and lightbox, floor plan, video tour, key facts, answer-first summary, description, documents list (locked), location map (suburb-level; exact address never public), Q&A placeholder, Book a viewing (office calendar link and phone from settings), bid panel in its pre-bidding state.
- **Home** sections wired to real data: featured auction (chosen by admin), Closing soon (automatic), categories with live counts, auction calendar (by closing week), how it works, results band (shows when there are sold listings), trust band (FFC number from settings).
- SEO/AEO basics now: server-rendered, clean URLs, titles and descriptions, `RealEstateListing` + `Offer` + `BreadcrumbList` structured data on listings, XML sitemap. Full AEO work stays in Sprint 8. `noindex` stays on until launch.

### 5.7 Back office

- Listings table: search, filter by status/category/city, columns for status, dates, minimum, deposit; bulk actions none (deliberate).
- Listing form tabs as designed: Details, Media, Documents, Pricing and deal type, Auction, SEO and AEO; autosave drafts; "last saved by".
- Review queue for auctioneers with approve / request changes.

## 6. 3c — CMS and leads

| Module                | Scope                                                                                                                                                                                                                                                                           |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Home page**         | Choose featured auction; edit hero text, how-it-works steps, trust items; toggle sections                                                                                                                                                                                       |
| **Pages**             | Title, slug, blocks: rich text, image, call to action, FAQ (FAQPage structured data), two-column; draft/publish; seeded About, How it works, Buyer's guide, Fees, Contact, Privacy (POPIA), PAIA manual, Rules of Auction (content from Thabekhulu's attorney)                  |
| **Blog**              | Posts with cover image, category, author, publish date (scheduling), SEO fields; list and detail pages                                                                                                                                                                          |
| **Gallery**           | Albums of images with captions; public gallery page                                                                                                                                                                                                                             |
| **Menus**             | Header and footer menus; items link to a page, category, listing, blog or custom URL; nesting one level; reorder                                                                                                                                                                |
| **Sell with us**      | Public form (name, email, mobile, property type, city, asking price, description, up to 10 photos, consent); spam protection (honeypot + rate limit); lead queue with statuses New → Contacted → Valuation booked → Listed / Not suitable; notes; `leads.view` / `leads.manage` |
| **Contact enquiries** | Contact form → enquiries queue; respond by email from the back office; `enquiries.*`                                                                                                                                                                                            |

All CMS output passes through the HTML sanitiser; permission `cms.manage`; every publish audit-logged.

## 7. Data model (new tables)

`categories`, `category_attributes`, `document_types`, `provinces`, `cities`, `listings`, `listing_attribute_values`, `listing_status_changes`, `media` (medialibrary), `listing_documents`, `pages`, `page_blocks`, `posts`, `post_categories`, `galleries`, `menus`, `menu_items`, `leads`, `lead_notes`, `enquiries`. Money stored as integer rand (bigint); percentages as basis points. All with foreign keys, indexes on filter columns, ULIDs for public URLs.

## 8. Tests (highlights)

- Pricing: suggested minimum formula, minimum floor enforced, renovated deal rule, deposit > 10% warning, pricing locked when live.
- Internal fields never in public HTML, Inertia props or structured data.
- Workflow: each transition, permissions per role, mandatory documents block submit, scheduler makes listings live at start time (SAST/UTC boundaries).
- Documents: private storage, unauthenticated and buyer downloads refused in Sprint 3.
- Uploads: type/size validation, alt text required, conversions queued.
- Public pages: SSR content present, filters and sorting, structured data valid, exact address never exposed, noindex until launch.
- CMS: sanitiser strips scripts and event handlers, menus render, scheduled posts hidden until publish time.
- Leads: honeypot and rate limit, consent required, photos stored privately.
- Contrast: theme checks include field borders.

## 9. Acceptance criteria

1. A listings manager creates a Land listing with attributes, 10 photos, mandatory documents and commission pricing; the form shows the suggested minimum and payout preview; submit for review works only when complete.
2. An auctioneer requests changes, then approves; the listing appears as "Coming soon" and goes Live at its start time.
3. The public Auctions page filters by category, city and price, shows the map, and works at 360 px; the Listing page matches the approved design and never shows internal prices or the exact address.
4. A content editor publishes a page, a blog post and a gallery, and edits the header menu.
5. A visitor submits Sell with us; it appears in the lead queue; every step above is in the audit log.
6. CI green on MySQL for all three PRs.

## 10. Needed from Thabekhulu for this sprint

- Default deposit and platform fee % per category (placeholders R 50 000 and 20% until confirmed).
- Document types per category (we seed the PRD list; they confirm).
- Launch cities and provinces to prioritise.
- Office calendar booking link and phone number.
- Texts for About, How it works, Buyer's guide, Fees; Rules of Auction and Privacy/PAIA from their attorney (placeholders until then).
- Photos and details for the first 10 listings (can arrive during Sprint 3).

## 11. Risks

| Risk                                       | Mitigation                                                                      |
| ------------------------------------------ | ------------------------------------------------------------------------------- |
| Large photo uploads slow on SA connections | Direct-to-storage uploads with progress; resizing in the queue                  |
| Legal page texts late                      | Placeholders clearly marked; launch blocked until replaced (Sprint 9 checklist) |
| Map tile usage limits on OpenStreetMap     | Cache tiles via CDN or switch to MapTiler (configurable provider)               |
| Scope size (three modules)                 | Split into 3a/3b/3c PRs; CMS blocks limited to five types in this sprint        |

## 12. Revision 2: changes from the investor brief

The mining-investor brief adds the following to Sprint 3. Items that need bidding, payments or KYC are built in their own sprints (see the sprint plan) but their data is captured now.

| Area                               | Change in Sprint 3                                                                                                                                                                                                                                                                                                                                                                   |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Mine profiles                      | Mining attributes seeded: commodity, right type (mining right / prospecting right / mining permit), right reference, right status, right expiry, area (ha), resource statement available (competent person's report), infrastructure, environmental authorisation, water use licence, HDSA ownership (%), deal structure (right transfer under section 11 / share sale / asset sale) |
| Deposit terms (per listing)        | Setting with two options: **security deposit** (held separately, refunded in full, never applied to price or forfeited automatically; default for Mining) or **applied to price** (earlier rule; default for Homes, Land, Malls). Enforcement in Sprints 5–7                                                                                                                         |
| Auction type (per listing)         | Open auction (default) or **sealed tender**; stored now, engine in Sprint 6                                                                                                                                                                                                                                                                                                          |
| Compliance checklist (per listing) | Items: seller authority verified, interest for sale defined, mining right and environmental approvals reviewed, bidding and sale terms legally reviewed, escrow arranged. Each signed off by a permitted user with date and note. **Deposits stay disabled until every item is signed off** (enforced in Sprint 5)                                                                   |
| Mandatory notice                   | Every Mining listing shows: "This listing is subject to verification. A winning bid does not by itself transfer a mining right or guarantee regulatory approval." Text editable in settings                                                                                                                                                                                          |
| Data room                          | Document visibility gains a third level, **approved bidders after NDA**; access control, watermarking and download logs enforced in Sprint 4                                                                                                                                                                                                                                         |
| Bidding terms                      | Versioned bidding and sale terms per listing (template from settings, overridable), shown on the listing                                                                                                                                                                                                                                                                             |
| Investment collections             | Curated groups with live counts on the home page (e.g. Mining by commodity, Income-producing, Development land, Renovated homes): rule-based or hand-picked (3c)                                                                                                                                                                                                                     |
| Investor insights                  | The blog is named "Investor insights" (3c)                                                                                                                                                                                                                                                                                                                                           |
| Legal pages                        | Add Disclaimer and Notice of automatically available records to the seeded legal pages (3c)                                                                                                                                                                                                                                                                                          |
| Permissions                        | New: `compliance.signoff`, `quotes.create`, `quotes.approve`, `quotes.send`, `quotes.view_all` (quotation sprint)                                                                                                                                                                                                                                                                    |

Estimated effect: Sprint 3 stays three PRs; 3b grows by about 3 days.
