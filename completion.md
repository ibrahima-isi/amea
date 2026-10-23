# UI modernization and application separation — Completion tracker

## Authoritative scope

Design: [UI and app separation](docs/superpowers/specs/2026-10-23-ui-and-app-separation-design.md).
Spec: [published specification](.scratch/ui-and-app-separation/spec.md).
Tickets: nine individually published issues under `.scratch/ui-and-app-separation/issues/`.
Tracker: local Markdown, configured in [issue-tracker.md](docs/agents/issue-tracker.md). Setup and publication completed on 2026-10-23.

## Current state

Planning only. No implementation, release, or production validation is complete.

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

Planning and publication are complete. Ticket 01 is the unblocked frontier; tickets 02–09 remain gated by dependencies. Implementation has not started and is not claimed by this setup task. Real logo and public content review remain release prerequisites. Production/DNS changes require explicit approval.

## Working-tree boundary

Existing Dockerfile and .dockerignore edits are unrelated changes and must be preserved. No bulk staging or commits.

## Implementation progress

All nine tickets and dependencies are approved and published; every implementation criterion remains unchecked. ready-for-agent describes triage readiness; blockers determine execution eligibility.

| Ticket | Blocked by | Status | Evidence / PR |
|---|---|---|---|
| [01 — Characterize member and staff journeys](.scratch/ui-and-app-separation/issues/01-characterize-member-and-staff-journeys.md) | None | Published; not started | None |
| [02 — Deliver the community homepage](.scratch/ui-and-app-separation/issues/02-deliver-community-homepage.md) | 01 | Published; not started | None |
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
