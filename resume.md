# Resume point

**Last updated:** 2026-10-24, during UI modernization and application separation.

## Current work

- Integration branch: `feat/ui-and-app-separation`; draft PR [#13](https://github.com/ibrahima-isi/amea/pull/13) targets `dev`.
- Authoritative scope: [.scratch/ui-and-app-separation/spec.md](.scratch/ui-and-app-separation/spec.md), nine tickets under its `issues/` directory, and root [completion.md](completion.md).
- Ticket 01 is implemented and locally verified. Its integration head `3f5bd785cbaf7ecdd121120c4774861cd72c7baa` passed [CI run 40026239502](https://github.com/ibrahima-isi/amea/actions/runs/40026239502).
- Ticket 02 homepage and official logo are integrated and locally verified: 27 HTTP / 32 Chrome assertions plus PHP regressions; see [homepage evidence](docs/testing/community-homepage.md). Exact logo integration head `178994c1ca1327b6efc28c934fa4b6b6db4de58b` passed [CI run 40052482901](https://github.com/ibrahima-isi/amea/actions/runs/40052482901).
- Tickets 03/04 are integrated and locally verified: registration 35 HTTP / 12 Chrome assertions, completion/correction/consent 28 HTTP / 61 Chrome assertions, PHP regressions and refreshed screenshots. Standards/Spec reviews found no remaining confirmed issues; the P3 shared HTTP harness finding is fixed and both HTTP suites passed again.
- Ticket 05 is implemented and locally verified: 76 HTTP/schema checks and 8 HTTPS Chrome checks plus legacy/PHP regressions; see docs/testing/app-separation-login.md. New staff target is authentication-only and cannot replace current administration yet. Tickets 06/07 are next after integration, then 08/09. Agent capacity is available again; independent review of 05 and remaining delegation are pending. Full-spec completion, release and rollback qualification are incomplete. Exact head 581e709 passed CI run 40086277814; later heads require fresh CI.

## Resume from here

1. Integrate the locally verified ticket 05 checkpoint and verify exact-head CI. Run its independent review; original Dockerfile edits remain preserved.
2. Resume implementer delegation. Continue 06/07, then 08 and 09 when their blockers are verified complete. Use isolated implementer worktrees and integration merges.
3. Keep PR #13 draft until the full specification and required reviews/checks are complete. Record exact-head CI separately from local evidence, merge and release.

## Boundaries

- No production, DNS, real SMTP delivery or external restoration actions have been performed or qualified by this work. Production changes require explicit approval.
- Preserve unrelated primary-checkout edits to `Dockerfile`, `.dockerignore` and `GEMINI.md`. `CLAUDE.md` retains the user's `read AGENTS.md` redirect.
- Current `AGENTS.md` and `docs/agents/` instructions are authoritative over conflicting historical policies. Use the local Markdown tracker; do not create remote issues.

Ticket 06 locally complete: staff dashboard/members/KYC/trash/private downloads and shared public correction proven via real TLS/MySQL/Chrome; see docs/testing/app-separation-members.md. Ticket 07 runs in parallel, then 08/09 and full review. Exact-head CI remains separate.
