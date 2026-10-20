# Thabekhulu Auctions

Premium online property auction platform for **Thabekhulu Development Group** (South Africa), covering Homes, Land, Mining and Malls. Built by Mayura Consultancy Services (MCS).

**Status:** Sprint 0 — kickoff and brand identity. No application code yet; Sprint 2 (Foundation) scaffolds the Laravel app.

## Documentation

| Document | Purpose |
| --- | --- |
| [docs/prd-summary.md](docs/prd-summary.md) | Agreed business rules and scope (full PRD maintained with the client) |
| [docs/sprint-plan.md](docs/sprint-plan.md) | Phase 1 (10 sprints) and Phase 2 (7 sprints) plan |
| [docs/client-checklist.md](docs/client-checklist.md) | What Thabekhulu must supply, and by which sprint |
| [docs/adr/](docs/adr/) | Architecture decision records |
| [docs/brand/](docs/brand/) | Logo files, design tokens, brand guidelines |

## Stack (see ADR 0001)

Laravel 13 · PHP 8.4 · React with server-side rendering (Inertia) · MySQL 8 · Redis · Horizon · Reverb · Meilisearch · Docker · Pest + PHPStan.

## Ways of working (see ADR 0002)

One pull request per sprint. A sprint is done only when CI is green and the PR is merged. Each sprint starts with a detailed specification approved before build.
