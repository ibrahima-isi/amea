# Journal browser qualification — 2026-10-31

## Current decision — user-owned deferral

On 2026-10-31 the user explicitly deferred these checks and will perform them later. Browser QA remains **incomplete**, while implementation and server delivery are merged and CI-verified. No further automated browser attempt is requested. Native Computer Use activation subsequently succeeded, but keyboard/navigation effects remained unverifiable; Playwright was proposed and never launched. The last disposable runner was stopped normally and its temporary data removed.

Remaining user checklist: public list/filter/pagination/article and cover; private draft creation/upload/preview/publication/edit/withdrawal/republication with stable URL/date; 375 px and desktop in both themes; keyboard/focus and mobile staff-menu behavior. Also verify the approved same-day event end-date prefill before saving: source inspection suggests only a server default after save, but this has **not** been reproduced in a browser and is not a confirmed defect.

For resumption, create an isolated worktree from the selected revision, install locked dependencies, then run the command below. Use the **new JSON URL**; all ports from earlier attempts in this record have expired. No production, existing demo seeding or certificate bypass is included. Sections below retain historical observations and limits.

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

On retry, Brave rendered the synthetic staff login in light desktop layout; its actual capture was saved to `/private/tmp/amea-journal-qa-20261031/staff-login-light-desktop.png`. The earlier fixture had expired, so a fresh disposable instance was started. After navigation to its live URL, page content again disappeared from native accessibility; Chrome also refused an action because its user-visible state had changed. This adds a login rendering observation only, and does not close journal editorial or mobile acceptance.

## Current blocker and remaining checks

The browser-provider inventory returned `Browsers: Error: The browser access host is unavailable`. Native Chrome initially exposed accessibility but then returned app-state-change errors and repeated 30-second timeouts with kernel resets. Native Brave provided the rendered view above, then lost page accessibility/paint after navigation. Re-querying state, raising the window, reloading and leaving full screen did not restore a usable rendered page. The same reports URL independently returned HTTP 200, 3,174 bytes in 0.005 seconds; this is not evidence of a product rendering defect or a passing browser check.

The user has deferred the remaining checks rather than waiting for further tool repair. Still unverified: rendered journal light theme, small viewport/overflow, keyboard/focus, list pagination and each filter in the browser, detail paragraphs/cover, and browser creation/upload/private preview/publication/edit/withdrawal/republication. No certificate warning was bypassed, and no external browser-control script was substituted.

The fixture intentionally uses single-origin loopback HTTP, SQLite and the existing test-session router. It does not qualify split-host isolation, TLS/Secure cookies, MySQL, production or real email. Prior HTTPS/MySQL evidence remains separate. The maintainer merged the follow-up despite the separate browser gap; that action does not validate these unobserved checks.

## Independent review and follow-up delivery

Two fresh-context reviewers independently examined `4aab39f...81472d2` read-only. **Standards:** zero documented violations or material smell findings. **Spec:** zero findings; fixture-only scope, real HTTP/CSRF seeding and explicit browser limits match approved task 4. Neither reviewer executed the browser checks. No fix wave is required.

The signed follow-up [PR #20](https://github.com/ibrahima-isi/amea/pull/20) was merged into `dev` as `3033d4b`. Its three jobs passed on signed head `09a414c` ([CI](https://github.com/ibrahima-isi/amea/actions/runs/41162939038)); [merged-dev CI](https://github.com/ibrahima-isi/amea/actions/runs/41163154556) also passed. This qualifies server/test support separately from the deferred browser gap.

Fresh local validation passed on this support: PHPUnit 159 tests / 310 assertions, scripts 48/186/78/24 and HTTP journeys 35 + 28. PHP 8.5.12 reported the same 10 pre-existing PHPUnit doc-comment metadata deprecations. The HTTP commands required normal loopback permission; their initial sandbox refusals were not application failures. A subsequent CI rehearsal failure led to a separately [reproduced and corrected fresh-volume initialization race](restore-ci.md). Check the latest head for its complete remote result.
