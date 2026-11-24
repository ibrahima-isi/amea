# Adopt Doctrine Migrations for the shared database

Accepted on 2026-11-06; delivered in PR #38.

The local and Dokploy Compose stacks will use Doctrine Migrations in their existing one-shot migration service. A Composer alias would simplify invocation but leave the existing replay behavior. Doctrine records applied versions, so an ordinary startup only runs pending migrations.

Existing databases will be enrolled explicitly after verification. This avoids replaying historical data changes such as the KYC backfill, whose execution cannot be inferred from the presence of its columns. An ambiguous database will block startup pending review.

See the [adoption specification](https://github.com/ibrahima-isi/amea/blob/6861587753a4fc6fd2551d7f8b9c766ad3135ee6/.scratch/doctrine-migrations/spec.md) for the transition, transaction boundaries, and acceptance criteria.
