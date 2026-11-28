<?php

declare(strict_types=1);

namespace Amea\Tests\Unit\Cache;

use Amea\Cache\AppCache;
use Amea\Cache\CacheInvalidator;
use Amea\Cache\CloudflareCachePurger;
use Amea\Command\CloudflareCacheCommand;
use PHPUnit\Framework\TestCase;

final class CacheInvalidatorAndCommandTest extends TestCase
{
    private const ZONE_ID = 'fedcba9876543210fedcba9876543210';

    public function testInvalidatorPurgesExactSliderJournalAndSettingsTargets(): void
    {
        $payloads = [];
        $transport = static function (string $url, array $headers, string $body) use (&$payloads): array {
            $payloads[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return ['status' => 200, 'body' => '{"success":true}', 'error' => ''];
        };

        $cache = AppCache::memoryOnly('invalidator-test');
        $cache->set(AppCache::NS_SLIDER, 'active', [['id' => 1]], 300);
        $cache->set(AppCache::NS_JOURNAL, 'recent', [['id' => 2]], 300);
        $cache->set(AppCache::NS_SETTINGS, 'all', ['association_name' => 'AEESGS'], 300);

        $cf = new CloudflareCachePurger(self::ZONE_ID, 'cf-token-test', true, CloudflareCachePurger::DEFAULT_API_BASE, 3, $transport);
        $invalidator = new CacheInvalidator($cache, $cf, 'https://aeesgs.org');

        $sliderRes = $invalidator->onSliderChanged('uploads/slider/photo-1.jpg');
        self::assertTrue($sliderRes['ok']);
        self::assertNull($cache->get(AppCache::NS_SLIDER, 'active'));
        self::assertNotNull($cache->get(AppCache::NS_SETTINGS, 'all'));
        self::assertSame([
            'https://aeesgs.org/',
            'https://aeesgs.org/index.php',
            'https://aeesgs.org/uploads/slider/photo-1.jpg',
        ], $payloads[0]['files']);

        // Published/withdrawn journal change purges listings (pages 1–3), article, and unversioned + versioned covers.
        $journalRes = $invalidator->onJournalChanged('rentree-2026', ['old123456789', 'new987654321']);
        self::assertTrue($journalRes['ok']);
        self::assertNull($cache->get(AppCache::NS_JOURNAL, 'recent'));
        $journalFiles = $payloads[1]['files'];
        self::assertLessThanOrEqual(30, count($journalFiles), 'Journal purge should fit in a single 30-URL Cloudflare API batch');
        self::assertContains('https://aeesgs.org/', $journalFiles);
        self::assertContains('https://aeesgs.org/journal.php', $journalFiles);
        self::assertContains('https://aeesgs.org/journal.php?view=upcoming', $journalFiles);
        self::assertContains('https://aeesgs.org/journal.php?view=all&page=2', $journalFiles);
        self::assertContains('https://aeesgs.org/journal.php?view=all&page=3', $journalFiles);
        self::assertContains('https://aeesgs.org/journal-article.php?slug=rentree-2026', $journalFiles);
        self::assertContains('https://aeesgs.org/journal-cover.php?slug=rentree-2026', $journalFiles);
        self::assertContains('https://aeesgs.org/journal-cover.php?slug=rentree-2026&v=old123456789', $journalFiles);
        self::assertContains('https://aeesgs.org/journal-cover.php?slug=rentree-2026&v=new987654321', $journalFiles);

        // Settings update invalidates settings and purges public HTML.
        $settingsRes = $invalidator->onSettingsChanged();
        self::assertTrue($settingsRes['ok']);
        self::assertNull($cache->get(AppCache::NS_SETTINGS, 'all'));
        self::assertSame(['files' => ['https://aeesgs.org/', 'https://aeesgs.org/index.php', 'https://aeesgs.org/legal-notice.php']], $payloads[2]);
        self::assertSame(['prefixes' => ['aeesgs.org/journal.php', 'aeesgs.org/journal-article.php']], $payloads[3]);
    }

    public function testStandaloneSettingsAreIsolatedByConnection(): void
    {
        $repositories = [];
        foreach (['first', 'second'] as $value) {
            $pdo = new \PDO('sqlite::memory:');
            $pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
            $repository = new \Amea\Repository\SettingRepository($pdo);
            $repository->upsert('name', $value);
            $repositories[] = $repository;
        }
        self::assertSame('first', $repositories[0]->getValue('name'));
        self::assertSame('second', $repositories[1]->getValue('name'));
    }

    public function testSliderInvalidationPrecedesFailingCleanup(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE member_file_lock (id INTEGER); INSERT INTO member_file_lock VALUES (1);
            CREATE TABLE slider_images (id INTEGER, image_path TEXT);
            INSERT INTO slider_images VALUES (1, 'uploads/slider/example.jpg')");
        $cache = AppCache::memoryOnly();
        $cache->set(AppCache::NS_SLIDER, 'active', [1], 300);
        $invalidator = new CacheInvalidator($cache, new CloudflareCachePurger('', '', false));
        $queue = $this->createMock(\Amea\Service\FileLifecycleService::class);
        $queue->expects(self::once())->method('retryDeletions')->willThrowException(new \RuntimeException('cleanup failed'));
        $service = new \Amea\Service\SliderService($pdo, $this->createStub(\Amea\Storage\Storage::class), $queue, $invalidator);
        try {
            $service->remove(1);
            self::fail('Expected cleanup failure');
        } catch (\RuntimeException $error) {
            self::assertSame('cleanup failed', $error->getMessage());
        }
        self::assertNull($cache->get(AppCache::NS_SLIDER, 'active'));
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM slider_images')->fetchColumn());
    }

    public function testExportRulesCommandMatchesShippedCacheRulesJson(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $cmd = new CloudflareCacheCommand($repoRoot, AppCache::memoryOnly('cli-test'), new CloudflareCachePurger('', '', false));

        $exported = $cmd->run(['cloudflare-cache.php', 'export-rules', '--public-host=aeesgs.org', '--staff-host=admin.aeesgs.org']);
        self::assertSame(0, $exported['exit']);

        $shipped = json_decode((string) file_get_contents($repoRoot . '/config/cloudflare/cache-rules.json'), true, 512, JSON_THROW_ON_ERROR);
        $generated = json_decode($exported['output'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($shipped, $generated);
        self::assertSame(
            ['amea_cache_static_assets', 'amea_cache_public_pages_and_media', 'amea_bypass_staff_and_stateful'],
            array_column($shipped['rules'], 'ref')
        );
        self::assertSame('bypass_by_default', $shipped['rules'][0]['action_parameters']['edge_ttl']['mode']);
        self::assertSame('bypass_by_default', $shipped['rules'][1]['action_parameters']['edge_ttl']['mode']);
        self::assertFalse($shipped['rules'][2]['action_parameters']['cache']);

        $custom = $cmd->run(['cloudflare-cache.php', 'export-rules', '--public-host=public.example', '--staff-host=staff.example']);
        $rules = json_decode($custom['output'], true, flags: JSON_THROW_ON_ERROR)['rules'];
        self::assertStringStartsWith('(http.host in {"public.example" "staff.example"}) and ', $rules[2]['expression']);

        $status = $cmd->run(['cloudflare-cache.php', 'status']);
        self::assertSame(0, $status['exit']);
        $statusDecoded = json_decode($status['output'], true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('app_cache', $statusDecoded);
        self::assertArrayHasKey('cloudflare', $statusDecoded);
        self::assertFalse($statusDecoded['cloudflare']['enabled']);

        $purgeAll = $cmd->run(['cloudflare-cache.php', 'purge', '--all']);
        self::assertSame(0, $purgeAll['exit']);
    }
}
