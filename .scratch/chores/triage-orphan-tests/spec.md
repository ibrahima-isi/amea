# Spec: Triage orphan test scripts

**Status:** needs-triage

## Problem

Six plain-PHP test scripts under `tests/` are not run by CI (the deploy workflow lists only four scripts plus PHPUnit). Baseline on 2026-10-21, PHP 8.5, from `dev`:

| Script | Result |
| --- | --- |
| `test_document_reconcile.php` | passes |
| `test_js_string_literal.php` | passes |
| `test_kyc_schema.php` | passes |
| `test_filter_option_nulls.php` | fails (exit 1) |
| `test_students_search_placeholders.php` | fails (exit 1) |
| `test_permissions_integration.php` | fails (exit 255) |

The failing ones have rotted unnoticed because nothing runs them. Whether the three passing ones should be wired into CI is undecided.

## Open questions for triage

- For each failing script: is it a real regression, an outdated test, or obsolete?
- Should the passing scripts join the CI test list, or be migrated into PHPUnit?
- Removing any script is a delete and needs the user's explicit approval (see AGENTS.md, Production safety).

## Blocked by

`ci-on-pull-requests` ticket 01, so that the outcome lands behind a working CI check.
