# ADR 0011 — Data room and buyer dashboard (Sprint 4c)

**Status:** Accepted

| Decision | Rationale | Alternative considered |
| --- | --- | --- |
| One `DataRoomAccess` class decides document access: "verified bidders" documents for any buyer with an in-date verification; data-room documents only for bidders **approved for that listing** who accepted the **current** NDA version; internal documents never; nothing on non-public listings | One auditable rule; approval for one listing never opens another | Checks in controllers |
| NDA click-through per listing (typed name, tick box, time, IP, browser, NDA version); can be signed once the buyer has applied, and is now a **blocking** item on the bidder approval checklist; changing the NDA text publishes a new version that everyone accepts again | Bidders agree to the exact wording in force | Account-wide NDA |
| Every download watermarked on every page (top and bottom): "Confidential · name · bidder number · time SAST · Thabekhulu data room"; images stamped too | Leaked copies are traceable to a bidder | Watermark on upload only |
| PDFs FPDI cannot read (compressed cross-reference streams, common in scans) are converted to PDF 1.4 with Ghostscript (`-dSAFER -dPDFSTOPONERROR`) before stamping; Ghostscript added to the Docker image and CI | Scanned title deeds and rights must work; damaged files fail instead of being "repaired" into blank pages (found in testing) | Commercial FPDI parser |
| If stamping fails, the download is **refused** (503), not served unmarked; the failure is logged and audit-recorded so staff can re-upload the file | Never leak an unmarked confidential document | Serve original |
| Every download logged (document, bidder, bidder number, IP, time) and shown to staff on the listing's bidders page; a bidder downloading more than the daily alert level (default 20) is flagged | Detect scraping or leaks | No log |
| **Settings are never injected into constructors**; they are read where used. Controllers are cached on routes and Octane workers live across requests, so constructor-held settings went stale (a new NDA version was not enforced in testing). Fixed in four classes and guarded by an architecture test | Correct behaviour in long-running workers | Clear caches on change |
| Buyer dashboard from the approved design: needs-your-attention (verification, refund account, renewal within 30 days, unsigned NDAs), applications with bidder numbers and document links, account status | One place for the buyer's next step | Separate pages only |
