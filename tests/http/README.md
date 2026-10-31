# Isolated HTTP baseline

Run `composer install` then `python3 tests/http/baseline.py` (Python 3 standard library, PHP with SQLite/fileinfo and the normal application extensions).

The runner copies application code and dependencies into a fresh OS temporary directory. It never copies `.env`, uploads, storage, or existing sessions/database. Both PHP subprocesses receive a minimal environment without host DB/SMTP credentials. A synthetic SQLite database is injected through the existing Container boundary; `APP_ENV=test` suppresses templated mail using the existing EmailService behavior. No worker runs. Temporary uploads and session data are deleted when the runner exits, including after assertions fail. Test entry points reject non-temporary roots and non-test SAPIs. The server binds only loopback on an ephemeral port.

Scenarios execute real HTTP requests with separate cookie jars: CSRF rejection, valid registration with a synthetic PDF, owner/foreign-session staged previews, confirmation and success message, anonymous download denial, staff login, authorized persisted document download. Assertions inspect HTTP status, redirects, response bodies and privacy headers; they do not inspect session internals or persisted rows.

Limitations of the default HTTP run: no JavaScript/layout/keyboard rendering; the current single application is characterized, not separation. Loopback HTTP uses a test session started before the production session configurator, so Secure-cookie/TLS behavior is not qualified. The SQLite adapter translates MySQL `INSERT IGNORE INTO` into SQLite `INSERT OR IGNORE INTO`; MySQL migrations/constraints and concurrent delivery require the isolated deployment rehearsal. Test delivery suppression proves no email is sent, not external delivery. Upload persistence is shown in a disposable copy, not across container replacements.

Homepage browser checks (ticket 02): `AMEA_BROWSER_CHECK=1 python3 tests/http/baseline.py` additionally launches a fresh headless Chrome profile using Node 22+ built-in WebSocket and CDP. Set `CHROME_BIN` for another Chrome/Chromium executable (default is macOS Google Chrome). No browser packages or live profile are used. The same isolated HTTP journey first publishes two photos/captions through the real staff form and later deletes them. Browser checks cover automatic/manual rotation, pause, reduced motion, device theme preference, switching themes, desktop/mobile overflow, keyboard skip link and single/empty states. Four generated screenshots are written to the ignored `docs/testing/screenshots/` directory. Ordinary HTTP baseline remains independent of Node/Chrome. Fixture photo is an existing approved public repository JPEG; fixture captions and staff/member accounts are synthetic. The fixture router serves only recognized static types under assets and public slider uploads; private student uploads stay behind their authorized handlers.

Registration browser checks (ticket03): `AMEA_REGISTRATION_BROWSER=1 python3 tests/http/baseline.py` launches fresh Chrome against the disposable fixture and writes eight registration/review screenshots. It verifies nationality enhancement initialization, desktop/mobile width, visible keyboard section control, synthetic PDF input, protected review link and confirmation feedback. Both themes are captured with reduced motion enabled. HTTP now also checks escaped retained fields, rejected MIME retention, review CSRF and staff field identities. JSON is an explicitly served public fixture asset type.

`harness.py` is the shared test-only disposable app/client boundary used by both
`baseline.py` and `tokens.py`. It retains the `amea-http-` temporary directory
prefix required by PHP fixture/router guards, copies no `.env`, forwards only the
sanitized process environment, uses independent cookie jars and always stops the
loopback PHP process before deleting the temporary application/session files.

Journal manual browser fixture (ticket04): run `python3 tests/http/journal-browser.py`
and keep the process alive during native browser interaction. It prints JSON with
the ephemeral URL, synthetic staff accounts/password, and the existing repository
JPEG path for the cover file picker. The journal starts empty; the runner verifies
the public empty state and homepage omission before any optional seed. Add `--seed`
for published future, ended and ongoing activities, a report and an announcement;
add `--archive-count 12` for a second archive page. All seeded publications use the
real authorized staff HTTP forms and CSRF; the five examples upload the repository
JPEG as their cover through the real media handler. The CLI fixture inserts only
synthetic accounts into the disposable SQLite database. Manual draft/cover creation,
preview, publication, edit, withdrawal and republication remain browser actions.
Stop the runner with Ctrl+C or SIGTERM: the shared context stops its owned PHP
server and removes the temporary database, uploads and sessions. This fixture is
single-origin loopback HTTP, using the existing test session router; it does not
qualify split-host routing, TLS, Secure cookies, MySQL or production behavior.
