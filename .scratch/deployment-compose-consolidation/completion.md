# Compose consolidation — tracking

## Authorization

On 31 October 2026, the four choices and [overall scope](design.md) were approved; the user authorized applying them to the repository and opening a PR into `dev`. No actual deployment was requested.

Base: `origin/dev` at `34d8ea3`. Isolated branch: `infra/consolidate-dokploy-compose`, worktree `/private/tmp/amea-compose-consolidation-20261031`. The primary checkout and other worktrees remain preserved.

## Execution

- [x] Replace the canonical file's contents, remove the duplicate, preserve MariaDB/volumes, sessions/cache and mail/build defaults.
- [x] Update the maintained guide and check references: only the historical design still names the removed file.
- [x] Validate Compose resolution and relevant checks with synthetic data.
- [x] Perform independent reviews: Standards 0 / Spec 0 findings, no correction requested.
- [ ] Create a signed commit, open the PR into `dev` and verify final-head CI.

Signed source commit: `26f0cf99777a3e0dd0f196fb0617cbd7f262d420`. [PR #22](https://github.com/ibrahima-isi/amea/pull/22) targets `dev`; its final-head CI still needs verification. Later evidence commits do not change the validated configuration. Local integration and branch cleanup remain conditional on an actual merge into `dev`.

No production migration/reconfiguration, DNS change, production scheduler activation or real SMTP delivery is performed. The migrations mentioned below apply only to the disposable synthetic database.

## Local checks

Actual Docker Compose resolution: 29 PASS assertions with a synthetic environment, without loading `.env`. The resolved `db` service is identical to the previous one; the four volume/network names are retained for the same project; `public`/`staff` active and `jobs` optional; separate sessions/cache; synchronous mail without a mandatory image variable.

Existing suites PASS: PHPUnit 159 tests / 310 assertions with the 10 pre-existing metadata deprecations; PHP scripts 48/186/78/24; HTTP journeys 35 and 28. Test files and CI configuration are unchanged. Changed Markdown links and `git diff --check` are verified before publication.

## Running canonical manifest validation

Docker Compose 5.3.0 and Docker Engine 29.6.1: build repository targets and start MariaDB `10.11.19-MariaDB-ubu2204` in a fresh project checked by exact labels. A temporary override creates only test-owned internal networks, with no published port; `APP_ENV=test`, synthetic credentials, empty SMTP, synchronous mail and no job started.

All eight migrations 00 through 07 are applied manually to `amea_rehearsal`. PASS: public starts without staff; public homepage/journal; staff login, synthetic authentication, CSRF and publication visible publicly; four wrong-application route denials; visibility of three shared file volumes; distinct session volumes/cookies; isolated caches. After public recreation, the database publication, shared files and public session are retained. All containers, volumes and networks from both disposable attempts are removed.

The first attempt encountered quoting and table-name errors solely in the temporary persistence helper. That helper was corrected outside the repository, then the complete rehearsal passed on a fresh project; no product code changed. The manifest resolved again after commit is identical to the validated snapshot. SHA-256 of `compose.dokploy.yml`: `240e5d99a80fd7082876caac9fd3646070fe40c46feb0388fb0c0497c4ca9c00`.

Local artifacts: `/private/tmp/amea-compose-validation-20261031.md`, `/private/tmp/amea-compose-static-validation-20261031.log` and `/private/tmp/amea-compose-runtime-validation-20261031.log`. This internal HTTP verification does not qualify a browser, TLS proxy, real data/resource identities or production deployment. Journal browser QA remains deferred to the user.

## Reviews

Two fresh-context reviewers separately inspected `34d8ea3...26f0cf9` read-only: Standards 0 documented violations / 0 material heuristics; Spec 0 findings. Local reports `/private/tmp/amea-compose-standards-review-20261031.md` and `/private/tmp/amea-compose-spec-review-20261031.md`. These static reviews are not presented as runtime tests executed by the reviewers.
