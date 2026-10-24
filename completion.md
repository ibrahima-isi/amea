# UI modernization and application separation — Completion tracker

## Authoritative scope

Design: [UI and app separation](docs/superpowers/specs/2026-10-23-ui-and-app-separation-design.md).
Spec: [published specification](.scratch/ui-and-app-separation/spec.md).
Tickets: nine individually published issues under `.scratch/ui-and-app-separation/issues/`.
Tracker: local Markdown, configured in [issue-tracker.md](docs/agents/issue-tracker.md). Setup and publication completed on 2026-10-23.

## Current state

Ticket 01 baseline implemented and validated locally. Ticket 02 homepage, carousel and official logo implemented and locally validated. Tickets 03–09, final integration, CI and production release remain pending.

- [x] Gather UI and separation decisions and review direction B.
- [x] Independent design builder review: no important planning gaps reported.
- [x] Synthesize local spec and nine-ticket proposal.
- [x] Confirm test boundaries — browser/HTTP journeys, PHP regressions, and isolated deployment checks approved.
- [x] Publish synthesized specification with ready-for-agent triage.
- [x] Approve ticket granularity and blocking dependencies — all nine tickets and their blockers approved.
- [x] Publish nine separate tickets and record identifiers/dependencies here.
- [ ] Implement and validate each approved ticket.
- [ ] Record final integration evidence and remaining release blockers.

## Evidence and completion rules

Mark implementation complete only after behavior is implemented and relevant checks pass. For each ticket record status, commit/PR, test evidence, blockers, and next work. Planning documents and mockups are not implementation evidence. Local validation, CI, merge, and production release are separate milestones.

## Blockers and next work

Planning and publication are complete. Ticket 01 is implemented and locally verified. Ticket 02 is implemented and locally verified, including the official logo supplied on 2026-10-24. Tickets 03/04 are the next frontier; tickets 05–09 remain gated by dependencies. Intended logo and existing public content reuse are approved below. Production/DNS changes require explicit approval.

## Asset and content approvals — 2026-10-23

- [x] User explicitly confirmed `assets/img/logo.png` is the correct official logo.
- [x] User explicitly approved reusing the existing public texts and photos already published in the carousel.

These approvals remove the corresponding asset/content blockers; implementation and validation remain separate requirements.

## Working-tree boundary

Existing Dockerfile and .dockerignore edits are unrelated changes and must be preserved. No bulk staging or commits.

## Implementation progress

All nine tickets and dependencies are approved and published; ticket 01 criteria are verified locally. ready-for-agent describes triage readiness; blockers determine execution eligibility.

| Ticket | Blocked by | Status | Evidence / PR |
|---|---|---|---|
| [01 — Characterize member and staff journeys](.scratch/ui-and-app-separation/issues/01-characterize-member-and-staff-journeys.md) | None | Implemented; locally verified | HTTP baseline + PHP regressions; see evidence below |
| [02 — Deliver the community homepage](.scratch/ui-and-app-separation/issues/02-deliver-community-homepage.md) | 01 | Implemented; locally verified | [Local evidence](docs/testing/community-homepage.md) |
| [03 — Modernize registration through confirmation](.scratch/ui-and-app-separation/issues/03-modernize-registration-confirmation.md) | 02 | Published; not started | None |
| [04 — Modernize secure completion, correction, and consent](.scratch/ui-and-app-separation/issues/04-modernize-completion-correction-consent.md) | 02 | Published; not started | None |
| [05 — Rehearse independently deployed public and staff login](.scratch/ui-and-app-separation/issues/05-rehearse-independent-apps-login.md) | 03, 04 | Published; not started | None |
| [06 — Move staff member review and private documents](.scratch/ui-and-app-separation/issues/06-move-staff-member-document-management.md) | 05 | Published; not started | None |
| [07 — Move staff content and operational tools](.scratch/ui-and-app-separation/issues/07-move-staff-operational-tools.md) | 05 | Published; not started | None |
| [08 — Preserve links and finish route isolation](.scratch/ui-and-app-separation/issues/08-preserve-links-route-isolation.md) | 06, 07 | Published; not started | None |
| [09 — Verify integrated release and rehearse rollback](.scratch/ui-and-app-separation/issues/09-verify-release-rollback.md) | 08 | Published; not started | None |

## Publication evidence

- Three tracker/domain/status configuration docs are linked from AGENTS.md.
- One spec and nine individual tickets published with approved blocking edges.
- No remote issues or labels created; no application code, production changes, or implementation tests claimed.

### Publication verification — 2026-10-23

Passed: exactly nine individual tickets; approved dependency graph; ready-for-agent statuses; all implementation criteria unchecked; local links resolve; one Agent skills block; CLAUDE.md/GEMINI.md retain their one-line redirects. Local tracker files are not Git-ignored. Documentation whitespace checks passed. No product tests were needed or run for this documentation-only publication.

Next: ticket 01, Characterize member and staff journeys. No implementation is complete or underway.


## Ticket 01 — isolated journey baseline (2026-10-23)

Implemented on `feat/ui-separation-01`, based on integration `66d4768`. No production application behavior changed. The isolated runner copies code into a disposable temporary application, injects synthetic SQLite through the existing Container seam, sanitizes subprocess environments, excludes existing `.env`/uploads/storage, disables templated email through existing APP_ENV=test behavior, and starts no workers.

Verified locally: 15 HTTP assertions including valid uploaded registration through confirmation, owner/foreign-session staged-document access, invalid CSRF, staff login, anonymous document denial and authorized staff access to the persisted synthetic PDF. PHPUnit 140 tests / 240 assertions passed with 10 existing deprecations; standalone permissions 47/47, OOP unit 186/186, OOP integration 78/78, password security 24/24. Test fixture PHP syntax and whitespace passed. PHP runtime 8.5.12; CI uses 8.4.

The synchronized CI already targets dev/main PRs and dev pushes; ticket 01 adds isolated HTTP journeys to that workflow without deployment. Workflow configured; remote CI, PR merge and release are not claimed. Inventory: [routes, sessions, uploads and worker ownership](docs/testing/ui-separation-baseline.md). Runner safety and limitations: [HTTP baseline](tests/http/README.md).

Limitations: no interactive browser/JavaScript or visual checks yet; loopback test cookies do not qualify Secure-cookie/TLS behavior; a test-only SQLite dialect bridge translates INSERT IGNORE, so MySQL is not qualified. Actual two-app routing/session/storage isolation, browser screenshots and deployment persistence/rollback remain successor-ticket work. No production, DNS, SMTP delivery or external restoration was exercised.

Next: ticket 02 after integration of this ticket. Ticket triage remains ready-for-agent; execution evidence and checked criteria record verified completion separately.


## Ticket 02 — community homepage (2026-10-23)

Implemented direction B on `feat/ui-separation-02`: independent reusable public layout/CSS, responsive neutral light/dark themes and Guinea accents, association introduction/themes/registration links, backend-fed photo captions, accessible automatic/pause/manual carousel with reduced-motion and single/empty handling. Existing staff layout remains unchanged. Fixed the existing uploader result-key mismatch preventing staff photo publication. Public flash JSON uses hex escaping and text is HTML escaped.

TDD: homepage identity/empty-state HTTP tracer failed before template changes; real permitted staff photo upload failed with NOT NULL image_path before the one-key uploader fix, then passed. Final combined suite: 27 HTTP assertions and 26 real-browser assertions passed. Browser/HTTP evidence and four inspected screenshots: [community homepage](docs/testing/community-homepage.md). PHPUnit 140/240 passed with 10 existing deprecations; standalone permissions47/OOP186/integration78/password24 all passed. Changed PHP syntax and whitespace passed. Ticket01 integration head `3f5bd785` CI passed [run40026239502](https://github.com/ibrahima-isi/amea/actions/runs/40026239502) per parent verification; ticket02 remote CI remains unclaimed.

**Historical blocker (resolved 2026-10-24):** at the 2026-10-23 partial delivery, `assets/img/logo.png` was invalid ASCII `fakeimage` (9 bytes), despite approval of the intended logo path. Templates used AEESGS textual fallback; a decodable official logo was required. Parent requested its correct path/link. Ticket02 was not fully resolved and tickets03/04 remained gated at that revision. No production changes or release performed.

Ticket02 review follow-up: actual Chrome at320px verified full-document width and paused carousel button bounds; no clipping reproduced, no CSS change needed. Two retained browser assertions added; combined suite27 HTTP/26 browser passed. At that partial revision, the logo remained the only ticket02 blocker (resolved below).

## Partial integration review — 2026-10-23

Standards review reported no actionable findings. Spec review reported no confirmed deviations beyond the explicit logo blocker. Focused Chrome verification confirmed headline/control fit at 320px without document overflow or carousel controls outside bounds; no CSS fix was needed. These partial reviews do not resolve ticket 02's logo blocker or complete the full specification or release qualification.

Partial ticket 02 integration head `363293a1e88e88733d179fdc173c0dff7eebbfef` passed [CI run 40027550070](https://github.com/ibrahima-isi/amea/actions/runs/40027550070). This evidence applies only to that revision; subsequent fixes and handoff documentation require fresh exact-head CI before claiming their remote validation.


## Ticket 02 — official logo completion (2026-10-24)

The user supplied the official 960×677 PNG. Its exact original bytes replace the invalid file in `assets/img/logo.png`; SHA-256 `7411e10e3ec99af4cd2a9d739b30921e85287dc7651e2a09a1001fb06f0e7d75`. No generated, cropped or edited logo was substituted. Header and empty-state sizing preserve the complete image aspect ratio in both themes; circular cropping was removed.

TDD: a real-browser decoded-logo assertion failed against the old invalid asset, then passed with the supplied PNG. Final local run: 27 HTTP assertions and 32 Chrome assertions passed, including image decoding/original dimensions and uncropped aspect ratio across single/multiple/empty states, both themes, 320px/mobile/desktop, keyboard and carousel behavior. Four screenshots refreshed and inspected. PHPUnit140 tests/240 assertions passed with10 existing deprecations; standalone permissions47/47, OOP186/186, integration78/78, password24/24 passed. Whitespace and byte-for-byte source comparison passed.

All ticket02 acceptance criteria are now verified locally. Active logo blocker removed; tickets03/04 are next. Remote exact-head CI, integration merge, full-spec validation and production release remain separate pending milestones.

## Ticket 03 — registration through confirmation (2026-10-24)

Implemented shared public form shell, responsive light/dark registration and review, keyboard section controls, readable field errors and protected PDF identity preview. Correction shares this template with accurate correction copy and existing optional consent semantics. Staff fields/backend rules preserved; optional Flatpickr absence no longer prevents the rest of registration/staff editing JavaScript initialization. Static fixture serves the actual countries JSON.

TDD public-shell tracer failed before implementation then passed. Local final evidence:35 HTTP +12 real Chrome assertions; eight registration/review screenshots (desktop1440/mobile390+320, light/dark), visually inspected. HTTP covers retained escaped input, upload MIME rejection with retained values, owner/foreign staged previews, review CSRF, confirmation and persisted authorized download; staff field identities aligned. PHPUnit140/240 (10 existing deprecations), permissions47, OOP186, integration78, password24 passed. [Evidence and limits](docs/testing/registration-public-journey.md). No production or SMTP action. Exact-head remote CI/integration merge and full-spec release remain pending separately.

Ticket03 visual review follow-up: removed the unintended Georgia heading override so public forms inherit the approved direction B sans typography. After integrating `c075de3`, reran35 HTTP +12 Chrome checks successfully, including320px registration/review overflow checks; refreshed eight screenshots and inspected mobile heading fit. CSS-only correction; PHP suites were not repeated for this visual-only change.
