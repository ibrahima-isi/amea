# Resume point

**Last updated:** 2026-10-24, during UI modernization and application separation.

## Current work

- Integration branch: `feat/ui-and-app-separation`; draft PR [#13](https://github.com/ibrahima-isi/amea/pull/13) targets `dev`.
- Authoritative scope: [.scratch/ui-and-app-separation/spec.md](.scratch/ui-and-app-separation/spec.md), nine tickets under its `issues/` directory, and root [completion.md](completion.md).
- Ticket 01 is implemented and locally verified. Its integration head `3f5bd785cbaf7ecdd121120c4774861cd72c7baa` passed [CI run 40026239502](https://github.com/ibrahima-isi/amea/actions/runs/40026239502).
- Ticket 02 homepage was integrated; official-logo completion is now locally verified: community homepage, public layout, responsive light/dark styling, accessible carousel and staff photo publication correction. Local checks passed: 27 HTTP assertions, 32 Chrome assertions including full-document/control fit at 320px, PHPUnit and standalone PHP regressions. Evidence and screenshots: [community-homepage.md](docs/testing/community-homepage.md).
- Partial ticket 02 integration head `363293a1e88e88733d179fdc173c0dff7eebbfef` passed [CI run 40027550070](https://github.com/ibrahima-isi/amea/actions/runs/40027550070). Later revisions require their own exact-head CI; this is not final-spec validation.
- Historical logo blocker (resolved 2026-10-24): `assets/img/logo.png` previously contained invalid nine-byte ASCII `fakeimage`. The intended logo path is approved, and reuse of existing public text/photos is approved, and the required decodable official logo file is now supplied and verified. The textual fallback remains available for unavailable assets.
- User supplied the official 960×677 PNG on 2026-10-24; unchanged bytes now replace the invalid asset. All ticket02 acceptance criteria pass locally, including decoded logo and uncropped aspect ratio. Logo-completion merge and exact-head CI remain separate milestones.
- Tickets 03 and 04 are the next frontier after ticket02 integration; tickets 05–09 remain gated by their documented dependencies. The full specification, final integration, release and rollback qualification are incomplete.

## Resume from here

1. Integrate/review the verified ticket02 logo completion and record exact-head CI; the active logo blocker is resolved.
2. Continue the approved dependency graph on the integration branch: 03/04, then 05, 06/07, 08 and 09. Use isolated implementer worktrees and integration merges.
3. Keep PR #13 draft until the full specification and required reviews/checks are complete. Report local checks, remote CI, merge and release separately.

## Boundaries

- No production, DNS, real SMTP delivery or external restoration actions have been performed or qualified by this work. Production changes require explicit approval.
- Preserve unrelated primary-checkout edits to `Dockerfile`, `.dockerignore` and `GEMINI.md`. `CLAUDE.md` retains the user's `read AGENTS.md` redirect.
- Current `AGENTS.md` and `docs/agents/` instructions are authoritative over conflicting historical policies. Use the local Markdown tracker; do not create remote issues.
