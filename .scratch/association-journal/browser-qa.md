# Journal browser qualification — 2026-10-31

## Scope and revision

Remaining work from [ticket 04](issues/04-integrated-qualification.md) and task 4 of the [approved plan](../../docs/superpowers/plans/2026-10-30-association-journal.md). Product revision is merged PR #19, `4aab39f`; signed test-support commit `8e67e8d` adds only the disposable fixture and its instructions. No product source, production configuration or retained demo data changes.

## Reproduction

From an isolated worktree with locked Composer dependencies:

```sh
python3 tests/http/journal-browser.py --seed --archive-count 12
```

The JSON output provides the ephemeral loopback URL, synthetic accounts and repository JPEG path. Keep the runner alive while interacting with the real forms; Ctrl+C or SIGTERM stops its PHP process and removes the disposable database, uploads and sessions. No host credentials or mail workers are used.

The support worker verified 38 HTTP checks, including the pre-seed empty state, 17 publications created through real staff forms/CSRF, type/date filters, ongoing activities, archive pagination, homepage limit, article URL, actual JPEG response, role denials, and full cleanup under both signals. These are server and fixture checks, not browser acceptance results.

## Actual browser evidence

Using supported native CUA control, a dedicated Brave tab opened the real fixture at `http://127.0.0.1:61386/journal.php?view=upcoming`.

- Accessibility text and a rendered screenshot showed the public journal in dark desktop layout: two actual image cards, an ongoing activity labelled « En cours », a future activity, event dates/place, and association signature/date. No staff/login control appeared in that observed view.
- A later accessibility observation of the actual homepage showed three recent publication links and the journal entry, without a staff entry.
- The theme button was activated and its accessible label changed to « Mode sombre ». Navigation to the reports filter changed the URL. Neither action provides a passing visual result: subsequent native captures were uniformly blank or had only browser chrome.

The rendered screenshot is present in the session's CUA tool output. Its attempted file capture timed out, leaving no portable PNG artifact; do not substitute the previous mockup or the later blank captures.

## Current blocker and remaining checks

The browser-provider inventory returned `Browsers: Error: The browser access host is unavailable`. Native Chrome initially exposed accessibility but then returned app-state-change errors and repeated 30-second timeouts with kernel resets. Native Brave provided the rendered view above, then lost page accessibility/paint after navigation. Re-querying state, raising the window, reloading and leaving full screen did not restore a usable rendered page. The same reports URL independently returned HTTP 200, 3,174 bytes in 0.005 seconds; this is not evidence of a product rendering defect or a passing browser check.

User input to restore browser access is pending. Still unverified: rendered light theme, small viewport/overflow, keyboard/focus, list pagination and each filter in the browser, detail paragraphs/cover, and browser creation/upload/private preview/publication/edit/withdrawal/republication. No certificate warning was bypassed, and no external browser-control script was substituted.

The fixture intentionally uses single-origin loopback HTTP, SQLite and the existing test-session router. It does not qualify split-host isolation, TLS/Secure cookies, MySQL, production or real email. Prior HTTPS/MySQL evidence remains separate. Keep the follow-up PR draft while browser acceptance remains incomplete.
