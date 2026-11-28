# Hybrid delivery for member communications

Status: accepted

Member communications are persisted in MariaDB (one communication record plus per-recipient delivery rows) and dispatched in small browser-driven batches over a reused Brevo SMTP connection (`SMTPKeepAlive = true`), while the existing `mail` CLI job drains and retries any remaining due recipients using the same shared daily quota counter. On Namecheap Stellar, synchronous in-request sending times out under LiteSpeed and Cloudflare limits, whereas pure cPanel cron delivery introduces a 5-minute minimum delay and fails silently whenever the cron worker is not yet armed.

## Considered Options

- **Pure background cron queue**: Simple POST handling, but forces staff to wait at least 5 minutes for cPanel cron to begin delivery and offers no immediate progress feedback.
- **Brevo HTTP Transactional API batching**: Very fast single-request batching, but introduces a second Brevo credential type (`BREVO_API_KEY`) and HTTP client dependency across Stellar, Dokploy, and CI secrets.
- **Hybrid DB-backed chunked SMTP + CLI fallback (chosen)**: Reuses existing Brevo SMTP credentials, finishes typical sends in seconds with live progress while the staff tab is open, and remains durable, deduplicated, and resumable across tab closure, transient SMTP errors, and daily quota caps.

## Consequences

- Both live web chunks and `ProcessMailQueueCommand` must claim recipients atomically and share one daily delivery counter so `MAIL_DAILY_LIMIT` (default `300`/day) is never exceeded. A crash between SMTP acceptance and database acknowledgement can cause a duplicate during recovery.
- Individual transactional emails (such as 5-minute password resets) remain separate from this bulk pipeline.
