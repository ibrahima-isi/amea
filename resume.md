# Resume point

**Last updated:** 2026-10-25, after ticket09 local release qualification.

## Current state

- Integration branch `feat/ui-and-app-separation`; draft PR [#13](https://github.com/ibrahima-isi/amea/pull/13) targets protected `dev`. Authoritative scope: `.scratch/ui-and-app-separation/spec.md`; root `completion.md` records ticket evidence.
- Tickets01–09 are integrated and locally verified. Ticket09 local qualification:352 HTTP/schema/operational checks,22 real HTTPS Chrome checks, one concurrent-worker proof. Separate public/staff/jobs builds, independent web starts, web/MySQL container replacement, real retained-image rollback/forward, and separate SQL/uploads/storage restore all pass. See `docs/testing/release-qualification.md`.
- Final PHP141/241 (10 existing deprecations), standalone335 checks, legacy HTTP35+28 and public Chrome44+61 pass. Screenshots are under `docs/testing/screenshots/`. Official logo and public content reuse are user-approved.
- Exact ticket08 head7cf99d8268342ac71b5596739653e61419a0f070 passed CI run40143965267. Current delivered-head status must be read from PR checks; historical CI is not final-head evidence.

## Next actions

1. Run the requested full independent Standards/Spec code review of the integrated nine-ticket implementation. Fix every actionable finding through one implementer and revalidate the affected boundaries.
2. Verify exact-final-head CI, mark PR13 ready only after implementation/review/checks are complete, then clean owned implementer worktrees. The user controls merging/deployment.
3. Production is a separate approved operation: review `docs/operations/public-staff-rollout.md` and `compose.apps.dokploy.yml`, verify actual MariaDB10.11/data/network/volume ownership and live backups/restoration, record exact image/proxy rollback, and disable former cron before enabling one new jobs owner.

## Boundaries

No production/DNS/real SMTP or external restoration was performed. Local MySQL8/synthetic fixtures do not qualify production MariaDB or storage. New production compose is a proposal reusing existing external DB/network/data volumes, not a new database. Preserve unrelated primary-checkout edits to `Dockerfile`, `.dockerignore` and `GEMINI.md`; current AGENTS/tracker instructions remain authoritative.
