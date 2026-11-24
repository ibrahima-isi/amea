# 03: Rehearse the transfer and resume an unfinished registration

Status: ready-for-agent

Blocked by: [02](02-validate-stellar-staging.md).

## What to build

Rehearse a move from the legacy VPS topology to the split Stellar installation. Show that a member can finish a staged registration after the move.

## Acceptance criteria

- [x] Use synthetic files and database records. Start an unconfirmed registration on the source and stage a document in the original browser.
- [x] Rehearse pausing source writers, copying to the test bucket, reading back and verifying SHA-256 for every selected object, and restoring the database and relevant session state in isolation. Verify keys, references, MIME and original staging age.
- [x] Verify that the same browser can resume the registration, preview its document and confirm it once with the original public hostname and cookie context. A separate browser cannot preview the document. Staff authentication remains isolated.
- [x] Exercise a failed or interrupted transfer, verify reopening conditions and measure the write pause and restore duration. Record the sequence and results.

## Comments
