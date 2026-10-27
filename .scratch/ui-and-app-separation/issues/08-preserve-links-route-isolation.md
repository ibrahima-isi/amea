# 08: Preserve links and finish route isolation

**What to build:** Member links keep working and old staff pages guide staff to the new host without exposing staff actions publicly.

**Blocked by:** [06: Move staff member review and private documents](06-move-staff-member-document-management.md), [07: Move staff content and operational tools](07-move-staff-operational-tools.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [x] Legacy staff GET URLs redirect to matching destinations with login and validated local return paths.
- [x] Old form submissions recover safely without replaying actions across hosts.
- [x] Complete route matrix proves public/staff isolation including downloads, recovery, and AJAX/auxiliary endpoints.

## Execution evidence

All criteria verified through freshly built isolated HTTPS/MySQL applications: 249 HTTP/schema checks,13 Chrome checks,84 tools HTTP checks and concurrent owner proof; PHP and legacy suites passed. [Detailed evidence](../../../docs/testing/app-separation-links.md). No production cutover claimed.
