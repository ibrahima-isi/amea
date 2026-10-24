# 07: Move staff content and operational tools

**What to build:** Staff manage carousel content, communications, exports, settings, accounts, profile, and progression on the administration host.

**Blocked by:** [05: Rehearse independently deployed public and staff login](05-rehearse-independent-apps-login.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [x] Existing tool permissions and user-visible behavior are preserved without staff redesign.
- [x] Carousel changes appear publicly; exports stay authorized; email behavior is tested through safe substitutes.
- [x] One shared background-job owner is configured and duplicate execution is checked.

## Execution evidence

Locally verified and integrated: [staff tools and shared jobs proof](../../../docs/testing/app-separation-tools.md), 84 HTTP checks, 9 Chrome checks and one concurrent-worker proof with three synthetic messages exactly once. Full-spec review and exact-head CI remain separate milestones.
