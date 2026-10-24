# Resume point

**Last updated:** 2026-10-24, during UI modernization and application separation.

## Current work

- Integration branch: `feat/ui-and-app-separation`; draft PR [#13](https://github.com/ibrahima-isi/amea/pull/13) targets `dev`.
- Authoritative scope: [.scratch/ui-and-app-separation/spec.md](.scratch/ui-and-app-separation/spec.md), nine tickets under its `issues/` directory, and root [completion.md](completion.md).
- Ticket 01 is implemented and locally verified. Its integration head `3f5bd785cbaf7ecdd121120c4774861cd72c7baa` passed [CI run 40026239502](https://github.com/ibrahima-isi/amea/actions/runs/40026239502).
- Ticket 02 homepage and official logo are integrated and locally verified: 27 HTTP / 32 Chrome assertions plus PHP regressions; see [homepage evidence](docs/testing/community-homepage.md). Exact logo integration head `178994c1ca1327b6efc28c934fa4b6b6db4de58b` passed [CI run 40052482901](https://github.com/ibrahima-isi/amea/actions/runs/40052482901).
- Tickets 03/04 are integrated and locally verified: registration 35 HTTP / 12 Chrome assertions, completion/correction/consent 28 HTTP / 61 Chrome assertions, PHP regressions and refreshed screenshots. Standards/Spec reviews found no remaining confirmed issues; the P3 shared HTTP harness finding is fixed and both HTTP suites passed again.
- Tickets 05–07 are integrated and locally verified. The staff target now includes member administration, authorized documents, carousel, communications, exports, settings, accounts/profile/progression, and a disabled-by-default designated jobs profile. Original administration remains preserved.
- Member rehearsal passed111 HTTP/schema +10 Chrome; tools passed84 HTTP +9 Chrome plus one competing-worker proof (three synthetic messages exactly once). PHP141/241, standalone335 and legacyHTTP35+28 passed. See docs/testing/app-separation-members.md and app-separation-tools.md.
- Historical06 source-mount limitation is closed by ticket08: freshly built images passed the combined member/route/session suite. Ticket08 is locally verified (249 HTTP/schema +13 Chrome), with ticket09 and final independent review remaining.
- Ticket05 independent Standards/Spec reviews found no confirmed deviations. Exact integration head `c15218d1e87cff82b93f28ed01c47abf01785793` passed [CI run40088586043](https://github.com/ibrahima-isi/amea/actions/runs/40088586043). Later heads require their own CI.

## Resume from here

1. Integrate locally verified ticket08 and record exact-head CI. Its fresh images passed249 HTTP/schema +13 Chrome checks; tools84 HTTP, concurrent owner and PHP/legacy regressions passed. See docs/testing/app-separation-links.md.
2. After08 integration, execute09 final fresh builds, persistence/container replacement, isolated backup/restore/rollback and full regression/browser qualification.
3. Run full integration Standards/Spec code review, fix findings, verify exact-final-head CI, then mark PR13 ready. Keep production/DNS/real-email actions outside this delivery.

## Boundaries

- No production, DNS, real SMTP delivery or external restoration actions have been performed or qualified by this work. Production changes require explicit approval.
- Preserve unrelated primary-checkout edits to `Dockerfile`, `.dockerignore` and `GEMINI.md`. `CLAUDE.md` retains the user's `read AGENTS.md` redirect.
- Current `AGENTS.md` and `docs/agents/` instructions are authoritative over conflicting historical policies. Use the local Markdown tracker; do not create remote issues.

Ticket08 checkpoint: implementation and local criteria complete; no production cutover. Fresh08 images include integrated06/07 source. Real prior ff3a8ab images are preserved locally as `amea-ui-prior-public:ff3a8ab` and `amea-ui-prior-staff:ff3a8ab` for ticket09 rollback (source built before08 changes; only the initial test tracer differed).
