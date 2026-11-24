# Qualify and rehearse the Stellar return

Status: ready-for-agent

## Problem Statement

AMEA plans to move from its combined VPS deployment to two web applications on Namecheap Stellar. The OOP migration delivered the two entry points, and the storage tooling has been tested with synthetic files on Cloudflare R2. Before a production move, the operator needs evidence from the assigned Stellar account, a rehearsal that preserves an unfinished registration, and a reviewed procedure for copying existing files to the private production bucket.

## Solution

Qualify the assigned account, then run the public website and Administration on isolated Stellar test domains. Rehearse the move using a disposable database, synthetic files and the test bucket. Verify that a member can resume and confirm an unfinished registration in the same browser after the simulated switch. Use those results to prepare the production transition already tracked in the object-storage backlog.

## User Stories

1. As an operator, I want to verify SSH, PHP and its extensions on the assigned account so that I can select the actual web and scheduled runtimes.
2. As an operator, I want to verify connectivity to R2 and Brevo so that I can catch hosting restrictions before deploying.
3. As a maintainer, I want to verify the two document roots and private paths on LiteSpeed so that only the intended entries and static assets are served.
4. As a maintainer, I want a release to serve both sites from the same revision so that they use compatible database and storage contracts.
5. As a visitor, I want the public site to reject Administration URLs locally so that staff functionality is available only on its own host.
6. As a staff user, I want to sign into Administration and open private documents through authorized routes so that member data stays protected.
7. As a member, I want to return to my unfinished registration in the same browser, see my staged document and confirm it without uploading it again.
8. As a member, I want a different browser to be denied access to my staged document so that transferring sessions preserves ownership.
9. As an operator, I want copied R2 objects to retain their keys, contents, MIME and original staging age so that existing references and cleanup continue to work.
10. As an operator, I want to restore the database, files and relevant session state in isolation so that I can verify recovery before approving a live move.
11. As an operator, I want to measure the write pause and exercise an interrupted move so that I can define concrete recovery and reopening conditions.
12. As an operator, I want the live source, destination and backup selections recorded for approval so that the production copy targets the intended resources.

## Implementation Decisions

- Preserve the two OOP entry points, the shared private Composer release, one MariaDB database, existing routes and isolated host-scoped sessions. Keep the supported Compose files. Adapt native LiteSpeed routing and the private runtime configuration to Stellar.
- Keep configuration outside both document roots. The approved release design publishes tested revisions from main through GitHub Actions and uses Secrets for private configuration. Qualify the release path on staging before using production resources.
- Run the existing account preflight with the intended cron PHP binary. Its anonymous R2 probe verifies HTTPS reachability. Its Brevo probe verifies TCP connectivity. Check both web runtimes separately, and verify authenticated R2 access and test mail during staging.
- Use a private synthetic bucket and disposable database for staging. Select a separate private production bucket for the live file copy. MariaDB is transferred and restored separately from R2.
- Reuse the guarded storage migration and recovery commands. Bind evidence to the actual release, database, backend, write gate and session stores. Account for the transition from the old single session store to split stores. Keep legacy staff authentication out of the public store.
- Preserve complete relative object keys, MIME, original age and database references. Compare source and destination SHA-256 using independent destination read-back. Keep the source and the verified backup through acceptance.
- Pause and drain source writers for final reconciliation. Start writes on the target only after the applicable verification succeeds. Define rollback separately for before and after the target accepts writes.
- Prepare the production approval package without changing production. [Object-storage ticket 06](../object-storage/issues/06-production-transition.md) owns execution and requires explicit operator approval of the concrete transition.

## Testing Decisions

- Exercise the account through SSH, actual web PHP on both test hosts, and the scheduled CLI. Record sanitized results, capabilities and blocking limits.
- Verify staging through the public website, Administration, authorized downloads, the existing Doctrine and job commands, and a synthetic R2 round-trip. Reuse the split HTTP and storage recovery rehearsals as the baseline.
- Start a registration on the source, stop after a document upload, then perform the simulated transfer and isolated restore. Preserve the public hostname and cookie context in the isolated browser test. Verify preview and successful confirmation in the original browser, access denial in another browser, and a single persisted member record after confirmation.
- Verify independent destination SHA-256, database-to-object references, source/target writer coordination, and recovery after an interruption. Record the exact tested artifact, environment, elapsed time and results in the feature completion record.

## Out of Scope

Production DNS changes, live database writes, production file copy, production bucket activation, and retirement of the VPS or source copies are outside these preparation tickets. The live transition and deletion decisions have separate approval requirements.

## Further Notes

The [merged OOP reconciliation plan](https://github.com/ibrahima-isi/amea/blob/6861587753a4fc6fd2551d7f8b9c766ad3135ee6/.scratch/composer-oop/plan.md) records the preflight's origin and the native hosting adaptations. The [object-storage specification](../object-storage/spec.md) defines the production migration safeguards. The previously tested R2 sandbox provides synthetic provider evidence. Stellar account qualification and the complete transfer with registration continuity need their own results.
