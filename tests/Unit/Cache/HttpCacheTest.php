<?php

declare(strict_types=1);

namespace Amea\Tests\Unit\Cache;

use Amea\Cache\CachePolicy;
use Amea\Cache\HttpCache;
use PHPUnit\Framework\TestCase;

final class HttpCacheTest extends TestCase
{
    public function testHasPublicSessionCookieDetectsPublicAndLegacyCookiesOnly(): void
    {
        self::assertFalse(HttpCache::hasPublicSessionCookie([]));
        self::assertFalse(HttpCache::hasPublicSessionCookie(['AMEA_STAFF' => 'staff123']));
        self::assertFalse(HttpCache::hasPublicSessionCookie(['AMEA_PUBLIC' => '   ']));
        self::assertTrue(HttpCache::hasPublicSessionCookie(['AMEA_PUBLIC' => 'abc123']));
        self::assertTrue(HttpCache::hasPublicSessionCookie(['PHPSESSID' => 'legacy123']));
    }

    public function testPublicHtmlHeadersEmitBrowserAndCloudflareDirectives(): void
    {
        $etag = HttpCache::strongEtag('homepage-body');
        $headers = HttpCache::publicHtmlHeaders(
            $etag,
            CachePolicy::publicHtml(60, 3600, 300, 86400, 604800),
            1732680000
        );

        self::assertSame('public, max-age=60, stale-while-revalidate=300', $headers['Cache-Control']);
        self::assertSame('public, max-age=3600, stale-while-revalidate=86400, stale-if-error=604800', $headers['CDN-Cache-Control']);
        self::assertSame($headers['CDN-Cache-Control'], $headers['Cloudflare-CDN-Cache-Control']);
        self::assertSame($etag, $headers['ETag']);
        self::assertSame('Accept-Encoding', $headers['Vary']);
        self::assertSame('Wed, 27 Nov 2024 04:00:00 GMT', $headers['Last-Modified']);

        $revalidateHeaders = HttpCache::publicHtmlHeaders(
            $etag,
            CachePolicy::publicHtml(0, 3600, 0, 86400, 604800, true)
        );
        self::assertSame('public, max-age=0, must-revalidate', $revalidateHeaders['Cache-Control']);
        self::assertSame('public, max-age=3600, stale-while-revalidate=86400, stale-if-error=604800', $revalidateHeaders['CDN-Cache-Control']);
        self::assertArrayNotHasKey('Last-Modified', $revalidateHeaders);
    }

    public function testPublicMediaHeadersSupportMustRevalidateAndNosniff(): void
    {
        $etag = HttpCache::strongEtag('cover-bytes');
        $headers = HttpCache::publicMediaHeaders(
            $etag,
            CachePolicy::publicMedia(300, 86400, 604800, 604800, true),
            1732680000
        );

        self::assertSame('public, max-age=300, must-revalidate', $headers['Cache-Control']);
        self::assertSame('public, max-age=86400, stale-while-revalidate=604800, stale-if-error=604800', $headers['CDN-Cache-Control']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertSame($etag, $headers['ETag']);
    }

    public function testNoStoreHeadersMatchPrivateAndPublicVariants(): void
    {
        $private = HttpCache::noStoreHeaders(true);
        self::assertSame('private, no-store', $private['Cache-Control']);
        self::assertSame('no-store', $private['CDN-Cache-Control']);
        self::assertSame('no-store', $private['Cloudflare-CDN-Cache-Control']);

        $publicError = HttpCache::noStoreHeaders(false);
        self::assertSame('no-store', $publicError['Cache-Control']);
        self::assertSame('no-store', $publicError['CDN-Cache-Control']);
    }

    public function testConditionalRequestMatchingFollowsRfc9110(): void
    {
        $etag = HttpCache::strongEtag('payload-v1');
        $lastModified = 1732680000;

        self::assertTrue(HttpCache::matchesConditionalRequest($etag, $lastModified, [
            'REQUEST_METHOD' => 'GET',
            'HTTP_IF_NONE_MATCH' => $etag,
        ]));
        self::assertTrue(HttpCache::matchesConditionalRequest($etag, $lastModified, [
            'REQUEST_METHOD' => 'HEAD',
            'HTTP_IF_NONE_MATCH' => '"other", W/' . $etag,
        ]));
        self::assertTrue(HttpCache::matchesConditionalRequest($etag, $lastModified, [
            'REQUEST_METHOD' => 'GET',
            'HTTP_IF_NONE_MATCH' => '*',
        ]));
        // If-None-Match takes precedence over If-Modified-Since per RFC 9110 §13.1.3.
        self::assertFalse(HttpCache::matchesConditionalRequest($etag, $lastModified, [
            'REQUEST_METHOD' => 'GET',
            'HTTP_IF_NONE_MATCH' => '"different-etag"',
            'HTTP_IF_MODIFIED_SINCE' => HttpCache::formatHttpDate($lastModified + 3600),
        ]));
        self::assertTrue(HttpCache::matchesConditionalRequest($etag, $lastModified, [
            'REQUEST_METHOD' => 'GET',
            'HTTP_IF_MODIFIED_SINCE' => HttpCache::formatHttpDate($lastModified),
        ]));
        self::assertFalse(HttpCache::matchesConditionalRequest($etag, $lastModified, [
            'REQUEST_METHOD' => 'GET',
            'HTTP_IF_MODIFIED_SINCE' => HttpCache::formatHttpDate($lastModified - 10),
        ]));
        self::assertFalse(HttpCache::matchesConditionalRequest($etag, $lastModified, [
            'REQUEST_METHOD' => 'POST',
            'HTTP_IF_NONE_MATCH' => $etag,
        ]));
    }
}
