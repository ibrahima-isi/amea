# 03: CLI mail worker fallback & unified daily quota

**What to build:** Upgrade the scheduled `mail` CLI job (`ProcessMailQueueCommand` / `scripts/process-mail-queue.php`) so it serves as the durable background fallback and retry processor for the hybrid communication engine. When invoked, the worker continues draining existing file-queued transactional emails AND also drains due DB-backed `communication_recipients` (both bulk Member communications and Consent reminders) over a reused `SMTPKeepAlive` connection, sharing the exact same DB daily quota ledger (`mail_daily_usage`) with live web chunks so `MAIL_DAILY_LIMIT` is never exceeded across both paths. Respect `MAIL_BATCH_SIZE`, `MAIL_MAX_ATTEMPTS`, `MAIL_SEND_PAUSE_MS`, exponential backoff on transient failures, stale-claim recovery, communication status/counter updates, and updating `students.cgu_reminder_sent_at` when a Consent reminder recipient is confirmed delivered. Update the email-queue operations runbook to document the hybrid worker behavior.

**Blocked by:** 01 — Fast bulk communications tracer bullet (hybrid engine & live chunked sending)

**Status:** ready-for-agent

- [x] `ProcessMailQueueCommand` drains both existing JSON file-queue messages and due DB-backed `communication_recipients` within a single locked run while honoring `MAIL_BATCH_SIZE`, `MAIL_DAILY_LIMIT`, `MAIL_MAX_ATTEMPTS`, and `MAIL_SEND_PAUSE_MS`.
- [x] File-queued emails and DB-backed communication sends share the unified daily quota ledger (`mail_daily_usage`) so combined web + CLI deliveries never exceed `MAIL_DAILY_LIMIT`; stopping cleanly and marking remaining unfinished communications `PAUSED_LIMIT` when the cap is reached.
- [x] Failed DB recipients increment `attempts`, persist `last_error`, schedule exponential backoff (`min(60 * 2^attempts, 3600)` seconds) while below `MAIL_MAX_ATTEMPTS`, and transition to `FAILED` once `MAIL_MAX_ATTEMPTS` is reached.
- [x] Confirmed delivery of a `CGU_REMINDER` recipient via the CLI worker updates `students.cgu_reminder_sent_at` and updates the parent communication's counters and aggregate status.
- [x] Existing file-queue behavior (single-instance lock, corrupt entry dead-lettering, retry backoff, dead-lettering after max attempts, graceful idle exit when no DB or queue is present) remains intact and backward-compatible.
- [x] The email-queue operations runbook documents the unified daily quota, hybrid bulk/consent draining, and Stellar/Dokploy environment tunables.
- [x] Automated PHPUnit integration and CLI subprocess tests verify combined file-queue + DB draining, shared daily limit enforcement, exponential backoff, dead-lettering, and status reconciliation.
