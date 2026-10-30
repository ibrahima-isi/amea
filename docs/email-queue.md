# Email Queue — Operations Runbook

How outbound email works and how to plan a worker on Docker/Dokploy. Production changes require explicit approval; no worker or SMTP delivery is enabled by this document.

## How it works

There are two delivery paths, chosen per message type:

| Path | Methods | Used by | Behaviour |
|------|---------|---------|-----------|
| **Synchronous** (always) | `EmailService::send()` | Password reset, "forgot password", CGU deletion request, bulk communications | Sent immediately, in-request. The user is waiting, so we never defer these. |
| **Queue-able** | `sendAsync()`, `sendFromTemplate()` | Registration received, KYC approved / clarification / rejected, admin update notification | Either sent in-request (default) **or** enqueued for the cron worker, depending on `MAIL_ASYNC`. |

The queue-able path is governed by one flag:

- **`MAIL_ASYNC` unset / `0`** → queue-able emails are sent **synchronously**, exactly
  as they are today. This is the safe default. **Production behaviour is unchanged
  until you opt in.**
- **`MAIL_ASYNC=1`** → queue-able emails are written to `storage/mail-queue/*.json`
  and delivered later by `scripts/process-mail-queue.php` (run from cron).

If the queue directory cannot be written, `EmailService` automatically falls back
to a synchronous send — a broken or unwritable queue never silently drops mail.

> The old fire-and-forget `exec()` worker (`scripts/send-email.php`) was removed.
> On shared hosting `PHP_BINARY` is often a non-CLI binary (e.g. `lsphp`), so the
> backgrounded process died before sending while the shell still returned exit 0 —
> the failure was invisible. The cron worker is verifiable and durable instead.

## The worker: `scripts/process-mail-queue.php`

Run from cron. Properties:

- **Single-instance** — holds an exclusive `flock`; overlapping cron runs no-op.
- **Rate-limited** — per-run batch cap and per-day cap, so the **Brevo free tier
  (300 emails/day)** is never exceeded.
- **Durable** — a message is deleted only after a confirmed send. Failures retry
  with exponential backoff (2 → 4 → 8 → 16 min, capped at 1 h); after
  `MAIL_MAX_ATTEMPTS` the message moves to `storage/mail-queue/failed/`
  (dead-letter) for manual inspection.

### Tunables (`.env`)

| Variable | Default | Meaning |
|----------|---------|---------|
| `MAIL_ASYNC` | _(unset)_ | `1` enables the durable queue for queue-able emails |
| `MAIL_BATCH_SIZE` | `50` | Max messages delivered per cron run |
| `MAIL_DAILY_LIMIT` | `300` | Max successful sends per calendar day (= Brevo free tier) |
| `MAIL_MAX_ATTEMPTS` | `5` | Attempts before a message is dead-lettered |
| `MAIL_SEND_PAUSE_MS` | `250` | Pause between sends (politeness throttle), in ms |

## Worker deployment and rollback

The Docker/Dokploy jobs profile and its shared storage are described in the [rollout guide](operations/public-staff-rollout.md). Before enabling a worker, verify the selected environment, writable persistent queue storage and scheduling, and ensure any previous worker is stopped. Test delivery separately only with explicit authorization. `MAIL_ASYNC` is off by default.

To roll back, set `MAIL_ASYNC=0` or unset it. Existing queued messages remain in persistent storage; plan their handling before stopping the worker.

## Monitoring & troubleshooting

- **Backlog**: `ls storage/mail-queue/*.json | wc -l` — should stay near zero. A
  growing backlog means the cron isn't running or you've hit the daily cap.
- **Dead-letters**: inspect `storage/mail-queue/failed/`. Each file keeps the full
  payload plus `attempts` and `failed_at`. To retry one, move it back up:
  `mv storage/mail-queue/failed/NAME.json storage/mail-queue/` (reset
  `next_attempt_at` to `0` if you want it sent on the next run).
- **Daily cap reached**: the log prints `Daily limit reached`. The counter file is
  `storage/mail-queue/.sent-YYYYMMDD`; it resets naturally at midnight (new date).
  If you upgrade the Brevo plan, raise `MAIL_DAILY_LIMIT` accordingly.
- **Nothing sends after `MAIL_ASYNC=1`**: the cron isn't firing. Re-check step 3/4
  (wrong PHP path is the usual cause).
