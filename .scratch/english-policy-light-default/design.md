# English technical content, light default, and canonical agent instructions

## Confirmed decisions — 31 October 2026

The user confirmed these decisions and the complete scope/testing boundaries interactively on 31 October 2026. The user then authorized specification publication only and explicitly requested a stop before implementation or ticket breakdown. The [specification](spec.md) records the approved scope; either execution path requires a later user decision.

1. A first visit defaults to **light**, even with a dark system preference. An explicitly saved user choice remains authoritative, including dark.
2. Technical documentation, code comments, internal identifiers and future commit messages use **English**. The application’s visible interface remains **French**.
3. **`AGENTS.md`** retains its current name and is the canonical project instruction file. `CLAUDE.md` contains exactly `read AGENTS.md`.
4. One file owns durable agent instructions. README, operational guides, ADRs, glossary and per-task completion records remain separate documents; they are not all merged into the instruction file.
5. Existing URLs, form/JSON/session keys and persisted business values stay compatible. French application and email text is preserved. This task introduces no data migration.
6. Move durable rules from the three `docs/agents/` guides into `AGENTS.md`, delete those guides and repair their references. Agent pointer files retain only their one-line reference.
7. Preserve published Git history. All new commit messages and PR text use English; current technical files are translated without rewriting old commits.

## Current facts

`CLAUDE.md` and `GEMINI.md` already contain only `read AGENTS.md`. The shared theme script currently prioritizes a saved value, then the system preference, and is used across public, administration, authentication and preview pages. Existing browser test sources explicitly expect the old system-following behavior.

A bounded inventory before PR #22 found 15 Markdown documents with French technical narrative, first-party JS/HTML comments and internal PHP methods/variables to translate. Newly merged Compose design/completion records also contain French and belong in the current-tree inventory. French UI text, country labels, exact enums and staged-session keys must be classified separately from technical prose.

PR #22 was merged as `e08f8cb`; this isolated worktree starts from that revision. The previous journal browser QA remains explicitly deferred to the user. No historical Git message, production data or user-visible copy has been changed during this interview.

## Approved implementation scope

- Inventory and translate current first-party technical narrative across README, glossary, ADRs, task documents and maintained guides, preserving dates, evidence, links and decision meaning. Exact UI/contract literals may remain French. Dependency/vendor content, source legal documents, human content/data, generated files and published Git messages are outside translation.
- Rename French internal identifiers and update all repository callers/tests/templates. Translate first-party technical comments. Keep public/persisted contracts and their literal values unchanged.
- Consolidate durable agent rules into `AGENTS.md`, including English technical conventions and light-default behavior. Preserve the signed isolated-worktree/PR workflow and security/data rules. Keep `CLAUDE.md` and `GEMINI.md` as exactly `read AGENTS.md`.
- Change the shared theme script so a valid saved light/dark choice wins, otherwise light is applied before paint regardless of OS preference. Preserve manual switching and French button labels. Storage failures must allow a usable light fallback and page-local toggling.
- Update existing tests that explicitly encode the previous OS-following behavior. The approved boundaries are the real theme-script/browser behavior and existing PHP/service/HTTP regression interfaces; tests observe user-visible state rather than private function layout.

## Approved validation and delivery boundaries

Use focused tests of the shared theme behavior plus the repository's existing isolated headless Chrome harness for fresh visits on a dark OS, saved choice, switching, reload and light/dark rendering. Use synthetic storage failures only as labelled simulations. No certificate-warning bypass or real account/data is needed for the loopback fixture. This theme regression work does not resume or close the separately deferred journal browser QA.

Run the existing PHP/HTTP CI command set after identifier changes; verify first-party comment/document coverage, preserved contracts, instruction pointers and translated-heading links. Use signed isolated-worktree commits, independent Standards/Spec review, a PR to `dev` and exact-final-head CI. No production deployment, DNS/SMTP, schema migration, published-history rewrite or unrelated worktree cleanup is included.

The scope and test boundaries are confirmed; execution is not authorized by this planning checkpoint. No source, theme, canonical-instruction or glossary file has been modified. No implementation ticket is created by specification publication.
