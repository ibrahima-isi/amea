# Active work

Current tracker after completed-record cleanup on 31 October 2026. Only the two items below remain open; readiness/triage is separate from execution authorization.

| Remaining item | Status | Remaining work |
| --- | --- | --- |
| [Association-journal browser acceptance](association-journal/issues/04-integrated-qualification.md) | `ready-for-human` — explicitly user-deferred | Small/large screens, both themes, keyboard/focus, filters/pagination and the full editorial/cover workflow. [Exact checklist and limits](association-journal/browser-qa.md). |
| [Orphan-test triage](chores/triage-orphan-tests/spec.md) | `needs-triage` | Classify six standalone scripts; decide which failures are regressions/stale tests and which tests should join CI or PHPUnit. Historical results require fresh triage. |

The possible same-day activity end-date prefill issue remains an **unconfirmed observation** inside browser acceptance, not a separately verified defect. Production deployment, real SMTP and live-data qualification are not reopened by this cleanup.

## Completed work retained in Git

Completed records are removed from the active tree, following the earlier repository-cleanup decision to preserve history in Git rather than duplicate it in an archive directory. Dates, source revisions, historical checklists, screenshots and generated evidence remain intact at the immutable revision **`fdafa3b81a5e26c9fc6948bec228205679368ee3`**. Surviving Markdown links to removed records point to that snapshot.

| Completed effort | Verified delivery | Preserved record |
| --- | --- | --- |
| English convention, canonical instructions and light default, tickets 01–06 | PRs #23–#28 merged; final PR checks 3/3 green | [Feature completion](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/english-policy-light-default/completion.md) |
| Journal implementation and server/test-support delivery, tickets 01–03 plus delivered parts of 04 | PRs #18/#20 merged; browser QA remains open above | [Journal history and evidence](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/association-journal/completion.md) |
| Deployment Compose consolidation | PR #22 merged | [Design and execution](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/deployment-compose-consolidation/completion.md) |
| Descriptive CI check names and coordinated required-check update | PR #33 merged (`94ac98e`); final head `7cfcc80` checks 3/3 green | [Spec, completion and ruleset evidence](https://github.com/ibrahima-isi/amea/blob/27960e331b2afb70ae999df742a16417991932c5/.scratch/ci-job-names/completion.md) |
| Repository cleanup | PR #19 merged | [Completed ticket](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/repository-cleanup/issues/01-remove-obsolete-artifacts.md) |
| Association-site research informing the delivered journal | Research complete; observations remain dated | [Research and source limitations](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/ui-visual-refresh/journal-research-2026-10-29.md) |

## Tracker maintenance evidence

Cleanup scope: **73 completed record/evidence files removed**, three active/context files retained, and this index added. [Maintenance execution evidence](tracker-cleanup/completion.md) is retained separately from the two open backlog items. Every removed byte is preserved at the revision above; application, tests, deployment, schema and canonical instruction sources are unchanged. The primary checkout and unrelated worktrees remain untouched. Only links in surviving documentation are repaired; historical inline path mentions remain dated provenance, not current navigation.

Static link/scope/provenance checks and the existing regression command set are recorded in the cleanup PR. Repository cleanup does not certify the two remaining items, production or journal browser acceptance.
