# Database Scalability Plan — 1 Primary + 2 Replicas

Plan to move from a single MySQL instance to a **3-node topology**: one
read/write **primary** and two **replicas** (one serving reads, one a hot
standby / backup). Incremental — each phase is shippable on its own and the app
keeps working throughout.

## ⚠️ Hosting prerequisite (read first)

The app currently runs on **Namecheap cPanel shared hosting**. Shared hosting
**does not allow MySQL replication** — you don't get `SUPER`/`REPLICATION` privileges,
binlog access, or a second DB host. This topology requires one of:

- **A VPS / dedicated server** (self-managed MySQL on 3 nodes), or
- **A managed MySQL service** with read replicas (AWS RDS / Aurora, DigitalOcean
  Managed MySQL, GCP Cloud SQL, PlanetScale). Managed replicas are the lowest-ops
  path and give automated backups + failover out of the box.

Everything below assumes you've moved the database tier to one of those. The
**application changes** (read/write splitting) are host-agnostic and can be done
first — they're a no-op until real replicas exist.

## Current state

```
[All controllers]
      ↓
Container::db()  ──►  Database::fromEnv()  ──►  single PDO  ──►  [MySQL]
```

- One PDO connection, one host (`DB_HOST`), reused per request (singleton).
- Every read and write goes to the same server.
- `config/database.php` exposes the same connection as `$conn` for legacy code.

## Target topology

```
                         writes
   [App]  ───────────────────────────────►  ┌──────────────┐
     │                                       │   PRIMARY    │  (read/write)
     │  reads (SELECT)                       │  db-primary  │
     ├──────────────────────────────────►   └──────┬───────┘
     │                                              │ async/semi-sync replication
     │                                       ┌──────▼───────┐
     └──────────────────────────────────►   │  REPLICA 1   │  (read-only, serves SELECTs)
                                             │  db-read-1   │
                                             └──────┬───────┘
                                                    │
                                             ┌──────▼───────┐
                                             │  REPLICA 2   │  (hot standby + backups,
                                             │  db-standby  │   promotable on failover)
                                             └──────────────┘
```

- **Primary** — all writes (`INSERT`/`UPDATE`/`DELETE`/DDL) and any
  read-after-write that must be strongly consistent.
- **Replica 1** — serves read-only `SELECT`s (list pages, dashboards, exports),
  offloading the primary.
- **Replica 2** — not in the read path; it's the **standby/backup**. Take
  `mysqldump`/snapshots here so backups never load the primary, and promote it to
  primary if the primary fails.

## Application changes (host-agnostic, do these first)

### 1. Connection registry with reader/writer split

Extend `Container` to hand out a **writer** and a **reader**:

```php
// src/Core/Container.php  (sketch)
private static ?\PDO $writer = null;
private static ?\PDO $reader = null;

public static function writer(): \PDO
{
    return self::$writer ??= Database::writer()->getConnection();
}

public static function reader(): \PDO
{
    // Falls back to the writer when no replica is configured, so a single-host
    // deployment (and the test suite) keeps working unchanged.
    return self::$reader ??= Database::reader()->getConnection();
}

// Keep db() as an alias of writer() for backward compatibility.
public static function db(): \PDO { return self::writer(); }
```

`Database` gains `writer()` (from `DB_HOST`) and `reader()` (from
`DB_READ_HOST`, falling back to `DB_HOST` when unset). New `.env` keys:

```dotenv
DB_HOST=db-primary           # writer
DB_READ_HOST=db-read-1       # reader replica (omit to use the primary)
DB_READ_USER=...             # optional read-only credentials
DB_READ_PASS=...
```

Because `reader()` defaults to the writer, **shipping this code changes nothing**
until `DB_READ_HOST` is set — safe to deploy on the current single host.

### 2. Route reads in repositories

Repositories take both connections (or fetch from `Container`). Read methods use
the reader; write methods use the writer:

```php
// e.g. StudentRepository
public function findPaginated(...) { /* use $this->reader  */ }
public function save(...)          { /* use $this->writer  */ }
public function update(...)        { /* use $this->writer  */ }
```

### 3. Handle replication lag (read-after-write)

Replicas are eventually consistent (typically < 1 s, but not zero). For flows
that **write then immediately read the same row**, read from the **writer** to
avoid showing stale data. Examples in this codebase:

- After `RegistrationController::register()` → `registration-details.php` re-reads
  the just-saved student → **read from writer**.
- After `StudentController::update()` → redirect to `student-details.php` → the
  redirect + page load is usually slower than lag, but if you see stale reads,
  pin this read to the writer.

Rule of thumb: **list/report/dashboard reads → replica; read-immediately-after-write
→ primary.**

## Replication & infrastructure (DBA / managed service)

1. **Enable binary logging** on the primary (`log_bin`, unique `server_id`,
   `binlog_format=ROW`).
2. **Provision two replicas** from a primary snapshot, each with a unique
   `server_id`, started with `CHANGE REPLICATION SOURCE TO ...` (or the managed
   provider's "add read replica" button).
3. **Use GTID-based replication** — makes failover and replica re-pointing far
   simpler than file/position.
4. **Consider semi-synchronous replication** to Replica 2 (the standby) so a
   committed write is guaranteed on at least one replica before acknowledgement —
   minimises data loss on primary failure. Keep Replica 1 async for low read latency.
5. **Monitor `Seconds_Behind_Source`** on both replicas; alert if lag exceeds a
   threshold (e.g. 5 s) so you can pull a lagging replica out of the read path.

## Backups & failover

- **Backups** run against **Replica 2** (the standby), never the primary:
  nightly `mysqldump --single-transaction` or filesystem/volume snapshots, shipped
  off-host (S3 / object storage), with periodic **restore drills** (an untested
  backup is not a backup).
- **Failover**: if the primary dies, promote Replica 2 (standby) to primary,
  re-point Replica 1 at the new primary, update `DB_HOST` (and rotate
  `DB_READ_HOST` if needed). Managed services automate most of this; self-managed
  setups can use **Orchestrator** or **MySQL Group Replication / InnoDB Cluster**
  for automatic failover.
- **Connection resilience**: add a short PDO connect timeout and a single retry in
  `Database`, so a brief failover blip doesn't surface as a hard 500.

## Incremental migration phases

| Phase | Work | Risk | Outcome |
|-------|------|------|---------|
| **0** | Ship reader/writer split in `Container`/`Database`; `reader()` falls back to writer | None (no-op on single host) | Code ready; behaviour identical |
| **1** | Move DB tier to managed MySQL (or VPS), single node | Medium (data migration + cutover) | Off shared hosting; backups automated |
| **2** | Add Replica 1; set `DB_READ_HOST`; route list/report reads to it | Low | Read load offloaded from primary |
| **3** | Add Replica 2 as semi-sync standby; run backups there | Low | DR/backup isolation |
| **4** | Add lag monitoring + automated failover (Orchestrator / InnoDB Cluster / managed) | Medium | Self-healing HA |

No big-bang rewrite: the app runs on one node through Phase 1, and each replica is
added behind an env flag.

## Quick wins (do now, before any of the above)

1. **Automated off-host backups** of the current single DB (nightly
   `mysqldump --single-transaction`, copied off the server). Highest value,
   lowest effort — protects against data loss today.
2. **Add indexes** for the hot query paths (`students.kyc_status`,
   `students.email`, `students.phone`, `student_country.student_id`) so a single
   node scales further before you need replicas.
3. **Ship the reader/writer split (Phase 0)** now — it's inert on one host but
   means the day you add a replica it's a config change, not a code change.
4. **Add a PDO connect timeout + one retry** in `Database` for resilience.
5. **Enable the slow query log** (or use the managed provider's insights) to find
   what actually needs offloading before investing in replicas.
