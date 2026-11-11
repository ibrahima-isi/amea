# 03: Run jobs and Doctrine through autoloaded commands

Status: ready-for-agent

Blocked by: None.

## What to build

Keep the current CLI commands usable by operators and both Compose stacks while moving their live implementation into Composer-autoloaded classes.

## Acceptance criteria

- [x] Thin CLI scripts preserve command paths, argument handling, outputs, exit statuses, and environment selection.
- [x] Mail and cleanup jobs retain the existing queue, locks, scheduler ownership, and trash backup confirmation. Failed and concurrent runs preserve existing handling.
- [x] Doctrine migration and enrollment retain command behavior, database selection, startup gates, and recovery safeguards. Historical migration sources remain intact.
- [x] Tests exercise the entry scripts and their job and Doctrine behavior with disposable data.
