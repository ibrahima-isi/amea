# 09: Verify integrated release and rehearse rollback

**What to build:** A release candidate demonstrates the full public redesign and two-service separation, with evidence and an approved-action boundary for production.

**Blocked by:** [08: Preserve links and finish route isolation](08-preserve-links-route-isolation.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [ ] Build/start each target independently; replace containers and verify database/upload persistence in isolation.
- [ ] Run full workflow/accessibility/theme/browser and PHP regression checks on the final candidate.
- [ ] Record content/logo review, schema compatibility, backup/restore rehearsal, rollout order, rollback steps, and remaining external blockers.
- [ ] No production/DNS/real-email actions occur without explicit approval; completion tracker separates local, CI, merge, and release evidence.
