# Member Communications & Consent/RGPD Module Specification

Status: ready-for-agent

## Problem Statement

Staff users cannot reliably or quickly send announcements to Members from the Administration communications page (`admin.aeesgs.org/communications.php`):

- Sending executes a synchronous SMTP loop inside a single HTTP POST request, opening a fresh Brevo STARTTLS/SMTP connection per recipient. On Namecheap Stellar behind LiteSpeed and Cloudflare, sending to a growing membership times out mid-loop with no durable record of who received the message.
- Failed sends are silently counted as successes, and the page sends from `contact@aeesgs.org` instead of the verified `noreply@aeesgs.org` sender used everywhere else.
- Loading the page and previewing recipient counts hydrate every active Member record in PHP memory, and preview counts include Members who have no email address—disagreeing with who will actually be emailed.
- Branded email formatting, recipient personalization, pre-send confirmation, self-test previews, send history, and recovery actions (resume, retry failed, cancel) are missing.
- Previously available Consent reminder and Deletion request workflows were dropped during the OOP refactor while orphan consent stat cards remained on the communications page, and the public consent acceptance endpoint currently reuses the KYC correction token (`review_token`) without recording refusal or deletion request timestamps or invalidating tokens after use.

## Solution

Deliver two cleanly separated, fast, reliable Administration modules backed by one shared hybrid email delivery engine (ADR 0005):

1. **Member Communications (`/communications.php`)** — focused strictly on bulk Member communications:
   - Fast single-aggregate statistics and accurate SQL `COUNT(*)` recipient previews that always exclude soft-deleted Members and records without a valid email.
   - Audience filters for status (including `Diplômé(e)` mapped across legacy imported status values), institution, privacy consent state, and KYC status (defaulting to excluding `REJECTED`, with explicit overrides).
   - Branded AEESGS HTML email rendering with safely escaped line breaks, `{{prenom}}` and `{{nom}}` personalization, one-click test send to the logged-in Staff user, and a confirmation dialog displaying the exact recipient count before queueing.
   - Instant durable queueing in MariaDB followed by fast browser-driven chunked sending over a reused Brevo SMTP connection (`SMTPKeepAlive`) with a live progress bar, send history, and Resume / Retry failed / Cancel controls.
   - Automatic fallback and retry draining by the existing scheduled `mail` CLI worker sharing one unified daily quota counter (`MAIL_DAILY_LIMIT`, default `300`/day).

2. **Consent & RGPD (`/consent.php`)** — a dedicated new Administration module gated by its own `'consent'` permission under *Adhérents et dossiers*:
   - Fast paginated and AJAX-searchable pending-consent and manual-selection Member lists.
   - Consent reminder sending over the exact same hybrid delivery engine, generating a dedicated per-Member `cgu_token` linking to the Public website (`PUBLIC_APP_URL`) and recording `cgu_reminder_sent_at` on confirmed delivery.
   - A Deletion requests tab listing active Members with recorded deletion requests, linking to their dossier and offering a direct soft-delete action to Trash when the Staff user also holds the `'students'` permission.
   - An isolated, one-time public consent acceptance flow using `cgu_token` exclusively (never KYC `review_token`), recording `consent_refused_at` and `deletion_requested_at` and invalidating `cgu_token` upon acceptance or deletion request.

## User Stories

1. As a Staff user with the `communications` permission, I want the communications page to load immediately using aggregate SQL counts, so that page load stays fast even as the membership grows.
2. As a Staff user, I want the communications summary cards to show communication-relevant numbers (active Members, reachable Members with a valid email, Members missing an email, and today's send count versus the daily quota), so that I know my real reach and remaining Brevo allowance before sending.
3. As a Staff user, I want to filter a Member communication by status (`Élève`, `Étudiant`, `Stagiaire`, `Diplômé(e)`), institution, privacy consent state, and KYC status, so that I reach the exact intended audience.
4. As a Staff user, I want the status filter to include legacy imported status variants (`PUPIL`, `STUDENT`, `TRAINEE`, `Diplômé`, `GRADUATE`), so that imported Members are never silently missed.
5. As a Staff user, I want the default KYC filter to exclude `REJECTED` dossiers while letting me choose any specific KYC status or all statuses, so that rejected applicants do not receive ordinary announcements by accident.
6. As a Staff user, I want the recipient preview count to reflect the exact number of active, non-trashed Members with a valid email matching my current filters, so that the count matches who will actually be queued.
7. As a Staff user, I want my plain-text message body to be wrapped in the branded AEESGS bulk-communication email template with line breaks preserved and arbitrary HTML safely escaped, so that emails look professional without risking XSS or broken layout.
8. As a Staff user, I want to use `{{prenom}}` and `{{nom}}` placeholders in the body of a Member communication, so that each Communication recipient receives a personalized message.
9. As a Staff user, I want bulk emails to be sent from `noreply@aeesgs.org` with `Reply-To` set to the association's configured contact email (`contact@aeesgs.org` fallback), so that Brevo delivers reliably and Member replies go to the association inbox.
10. As a Staff user, I want a one-click "Envoyer un test à moi-même" button that sends a single preview email to my own Staff user email address without creating a bulk communication record, so that I can inspect rendering and personalization before emailing Members.
11. As a Staff user, I want a confirmation modal showing the subject and exact recipient count before queueing a Member communication, so that I cannot accidentally trigger a bulk send on a misclick.
12. As a Staff user, I want submitting a Member communication to persist the communication and its Communication recipients in the database immediately and dispatch batches over a reused SMTP connection with a live progress bar, so that sending finishes in seconds without browser or gateway timeouts.
13. As a Staff user, I want closing my browser tab mid-send to leave already-sent recipients untouched and allow either reopening the page to resume or letting the scheduled `mail` CLI job finish the remaining recipients automatically, so that no Member receives duplicates and no send is lost.
14. As a Staff user, I want both live web chunks and the scheduled `mail` CLI worker to enforce the same daily send cap (`MAIL_DAILY_LIMIT`, default `300`), so that bulk sends and transactional emails together never exceed our Brevo daily limit.
15. As a Staff user, I want a communication that hits the daily limit to pause cleanly in a `PAUSED_LIMIT` state with clear remaining counts and automatically continue once the next day's quota opens (or when resumed), so that large sends complete reliably over multiple days if needed.
16. As a Staff user, I want to see a history of recent Member communications with subject, status badge, delivered/failed/total counts, timestamp, and author, so that the team has an audit trail of outreach.
17. As a Staff user, I want to inspect failure reasons for failed Communication recipients and click **Retry failed** to re-queue only failed rows, so that transient SMTP errors can be recovered without re-sending to everyone.
18. As a Staff user, I want to click **Resume** on an unfinished communication or **Cancel remaining** to cancel unsent recipients, so that I stay in full control of in-flight sends.
19. As a Staff user on a slow or flaky connection, I want atomic recipient claiming and stale-claim recovery, so that opening two tabs or overlapping with the cron worker never sends duplicate emails to the same Member.
20. As an operator deploying to Namecheap Stellar, I want `MAIL_DAILY_LIMIT`, `MAIL_BATCH_SIZE`, `MAIL_MAX_ATTEMPTS`, and `MAIL_SEND_PAUSE_MS` supported in runtime configuration rendering, so that quota and pacing can be tuned without code changes when upgrading Brevo plans.
21. As a Super Admin, I want a dedicated `'consent'` module permission in user management, separate from `'communications'`, so that Staff users who send announcements do not automatically gain GDPR deletion oversight.
22. As a Staff user with the `'consent'` permission, I want to access a dedicated **Consentement & RGPD** module under *Adhérents et dossiers* in the Administration sidebar, so that legal consent tracking and deletion requests have their own clear workspace.
23. As a Staff user without the `'consent'` permission (or an unauthenticated visitor or public-host request), I want access to Consent & RGPD endpoints to be denied according to split-host and permission boundaries, so that private Member consent data remains protected.
24. As a Staff user with the `'consent'` permission, I want to view summary counts (consented, pending consent, reminders sent, open deletion requests) and browse a paginated, searchable list of Members awaiting consent without dumping all Members into the page DOM, so that the page stays fast at scale.
25. As a Staff user with the `'consent'` permission, I want to send a Consent reminder to (a) Members without consent, (b) all active Members for a terms update, or (c) manually selected Members chosen via server-side search, using the same fast hybrid delivery engine and branded consent reminder template, so that large consent campaigns never time out.
26. As a Staff user, I want every Consent reminder link to use the configured Public website origin (`PUBLIC_APP_URL`) and a fresh dedicated `cgu_token` (never the KYC `review_token`), and I want `cgu_reminder_sent_at` recorded only when the email is confirmed sent, so that consent links work across the split domains and reflect real delivery.
27. As a Member receiving a Consent reminder, I want clicking my personal link on the Public website to let me accept or refuse the terms, and if I accept, immediately record my consent, clear any prior refusal/deletion markers, and invalidate the link so it cannot be replayed later.
28. As a Member who refuses the terms via my personal consent link, I want my refusal timestamp recorded while keeping my token valid for the immediate follow-up screen so I can either reconsider and accept or submit a Deletion request.
29. As a Member who submits a Deletion request, I want `deletion_requested_at` persisted on my record, my `cgu_token` invalidated, and the association's contact email notified, so that my request is reliably tracked even if an email notification is missed.
30. As a Staff user with the `'consent'` permission, I want a **Demandes de suppression** tab listing all active Members with pending deletion requests, with a link to inspect each Member's dossier and—if I also hold the `'students'` permission—a direct **Déplacer vers la corbeille** button routed through the existing soft-delete/Trash workflow, so that GDPR requests are handled safely without bypassing Trash or document lifecycle rules.
31. As a Staff user using dark or light mode on desktop or mobile, I want both the Communications and Consent & RGPD interfaces to respect the repository theme variables and accessibility conventions, so that both pages feel native to the Administration shell.

## Implementation Decisions

### Domain & Module Boundaries

- **Two distinct Administration modules**:
  - **Communications** (`/communications.php`, `/communications-count.php`, `/communications-dispatch.php`): requires admin role + `'communications'` permission; handles bulk Member communications (`BULK` type), self-test previews, live chunk dispatch, history, and lifecycle actions (resume, retry failed, cancel).
  - **Consent & RGPD** (`/consent.php`, `/consent-members.php`, `/consent-dispatch.php`): requires admin role + `'consent'` permission; handles Consent reminders (`CGU_REMINDER` type), paginated/searchable pending & selectable Member lists, live chunk dispatch, and Deletion requests (direct soft-delete action additionally requires `'students'` permission and delegates to the existing soft-delete lifecycle service).
  - Navigation whitelist and router inventory register every new staff GET/POST route under the staff application only; public host requests to any staff route continue returning local `404` without redirect (ADR 0001).
  - User management adds `'consent'` (`Consentement & RGPD`) to the canonical module permission whitelist and renders it in admin user creation/editing forms; Super Admin (ID 1) retains automatic access to all modules.

### Database Schema & Doctrine Migrations

- Add Doctrine Migrations (kept aligned with the baseline schema definition, separating DDL safely for MariaDB and preserving SQLite test parity):
  - **`communications`**: `id`, `type` (`BULK` | `CGU_REMINDER`), `subject`, `body`, `filters_json` (JSON snapshot of targeting filters/scope), `status` (`QUEUED` | `SENDING` | `PAUSED_LIMIT` | `COMPLETED` | `COMPLETED_WITH_ERRORS` | `CANCELLED`), `total_recipients`, `sent_count`, `failed_count`, `cancelled_count`, `created_by`, `created_at`, `started_at`, `completed_at`.
  - **`communication_recipients`**: `id`, `communication_id` (FK cascading delete), `student_id` (nullable FK/reference), `email`, `first_name`, `last_name`, `status` (`PENDING` | `SENDING` | `SENT` | `FAILED` | `CANCELLED`), `attempts`, `next_attempt_at`, `claimed_at`, `sent_at`, `last_error`, indexed on `(communication_id, status, next_attempt_at)` and `(status, next_attempt_at)`.
  - **`mail_daily_usage`**: `usage_date` (`DATE` primary key), `sent_count` (`INT NOT NULL DEFAULT 0`), `updated_at` — shared transactional daily quota counter across live web chunks and the CLI `mail` worker.
  - **`students` additions**: `cgu_token` (`VARCHAR(64) NULL UNIQUE`), `cgu_reminder_sent_at` (`DATETIME NULL`), `consent_refused_at` (`DATETIME NULL`), `deletion_requested_at` (`DATETIME NULL`), plus supporting index for deletion-request listing.

### Hybrid Delivery Engine & Transport Seam

- **Sender contract**: Every bulk and consent reminder email sends with `From: noreply@aeesgs.org` (`AEESGS`) and `Reply-To` resolved from `settings.contact_email` (fallback `contact@aeesgs.org`).
- **Persistent SMTP session & single new test seam**:
  - Introduce a thin batch mail transport seam inside the mail service so callers open one session, send a batch of messages over a single reused PHPMailer connection (`SMTPKeepAlive = true`, explicit `smtpClose()` in `finally`), and receive per-message success/error results.
  - Under non-production test/dev environments (`APP_ENV` in `demo|dev|development|local|test|testing`), delivery remains suppressed without touching Brevo unless an explicit test transport is injected.
- **Chunk claiming, concurrency & recovery**:
  - Live dispatch claims up to `25` due recipients (`status = 'PENDING'` with `next_attempt_at <= NOW()`, plus stale `SENDING` claims older than 5 minutes) inside a transaction, marking them `SENDING` with `claimed_at = NOW()` before opening SMTP.
  - Before each send, the engine reserves 1 slot against `mail_daily_usage` for today up to `MAIL_DAILY_LIMIT` (default `300`). If the daily limit is reached, remaining claimed recipients revert to `PENDING` and the communication transitions to `PAUSED_LIMIT`.
  - On send success: mark recipient `SENT` with `sent_at = NOW()`; if the communication type is `CGU_REMINDER`, update `students.cgu_reminder_sent_at = NOW()`.
  - On send failure: increment `attempts` and record truncated `last_error`. If `attempts >= MAIL_MAX_ATTEMPTS` (default `5`), mark `FAILED`; otherwise reschedule `PENDING` with exponential backoff (`min(60 * 2^attempts, 3600)` seconds).
  - After every chunk, recompute communication counters and derive its aggregate status (`COMPLETED`, `COMPLETED_WITH_ERRORS`, `PAUSED_LIMIT`, `CANCELLED`, `SENDING`, or `QUEUED`).
- **Scheduled CLI worker convergence**:
  - The existing `mail` CLI job drains both legacy file-queued transactional emails and due `communication_recipients` using the same shared daily quota counter (`mail_daily_usage`, migrating/honoring any same-day counter state cleanly) and persistent SMTP batch transport, respecting `MAIL_BATCH_SIZE`, `MAIL_DAILY_LIMIT`, `MAIL_MAX_ATTEMPTS`, and `MAIL_SEND_PAUSE_MS`.
  - Runtime environment rendering for Namecheap Stellar allowlists `MAIL_DAILY_LIMIT`, `MAIL_BATCH_SIZE`, `MAIL_MAX_ATTEMPTS`, and `MAIL_SEND_PAUSE_MS`.

### Rendering, Filtering & Security Contracts

- **Email templates**:
  - Fix bulk communication email template rendering so escaped plain-text body converts newlines cleanly to `<br>` without double-escaping HTML entities or allowing user-injected raw HTML; substitute `{{prenom}}` and `{{nom}}` safely before rendering.
  - Render Consent reminders with the branded `cgu-reminder` template, generating a fresh cryptographically random 64-hex-character `cgu_token` per targeted Member and building the acceptance URL strictly from `StaffNavigation::publicOrigin()` (`PUBLIC_APP_URL`); fail closed with a clear French error if `PUBLIC_APP_URL` is unconfigured.
- **Recipient eligibility & filters**:
  - Always filter `deleted_at IS NULL AND email IS NOT NULL AND TRIM(email) != ''` and validate email syntax before queueing.
  - Normalize status filter groups: `ELEVE` → `['ELEVE', 'PUPIL']`, `ETUDIANT` → `['ETUDIANT', 'STUDENT']`, `STAGIAIRE` → `['STAGIAIRE', 'TRAINEE']`, `Diplômé(e)` → `['Diplômé(e)', 'Diplômé', 'GRADUATE']`.
  - Support filters for `status`, `institution`, `consent` (`consented` | `not_consented`), and `kyc_status` (default `exclude_rejected`, plus `all` or any specific enum value).
- **HTTP compatibility & CSRF**:
  - Every mutating endpoint (`POST /communications.php`, `/communications-dispatch.php`, `/consent.php`, `/consent-dispatch.php`, deletion soft-delete) requires valid CSRF verification and explicit admin + module permission checks.
  - Standard non-AJAX form submission to `POST /communications.php` still returns `302` with a French flash summary mentioning queued/dispatched `email(s)` and redirects to `communications.php?campaign=<id>` so existing smoke workflows and non-JS fallbacks continue to work cleanly while JS-enabled browsers immediately drive live chunk progress.
- **Public consent token lifecycle**:
  - `AcceptTermsController` looks up Members exclusively via `cgu_token` (active non-trashed Members only), never `review_token`.
  - `accept`: sets `consent_privacy = 1`, clears `consent_refused_at`, `deletion_requested_at`, and `cgu_token`.
  - `refuse`: sets `consent_privacy = 0`, sets `consent_refused_at = NOW()`, preserves `cgu_token` for the immediate follow-up choice screen.
  - `request_deletion`: sets `deletion_requested_at = NOW()`, clears `cgu_token`, and sends notification email to `settings.contact_email`.

## Testing Decisions

- **Testing philosophy**: Test observable external behavior through high-level seams—HTTP responses, rendered HTML/emails, database state transitions, CLI exit codes/output, and recorded transport envelopes—rather than internal helper implementations.
- **Testing seams**:
  - Reuse real **SQLite PDO** + real **Twig rendering** for unit/integration suites (following existing controller, repository, and service integration test patterns).
  - Reuse real **CLI subprocess execution** (following existing CLI command integration tests) for the `mail` worker.
  - Reuse real **split-host HTTP & permissions suites** (public vs staff app boundary, CSRF enforcement, role/permission checks, `APP_ENV=test` suppression).
  - Introduce **one new seam** at the outbound SMTP session boundary in the mail service so tests can inject a recording/fault-injecting transport double to verify persistent session reuse, exact `From`/`Reply-To`/HTML payloads, partial failures, backoff scheduling, stale-claim recovery, and shared daily quota caps without network calls.
- **Modules covered**:
  - Mail batch transport & `EmailService` (sender headers, persistent batch reuse, failure reporting, test-env suppression).
  - Communication repository & hybrid delivery service (filtering including legacy statuses and invalid-email exclusion, atomic claiming, stale-claim recovery, shared daily quota across web + CLI, retry/resume/cancel state machine, self-test preview).
  - Communication controller & Twig view rendering (stats aggregation, count accuracy, CSRF, flash/redirect contract, template personalization & escaping, dark-mode markup).
  - CLI `ProcessMailQueueCommand` (draining DB communication recipients + file queue together, shared daily cap, backoff & dead-lettering, updating `cgu_reminder_sent_at`).
  - Consent & RGPD module, `UserService` permissions, and `AcceptTermsController` (module permission enforcement, paginated/searchable member queries, `cgu_token` isolation from `review_token`, accept/refuse/deletion state transitions, Trash handoff permission check).
  - Doctrine Migrations & baseline schema parity + Stellar runtime-env allowlist tests.

## Out of Scope

- Brevo HTTP Transactional API integration (`BREVO_API_KEY`) or switching away from Brevo SMTP.
- Rich-text WYSIWYG HTML authoring, file attachments, or scheduled future-date campaign sending.
- Automated permanent hard-deletion of Member records from the Consent & RGPD module (deletion requests remain routed through the existing soft-delete/Trash and retention workflow).
- Production deployment, cron activation, or live Brevo sends (repository validation only; production rollout remains separately authorized).

## Further Notes

- Architectural rationale for the hybrid delivery engine is captured in ADR 0005 (`docs/adr/0005-hybrid-bulk-communication-delivery.md`).
- Canonical terminology (**Member communication**, **Communication recipient**, **Consent reminder**, **Deletion request**) is recorded in `GLOSSARY.md`.
- Maintain full `[data-bs-theme="dark"]` parity and the repository's light-by-default theme behavior on both Administration pages.
