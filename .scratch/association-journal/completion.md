# Association journal — tracking

## Verified current state — 31 October 2026

**Implementation and delivery to `dev` complete; browser QA deferred to the user.** On 31 October, the user said “laisse tomber cela je le ferai après” (leave it; I will do it later) and requested a tracking update. This deferral is neither successful QA nor full compliance with the visual design.

PR #17, #18, #19 and [#20](https://github.com/ibrahima-isi/amea/pull/20) are merged into `dev` (`23b5c55`, `87d6a19`, `4aab39f`, `3033d4b`). Final signed head `09a414c9100e6ebebddddf65ae09ca6d88264f44` of #20 has three green jobs: [CI 41162939038](https://github.com/ibrahima-isi/amea/actions/runs/41162939038). [`dev` CI 41163154556](https://github.com/ibrahima-isi/amea/actions/runs/41163154556) is also green on `3033d4b`. Signed local integration is `0343f9d`; no push to remote `main`.

Validation obtained: PHP 159 tests / 310 assertions, scripts 48/186/78/24, HTTP 35 + 28 PASS; 10 pre-existing PHPUnit deprecations. The restore-volume initialization defect was reproduced on trial 7 then corrected, with 100/100 green targeted trials and 535 PASS lines in the final CI rehearsal, including SQL/files and journal/cover restoration. Two independent support reviews, then two fix reviews: Standards 0 / Spec 0 each time. [Restore evidence](restore-ci.md).

Only partial browser QA: public dark/large-screen and light login views observed. Still for the user to do: light/dark journal at 375 px and large screen, keyboard/focus, filters/pagination and writing/cover/preview/publication/edit/withdrawal/republication cycle. Native control is enabled but its keystrokes remain unverifiable; Playwright was not launched. The last attempt's fixture is stopped and its temporary data removed. [Resumption procedure and limits](browser-qa.md).

Previous delivery worktrees/branches were archived and cleaned; unrelated worktrees and their demo files are preserved. No production, additional real migration, DNS change or real SMTP delivery.

## Implementation history

- [x] Retrieve the earlier need, explicitly deferred in the initial delivery.
- [x] Examine association websites and record sources/limits.
- [x] Confirm a public section and three content types through interactive input.
- [x] Confirm management only from the independent staff application.
- [x] Prepare an exploratory public feed/article mockup with clearly marked fictional content.
- [x] Check filters, opening an announcement/activity/report, returning to the feed and absence of administration controls in a real browser.
- [x] Check image loading and no overflow at a 375px viewport (343px content).
- [x] Authorized administrators, draft then direct publication confirmed.
- [x] Automatic archiving of ended activities, page retention and separate activity report confirmed.
- [x] Write the complete scope, reviewed and approved before implementation.
- [x] Independent scope review: no important missing question for planning; [report](builder-check.md).
- [x] Complete scope approved on 30 October 2026, implementation explicitly authorized.
- [x] Plan and four local tickets established with dependencies 01→02→03→04.
- [x] Implement actual journeys and qualify PHP/HTTP/images/archives in a synthetic environment.
- [x] Remote delivery closeout: PR #18 and #20 merged, final-head and `dev` CI green.
- [ ] Complete browser QA: explicitly deferred; the user will resume it later.

Context/decisions: [design.md](design.md), [spec](spec.md), [approved plan](../../docs/superpowers/plans/2026-10-30-association-journal.md). The journal is implemented in its dedicated worktree and published in [PR #18](https://github.com/ibrahima-isi/amea/pull/18), targeting `dev`. Earlier local design files in the redesign worktree are preserved separately.

Historical mockup: `http://localhost:18444/journal.html`, [capture](../../docs/testing/evidence/2026-10-29/journal-public-maquette.jpg). It is distinct from the actual application: journal `https://localhost:18443/journal.php`, local read-only HTTP `http://localhost:18447/journal.php`, independent management `https://localhost:19443/manage-journal.php`. The 18447 facade allows no POST or staff access; management forms remain HTTPS and protected.

Implementation: dedicated managed worktree `/Users/ibrahimadiallo/.codex/worktrees/association-journal/amea`, branch `feat/association-journal`, base `4b16e1644bcf34c28db0d3922fd7687ddf9a0a8d` (former PR #17 head). PR #17 was squash-merged into `dev` as `23b5c55` on 30 October; that dependency is resolved. No change to the primary checkout or demo worktree.

| Ticket | Execution | Evidence |
|---|---|---|
| [01](issues/01-editorial-core.md) | Implemented/tested and reviewed | Signed commits 1b8d3d7/ddbe909; concurrent CAS red→green; journal 15/56, suite 158/306; review PASS; 10 existing deprecations |
| [02](issues/02-private-management.md) | Implemented/tested and reviewed | Signatures 47c2b7e/8f03831; PHP 159/310, permissions 48; two P2 findings for 422 form retention fixed and re-review PASS; synthetic HTTP green |
| [03](issues/03-public-reading.md) | Implemented/reviewed after session fix | Signatures 8132513/e7721a5; 481 PASS in latest rehearsal; sessionexpired P2 fixed and review PASS |
| [04](issues/04-integrated-qualification.md) | Delivery merged and final CI validated; browser QA deferred to the user | #20: signed head `09a414c`, CI 3/3 and `dev` green; 535 rehearsal PASS; PHP 159/310; reviews Standards 0 / Spec 0; [browser resumption](browser-qa.md) |

The first agent reached its usage limit; the parent completed the engine and independent review found a concurrency P2, fixed with atomic revision. The two P2 findings for 422 form retention and anonymous-session P2 were then reproduced/fixed/reviewed. Global review found only a P3 for English labels in the private list, fixed in 7dc5faa and re-review PASS. [Review](evidence/reviews/final-review.md), [verified correction](evidence/reviews/final-rereview.md).

Actual demo: migration 07 applied manually after verifying the synthetic amea_rehearsal database, before web replacement. Two members and four accounts retained; journal_posts empty, no fictional publication added. [Migration evidence](../../docs/testing/evidence/2026-10-30/journal-demo-migration.json). The four assets and 33 runtime checks match code 7dc5faa: [served evidence](../../docs/testing/evidence/2026-10-30/journal-served.json).

Limits: the automated browser returned “Local browser use is disabled in Work settings” during the actual HTTP reading attempt; no application capture or browser QA claimed. The HTTPS certificate was not bypassed and private sessions remain protected. PR #18 is published and its three checks succeeded on `bb2745e`; PR #17 is now merged. After conflict correction, the new head's CI must be checked again. Browser QA and PR #18 merge remain open; no production authorized. PR #18 auto-merge was disabled before the correction. Retain mounted local-runtime files while the demo uses them.

## Verified resumption — 30 October 2026

The existing worktree and all its changes were preserved on `feat/association-journal`, code `7dc5faa`. Read-only GitHub checks confirm PR #17 open/draft/unmerged, no remote journal branch or PR; `dev` remains `01e6a3b`, `main` `137c851`. No fetch, push or change to those branches.

**21 additional public HTTP checks PASS**, without creating a publication or resetting: five empty anonymous views without cookies/private links, ten private-route GET/POST denials, two missing resources returning 404, journal POST denied and three served assets identical to the worktree. [Resumption evidence](../../docs/testing/evidence/2026-10-30/journal-resumption.json). This verification does not replace the demo's 33 historical checks or repeat private HTTPS/full Docker suite.

The supported browser tool is unavailable and the inventory contains no controllable browser; browser QA remains open without a settings/certificate bypass. Approval review again accepted targeted commands, including remote reads and these local checks. The signed documentation commit can therefore use that normal review process again. Publishing the journal branch and creating its draft PR still require explicit authorization; the PR body is prepared locally.

## PR #18 — conflict resolution after PR #17

Publication of `bb2745e` was explicitly authorized, then PR #18 created and linked to the chat. Its three checks passed: [PHP/HTTP and application CI](https://github.com/ibrahima-isi/amea/actions/runs/41135097285), [migration validation](https://github.com/ibrahima-isi/amea/actions/runs/41135097506), with 534 HTTPS checks and 159 tests/310 PHP assertions.

After PR #17 was squashed into `23b5c55`, Git reproduced six conflicts, although the full trees of that `dev` head and initial base `4b16e16` are identical. Merging those histories therefore retains all code from `bb2745e`, without force-push or abandoning `dev`-specific changes. Only this tracking record, root tracking, plan and resolution evidence are updated. Favorable independent verification. [Evidence](../../docs/testing/evidence/2026-10-30/journal-dev-merge.json).

Local result validation: PHPUnit 159/310 (10 pre-existing deprecations), PHP scripts 48/186/78/24 and historical HTTP journeys 35+28 PASS. The new head requires its own remote checks before any conclusion. Browser QA remains open, auto-merge is disabled and no deployment is performed.
