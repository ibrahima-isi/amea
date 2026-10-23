# 01: Characterize member and staff journeys

**What to build:** An isolated test environment exercises existing registration, secure document access, and staff login before changes; route/session/upload/job inventory identifies migration constraints.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [ ] Baseline HTTP/browser scenarios cover a valid registration and authorized/unauthorized document access with safe fixtures.
- [ ] Existing PHP regression checks run; gaps and route/session/worker ownership inventory are recorded.
- [ ] Feature PRs targeting dev receive relevant CI checks without deployment.
