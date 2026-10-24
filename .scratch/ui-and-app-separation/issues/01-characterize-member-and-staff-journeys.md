# 01: Characterize member and staff journeys

**What to build:** An isolated test environment exercises existing registration, secure document access, and staff login before changes; route/session/upload/job inventory identifies migration constraints.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [x] Baseline HTTP/browser scenarios cover a valid registration and authorized/unauthorized document access with safe fixtures.
- [x] Existing PHP regression checks run; gaps and route/session/worker ownership inventory are recorded.
- [x] Feature PRs targeting dev receive relevant CI checks without deployment.

## Execution evidence

Implemented in `feat/ui-separation-01`: [baseline and inventory](../../../docs/testing/ui-separation-baseline.md), [HTTP harness](../../../tests/http/README.md). All three criteria verified locally; HTTP rather than interactive browser baseline. CI workflow configured for dev PRs, remote run remains separate evidence. See root completion record for counts and limitations.
