<?php
namespace Amea\Tests\Integration\Service;

use Amea\Cache\AppCache;
use Amea\Cache\CloudflareCachePurger;
use Amea\Core\Container;
use Amea\Repository\JournalRepository;
use Amea\Repository\SliderRepository;
use Amea\Service\FileLifecycleService;
use Amea\Service\JournalService;
use Amea\Service\SliderService;
use Amea\Storage\Storage;
use PHPUnit\Framework\TestCase;

final class ServiceCacheIsolationTest extends TestCase
{
    private \PDO $writer;
    private AppCache $otherCache;

    protected function setUp(): void
    {
        $other = makeTestDb();
        $this->writer = makeTestDb();
        Container::setDb($other);
        Container::setCloudflare(new CloudflareCachePurger('', '', false));
        $this->otherCache = Container::cacheForConnection($other);
    }

    protected function tearDown(): void
    {
        Container::reset();
    }

    public function testSliderRemovalRefreshesOnlyTheWriterCache(): void
    {
        $this->otherCache->set(AppCache::NS_SLIDER, 'marker', 'other', 300);
        $this->writer->exec("INSERT INTO slider_images (image_path) VALUES ('uploads/slider/example.jpg')");
        $images = new SliderRepository($this->writer);
        self::assertCount(1, $images->getActiveImages());
        $service = new SliderService($this->writer, $this->createStub(Storage::class), $this->createStub(FileLifecycleService::class));
        self::assertTrue($service->remove(1));
        self::assertSame([], $images->getActiveImages());
        self::assertSame('other', $this->otherCache->get(AppCache::NS_SLIDER, 'marker'));
    }

    public function testJournalPublicationRefreshesOnlyTheWriterCache(): void
    {
        $this->otherCache->set(AppCache::NS_JOURNAL, 'marker', 'other', 300);
        $posts = new JournalRepository($this->writer);
        self::assertSame([], $posts->findRecentPublished());
        $article = (new JournalService($posts))->save(null, [
            'type' => 'REPORT', 'title' => 'Rapport', 'summary' => 'Résumé', 'body' => 'Texte',
        ], 1, true);
        self::assertSame([$article['id']], array_column($posts->findRecentPublished(), 'id'));
        self::assertSame('other', $this->otherCache->get(AppCache::NS_JOURNAL, 'marker'));
    }
}
