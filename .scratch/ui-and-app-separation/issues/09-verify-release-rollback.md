# 09: Verify integrated release and rehearse rollback

**What to build:** A release candidate demonstrates the full public redesign and two-service separation, with evidence and an approved-action boundary for production.

**Blocked by:** [08: Preserve links and finish route isolation](08-preserve-links-route-isolation.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [x] Build/start each target independently; replace containers and verify database/upload persistence in isolation.
- [x] Run full workflow/accessibility/theme/browser and PHP regression checks on the final candidate.
- [x] Record content/logo review, schema compatibility, backup/restore rehearsal, rollout order, rollback steps, and remaining external blockers.
- [x] No production/DNS/real-email actions occur without explicit approval; completion tracker separates local, CI, merge, and release evidence.

## Execution evidence

Locally executed qualification: [release evidence](../../../docs/testing/release-qualification.md). 352 HTTP/schema/operational +22 HTTPS Chrome checks, one competing-worker proof, full PHP and public browser regressions pass. Real image rollback/forward and separate SQL/uploads/storage restore verified through HTTP records/file hashes. Production approval and external qualifications remain outside this local delivery; final independent review and exact-head CI are separate milestones.
