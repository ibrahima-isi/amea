<?php
namespace Amea\Tests\Integration\Repository;
use Amea\Repository\JournalRepository;
use PHPUnit\Framework\TestCase;
final class JournalRepositoryTest extends TestCase
{
    public function testPublicPageUpperBoundIsFiftyAndInvalidViewIsRejected(): void
    {
        $posts = new JournalRepository(makeTestDb());
        for ($index = 1; $index <= 55; $index++) {
            $posts->insert([
                'type' => 'ANNOUNCEMENT', 'status' => 'PUBLISHED',
                'slug' => 'avis-' . $index, 'title' => 'Avis',
                'summary' => 'Résumé', 'body' => 'Texte',
                'published_at' => '2026-10-30 10:00:00',
                'created_by' => 1, 'updated_by' => 1,
                'created_at' => '2026-10-30 10:00:00', 'updated_at' => '2026-10-30 10:00:00',
            ]);
        }
        $page = $posts->findPublicPage('all', 1, 1000, '2026-10-30 12:00:00');
        self::assertSame(55, $page['total']);
        self::assertCount(50, $page['items']);
        self::assertCount(50, $posts->findRecentPublished(1000));
        $this->expectException(\InvalidArgumentException::class);
        $posts->findPublicPage('status = DRAFT', 1, 20, '2026-10-30 12:00:00');
    }

    public function testPublicViewsExcludeDraftsAndRespectDatesOrderingAndBounds(): void
    {
        $posts = new JournalRepository(makeTestDb());
        $base = ['type'=>'ANNOUNCEMENT','status'=>'PUBLISHED','title'=>'Avis','summary'=>'Résumé','body'=>'Texte','cover_alt'=>'','created_by'=>1,'updated_by'=>1,'created_at'=>'2026-10-30 10:00:00','updated_at'=>'2026-10-30 10:00:00','published_at'=>'2026-10-30 10:00:00'];
        $draft = $posts->insert(array_replace($base, ['status'=>'DRAFT','slug'=>'brouillon']));
        $notice = $posts->insert(array_replace($base, ['slug'=>'avis']));
        $future = $posts->insert(array_replace($base, ['type'=>'ACTIVITY','slug'=>'demain','activity_start_at'=>'2026-10-31 00:00:00','activity_end_at'=>'2026-10-31 23:59:59']));
        $past = $posts->insert(array_replace($base, ['type'=>'ACTIVITY','slug'=>'hier','activity_start_at'=>'2026-10-29 00:00:00','activity_end_at'=>'2026-10-29 23:59:59']));
        $now='2026-10-30 12:00:00';
        self::assertSame(3,$posts->findPublicPage('all',1,20,$now)['total']);
        self::assertSame([$past,$future,$notice],array_column($posts->findPublicPage('all',1,20,$now)['items'],'id'));
        self::assertSame([$future],array_column($posts->findPublicPage('upcoming',1,20,$now)['items'],'id'));
        self::assertSame([$past],array_column($posts->findPublicPage('archives',1,20,$now)['items'],'id'));
        self::assertNull($posts->findPublishedBySlug('brouillon'));
        self::assertSame($draft,$posts->findById($draft)['id']);
        self::assertSame([$future],array_column($posts->findPublicPage('all',2,1,$now)['items'],'id'));
        self::assertCount(1,$posts->findPublicPage('all',0,0,$now)['items']);
        self::assertSame(4,$posts->findAdminPage()['total']);
        self::assertSame([$past,$future,$notice],array_column($posts->findRecentPublished(100),'id'));
        self::assertSame(0,$posts->findPublicPage('reports',1,20,$now)['total']);
        self::assertSame(1,$posts->findPublicPage('announcements',1,20,$now)['total']);
    }
}
