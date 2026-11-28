# Email Queue & Hybrid Communications — Operations Runbook

How outbound email, bulk Member communications, and Consent reminders work across Namecheap Stellar and Docker/Dokploy. Production changes require explicit approval; no worker or SMTP delivery is enabled by this document.

## How it works

There are three delivery paths, chosen per message type:

| Path | Mechanism | Used by | Behaviour |
|------|-----------|---------|-----------|
| **Synchronous** (always) | `EmailService::send()` / `sendWithResult()` | Password reset, "forgot password", CGU deletion notification, communication self-test preview | Sent immediately in-request over Brevo SMTP (`noreply@aeesgs.org`). |
| **Queue-able transactional** | `sendAsync()`, `sendFromTemplate()` | Registration received, KYC approved / clarification / rejected, admin update notification | Sent synchronously by default (`MAIL_ASYNC` unset/`0`), or written to `storage/mail-queue/*.json` when `MAIL_ASYNC=1`. |
| **Hybrid DB-backed communications** | `CommunicationService` + `communications` / `communication_recipients` | Bulk Member communications (`/communications.php`) and Consent reminders (`/consent.php`) | Persisted immediately in MariaDB, dispatched in fast 25-recipient chunks over a single reused SMTP session (`SMTPKeepAlive = true`) while the staff page is open, and automatically drained/retried by `scripts/process-mail-queue.php` if the browser closes or a daily cap / transient error occurs. See [ADR 0005](adr/0005-hybrid-bulk-communication-delivery.md). |

All bulk communications and Consent reminders send from `noreply@aeesgs.org` with `Reply-To` set to `settings.contact_email` (falling back to `contact@aeesgs.org`).

If `MAIL_ASYNC=1` is enabled and the file queue directory cannot be written, `EmailService` falls back to a synchronous send so transactional mail is never silently dropped.

## Unified daily quota (`mail_daily_usage`)

Live web chunks and `scripts/process-mail-queue.php` share one atomic daily counter in the `mail_daily_usage` table (keyed by UTC date) capped at `MAIL_DAILY_LIMIT` (default `300`/day for Brevo's free tier).

- Slots are reserved atomically before opening SMTP and released for any failed attempts so transient failures do not burn quota.
- When `MAIL_DAILY_LIMIT` is reached, remaining unsent communication recipients stay `PENDING` and their parent communication transitions to `PAUSED_LIMIT` until the next UTC day (or a raised `MAIL_DAILY_LIMIT`).
- Both workers honor the legacy UTC counter in `storage/mail-queue/.sent-YYYYMMDD` as a floor when reserving a slot. The file-only worker maintains that counter when no database is configured. A configured database outage stops the worker.

## The worker: `scripts/process-mail-queue.php`

Run from cron (`run-shared-jobs.php mail` on Docker/Dokploy or `bin/run-job.php mail` on Namecheap Stellar). Properties:

- **Single-instance** — holds an exclusive `flock` (`storage/mail-queue/.lock`); overlapping cron runs no-op.
- **Transactional priority + DB fallback** — drains file-queued transactional emails first, then uses the remaining batch capacity (`MAIL_BATCH_SIZE`) to drain due DB `communication_recipients` (bulk and Consent reminders) over a reused `SMTPKeepAlive` connection.
- **Stale-claim recovery** — The web and CLI dispatchers hold an exclusive lock in the shared queue directory throughout each SMTP batch. Process death releases the lock. A later dispatcher can reclaim a five-minute-old claim. Cancellation stops recipients that have yet to be admitted to SMTP. If the worker dies after reserving a slot, the original reservation remains counted because delivery cannot always be determined. If SMTP accepted the message before the worker died and the database still shows `SENDING`, recovery can deliver it again.
- **Durable retries** — failed deliveries retry with exponential backoff (2 → 4 → 8 → 16 min, capped at 1 h). After `MAIL_MAX_ATTEMPTS`, file-queue entries move to `storage/mail-queue/failed/` and DB recipients transition to `FAILED` (recoverable via **Réessayer les échecs** in the Administration UI).
- **Consent reminders** — confirmed delivery updates `students.cgu_reminder_sent_at` and activates the new link if the member's email and previous token still match. A failed send preserves the last delivered link.

### Tunables (`.env` / Stellar `runtime.env`)

| Variable | Default | Meaning |
|----------|---------|---------|
| `MAIL_ASYNC` | _(unset)_ | `1` enables the durable file queue for queue-able transactional emails |
| `MAIL_BATCH_SIZE` | `50` | Max combined messages delivered per CLI worker run |
| `MAIL_DAILY_LIMIT` | `300` | Unified daily cap across web chunks + CLI worker (= Brevo free tier) |
| `MAIL_MAX_ATTEMPTS` | `5` | Attempts before dead-lettering (file) or marking `FAILED` (DB) |
| `MAIL_SEND_PAUSE_MS` | `250` (CLI) / `50` (web chunk) | Pause between messages inside a persistent SMTP session, in ms |

Empty values use the listed defaults. Both Compose stacks pass these settings to the applications and jobs. All four tunables (`MAIL_DAILY_LIMIT`, `MAIL_BATCH_SIZE`, `MAIL_MAX_ATTEMPTS`, `MAIL_SEND_PAUSE_MS`) are allowlisted in `scripts/stellar/render-runtime-env.php`.

## Worker deployment and rollback

The Docker/Dokploy jobs profile and Stellar native layout are described in [public-staff-rollout.md](operations/public-staff-rollout.md) and [stellar-production-transition.md](operations/stellar-production-transition.md). `MAIL_ASYNC` remains off by default so 5-minute password-reset emails do not wait on a 5-minute cPanel cron interval.

To roll back `MAIL_ASYNC`, unset it or set `MAIL_ASYNC=0`. Existing queued file entries and DB `communication_recipients` remain durable until processed or cancelled.

## Monitoring & troubleshooting

- **File backlog**: `ls storage/mail-queue/*.json | wc -l` — should stay near zero.
- **File dead-letters**: inspect `storage/mail-queue/failed/`. To retry one, move it back to `storage/mail-queue/` and reset `next_attempt_at` to `0`.
- **DB communication status**: inspect `/communications.php` (history + expandable failure reasons + Resume / Retry failed / Cancel) or query `communications` / `communication_recipients`.
- **Daily cap reached**: check `mail_daily_usage`. Raise `MAIL_DAILY_LIMIT` after upgrading your Brevo plan.
