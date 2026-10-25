# Integrated release qualification — ticket 09

Local operational rehearsal executed on 2026-10-24; final public browser/PHP regression checks completed on 2026-10-25. Source is the integrated candidate based on `7cf99d8268342ac71b5596739653e61419a0f070`, plus the ticket09 test/operational artifacts in this commit. Final independent code review, exact delivered-head CI, merge and production release are separate milestones.

## Executed evidence

`tests/apps/run.sh` now runs the integrated qualification through the existing dev-targeted CI entry point. The fresh isolated run passed **352 HTTP/schema/operational checks, 22 real HTTPS Chrome checks and one concurrent-worker proof** (three synthetic messages processed exactly once, no SMTP). This combines member registration/correction, staff administration/tools, route/method and legacy-link matrices, schema compatibility and the following release checks:

- Public, staff and jobs images were built separately from current source. Public started and served legal content while staff was stopped; staff started and served login while public was stopped. Jobs ran only the synthetic CLI proof, never a real scheduler/purge loop.
- Actual public, staff and MySQL containers were replaced independently, retaining their volumes. Member records, authorized private PDF hashes, public carousel image hashes and a private storage marker remained identical afterward.
- A real retained pre-08 public image (`amea-ui-prior-public:ff3a8ab`, SHA256 `44ed8abf671895f3599e1b550cba669cbcdd2a58ed4d8a39d73dfe6e13c6ff11`) restored its observed old HTTP404 staff-link behavior. Forward deployment restored HTTP302. Shared member/private/public file observations remained identical across both image transitions.
- SQL was exported with a transactional dump; uploads/storage were archived. Both were restored into a **different fresh project/database/volumes**, then member content and authorized private/public image bytes were verified over HTTPS after a fresh staff login. The private storage marker matched; direct student file access remained denied. Session volumes were deliberately not restored.
- Legacy schema00–05 rejects French member confirmation before migration06; after its additive expansion, confirmation passes. Legacy values/records, defaults, collation and comments survive; repeating06 is harmless.
- TRACE, PATCH and PUT are rejected on both applications. An actual pre-test TRACE request returned405; no additional Apache policy change was necessary.

`tests/apps/release.py` can reproduce the prior image from the exact trusted commit if its local tag is absent. A shallow-checkout smoke test verified fetching and archiving that full revision. CI can therefore run without a previously retained local image; the fallback fetch is read-only against the repository origin.

Final focused regressions passed:

| Boundary | Result |
|---|---|
| PHPUnit | 141 tests, 241 assertions; 10 existing deprecations |
| Standalone PHP | 47 permission +186 OOP unit +78 OOP integration +24 password checks |
| Legacy public HTTP/browser | 35 HTTP +44 Chrome assertions |
| Legacy correction/consent HTTP/browser | 28 HTTP +61 Chrome assertions |
| New Dokploy compose proposal | Resolved syntax valid with dummy variables only; no resources started |

The public browser runs regenerated/verified the committed desktop/mobile light/dark screenshots under `docs/testing/screenshots/`. They cover keyboard focus, reduced motion, carousel empty/single/multiple states, registration/review and token/consent/legal layouts, including320px. Two-host browser checks cover secure isolated sessions and staff/public workflows. Exact logs were captured locally as `/private/tmp/amea09-release-first.log`, `/private/tmp/amea09-php.log`, `/private/tmp/amea09-public-browser.log` and `/private/tmp/amea09-token-browser.log`.

## Reproduction

Run `bash tests/apps/run.sh` from a normal checkout with Docker, Python3, Node24 and Chrome; set `CHROME_BIN` when needed. `AMEA_SKIP_BROWSER=1` is the CI mode and preserves all HTTP/schema, jobs and persistence/restore/rollback checks. A fresh `amea-ui-test-*` project is required. Ports are loopback-only; the restore target uses18448/19448. The script refuses existing containers or volumes with its project labels and cleans only owned temporary projects on success or failure.

The runner uses synthetic data/credentials and `APP_ENV=test`, with certificate bypass confined to its local test client/profile. No user DNS/trust settings are changed. All source and restore containers/volumes from the successful run were removed. Existing unrelated Docker projects were not touched.

## Deployment boundaries

The user supplied the official logo and approved reuse of existing public copy and published photos. Production assets/data still require the actual operator's final release check. [Rollout/rollback procedure](../operations/public-staff-rollout.md) and `compose.apps.dokploy.yml` are reviewable proposals only. The new compose deliberately reuses an existing database/network and external data volumes; it does not provision a replacement production database.

Production currently uses MariaDB10.11; this rehearsal used MySQL8. Actual production database compatibility, volume ownership/storage, verified live backups, real restoration, SMTP delivery and DNS/cutover are **not qualified**. They remain explicit prerequisites for an approved production transition. No production change, real email, production data deletion or DNS action occurred. The retained-image test proves only the named local version transition, not arbitrary historical rollback or production recovery.
