# ADR 0002 — Repository and CI workflow

**Status:** Accepted (Sprint 0)

## Decision

- `main` is always releasable. One branch and pull request per sprint (`sprint-N-topic`).
- A sprint is done only when CI is green and the PR is merged; the next sprint starts after that.
- Every sprint begins with a detailed specification approved before build.
- CI is kept lean: checks run locally before pushing; draft PRs run lint and static analysis only; the full test suite runs once when the PR is marked ready; no repeat run on `main` after merge; superseded runs are cancelled; docs-only changes skip CI.
- The repository is made private before production go-live; access tokens are fine-grained and limited to this repository.

## Rationale

Keeps every sprint reviewable and reversible, and keeps GitHub Actions minutes under control.
