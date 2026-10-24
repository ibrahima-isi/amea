# 06: Move staff member review and private documents

**What to build:** Staff use the administration host to review/manage members submitted through the public app and access private documents securely.

**Blocked by:** [05: Rehearse independently deployed public and staff login](05-rehearse-independent-apps-login.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [x] Member listing, detail/edit, KYC, archived records, and associated document actions remain available by role.
- [x] Shared files/data are visible to authorized users only; staged uploads and public sessions cannot grant staff access.
- [x] Public member preview/correction workflows continue working against shared records.

## Execution evidence

Locally implemented and verified 2026-10-24. See [rehearsal](../../../docs/testing/app-separation-members.md) and root completion.md. Full-spec review, integration CI and PR readiness remain separate milestones.
