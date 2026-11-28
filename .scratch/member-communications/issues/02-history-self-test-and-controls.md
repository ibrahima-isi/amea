# 02: Communication history, self-test preview & Resume / Retry / Cancel controls

**What to build:** Give Staff users pre-send confidence and full post-send control over Member communications on `/communications.php`. Add a one-click **"Envoyer un test à moi-même"** action that sends a single personalized preview email to the logged-in Staff user's email address without creating a bulk communication record, display a paginated/recent history table of past Member communications with live status badges (`En attente`, `En cours`, `En pause (limite quotidienne)`, `Terminé`, `Terminé avec erreurs`, `Annulé`), delivered/failed/total counts, timestamp, author, and expandable failure diagnostics, and provide CSRF-protected actions to **Resume** unfinished/paused communications, **Retry failed** recipients (re-queueing only `FAILED` rows), and **Cancel remaining** unsent recipients.

**Blocked by:** 01 — Fast bulk communications tracer bullet (hybrid engine & live chunked sending)

**Status:** ready-for-agent

- [x] **"Envoyer un test à moi-même"** validates CSRF, subject, and body, sends one rendered branded email personalized with the logged-in Staff user's name to their account email address (failing cleanly with a clear French message if the Staff user has no valid email), and creates zero rows in `communications` or `communication_recipients`.
- [x] Recent Member communications history renders on `/communications.php` showing subject, filter summary, status badge, delivered/failed/total counts, author name, and creation/completion timestamp, loaded via efficient SQL queries.
- [x] Staff users can inspect recorded `last_error` messages for failed Communication recipients of a communication without exposing raw stack traces.
- [x] **Resume** restarts live chunked dispatch for an unfinished `QUEUED`, `SENDING`, or `PAUSED_LIMIT` communication (respecting the current daily quota) without ever duplicating delivery to already-`SENT` recipients.
- [x] **Retry failed** resets only `FAILED` recipients of a communication back to `PENDING` (with reset attempt/schedule state) and resumes chunked dispatch while leaving `SENT` and `CANCELLED` recipients untouched.
- [x] **Cancel remaining** transitions remaining `PENDING` recipients to `CANCELLED`, updates communication status and `cancelled_count`, and stops any active live progress loop cleanly.
- [x] Automated integration tests cover self-test preview, history retrieval, failure inspection, resume without duplicates, retry-failed isolation, and cancel-remaining transitions.
