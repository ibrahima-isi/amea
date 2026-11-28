# Cloudflare Edge Caching, Origin Cache, and Purge Operations

AMEA uses a layered caching strategy designed for split public/staff hosting (`aeesgs.org` and `admin.aeesgs.org`) on Namecheap Stellar (LiteSpeed/PHP 8.5) or Docker Compose behind Cloudflare:

1. **Cloudflare Edge Cache (CDN):** serves anonymous public HTML, published journal covers, homepage carousel images, and versioned static assets directly from Cloudflare POPs via RFC 9213 `Cloudflare-CDN-Cache-Control` / `CDN-Cache-Control` headers.
2. **Conditional Revalidation (`304 Not Modified`):** strong `ETag` and `Last-Modified` validators short-circuit conditional requests before streaming objects from Cloudflare R2 or sending HTML bodies.
3. **Active Cloudflare Cache Purge API v4:** staff content mutations (carousel uploads/deletions, journal publication/edit/withdrawal, settings updates) and release promotions immediately purge affected Cloudflare edge URLs.
4. **Shared Origin `AppCache`:** zero-daemon atomic JSON cache in `AMEA_STATE_DIR/cache/app` (Stellar) or `storage/app-cache` (Docker shared volume) removes redundant MariaDB reads for settings, active carousel metadata, recent journal items, and shared form lookups, while remaining write-gate safe during cutovers.
5. **Production Twig Compilation:** production (`APP_ENV=production`) disables per-request `auto_reload` template stat checks and isolates compiled templates per release directory fingerprint so rollbacks never serve stale bytecode.

---

## Route Caching & Privacy Matrix

| Host | Route | Session | Browser `Cache-Control` | Cloudflare `CDN-Cache-Control` | Revalidation | Active Purge Trigger |
| --- | --- | --- | --- | --- | --- | --- |
| Public & Staff | `/assets/*?v=<mtime>` | None | `public, max-age=31536000, immutable` | `public, max-age=31536000, immutable` | Origin ETag | Auto-busted by `filemtime` |
| Public & Staff | `/assets/*` (unversioned) | None | `public, max-age=604800, stale-while-revalidate=86400` | `public, max-age=2592000, stale-while-revalidate=86400` | Origin ETag | Deploy purge |
| Public | `/` (anonymous, no `AMEA_PUBLIC`/`PHPSESSID` cookie) | None | `public, max-age=0, must-revalidate` | `public, max-age=3600, stale-while-revalidate=86400, stale-if-error=604800` | Strong `ETag` (`304`) | Slider / Journal / Settings |
| Public | `/` (with `AMEA_PUBLIC` or `PHPSESSID` cookie) | Active | `private, no-store` | `no-store` | None | Bypassed at edge |
| Public | `/legal-notice.php` | None | `public, max-age=3600, stale-while-revalidate=86400` | `public, max-age=86400, stale-while-revalidate=604800, stale-if-error=604800` | Strong `ETag` (`304`) | Settings / Deploy |
| Public | `/journal.php` (`all`, `reports`, `announcements`) | None | `public, max-age=60, stale-while-revalidate=300` | `public, max-age=1800, stale-while-revalidate=86400, stale-if-error=604800` | Strong `ETag` (`304`) | Journal (pages 1–3) / Settings |
| Public | `/journal.php` (`upcoming`, `archives`) | None | `public, max-age=60, stale-while-revalidate=60` | `public, max-age=300, stale-while-revalidate=600, stale-if-error=86400` | Strong `ETag` (`304`) | Journal (pages 1–3) / Settings |
| Public | `/journal-article.php?slug=...` (published `200`) | None | `public, max-age=60, stale-while-revalidate=300` | `public, max-age=3600, stale-while-revalidate=86400, stale-if-error=604800` | Strong `ETag` (`304`) | Journal edit / withdraw / Settings |
| Public | `/journal-cover.php?slug=...` (published `200`) | None | `public, max-age=300, must-revalidate` | `public, max-age=86400, stale-while-revalidate=604800, stale-if-error=604800` | Strong `ETag` + `Last-Modified` (`304`, before R2 stream for SHA-256-verified objects) | Journal edit / withdraw / cover replace |
| Public | `/uploads/slider/*` (active `200`) | None | `public, max-age=86400, stale-while-revalidate=86400` | `public, max-age=604800, stale-while-revalidate=2592000, stale-if-error=2592000` | Strong `ETag` + `Last-Modified` (`304`, before R2 stream for SHA-256-verified objects) | Slider delete |
| Public | Drafts, invalid slugs, `404`, `405`, `503` pause | None | `no-store` | `no-store` | None | Never cached |
| Public | `/register.php`, `/registration-details.php`, `/registration-preview.php`, `/kyc-correction.php`, `/accept-cgu.php` | Active | `private, no-store` | `no-store` | None | Never cached |
| Staff | All routes on `admin.aeesgs.org` (including `/uploads/slider/*`, excluding `/assets/*`) | Active | `private, no-store` | `no-store` | None | Never cached |

> [!NOTE]
> `/` intentionally sends browser `Cache-Control: public, max-age=0, must-revalidate` while keeping a 1-hour Cloudflare Edge TTL (`CDN-Cache-Control`). That prevents a browser redirected to `/` after `/registration-details.php` or `/kyc-correction.php` from displaying a stale local anonymous copy over the session flash banner, while Cloudflare POPs still absorb anonymous traffic globally. Journal publish/withdraw actions immediately purge listing pages 1–3 across all 5 views (fitting within one 30-URL Cloudflare API batch alongside article and cover URLs); pages 4+ remain uncached. Public content URLs with unrecognized query strings or obsolete cover versions still serve the current content with `no-store`. This keeps cached variants within the URL set covered by invalidation.

---

## Enabling Cloudflare Edge Caching

> [!IMPORTANT]
> Applying live Cloudflare dashboard/API changes to `aeesgs.org` is a production change and requires explicit maintainer approval.

### 1. Apply Cloudflare Cache Rules

By default, Cloudflare caches static file extensions but bypasses HTML and `.php` paths. Apply the three rules in [cache-rules.json](../../config/cloudflare/cache-rules.json) (fits within the Cloudflare Free plan's 10-rule quota).

To generate the exact Rulesets API JSON for staging or another host pair:

```sh
php scripts/cloudflare-cache.php export-rules \
  --public-host=aeesgs.org \
  --staff-host=admin.aeesgs.org
```

Cloudflare Cache Rules evaluate top-to-bottom and the **last matching rule wins**. Maintain this exact order in **Cloudflare Dashboard → Caching → Cache Rules**:
1. **`amea_cache_static_assets`** — caches `GET`/`HEAD` static assets under `/assets/` on both `aeesgs.org` and `admin.aeesgs.org` regardless of session cookies, using **Edge TTL = Use cache-control header if present, bypass cache if it is not (`bypass_by_default`)** and **Browser TTL = Respect origin**.
2. **`amea_cache_public_pages_and_media`** — marks anonymous `GET` public pages, published journal covers, and carousel media on `aeesgs.org` as **Eligible for cache**, using **Edge TTL = `bypass_by_default`** (fails closed to bypass whenever origin omits cache headers or emits `no-store`), **Browser TTL = Respect origin**, **Serve stale while updating = Enabled**, and **Respect strong ETags = Enabled**.
3. **`amea_bypass_staff_and_stateful`** (last / highest precedence) — fail-closed bypass for `admin.aeesgs.org`, non-`GET` requests, requests carrying `AMEA_PUBLIC=`, `AMEA_STAFF=`, or `PHPSESSID=` cookies, and stateful public endpoints (explicitly exempting `GET`/`HEAD` `/assets/*`).

Settings changes purge the exact public homepage URLs and legal notice, plus the journal listing and article path prefixes on the public host.

### 2. Recommended Free Cloudflare Edge Settings

Enable these zone-level settings on `aeesgs.org` in Cloudflare:
- **Caching → Tiered Cache:** Enable **Smart Tiered Cache** (reduces origin hits on Stellar and R2 reads across global POPs).
- **Speed → Optimization → Protocol Optimization:** Enable **HTTP/3 (with QUIC)**, **0-RTT Connection Resumption**, **Brotli**, and **Early Hints**.
- **Caching → Configuration:** Set **Caching Level = Standard** and **Browser Cache TTL = Respect Existing Headers**.

### 3. Configure Active Edge Cache Purge Credentials

Create a least-privilege Cloudflare API token (**My Profile → API Tokens → Create Token**):
- **Permission:** `Zone → Cache Purge → Purge` (no DNS, WAF, Workers, or R2 edit permissions).
- **Zone Resources:** `Include → Specific zone → aeesgs.org`.

Store the credentials in the private untracked `.env` (Docker) or GitHub Environment secrets for Stellar (`scripts/stellar/render-runtime-env.php` renders them into `0600` `runtime.env` without echoing values):

```dotenv
CLOUDFLARE_ZONE_ID=<32-hex-zone-id>
CLOUDFLARE_API_TOKEN=<scoped-cache-purge-token>
CLOUDFLARE_CACHE_ENABLED=1
```

When omitted, the application runs cleanly with local `AppCache` and origin HTTP headers and treats Cloudflare API purges as a safe no-op.

---

## CLI Operations & Verification

```sh
# Inspect local AppCache and Cloudflare purge readiness (redacts all secrets)
php scripts/cloudflare-cache.php status

# Clear local AppCache and purge the entire Cloudflare zone cache
php scripts/cloudflare-cache.php purge --all

# Purge specific public URLs
php scripts/cloudflare-cache.php purge --urls=/,/journal.php
```

### Verifying Live Edge Hits

AMEA PHP routes accept `GET` (and return `405` on `HEAD`), while static `/assets/*` files support both `GET` and `HEAD`. Confirm response headers with `curl -sD - -o /dev/null`:
- First anonymous `GET` to `https://aeesgs.org/` returns `CF-Cache-Status: MISS` (or `EXPIRED`), `Cache-Control: public, max-age=0, must-revalidate`, `CDN-Cache-Control: public, max-age=3600, ...`, `ETag: "..."`, and **no** `Set-Cookie`.
- Second anonymous `GET` returns `CF-Cache-Status: HIT` with near-zero TTFB.
- Request with `-H 'Cookie: AMEA_PUBLIC=test'` returns `CF-Cache-Status: DYNAMIC` or `BYPASS` and `Cache-Control: private, no-store`.
- Request to `https://admin.aeesgs.org/login.php` or `https://aeesgs.org/register.php` returns `CF-Cache-Status: DYNAMIC` or `BYPASS` and `Cache-Control: private, no-store`.
- Request to `https://admin.aeesgs.org/assets/css/style.css` returns `CF-Cache-Status: HIT` even when signed in.
- Request to an unpublished or invalid journal cover returns `404` with `Cache-Control: no-store` and `CF-Cache-Status: DYNAMIC` or `BYPASS`.

### Rollback

- Disable rule `amea_cache_public_pages_and_media` in Cloudflare Cache Rules (or enable Cloudflare Development Mode) to immediately bypass HTML/media edge caching.
- Set `CLOUDFLARE_CACHE_ENABLED=0` in `runtime.env` to stop outbound Cloudflare Purge API calls while keeping local `AppCache` and HTTP `ETag`/`304` revalidation active.

## Diagnose missing or weak HTML validators

Run the same anonymous GET against Cloudflare and the Stellar virtual host. Use
the origin address from the approved Stellar inventory and a CA bundle that
trusts its certificate. `--resolve` preserves the public hostname in both TLS
SNI and the HTTP request. A loopback address may select a different virtual host.

```bash
url='https://aeesgs.org/legal-notice.php'
for encoding in gzip identity; do
    curl -fsS -D - -o /dev/null -H "Accept-Encoding: $encoding" "$url"
    curl -fsS -D - -o /dev/null -H "Accept-Encoding: $encoding" \
        --resolve "aeesgs.org:443:${STELLAR_ORIGIN_IP}" \
        --cacert "$STELLAR_ORIGIN_CA_BUNDLE" "$url"
done
```

Repeat each request with its own `ETag` value in `If-None-Match`, including any
`W/` prefix. Record the HTTP status, `ETag`, `Content-Encoding`, `Vary` and
`CF-Cache-Status`. The production Stellar verifier runs this revalidation through
Cloudflare with `--check-legal-etags`. It expects a strong gzip ETag and accepts
a weak identity ETag when both requests revalidate with `304`.

When the origin supplies an ETag and the edge omits it, compare the decoded HTML
for Cloudflare transformations. `data-cfemail` and `email-decode.min.js` indicate
Email Address Obfuscation. A script pointing to `/cdn-cgi/challenge-platform/`
indicates Cloudflare challenge code. Use the zone's Rule Simulator and Security
Settings in an existing read-only Cloudflare session to inspect active settings.
The application's purge credential is reserved for purge operations.

On this page, the scoped Cloudflare configuration rule disables Email Address
Obfuscation and Stellar adds `no-transform` to the origin response to prevent
JavaScript Detections injection. Preserve the Stellar directive across releases
before enabling the deployment probe. If the edge's identity ETag is the gzip
ETag with a `W/` prefix, compare the origin's two encodings and inspect
**Respect Strong ETags** in the matching Cache Rule. Cloudflare can request gzip
from the origin and decompress it for identity clients.
