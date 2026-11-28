<?php

declare(strict_types=1);

namespace Amea\Cache;

/**
 * RFC 9110 / RFC 9213 HTTP caching helper tailored for Cloudflare Edge + browser caching.
 *
 * Emits Cloudflare-CDN-Cache-Control and CDN-Cache-Control independently of browser
 * Cache-Control so edge POPs can hold long TTLs with stale-while-revalidate and
 * stale-if-error while browsers revalidate promptly and private/sessioned responses
 * fail closed with no-store.
 */
final class HttpCache
{
    public const PUBLIC_SESSION_COOKIES = ['AMEA_PUBLIC', 'PHPSESSID'];

    /**
     * Detect whether the request carries an existing public browser session cookie.
     *
     * @param array<string, mixed>|null $cookies
     */
    public static function hasPublicSessionCookie(?array $cookies = null): bool
    {
        $source = $cookies ?? $_COOKIE;
        foreach (self::PUBLIC_SESSION_COOKIES as $name) {
            $value = $source[$name] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }
        return false;
    }

    /** Match the raw query string because Cloudflare purges exact URL variants. */
    public static function hasQuery(string ...$allowed): bool
    {
        return in_array($_SERVER['QUERY_STRING'] ?? '', $allowed, true);
    }

    public static function strongEtag(string $seed): string
    {
        return '"' . substr(hash('sha256', $seed), 0, 32) . '"';
    }

    public static function formatHttpDate(int $timestamp): string
    {
        return gmdate('D, d M Y H:i:s', max(0, $timestamp)) . ' GMT';
    }

    /**
     * Build headers for cacheable anonymous public HTML responses.
     *
     * @return array<string, string>
     */
    public static function publicHtmlHeaders(string $etag, CachePolicy $policy, ?int $lastModified = null): array
    {
        $cdn = $policy->cdnCacheControl();
        $headers = [
            'Cache-Control' => $policy->browserCacheControl(),
            'CDN-Cache-Control' => $cdn,
            'Cloudflare-CDN-Cache-Control' => $cdn,
            'ETag' => $etag,
            'Vary' => 'Accept-Encoding',
        ];
        if ($lastModified !== null && $lastModified > 0) {
            $headers['Last-Modified'] = self::formatHttpDate($lastModified);
        }

        return $headers;
    }

    /**
     * Build headers for cacheable public media (carousel photos, published journal covers).
     *
     * @return array<string, string>
     */
    public static function publicMediaHeaders(string $etag, CachePolicy $policy, ?int $lastModified = null): array
    {
        $cdn = $policy->cdnCacheControl();
        $headers = [
            'Cache-Control' => $policy->browserCacheControl(),
            'CDN-Cache-Control' => $cdn,
            'Cloudflare-CDN-Cache-Control' => $cdn,
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($lastModified !== null && $lastModified > 0) {
            $headers['Last-Modified'] = self::formatHttpDate($lastModified);
        }

        return $headers;
    }

    /**
     * Fail-closed non-cacheable headers for private, stateful, staff, or error responses.
     *
     * @return array<string, string>
     */
    public static function noStoreHeaders(bool $private = true): array
    {
        return [
            'Cache-Control' => $private ? 'private, no-store' : 'no-store',
            'CDN-Cache-Control' => 'no-store',
            'Cloudflare-CDN-Cache-Control' => 'no-store',
        ];
    }

    /**
     * Evaluate HTTP conditional GET/HEAD headers per RFC 9110 §13.1.2 / §13.1.3.
     *
     * @param array<string, mixed>|null $server
     */
    public static function matchesConditionalRequest(string $etag, ?int $lastModified = null, ?array $server = null): bool
    {
        $env = $server ?? $_SERVER;
        $method = strtoupper((string) ($env['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return false;
        }

        $ifNoneMatch = $env['HTTP_IF_NONE_MATCH'] ?? null;
        if (is_string($ifNoneMatch) && trim($ifNoneMatch) !== '') {
            $trimmed = trim($ifNoneMatch);
            if ($trimmed === '*') {
                return true;
            }
            $target = self::normalizeWeakEtag($etag);
            foreach (explode(',', $trimmed) as $candidate) {
                if (self::normalizeWeakEtag($candidate) === $target) {
                    return true;
                }
            }
            // RFC 9110 §13.1.3: ignore If-Modified-Since whenever If-None-Match is present.
            return false;
        }

        if ($lastModified === null || $lastModified <= 0) {
            return false;
        }

        $ifModifiedSince = $env['HTTP_IF_MODIFIED_SINCE'] ?? null;
        if (!is_string($ifModifiedSince) || trim($ifModifiedSince) === '') {
            return false;
        }

        $since = strtotime($ifModifiedSince);
        if ($since === false) {
            return false;
        }

        return $lastModified <= $since;
    }

    /**
     * @param array<string, string> $headers
     */
    public static function sendHeaders(array $headers, bool $clearLegacyNoCache = false): void
    {
        if (headers_sent()) {
            return;
        }
        if ($clearLegacyNoCache) {
            header_remove('Pragma');
            header_remove('Expires');
        }
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value, true);
        }
    }

    public static function sendNoStore(bool $private = true): void
    {
        self::sendHeaders(self::noStoreHeaders($private));
    }

    /**
     * Emit public cacheable HTML with strong ETag and 304 Not Modified short-circuit.
     */
    public static function respondPublicHtml(
        string $body,
        CachePolicy $policy,
        ?int $lastModified = null,
        bool $cacheable = true
    ): void {
        $weights = [];
        foreach (explode(',', strtolower($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')) as $entry) {
            if (preg_match('/^\s*(gzip|identity|\*)\s*(?:;\s*q=(0(?:\.\d{0,3})?|1(?:\.0{0,3})?))?\s*$/D', $entry, $match)) {
                $weights[$match[1]] = (float) ($match[2] ?? 1);
            }
        }
        $gzip = $weights['gzip'] ?? $weights['*'] ?? 0;
        $identity = $weights['identity'] ?? (($weights['*'] ?? null) === 0.0 ? 0 : 1);
        if ($gzip <= 0 && $identity <= 0) {
            self::sendNoStore();
            header('Vary: Accept-Encoding');
            http_response_code(406);
            return;
        }
        if ($gzip > 0 && $gzip >= $identity) {
            $compressed = gzencode($body);
            if ($compressed !== false) {
                $body = $compressed;
                header('Content-Encoding: gzip');
            }
        }
        $etag = self::strongEtag($body);
        $headers = self::publicHtmlHeaders($etag, $policy, $lastModified);
        self::sendHeaders($cacheable ? $headers : array_replace($headers, self::noStoreHeaders(false)), true);

        if (self::matchesConditionalRequest($etag, $lastModified)) {
            http_response_code(304);
            return;
        }

        echo $body;
    }

    /**
     * Serve verified public media with optional metadata-only 304 short-circuit before R2 streaming.
     *
     * Callers must set $allowMetadataNotModified to true ONLY when object metadata
     * already carries a write-verified SHA-256 digest and allowlisted MIME type.
     *
     * @param callable(): array{stream: resource, size: int, mime: string, sha256: string} $opener
     */
    public static function respondPublicMedia(
        string $etagPrefix,
        ?string $metadataDigest,
        ?int $lastModified,
        CachePolicy $policy,
        bool $allowMetadataNotModified,
        callable $opener,
        bool $cacheable = true
    ): void {
        $etag = self::strongEtag($etagPrefix . $metadataDigest);
        if (!$allowMetadataNotModified) {
            $lastModified = null;
        }
        $cacheHeaders = self::publicMediaHeaders($etag, $policy, $lastModified);
        if (!$cacheable) {
            $cacheHeaders = array_replace($cacheHeaders, self::noStoreHeaders(false));
        }

        if ($allowMetadataNotModified && self::matchesConditionalRequest($etag, $lastModified)) {
            self::sendHeaders($cacheHeaders, true);
            http_response_code(304);
            return;
        }

        $file = $opener();
        try {
            $etag = self::strongEtag($etagPrefix . $file['sha256']);
            $cacheHeaders['ETag'] = $etag;
            if (!$allowMetadataNotModified && self::matchesConditionalRequest($etag, $lastModified)) {
                self::sendHeaders($cacheHeaders, true);
                http_response_code(304);
                return;
            }
            self::sendHeaders($cacheHeaders, true);
            header('Content-Type: ' . $file['mime']);
            header('Content-Length: ' . $file['size']);
            fpassthru($file['stream']);
        } finally {
            if (isset($file['stream']) && is_resource($file['stream'])) {
                fclose($file['stream']);
            }
        }
    }

    private static function normalizeWeakEtag(string $tag): string
    {
        $trimmed = trim($tag);
        if (str_starts_with($trimmed, 'W/') || str_starts_with($trimmed, 'w/')) {
            $trimmed = substr($trimmed, 2);
        }
        return trim($trimmed);
    }
}
