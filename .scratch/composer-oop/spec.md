# Composer application structure

Status: ready-for-agent

## Problem Statement

AMEA already uses Composer and autoloaded controllers, services, and repositories. Maintainers still have to trace request startup, shared behavior, and scheduled commands through procedural bootstrap and helper files. The source tree also carries a combined web runtime alongside the two independently deployed applications.

## Solution

Keep one Composer package and give the public website and administration dedicated document roots. Move live web and CLI behavior into autoloaded classes behind thin entry scripts. Migrate active callers in stages, then retire the combined web runtime and its file-based routing fallback once the split Compose stacks qualify.

## User Stories

1. As a maintainer, I want Composer to autoload live application code, so that I can trace behavior through named classes.
2. As a maintainer, I want entry scripts to delegate to classes, so that startup and command behavior have clear owners.
3. As a maintainer, I want the public website and administration to share one Composer dependency set, so that I can maintain shared business code in one place.
4. As an operator, I want a dedicated document root for each application, so that each target can start independently with a fixed identity.
5. As a member, I want my existing public registration and correction links to keep working, so that I can complete my submission.
6. As a member, I want staged documents accessible only through their authorized workflow, so that my files remain private.
7. As a visitor, I want a staff URL on the public host to return a local 404, so that the host boundary is clear.
8. As a visitor, I want anonymous journal reads to stay session-free, so that I can read public information without starting a session.
9. As a staff user, I want my existing administration and password recovery links to keep working on the staff host, so that my daily workflows continue.
10. As a staff user, I want login, session expiry and revocation, and authorized return destinations to behave as before, so that I can resume work safely.
11. As an administrator, I want permissions, CSRF validation, and private downloads to retain their checks, so that actions and records stay limited to authorized users.
12. As a web client, I want existing methods, status codes, redirects, form fields, and JSON contracts preserved, so that current requests continue to work.
13. As a French-speaking user, I want existing application and email content preserved, so that I receive the same instructions.
14. As an operator, I want scheduled mail and cleanup commands to keep their invocations and safeguards, so that operational automation remains compatible.
15. As an operator, I want Doctrine startup and enrollment to retain their environment selection, locking, and failure handling, so that migrations remain safe to run and retry.
16. As an operator, I want existing database and storage volumes retained in both stacks, so that the directory change does not move member data.
17. As a developer, I want one documented Compose launch path for each environment, so that local qualification uses the same application separation as Dokploy.
18. As a reviewer, I want end-to-end test and integration evidence recorded by ticket, so that I can verify when the legacy runtime is safe to remove.

## Implementation Decisions

- Keep the existing PHP stack and one Composer package. Give the public website and administration separate thin front controllers and fixed identities independent of request headers. Keep configuration, dependencies, templates, CLI scripts, and private uploads outside both document roots.
- Use the split Compose applications as the compatibility baseline. Keep one route inventory for app, path, method, handler, and session behavior. Preserve published URLs, including `.php` paths, the `/index.php` alias for `/`, and the administration root redirect to `/login.php`.
- Reject a foreign-app URL with a local 404 before method and session handling. Preserve 405 and the Allow header for unsupported methods on known routes. Preserve session cookie scope, expiry, revocation and return destinations, CSRF validation, role and module permissions, and authorization for staged and persisted documents.
- Migrate global web helpers and configuration startup to their existing class owners where possible. Add focused classes for behavior that lacks an owner. PHP built-ins and declarative configuration remain available.
- Keep existing CLI command paths, arguments, environment precedence, output, exit status, and locking and recovery safeguards. Move live job and migration command behavior into autoloaded classes. Historical migration sources and any compatibility code used only to rehearse them remain historical.
- Keep both Compose service layouts, application origins, public asset URLs, shared volumes, and Doctrine startup and scheduler ownership. Retire the combined root Docker, Apache, and bare PHP launch paths and remove the fallback that resolves an HTTP URL to a root PHP file. Keep the disposable SQLite HTTP test harness.
- Deliver incrementally on one signed integration branch in one isolated worktree based on `dev`. Use a single PR to `dev` and record ticket validation and delivery evidence separately.

## Testing Decisions

- Exercise the application through the split HTTP targets and public CLI entry scripts. Prefer assertions on responses, redirects, cookies, authorization, command results, and durable state over private method or directory checks.
- Extend the existing split-app route rehearsal to cover the root aliases, cross-app rejection, method handling, and removal of the file fallback. Reuse the SQLite HTTP journeys, PHPUnit and standalone PHP checks, and JavaScript regression suite.
- Rehearse both Compose stacks on disposable data, including migration startup and failure, enrollment and recovery, and the jobs runner. Keep fixture injection in test code. Use syntax and packaging checks to verify the new entry points and document roots.

## Out of Scope

Framework adoption, separate dependency sets, schema or data migration, changes to business behavior, PHP or Twig built-in removal, edits to historical migration versions, production deployment, and live-data qualification.

## Further Notes

The dedicated document roots adopt the physical entry-point split while retaining the app boundary and shared data described in [ADR 0001](../../docs/adr/0001-independent-public-and-staff-entry.md). [ADR 0003](../../docs/adr/0003-use-separate-composer-web-entry-points.md) records the Composer and runtime decision.
