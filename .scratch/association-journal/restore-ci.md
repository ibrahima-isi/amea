# Fresh restore volume initialization

Follow-up to [journal qualification ticket 04](issues/04-integrated-qualification.md), within draft PR #20. Product code, deployment configuration and retained demo data are unchanged.

## Reproduced failure

[CI 41158218435](https://github.com/ibrahima-isi/amea/actions/runs/41158218435) on `f8a4ad2` passed `test` and `php-tests`, but `apps-rehearsal` failed before importing SQL or extracting files. Public and staff container creation concurrently populated a fresh shared storage volume; Docker reported `storage/mail-queue: file exists`.

A reduced real Docker loop asserted that each project was fresh, then created both containers using the existing synthetic images. It reproduced the same error on trial 7 in 5.73 seconds. No database or web container was started. Each owned project was cleaned in `finally`; this excludes stale data and backup contents as causes of that reproduction.

## Correction and evidence

Signed commit `840a507` adds three lines to `tests/apps/release.py`: precreate only `public`, then run the original full startup. One image populates the volumes before sibling creation. The existing error handling, temporary-project cleanup, cache tmpfs, SQL/file import, byte checks, rollback and privacy assertions are preserved. No retry or ignored error is added.

The same reduced loop with this single precreation step passed 100 consecutive fresh projects in 98.53 seconds, exit 0. Python syntax and `git diff --check` passed.

Two fresh-context reviewers independently inspected `f8a4ad2...840a507`: Standards 0 / Spec 0 findings. They confirmed cleanup, pinned image selection and restoration assertions remain intact; neither reran the operational tests. No follow-up source fix was required.

The complete local rehearsal recorded 521 PASS lines and crossed the previously failing creation step. Its execution channel was lost after a tool-host renewal; process inspection confirmed it was no longer running, and its final exit was unavailable. This is **incomplete local evidence**, not a successful full restore. Both owned source/restore projects were explicitly cleaned afterward. The final complete gate is [PR #20 latest-head CI](https://github.com/ibrahima-isi/amea/pull/20/checks).

Local detailed artifacts: `/private/tmp/amea-copyup-red-20261031.md`, `/private/tmp/amea-restore-ci-diagnosis-20261031.md`, and `/private/tmp/amea-restore-ci-full-20261031.log`. The reduced loop and its original red version are retained alongside them. These operational tests do not qualify the separately open browser journeys.
