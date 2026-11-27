# ADR 0004: Cloudflare Edge Caching, Origin Cache, and Database Indexes

- **Status:** Accepted
- **Date:** 2026-11-27

## Context

AMEA runs split public (`aeesgs.org`) and staff (`admin.aeesgs.org`) PHP 8.5 applications on Namecheap Stellar shared hosting (or Docker Apache) backed by MariaDB and Cloudflare R2 private object storage, proxied behind Cloudflare.

Profiling the codebase revealed four compounding performance bottlenecks:

1. **Zero Cloudflare CDN caching on dynamic public pages and R2-backed media:**
   - Homepage (`/`) and legal notice (`/legal-notice.php`) unconditionally started PHP sessions on anonymous visits, sending `Set-Cookie: AMEA_PUBLIC=...` and `Cache-Control: no-store`, preventing Cloudflare edge caching.
   - Public carousel photos (`/uploads/slider/*`) and published journal covers (`/journal-cover.php?slug=...`) emitted `Cache-Control: no-store` on every `200` response, forcing every visitor to invoke PHP, query MariaDB, download full bytes from Cloudflare R2 over S3 API, and re-verify MIME/image headers.
   - Static assets did not distinguish `?v=<mtime>` versioned assets from unversioned assets, missing 1-year `immutable` caching and origin `mod_deflate` compression.
2. **Repeated per-request overhead in PHP:**
   - `BaseController` ran three separate `SELECT * FROM settings` queries plus `getimagesize()` on `assets/img/logo.png` on every request—including media endpoints (`SliderMediaController`, `JournalPublicController::cover`) that never rendered Twig templates.
   - `View` left Twig `auto_reload => true` enabled even in production.
3. **Missing secondary and composite indexes in MariaDB:**
   - `students` lacked composite indexes on `(deleted_at, kyc_status, registration_date, id)`, `(deleted_at, registration_date, kyc_status, status, gender, consent_privacy)`, `(deleted_at, institution, status, kyc_status)`, `(deleted_at, kyc_status, status, study_level)`, `(identity_document)`, and `(cv_path)`.
   - `slider_images` lacked indexes on `(image_path)` and `(display_order, id)`; `journal_posts` lacked `(status, type, published_at, id)`, `(updated_at, id)`, and `(cover_path)`; `countries`, `locations`, and `users` lacked lookup/sort indexes.
4. **Redundant queries and over-fetching:**
   - `DashboardController` executed 7 separate scans of `students` per page load; `CommunicationController` hydrated every `Student` object in PHP memory just to count filtered recipients and consent totals; `JournalRepository::findRecentPublished()` executed an unused `SELECT COUNT(*)`; `ExportService` omitted `deleted_at IS NULL` and missed active-member indexes; `FileOperationRepository::referenced()` used an `OR` predicate across `identity_document` and `cv_path`.

## Decision

1. **Cloudflare-native HTTP caching (`Amea\Cache\HttpCache` + `Amea\Cache\CachePolicy`):**
   - Emit RFC 9213 `Cloudflare-CDN-Cache-Control` and `CDN-Cache-Control` headers with `stale-while-revalidate` and `stale-if-error`, plus strong `ETag` and `304 Not Modified` short-circuiting. For R2-backed media, metadata-only `304` short-circuiting is enabled only when object metadata already carries a write-verified SHA-256 digest and allowlisted MIME type; legacy/unverified objects verify via `open()` first.
   - Make `/legal-notice.php` session-free and `/` conditional-session (`Router::SESSION_IF_COOKIE`): anonymous requests stay session-free and Cloudflare-cacheable for 1 hour at the edge while sending browser `Cache-Control: public, max-age=0, must-revalidate` so post-registration redirects never hide flash banners; browsers carrying `AMEA_PUBLIC` / `PHPSESSID` open the session and receive `Cache-Control: private, no-store` + `CDN-Cache-Control: no-store`.
   - Keep strict fail-closed `no-store` headers on every staff route (emitted before session startup so expiry `302`s and `/uploads/slider/*` on `staff` never expose `public` headers), stateful public forms, private downloads/previews, draft/withdrawn journal covers, `404`/`405`, and write-gate `503`.
2. **Active Cloudflare Cache Purge API v4 (`Amea\Cache\CloudflareCachePurger` + `Amea\Cache\CacheInvalidator`):**
   - Automatically purge affected public URLs from service/repository boundaries on carousel, journal (including listing pages 1–3 across all 5 views), and settings writes using a least-privilege `Zone.Cache Purge` token, failing open on transient API errors with a short timeout and never logging credentials. Serve dynamic URL variants outside the purge set with `no-store`, including journal listing pages after page 3.
   - Ship three declarative Cloudflare Cache Rules in `config/cloudflare/cache-rules.json` (`amea_cache_static_assets`, `amea_cache_public_pages_and_media`, and final fail-closed `amea_bypass_staff_and_stateful` with `edge_ttl.mode = "bypass_by_default"`) and a CLI helper (`scripts/cloudflare-cache.php`).
3. **Zero-daemon shared origin cache (`Amea\Cache\AppCache`):**
   - Store short-TTL application cache entries (`settings`, active slider metadata, recent journal items, lookup tables) atomically in `AMEA_STATE_DIR/cache/app` (Stellar) or `storage/app-cache` (Docker shared volume, excluded from `RecoverySnapshot`), avoiding all disk mutations (writes, invalidations, corrupt-entry cleanup) during `FileWriteGate` pauses and isolating `:memory:` SQLite tests in RAM.
4. **Database indexes & consolidated queries (`Version20261127000001`):**
   - Add a nontransactional Doctrine migration adding all missing composite/covering indexes, keep `schema.sql` and `BaselineEnrollment` aligned, consolidate dashboard and communication aggregations into covering-index queries using the database engine's own clock, add `deleted_at IS NULL` to `ExportService`, and split `FileOperationRepository::referenced()` into indexed point lookups.

## Consequences

- **Positive:** Anonymous public pages and media offload from Stellar PHP/MariaDB/R2 to Cloudflare Edge POPs; conditional revalidations return `304` without R2 GETs for verified objects; staff lists, KYC queues, dashboards, exports, and file reference checks execute indexed/covering scans; invalidation on editorial updates is immediate at both origin and Cloudflare Edge.
- **Trade-offs:** Operators must apply the three declarative Cloudflare Cache Rules and provision a scoped `CLOUDFLARE_API_TOKEN` + `CLOUDFLARE_ZONE_ID` to activate full HTML edge caching and automated edge purges; without those credentials, the system still benefits immediately from DB indexes, query consolidation, `AppCache`, static asset caching, and `ETag`/`304` revalidation.
- **Database rollout:** Existing databases require the standard backup-confirmed `php scripts/migrate.php migrate` step (`AMEA_SCHEMA_BACKUP_CONFIRMED=1`) for `Version20261127000001` (online InnoDB `ADD KEY`).
