# Application separation inventory (2026-10-23)

Source of truth: current root entry points plus `src/bootstrap.php`. The router's legacy fallback still exposes other root PHP files; route allowlists must account for the inventory below, not just navbar links.

| Owner after extraction | Current entry points / behavior |
|---|---|
| Public | `/`, `index.php`, `register.php`, `registration-details.php`, `registration-preview.php`, `kyc-correction.php`, `accept-cgu.php`, `legal-notice.php` |
| Staff identity | `login.php`, `logout.php`, `forgot-password.php`, `reset-password.php` |
| Staff membership and private files | `dashboard.php`, `students.php`, `student-details.php`, `edit-student.php`, `kyc-list.php`, `kyc-detail.php`, `trash.php`, `download.php`, `upgrade-levels.php`, `confirm-upgrade.php` |
| Staff operations | `users.php`, `add-user.php`, `edit-user.php`, `profile.php`, `export.php`, `export-preview.php`, `settings.php`, `manage-slider.php`, `communications.php`, `communications-count.php` |
| Infrastructure | `404-handler.php`; deny direct execution where appropriate. Re-inventory root PHP and reachable subdirectories when defining allowlists |

`src/Core/Session.php` uses the default PHP session cookie/name/storage for all routes, with 30-minute inactivity expiry, Secure, HttpOnly, SameSite Strict. `config/session.php` additionally checks session_version for legacy consumers; routed BaseController uses Session directly. Registration state and staged ownership currently share this session with staff identity. New hosts need independent cookie names, namespaces and storage, and explicit treatment of the legacy bootstrap.

`uploads/students/pending` contains session-owned staged files, including `cvs/`; confirmation finalizes to `uploads/students` and `uploads/students/cvs`. `registration-preview.php` checks the current pending session and permitted path/MIME. `download.php` requires admin and restricts realpath to uploads. Public carousel files are managed by SliderController/SliderRepository; separate their publication from private mounts. Correction/consent retain tokenized URL behavior; migrations must preserve those URLs.

Background work: `scripts/process-mail-queue.php` owns `storage/mail-queue`; `scripts/purge-pending-uploads.php` reaps staging; `scripts/purge-trashed-students.php` purges retained records/files. Do not start any of them twice after extraction. EmailService queueing is opt-in through MAIL_ASYNC; synchronous fallback exists and must be disabled/safely captured in rehearsal. No jobs or real email ran during baseline.

Baseline evidence: `tests/http/baseline.py` passes 15 assertions covering registration, staged-file isolation, staff login and document access. PHPUnit: 140 tests, 240 assertions, 10 pre-existing PHPUnit deprecations. Standalone scripts: permissions 47/47, OOP unit 186/186, OOP integration 78/78, password security 24/24. Local PHP 8.5.12; CI configured PHP 8.4. No remote CI run claimed. Existing CI already targets dev/main PRs and dev pushes after integration-base synchronization; this ticket adds HTTP journeys to that job, with no deployment.

Remaining qualification: browser JavaScript/Tagify, visual screenshots, mobile/dark/focus/reduced motion, two-host TLS/session separation, token expiry and correction/consent journeys, actual MySQL schema, cross-app data visibility, container replacement, rollback/restore and one-worker ownership. These belong to successor tickets and cannot be inferred from this baseline. See `tests/http/README.md` for fixture/dialect limitations.
