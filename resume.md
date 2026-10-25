# Resume point

**Last updated:** 2026-10-25, after final independent integration code review.

## Current state

- Integration branch `feat/ui-and-app-separation`; PR [#13](https://github.com/ibrahima-isi/amea/pull/13) targets protected `dev`. Authoritative scope: `.scratch/ui-and-app-separation/spec.md`; root `completion.md` records ticket evidence.
- Tickets01–09 are integrated and locally verified. Ticket09 local qualification:352 HTTP/schema/operational checks,22 real HTTPS Chrome checks, one concurrent-worker proof. Separate public/staff/jobs builds, independent web starts, web/MySQL container replacement, real retained-image rollback/forward, and separate SQL/uploads/storage restore all pass. See `docs/testing/release-qualification.md`.
- Final PHP141/241 (10 existing deprecations), standalone335 checks, legacy HTTP35+28 and public Chrome44+61 pass. Screenshots are under `docs/testing/screenshots/`. Official logo and public content reuse are user-approved.
- Final independent Standards/Spec review is complete on source `18dcea0125bfa6e6b94b84a0b2287e6994642643`, baseline `039949a1899934a92c70d8deec79ba9c0b7203a4`: Standards0 actionable findings, Spec0 confirmed findings. See `docs/testing/final-code-review.md`.
- Current exact delivered-head CI and readiness are reported by [PR13 checks](https://github.com/ibrahima-isi/amea/pull/13/checks) and final delivery; historical green runs are not evidence for later revisions.

## Next actions

1. Complete delivery against exact-head PR13 checks: mark ready only after successful checks and clean owned implementer worktrees. No remaining implementation or review finding is recorded.
2. User review and squash merge into protected `dev`; agents do not merge or deploy automatically. Read current PR checks/readiness rather than assuming a historical status.
3. Production is a separate approved operation: review `docs/operations/public-staff-rollout.md` and `compose.apps.dokploy.yml`, verify actual MariaDB10.11/data/network/volume ownership and live backups/restoration, record exact image/proxy rollback, and disable former cron before enabling one new jobs owner.

## Boundaries

No production/DNS/real SMTP or external restoration was performed. Local MySQL8/synthetic fixtures do not qualify production MariaDB or storage. New production compose is a proposal reusing existing external DB/network/data volumes, not a new database. Preserve unrelated primary-checkout edits to `Dockerfile`, `.dockerignore` and `GEMINI.md`; current AGENTS/tracker instructions remain authoritative.
