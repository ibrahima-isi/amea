# 08: Preserve links and finish route isolation

**What to build:** Member links keep working and old staff pages guide staff to the new host without exposing staff actions publicly.

**Blocked by:** [06: Move staff member review and private documents](06-move-staff-member-document-management.md), [07: Move staff content and operational tools](07-move-staff-operational-tools.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [ ] Legacy staff GET URLs redirect to matching destinations with login and validated local return paths.
- [ ] Old form submissions recover safely without replaying actions across hosts.
- [ ] Complete route matrix proves public/staff isolation including downloads, recovery, and AJAX/auxiliary endpoints.
