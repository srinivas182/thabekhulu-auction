# Sprint 9 — Hardening: detailed specification

**Status:** Draft for approval · **Depends on:** Sprints 0–8 (merged) · **Source:** sprint plan line 9 ("security, load and independent penetration testing, backups and disaster-recovery drill, deployment documentation")

## 1. Objective

Make the platform safe to launch: an in-house security review and fixes, a load test on production-like hardware, an independent penetration test with findings fixed and retested, proven backups and a timed restore drill, and documentation a hosting team can deploy and operate from.

## 2. Delivery

| Part                                               | Contents                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| -------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **9a Security review (in-house)**                  | Dependency scanning in CI (`composer audit`, `npm audit`) failing on high/critical; an automated test that **every back-office route requires a permission** and every buyer/seller route an account of the right type; review against OWASP ASVS level 2 with a findings register; upload hardening (type sniffing, size limits, optional ClamAV scan); cookie and session flags, HSTS, CSP review; security-event logging review; POPIA retention schedule with purge jobs; secrets review |
| **9b Performance and load**                        | Production-like staging (multi-core, Docker, MySQL, Redis, Reverb behind nginx); k6 scripts for bidding (500 bidders), the live feed (2 000 WebSocket viewers) and page traffic; query review (N+1, indexes) and caching review; results report against the targets in section 4                                                                                                                                                                                                             |
| **9c Independent penetration test**                | Scope document, test accounts for each role, staging access and rules of engagement prepared by us; the test performed by an **independent firm appointed by Thabekhulu**; findings fixed and **retested by the same firm** before launch                                                                                                                                                                                                                                                    |
| **9d Backups, disaster recovery, deployment docs** | Automated encrypted backups of the database and private files, stored off-site with retention; a timed restore drill on a clean server; runbook (incidents, restores, key rotation); deployment guide (server requirements, environment, Docker services, Reverb/nginx, queues, scheduler, TLS, first deploy, upgrades, rollback); health checks, uptime and error monitoring                                                                                                                |

## 3. A divergence from the PRD to decide

The PRD says bids are processed "under a **Redis** atomic lock". The engine instead uses a **MySQL row lock** on the listing's auction state (ADR 0015), proven by the Sprint 6c concurrency test (40 parallel processes, 1 000 simultaneous bids, all invariants held). Decision H1 below.

## 4. Decisions needing approval

| #   | Decision               | Recommendation                                                                                                                                                                                                                                                                  | Alternative                  |
| --- | ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| H1  | Bid lock               | **Keep the MySQL row lock**: the bid and its ledger write commit in one database transaction, so the lock and the data can never disagree; a Redis lock would add a second system that can fail separately. Re-measure under 9b load; switch only if the lock is the bottleneck | Add Redis lock as in the PRD |
| H2  | Load targets           | 500 concurrent bidders and 2 000 live-feed viewers on one listing: bid accepted and broadcast in **≤ 300 ms at the 95th percentile**, no errors, all bid invariants held; public pages ≤ 1 s p95 at 50 requests per second                                                      | Lower targets                |
| H3  | Security standard      | OWASP ASVS **level 2** as the review checklist                                                                                                                                                                                                                                  | Level 1 only                 |
| H4  | Penetration test       | Independent firm (CREST- or equivalent-accredited) chosen and paid by Thabekhulu; grey-box test of staging; launch only after high/critical findings are fixed and retested                                                                                                     | In-house only                |
| H5  | Backups                | Database: nightly full backup plus binary logs for point-in-time recovery; private files: nightly; encrypted, off-site, kept 30 daily / 12 monthly; **RPO 15 minutes, RTO 4 hours**, proven by a timed restore drill                                                            | Daily only                   |
| H6  | Retention (POPIA/FICA) | KYC records kept **5 years after the business relationship ends** (FIC Act record-keeping), then purged by a scheduled job; other personal data per a schedule Thabekhulu's attorney approves                                                                                   | Keep forever                 |
| H7  | Monitoring             | Health endpoint, uptime checks, error tracking (Sentry or equivalent), queue and scheduler heartbeats, alerts to named staff                                                                                                                                                    | Logs only                    |
| H8  | Secrets                | Production secrets only in the server environment or a secrets manager; **rotate every credential used during development**, including the GitHub token shared in the project notes                                                                                             | —                            |

## 5. Acceptance criteria

1. CI fails on a high/critical dependency advisory; the route-permission test covers every route.
2. The ASVS level 2 findings register has no open high items.
3. The load test meets H2 on staging hardware, with the report attached.
4. The independent penetration test report shows no open high/critical findings after retest.
5. A restore from backup onto a clean server succeeds within the RTO, with data no older than the RPO.
6. The hosting team deploys staging from the deployment guide alone.

## 6. Needed from Thabekhulu

- **Appoint and budget the penetration testing firm** (and the testing window).
- **Hosting decision** for staging and production (VPS or cloud provider, region), so 9b and 9d run on the real platform.
- Attorney approval of the **retention schedule**.
- Named people for **monitoring alerts** and incident response.
- Confirmation of the **RPO/RTO targets** (H5).
