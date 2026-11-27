<?php

declare(strict_types=1);

namespace Amea\Tests\Unit\Cache;

use Amea\Cache\CloudflareCachePurger;
use PHPUnit\Framework\TestCase;

final class CloudflareCachePurgerTest extends TestCase
{
    private const ZONE_ID = '0123456789abcdef0123456789abcdef';
    private const SECRET_TOKEN = 'cf-super-secret-purge-token-999';

    public function testDisabledWhenCredentialsAreMissingOrInvalid(): void
    {
        $empty = new CloudflareCachePurger('', '', true);
        self::assertFalse($empty->isEnabled());
        $res = $empty->purgeUrls(['https://aeesgs.org/']);
        self::assertFalse($res['attempted']);
        self::assertTrue($res['ok']);

        $badZone = new CloudflareCachePurger('not-a-valid-zone-id', self::SECRET_TOKEN, true);
        self::assertFalse($badZone->isEnabled());

        $explicitlyOff = new CloudflareCachePurger(self::ZONE_ID, self::SECRET_TOKEN, false);
        self::assertFalse($explicitlyOff->isEnabled());
        self::assertStringNotContainsString(self::SECRET_TOKEN, json_encode($explicitlyOff->status(), JSON_THROW_ON_ERROR));
    }

    public function testPurgeUrlsDeduplicatesFiltersAndBatchesByThirty(): void
    {
        $requests = [];
        $transport = static function (string $url, array $headers, string $body, int $timeout) use (&$requests): array {
            $requests[] = ['url' => $url, 'headers' => $headers, 'body' => json_decode($body, true, 512, JSON_THROW_ON_ERROR), 'timeout' => $timeout];
            return ['status' => 200, 'body' => '{"success":true,"result":{"id":"ok"}}', 'error' => ''];
        };

        $purger = new CloudflareCachePurger(self::ZONE_ID, self::SECRET_TOKEN, true, CloudflareCachePurger::DEFAULT_API_BASE, 4, $transport);
        self::assertTrue($purger->isEnabled());

        $urls = [];
        for ($i = 1; $i <= 65; $i++) {
            $urls[] = "https://aeesgs.org/journal-article.php?slug=post-{$i}";
        }
        $urls[] = 'https://aeesgs.org/journal-article.php?slug=post-1'; // duplicate
        $urls[] = "https://aeesgs.org/bad\r\nheader"; // invalid
        $urls[] = 'ftp://aeesgs.org/ignored'; // invalid scheme

        $result = $purger->purgeUrls($urls);
        self::assertTrue($result['attempted']);
        self::assertTrue($result['ok']);
        self::assertSame(65, $result['purged']);
        self::assertSame(3, $result['batches']);
        self::assertSame([], $result['errors']);

        self::assertCount(3, $requests);
        self::assertSame(CloudflareCachePurger::DEFAULT_API_BASE . '/zones/' . self::ZONE_ID . '/purge_cache', $requests[0]['url']);
        self::assertSame('Bearer ' . self::SECRET_TOKEN, $requests[0]['headers']['Authorization']);
        self::assertSame(4, $requests[0]['timeout']);
        self::assertCount(30, $requests[0]['body']['files']);
        self::assertCount(30, $requests[1]['body']['files']);
        self::assertCount(5, $requests[2]['body']['files']);
    }

    public function testPurgeEverythingSendsExpectedPayloadAndRedactsSecretOnFailure(): void
    {
        $captured = null;
        $okTransport = static function (string $url, array $headers, string $body) use (&$captured): array {
            $captured = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return ['status' => 200, 'body' => '{"success":true}', 'error' => ''];
        };

        $purger = new CloudflareCachePurger(self::ZONE_ID, self::SECRET_TOKEN, true, CloudflareCachePurger::DEFAULT_API_BASE, 3, $okTransport);
        $ok = $purger->purgeEverything();
        self::assertTrue($ok['attempted']);
        self::assertTrue($ok['ok']);
        self::assertSame(['purge_everything' => true], $captured);

        $failTransport = static function (): array {
            return [
                'status' => 403,
                'body' => '{"success":false,"errors":[{"message":"Invalid token ' . self::SECRET_TOKEN . '"}]}',
                'error' => '',
            ];
        };
        $failingPurger = new CloudflareCachePurger(self::ZONE_ID, self::SECRET_TOKEN, true, CloudflareCachePurger::DEFAULT_API_BASE, 3, $failTransport);
        $fail = $failingPurger->purgeEverything();
        self::assertTrue($fail['attempted']);
        self::assertFalse($fail['ok']);
        self::assertCount(1, $fail['errors']);
        self::assertStringNotContainsString(self::SECRET_TOKEN, $fail['errors'][0]);
        self::assertStringContainsString('[REDACTED]', $fail['errors'][0]);
    }
}
