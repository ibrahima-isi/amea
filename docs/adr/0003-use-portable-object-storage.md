# Use portable object storage for uploaded files

The user confirmed Cloudflare R2 as the initial target on 2026-11-12, with the ability to move later to Hetzner Object Storage, AWS S3 or another S3-compatible provider. Application file operations will use a shared storage boundary and the S3-compatible API. Provider connection details belong in configuration so that changing the provider does not require rewriting upload and download business flows.

The migration design must cover the existing production files and database references while preserving file contents and access. File verification, cutover and recovery procedures are being developed in the [storage discussion](../../.scratch/object-storage/interview.md).

The user accepted a preliminary online copy followed by a final write pause for verification and cutover. KYC and CV objects will remain in private storage and be streamed through PHP after authorization, retaining request-time application access control. This choice carries application-server transfer overhead in exchange for keeping private-document access tied to application permissions on each request.

Current relative file paths will become object keys. Provider configuration selects the backing storage, preserving the relationship between existing database values and file contents through the transition. A separate file catalogue was considered. The selected approach limits schema and reference transformations during the production migration. Inventory will identify any reference corrections that require a reviewed database update. The source copy and associated backup remain available until migration validation, with deletion decided separately.
