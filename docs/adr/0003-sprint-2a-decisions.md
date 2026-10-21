# ADR 0003 — Sprint 2a implementation decisions

**Status:** Accepted (Sprint 2a)

| Decision                                                                                   | Rationale                                                                                                          | Alternative                                           |
| ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------- |
| Start from Laravel's official React starter kit (Laravel 13, Inertia 3, React 19, Fortify) | Maintained by Laravel; ships login, 2FA, passkeys and settings pages already tested                                | Hand-built auth scaffolding: slower, more to maintain |
| Keep passkeys                                                                              | Phishing-resistant login at no extra cost; complements 2FA                                                         | Remove to match spec exactly                          |
| Front-end lint/format with Vite+ (oxlint, oxfmt)                                           | Ships with the kit; faster than ESLint/Prettier with equivalent rules                                              | ESLint + Prettier as originally specified             |
| Self-host fonts (@fontsource)                                                              | Faster first paint, no third-party requests, simpler CSP, POPIA-friendly                                           | Google Fonts or Bunny Fonts CDN                       |
| Store times in UTC, display in Africa/Johannesburg                                         | Unambiguous auction close times; safe if non-SA buyers or servers are added                                        | App timezone set to SAST                              |
| Defer Laravel Reverb to Sprint 6                                                           | Current Reverb release is not yet compatible with Laravel 13's HTTP message library; not needed until live bidding | Downgrade framework dependencies                      |
| PHPStan raised from level 7 to 8                                                           | Spec requirement; caught 11 unchecked nullable-user cases in kit code                                              | Stay at level 7                                       |
