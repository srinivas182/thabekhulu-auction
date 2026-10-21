# Thabekhulu Auctions

Premium online property auction platform for **Thabekhulu Development Group** (South Africa), covering Homes, Land, Mining and Malls. Built by Mayura Consultancy Services (MCS).

**Status:** Sprint 2 — Foundation (2a scaffold). See [docs/sprint-plan.md](docs/sprint-plan.md).

## Run it locally (Docker)

Requirements: Docker Desktop (or Docker Engine + Compose v2).

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer setup
```

| Service                    | URL                           |
| -------------------------- | ----------------------------- |
| Website                    | http://localhost:8080         |
| Back office                | http://localhost:8080/admin   |
| Horizon (queues)           | http://localhost:8080/horizon |
| Mailpit (test email inbox) | http://localhost:8025         |
| Meilisearch                | http://localhost:7700         |

Containers: `app` (PHP 8.4-FPM), `nginx`, `mysql` (8.4), `redis`, `horizon`, `scheduler`, `ssr` (Inertia server-side rendering), `meilisearch`, `mailpit`.

Front-end hot reload: `docker compose exec app npm run dev`.

## Checks (run before pushing)

```bash
vendor/bin/pint --test                     # PHP style
vendor/bin/phpstan analyse                 # static analysis, level 8
npm run check && npm run types:check       # front-end lint, format, TypeScript
vendor/bin/pest                            # tests
```

CI runs the same checks: lint and static analysis on draft PRs, the full suite on MySQL once a PR is ready for review (see [ADR 0002](docs/adr/0002-workflow.md)).

## Code layout

```
app/Domains/<Domain>/      business logic by domain (Platform, Identity, Access, Settings, Theme, Audit…)
app/Http/                  shared HTTP layer; controllers stay thin
resources/js/pages/        public/*, admin/*, auth/*, settings/*, error
resources/js/layouts/      public, app (back office), auth, settings
resources/css/app.css      brand tokens (light and dark); theme manager overrides them at runtime
docs/                      PRD summary, sprint specs, ADRs, brand assets
```

## Documentation

| Document                                             | Purpose                                     |
| ---------------------------------------------------- | ------------------------------------------- |
| [docs/prd-summary.md](docs/prd-summary.md)           | Agreed business rules and scope             |
| [docs/sprint-plan.md](docs/sprint-plan.md)           | Phase 1 and Phase 2 plan                    |
| [docs/sprints/](docs/sprints/)                       | Detailed specification for each sprint      |
| [docs/client-checklist.md](docs/client-checklist.md) | What Thabekhulu must supply, and when       |
| [docs/adr/](docs/adr/)                               | Architecture decision records               |
| [docs/brand/](docs/brand/)                           | Logo files, design tokens, brand guidelines |

## Notes

- Times are stored in UTC and displayed in South African time (`DISPLAY_TIMEZONE`).
- Search engines are blocked (`noindex`) until `PUBLIC_SITE_INDEXING=true` at launch.
- Laravel Reverb (real-time bidding) is added in Sprint 6.
