# Ticket 01 — instruction rule coverage

Baseline sources are pinned at `2575def178c25fdbf03d575e021410085f6f425e`, before implementation. This is an audit map, not a competing source of durable instructions. The canonical source is the root repository-guidelines document.

| Baseline material | Canonical destination | Preservation or clarification |
| --- | --- | --- |
| Repository context, structure, namespace and bootstrap/PDO conventions | Project Structure & Module Organization | Retained; stale "separation not implemented" narrative corrected to the actual independent entry points and deferred physical split recorded by ADR 0001. |
| Install, local database/server, PHPUnit/standalone, lint and build commands | Build, Test, and Development Commands | Retained; the root Dockerfile command is identified as legacy alongside the existing canonical split-target build. No build/config source changed. |
| Indentation, names, services/repositories, French enums, Twig/theme and form alignment | Coding Style & Naming Conventions; Canonical Instructions and Language | Existing safeguards retained; the approved technical-English policy explicitly preserves French UI/email and compatibility literals. No source identifier is renamed. |
| PHPUnit naming/layout, SQLite fixtures, regression expectations and the single CI workflow | Testing Guidelines | Retained. Documentation checks do not assert theme/browser implementation or execution. |
| Signed commits, isolated worktrees, protected PR target, latest-head gate, actual-merge prerequisite, local integration and bounded cleanup | Commit & Pull Request Guidelines | Retained without unsigned fallback, remote-main push or unrelated-branch cleanup. |
| Credentials, extensions, CSRF/escaping/prepared queries, uploads/documents, roles, secure cookies and expiry, production approval | Security & Collaboration | Retained; existing host-scoped public/staff session isolation is made explicit. |
| Short responses and interactive scope/design/clarification | Canonical Instructions and Language | Retained from the user's project instructions; the conversation language remains distinct from English technical artifacts. |
| Idempotent CLI migrations, schema baseline and explicit selected database/order | Database Changes | Retained; no migration or data change is part of this ticket. |
| Local publish/fetch semantics, versioned feature specs/tickets, numbered files, comments and blockers | Local Issue Tracker | Retained; no remote issue/label is introduced. Active consumers use this canonical section. |
| Five identity-mapped triage roles and separate implementation evidence | Triage Roles; Local Issue Tracker | All five strings/meanings retained. The old global-root evidence pointer is clarified to the approved per-feature completion record; existing historical global documents are not deleted in this ticket. |
| Wayfinding map/children/types, frontier, claim/resolve/answer and map pointer | Wayfinding Lifecycle | Retained separately from implementation triage, including first eligible number and resolved prerequisites. |
| Single-context glossary/ADR layout, optional context map and missing-doc/lazy-creation behavior | Domain Documents | Retained; ordinary domain documents remain separate and are not translated in this ticket. |
| Exact Claude and Gemini delegation | Unchanged delegation files | Each must remain byte-for-byte `read AGENTS.md` with its existing trailing newline. |
| Approved light-default requirement and saved preference priority | Theme Requirement | Added as a requirement only. Ticket 02 owns runtime implementation and its approved behavior checks. |

The removed guide paths occur only in immutable history, this coverage context and the approved planning description of their removal. The completed cleanup plan links its former tracker guide to an immutable pre-consolidation Git revision rather than retaining an active broken pointer.
