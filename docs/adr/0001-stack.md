# ADR 0001 — Technology stack

**Status:** Accepted (Sprint 0)

## Decision

Laravel 13 (PHP 8.4), React with server-side rendering via Inertia, MySQL 8, Redis (cache, queues, locks), Laravel Horizon, Laravel Reverb (WebSockets), Meilisearch (search with geo), Docker, Pest and PHPStan level 8. Standalone repository, independent of the ERP and GMLM codebases.

## Rationale

- Same stack as the Thabekhulu ERP, so the team, tooling and conventions carry over.
- Server-side rendering is required for SEO and AEO: crawlers and AI tools must read full listing content without JavaScript.
- Live bidding needs WebSockets, queues and atomic locks; Reverb + Redis provide these natively in Laravel.

## Alternatives considered

- **Next.js front end + Laravel API:** strong SSR, but two deployables and duplicated auth. Rejected for Phase 1.
- **Vue + Inertia (GMLM stack):** viable, but the client prefers React and the ERP uses React.
- **Pusher/Ably for real-time:** managed, but adds recurring cost and an external dependency; Reverb is self-hosted.

## Consequences

Requires a VPS or cloud host (long-running Reverb and Horizon processes). Shared cPanel hosting is not supported.
