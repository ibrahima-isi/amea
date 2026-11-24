# 02: Validate the two-site Stellar installation

Status: ready-for-agent

Blocked by: [01](01-qualify-stellar-account.md).

## What to build

Run the public website and Administration on the qualified account from one private release, using an isolated MariaDB database and the private R2 test bucket.

## Acceptance criteria

- [x] Both test domains serve the same reviewed release through separate document roots, with approved static assets, distinct sessions and private code and configuration.
- [x] Verify registration, staff login, authorized private downloads, published media and a local 404 for staff routes on the public host.
- [x] Verify authenticated write and read-back of a synthetic R2 object, a test email and successful use of the intended CLI configuration for Doctrine and scheduled jobs.
- [x] Verify shared private write-gate and jobs state across release changes. Record actual target evidence for the public, staff and jobs runtimes.
- [ ] Exercise the approved publication path on staging with isolated settings, including refusal to promote after a failed migration and recovery to a compatible release. Use synthetic data and retain Compose support.

## Comments
