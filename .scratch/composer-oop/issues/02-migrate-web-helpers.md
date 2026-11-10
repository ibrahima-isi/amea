# 02: Migrate web workflows to class-owned behavior

Status: ready-for-agent

Blocked by: [01](01-split-web-entry-points.md).

## What to build

Move active controller and template callers from procedural compatibility functions to the classes that own authentication, settings, formatting, uploads, and other shared behavior.

## Acceptance criteria

- [ ] Live web callers use Composer-autoloaded classes for CSRF, permissions, configuration, validation, formatting, logging, and file handling.
- [ ] Member submission, correction and staged previews, staff workflows and private downloads retain their existing contracts, including French content and access checks.
- [ ] Existing PHPUnit, standalone and HTTP regressions exercise the migrated class entry points. Any compatibility functions still present are used only by callers scheduled for retirement or historical coverage.
