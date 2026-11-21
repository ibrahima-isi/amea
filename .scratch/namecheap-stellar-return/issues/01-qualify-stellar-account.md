# 01: Qualify the assigned Stellar account

Status: ready-for-agent

Blocked by: None.

## What to build

Give the operator a capability report from the actual Stellar account and identify any gaps that prevent an isolated two-site installation.

## Acceptance criteria

- [ ] Record SSH access, the intended cron PHP binary, PHP versions and required extensions in CLI and the two actual test web runtimes.
- [ ] Run the existing preflight from a private location and record sanitized R2 HTTPS and Brevo TCP results. Treat authenticated R2 and mail delivery as staging checks.
- [ ] Confirm the permitted document roots, TLS setup, private writable paths, database compatibility, limits for uploads and temporary disk, and scheduled execution and filesystem primitives used by AMEA.
- [ ] Record test domains and account constraints, with any unverified or failing capability identified before staging.

## Comments
