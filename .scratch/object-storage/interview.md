# Object storage discussion

Discussion opened on 2026-11-12 against local `dev` at `eaca561`.

## Confirmed scope

The user selected these answers on 2026-11-12.

- Cover every existing upload flow, including member documents and photos, journal covers, and slider images.
- Initially prepare storage support and defer migration. The clarification below supersedes that scope split.
- Prepare storage capabilities for future videos and attachments. Design their upload screens separately.

### Production migration clarification

The user subsequently clarified the goal in French.

- Replace the existing `uploads/` storage with Cloudflare R2.
- Keep the ability to move to Hetzner Object Storage, AWS S3 or another S3-compatible provider later.
- Include migration of existing files and any necessary database reference changes in the design.
- Preserve every file and its accessibility. The application has operated in production for over a year.
- Target R2 for provider qualification.
- Perform the main copy while the site operates, then pause registrations, modifications and deletions for final verification and cutover.
- Keep the bucket private and transmit KYC/CV downloads through PHP after the existing authorization checks.
- Use current relative paths as object keys and select the provider through configuration. Inventory determines any necessary database reference corrections.
- Retain the local copy and associated backup until migration validation. Decide their deletion separately.

## Confirmed architectural direction

Use a provider-independent application storage boundary with S3-compatible access to R2. Provider connection settings and object references must support later provider changes. See ADR 0003.

## Observed implementation

- `composer.json` requires PHPMailer, Twig and Doctrine Migrations. It declares no S3 client or storage adapter.
- `src/Core/FileUploader.php` writes uploaded files locally, deletes local paths, and finalizes staged files with a filesystem rename.
- `src/Controller/RegistrationController.php` keeps pending files in the registration session and serves previews from a local staging directory.
- `src/Controller/DownloadController.php` checks administrator access and serves local member documents.
- `src/Service/JournalMediaService.php` manages local JPEG, PNG and WebP covers with a 5 MiB limit.
- `compose.dokploy.yml` shares an uploads volume across public, staff and jobs services.
- ADR 0001 retains shared member files and distinct public/staff access boundaries.
- Persistent database references appear in `students.identity_document`, `students.cv_path`, `slider_images.image_path` and `journal_posts.cover_path`. Pending registration references also live in sessions.
- Slider templates use database paths directly as image URLs. Journal and private document controllers use access-controlled endpoints.
- `DocumentReconcileService` uses local filesystem checks and compares orphan candidates by basename. Migration reconciliation needs complete relative keys to distinguish identically named files in separate directories.
- Journal replacement/removal retains earlier cover files. A database-only inventory would omit these existing files.
- Pending-upload and trashed-member purge jobs delete files. Migration coordination must include these writers as well as HTTP requests.

## Proposed verification and recovery approach

These are engineering proposals to refine in the design.

- Inventory the complete uploads tree together with database references, including unreferenced files, draft covers, trashed-member documents and staged uploads.
- Record each file's full relative key, size and SHA-256 digest in a migration manifest. Preserve relative directory distinctions.
- Copy files while retaining the source. Resume interrupted copies using the manifest.
- Read back destination object contents and compare their digest and size with the source manifest. Object counts or ETags alone do not establish byte equality.
- Compare the final frozen source inventory and database references with the verified destination before changing active references.
- Rehearse database updates and restoration with the repository's Doctrine migration and recovery conventions.
- Exercise registration staging/confirmation, member correction, staff downloads, slider images, journal publication/privacy and file cleanup through the application.
- Keep source files and a coordinated database backup for recovery. Cover files written after cutover in the rollback design.
- A live inventory and rehearsal must establish existing missing files, data volume, transfer duration and the measured write-pause window.

## Design review

The [design specification](spec.md) records the selected approach and engineering proposals for sessions, media delivery, failures and recovery. An independent builder review found no important unanswered planning questions. The user approved the complete design on 2026-11-12. The [implementation plan](plan.md) converts it into separately bounded work items.

## Reference documentation

- [R2 S3 API compatibility](https://developers.cloudflare.com/r2/api/s3/api/) documents supported operations and differences from AWS S3.
- [R2 presigned URLs](https://developers.cloudflare.com/r2/api/s3/presigned-urls/) documents temporary bearer links and their S3 endpoint restriction.
- [Rclone with R2](https://developers.cloudflare.com/r2/examples/rclone/) documents copying local files through the S3-compatible endpoint.
