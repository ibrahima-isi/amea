# Active work

Active efforts and their remaining work are listed below. Triage status is separate from execution authorization.

| Active effort | Completion checklist | Status / remaining work |
| --- | --- | --- |
| [Member communications & Consent/RGPD](member-communications/spec.md) | [completion.md](member-communications/completion.md) | Tickets 01–05 implemented and locally qualified. |
| [Portable object storage](object-storage/spec.md) | [completion.md](object-storage/completion.md) | Tickets 01–05 complete. [Ticket 06](object-storage/issues/06-production-transition.md) records the live [Stellar cutover](../docs/operations/stellar-production-transition.md) and authenticated download verification; only the first scheduled 03:00 Dakar backup (`2026-11-28`) and purge ordering remain pending. |
| [Association-journal browser acceptance](association-journal/issues/04-integrated-qualification.md) | [completion.md](association-journal/completion.md) | `ready-for-human` — user-deferred browser QA ([checklist](association-journal/browser-qa.md)). |
| [Orphan-test triage](chores/triage-orphan-tests/spec.md) | — | `needs-triage` — classify remaining standalone scripts under `tests/`. |

## Completed work retained in Git

Completed feature directories are removed from `.scratch/` once closed; full history remains preserved in Git.

| Completed effort | Verified delivery | Preserved record |
| --- | --- | --- |
| Namecheap Stellar return (tickets 01–04) | PRs #58, #59, #61, #62 merged; live GitHub Actions staging deploy `#45735874309` & rollback `#45736362407` passed; proposal promoted to [stellar-production-transition.md](../docs/operations/stellar-production-transition.md) | [Record at `688a6b2`](https://github.com/ibrahima-isi/amea/blob/688a6b2a7af7d814c0d30f0e4422f71944258231/.scratch/namecheap-stellar-return/completion.md) |
| Public navbar design | PR #56 merged (`3e769d4`) | [Record at `6861587`](https://github.com/ibrahima-isi/amea/blob/6861587753a4fc6fd2551d7f8b9c766ad3135ee6/.scratch/public-navbar-design/completion.md) |
| AEESGS backoffice redesign | PR #54 merged (`4949909`) | [Record at `6861587`](https://github.com/ibrahima-isi/amea/blob/6861587753a4fc6fd2551d7f8b9c766ad3135ee6/.scratch/aeesgs-backoffice-redesign/completion.md) |
| Composer OOP split entry points & Stellar reconciliation | PRs #39, #47, #48 merged | [Record at `6861587`](https://github.com/ibrahima-isi/amea/blob/6861587753a4fc6fd2551d7f8b9c766ad3135ee6/.scratch/composer-oop/completion.md) |
| Doctrine Migrations adoption | PR #38 merged (`eaca561`) | [Record at `6861587`](https://github.com/ibrahima-isi/amea/blob/6861587753a4fc6fd2551d7f8b9c766ad3135ee6/.scratch/doctrine-migrations/completion.md) |
| Descriptive CI check names | PR #33 merged (`94ac98e`) | [Record at `27960e3`](https://github.com/ibrahima-isi/amea/blob/27960e331b2afb70ae999df742a16417991932c5/.scratch/ci-job-names/completion.md) |
| Earlier tracker cleanup | PR #32 merged (`5729ab6`) | [Record at `6861587`](https://github.com/ibrahima-isi/amea/blob/6861587753a4fc6fd2551d7f8b9c766ad3135ee6/.scratch/tracker-cleanup/completion.md) |
| English convention, canonical instructions and light default | PRs #23–#28 merged | [Record at `fdafa3b`](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/english-policy-light-default/completion.md) |
| Association journal implementation (tickets 01–03) | PRs #18, #20 merged | [Record at `fdafa3b`](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/association-journal/completion.md) |
| Deployment Compose consolidation | PR #22 merged | [Record at `fdafa3b`](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/deployment-compose-consolidation/completion.md) |
| Repository cleanup | PR #19 merged | [Record at `fdafa3b`](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/repository-cleanup/issues/01-remove-obsolete-artifacts.md) |
