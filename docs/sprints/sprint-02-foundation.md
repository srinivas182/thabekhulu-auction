# Sprint 2 — Foundation: detailed specification

**Status:** Draft for approval · **Depends on:** Sprint 0 (merged) · **Delivers to:** every later sprint

## 1. Objective

A running, secure, tested Laravel application that every later sprint builds on: login with two-factor, staff roles, the settings framework, admin-editable themes, the audit log, feature flags, the admin and public shells, Docker and CI. No auction features yet.

## 2. Delivery: three sub-sprints, one PR each

| Sub-sprint | Contents | Merge rule |
| --- | --- | --- |
| 2a Scaffold | Laravel app, React + SSR, Tailwind with brand tokens, Docker, CI, security headers, public and admin shells | CI green, merged |
| 2b Identity and access | Login, buyer sign-up (basic), password reset, email verification, 2FA, sessions, staff invites, roles and permissions | CI green, merged |
| 2c Platform services | Settings framework, theme manager, audit log, feature flags, admin pages for all of these | CI green, merged |

## 3. Stack and versions

| Area | Choice | Why |
| --- | --- | --- |
| Framework | Laravel 13, PHP 8.4 | Same as the ERP; current LTS line |
| Front end | React 19 + TypeScript via Inertia 2, server-side rendering on | SEO/AEO needs server-rendered HTML |
| Styling | Tailwind CSS 4 using CSS variables from the theme | Colours change at runtime from the admin, no rebuild |
| Auth | Laravel Fortify (session, web) + Sanctum (future mobile/API tokens) | First-party, supports 2FA |
| Permissions | spatie/laravel-permission | Roles + fine-grained permissions, cached |
| Settings | spatie/laravel-settings | Typed, versioned settings classes |
| Audit | spatie/laravel-activitylog | Model change and event logging |
| Feature flags | Laravel Pennant | First-party, database-backed |
| Quality | Pint, Larastan (PHPStan level 8), Pest 3, ESLint, Prettier, TypeScript strict | |
| Local env | Docker Compose: php-fpm, nginx, mysql 8, redis, horizon, reverb, meilisearch, mailpit, ssr | Mirrors production services |

## 4. Code structure

```
app/
  Domains/
    Identity/      users, auth actions, 2FA, sessions, invites
    Access/        roles, permissions, policies, seeders
    Settings/      settings classes, admin controllers
    Theme/         theme settings, CSS compiler, contrast checker
    Audit/         activity log viewer, login events
    Platform/      feature flags, health check, security headers
  Http/            thin controllers delegate to Domain actions/services
resources/js/
  Layouts/ (PublicLayout, AdminLayout, AuthLayout)
  Pages/   (Public/*, Admin/*, Auth/*)
  Components/ui/   (Button, Input, Select, Badge, Table, Modal, Toast, ColorField)
```

Rule: controllers validate (Form Requests) and call one action or service; business logic never lives in controllers or React components.

## 5. Identity and access (2b)

### 5.1 Accounts

- One `users` table with `type`: `staff` or `buyer` (seller added later, behind the `sellers` flag).
- ULID primary keys on users and all public-facing records.
- Buyer sign-up (basic): first name, last name, email, mobile (+27 format), password, POPIA consent and Terms acceptance with version and timestamp. ID number and KYC arrive in Sprint 4.
- Staff are never created by sign-up: super admin invites by email with a role; the invite link expires in 72 hours; staff set a password and must enrol 2FA before first access.

### 5.2 Login and security

- Email + password; rate limit 5 attempts per minute per email+IP, then a lockout message with retry time.
- Password policy: minimum 12 characters, checked against breached-password list; passwords hashed with Argon2id.
- Email verification required before any account action beyond profile.
- 2FA (authenticator app, TOTP) with 8 recovery codes: **mandatory for staff**, optional for buyers in Sprint 2 (becomes required for bidding in Sprint 6 via step-up).
- Session list in profile (device, IP, last active) with "sign out other sessions".
- Login, logout, failed login, 2FA enrol/disable, password change and lockouts are written to the audit log.
- Staff can be deactivated (immediate session revoke), never hard-deleted.

### 5.3 Roles and permissions

Roles seeded: Super admin, Auctioneer, Listings manager, KYC officer, Finance, Content editor, Support, Buyer, Seller (inactive).

Permissions are named `module.action` (for example `listings.publish`, `kyc.approve`, `refunds.issue`, `theme.manage`). Sprint 2 seeds the full matrix for all Phase 1 modules so later sprints only attach policies. Super admin bypasses checks via a gate; every other check goes through policies. A super admin can create custom roles from the permission list.

## 6. Settings framework (2c)

Typed settings groups, each with defaults, validation and an admin page (pages only for groups marked ✓; others get pages in their own sprint):

| Group | Key settings (defaults) | Admin page now |
| --- | --- | --- |
| General | Site name, contact email, phone, office address, timezone Africa/Johannesburg, currency ZAR, date format | ✓ |
| Auction | Duration 30 days; soft close on, 5 min window, 5 min extension; closing-soon 72 h; refund delay 5 days; runner-up hold 14 days; step-up threshold; suspension 12 months; Hot badge threshold 10 bids; New badge 7 days | ✓ |
| Theme | Section 7 | ✓ |
| Security | Staff 2FA required (locked on), session lifetime, admin IP allow-list (optional) | ✓ |
| Notifications, Payments, KYC, SEO | Stubs with defaults | Later sprints |

Every change is audit-logged with old and new values. Settings are cached and the cache is cleared on save.

## 7. Theme manager (new requirement)

Super admin (permission `theme.manage`) can change the look of the **website** and the **back office** separately, without a developer.

### 7.1 What can be changed

| Item | Website | Back office |
| --- | --- | --- |
| Primary colour (buttons, headers) | ✓ | ✓ |
| Accent colour (premium, focus, highlights) | ✓ | ✓ |
| Background and surface colours | ✓ | ✓ |
| Text and secondary text colours | ✓ | ✓ |
| Border colour | ✓ | ✓ |
| Dark-mode palette (each colour above) | ✓ | ✓ |
| Logo (light background), logo (dark background), favicon | ✓ | ✓ (sidebar logo) |
| Display font and interface font, from a curated list of 8 pairings | ✓ | Interface font only |
| Corner style: sharp, soft, round | ✓ | ✓ |

Auction status colours (Live, Closing soon, Processing, Sold, Coming soon) are **locked**: buyers learn their meaning, and changing them would cause confusion.

### 7.2 Guardrails

- **Contrast check on save:** every text/background pair must meet WCAG AA (4.5:1 for text, 3:1 for large text and UI). Failing pairs are shown with a suggested nearest passing colour; save is blocked until fixed.
- **Live preview** of a sample page (header, listing card, buttons, form) before publishing.
- **Draft → Publish:** changes are saved as a draft and only go live on Publish.
- **Version history:** each publish is stored; one-click restore of any previous version.
- **Reset to brand defaults** (the v1.0 brand tokens from Sprint 0).
- Logo uploads: SVG (sanitised) or PNG/WebP, max 1 MB; favicon auto-generated in required sizes.

### 7.3 How it works

Published theme values are compiled to CSS custom properties, cached in Redis, and inlined in the `<head>` of every server-rendered page (no extra request, no flash of the wrong colours). Tailwind utilities read these variables, so a publish takes effect immediately with no rebuild or deploy.

## 8. Audit log (2c)

- Logged automatically: create/update/delete on users, roles, permissions, settings, theme versions, feature flags; auth events (5.2).
- Each entry: actor, action, subject, old/new values (sensitive fields masked), IP, user agent, timestamp.
- Admin viewer with filters (user, module, action, date range) and CSV export. Entries cannot be edited or deleted by anyone, including super admin.
- Retention: 7 years (setting), pruned by a scheduled job.

## 9. Feature flags (2c)

Laravel Pennant, database driver. Super admin toggle page, changes audit-logged.

| Flag | Default | Turned on in |
| --- | --- | --- |
| `sellers` | Off | Phase 2 |
| `consortium_bidding` | Off | Phase 2 |
| `investor_club` | Off | Phase 2 |
| `whatsapp_notifications` | Off | Sprint 6 |
| `soft_close` | On | Controlled from Auction settings |
| `public_site_indexing` | Off (noindex until launch) | Sprint 9 |

## 10. Shells

- **Admin (`/admin`):** sidebar navigation driven by permissions (users see only what they can access), top bar with profile, theme-aware light/dark toggle, dashboard placeholder with system health (queue, cache, database, Reverb), Profile (details, password, 2FA, sessions), Staff, Roles, Settings, Theme, Audit log, Feature flags.
- **Public:** brand header and footer, placeholder home page, login and sign-up, 404/500/503 pages in brand style; server-rendered; `noindex` while the `public_site_indexing` flag is off.

## 11. Security baseline (2a)

- Headers: Content-Security-Policy (nonce-based scripts), HSTS, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy, X-Content-Type-Options.
- Secure, HttpOnly, SameSite=Lax cookies; CSRF on all forms; encrypted session store in Redis.
- Global rate limits (web and API), stricter on auth routes.
- `.env.example` with no secrets; app refuses to boot in production with debug on.
- `/up` health endpoint for load balancers.

## 12. CI (2a)

GitHub Actions, following ADR 0002:

| Trigger | Jobs | Target time |
| --- | --- | --- |
| Draft PR | Pint check, ESLint, TypeScript, PHPStan level 8 | under 4 min |
| PR marked ready | Above + Pest (MySQL 8 + Redis services, parallel) + front-end build + SSR build | under 10 min |
| Docs-only changes | Skipped | — |
| Push to main | None (already tested in PR) | — |

Composer and npm caches; `concurrency` cancels superseded runs.

## 13. Database (this sprint)

`users`, `password_reset_tokens`, `sessions`, `staff_invitations`, `two_factor` columns on users, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `settings`, `theme_versions` (scope website/admin, values JSON, status draft/published, published_by, published_at), `activity_log`, `features`, `jobs`, `failed_jobs`, `cache`. All with foreign keys, indexes on lookup columns, and timestamps.

## 14. Tests (Pest)

- Auth: sign-up, verification, login, lockout after 5 failures, password reset, 2FA enrol/challenge/recovery code, staff cannot access admin without 2FA, deactivated staff are logged out.
- Access: each role sees only its permitted admin pages; policies deny by default; super admin gate.
- Invites: expiry, single use, role applied.
- Settings: validation, defaults, cache refresh, audit entry with old/new values.
- Theme: contrast checker (pass/fail cases), draft not live until publish, restore version, reset to defaults, CSS variables rendered in SSR head, SVG sanitisation.
- Audit: entries immutable, sensitive fields masked, filters.
- Flags: defaults, toggle, audit entry.
- Security: headers present, rate limits, noindex while flag off.
- Architecture tests: controllers don't touch models directly for writes; no debug calls left in code.

## 15. Acceptance criteria

1. `docker compose up` starts the full stack; app reachable with seeded super admin (credentials printed once by the seeder).
2. A super admin can invite a staff member, who enrols 2FA and sees only pages for their role.
3. A buyer can sign up, verify email, log in and manage their profile and sessions.
4. Changing the website primary colour in Theme, previewing and publishing changes the public site immediately; a failing contrast pair blocks save; restoring the previous version works.
5. Every action above appears in the audit log.
6. CI is green on all three PRs within the target times.

## 16. Out of scope (later sprints)

Categories and listings (3), KYC and ID numbers (4), payments (5), bidding and WebSockets features (6), notifications content (4–6), SEO/AEO structured data (8), production hosting (9).

## 17. Risks

| Risk | Mitigation |
| --- | --- |
| Admin picks colours that make the site unreadable | Contrast check blocks save; status colours locked; reset to defaults |
| SSR adds complexity | Set up and tested in 2a before any feature pages |
| CI minutes | Lean triggers per ADR 0002; target under 10 min |
