<?php
namespace Amea\Tests\Integration\Service;
use Amea\Repository\JournalRepository;
use Amea\Service\JournalService;
use PHPUnit\Framework\TestCase;
final class JournalServiceTest extends TestCase
{
    private function service(): array
    {
        $posts=new JournalRepository(makeTestDb());
        return [$posts,new JournalService($posts,fn()=>new \DateTimeImmutable('2026-10-30 12:00:00',new \DateTimeZone('Africa/Dakar')))];
    }
    public function testEditorialLifecycleAndUntrustedFields(): void
    {
        [$posts,$service]=$this->service();
        $draft=$service->save(null,['type'=>'ANNOUNCEMENT','title'=>'Assemblée générale','status'=>'PUBLISHED','created_by'=>99,'cover_path'=>'secret'],1);
        self::assertSame('DRAFT',$draft['status']); self::assertSame(1,$draft['created_by']); self::assertNull($draft['cover_path']);
        self::assertNull($posts->findPublishedBySlug($draft['slug']));
        $published=$service->save($draft['id'],['summary'=>'Notre rencontre','body'=>'Tous les membres sont invités.'],1,true);
        self::assertSame($draft['id'],$posts->findPublishedBySlug($draft['slug'])['id']);
        $service->withdraw($draft['id'],1); self::assertNull($posts->findPublishedBySlug($draft['slug']));
        $later=new JournalService($posts,fn()=>new \DateTimeImmutable('2026-11-02 12:00:00',new \DateTimeZone('Africa/Dakar')));
        $again=$later->save($draft['id'],['title'=>'Nouveau titre'],1,true);
        self::assertSame($published['slug'],$again['slug']); self::assertSame('2026-10-30 12:00:00',$again['published_at']);
        self::assertSame('2026-11-02 12:00:00',$again['updated_at']);
        self::assertNotSame($draft['slug'],$service->save(null,['type'=>'REPORT','title'=>'Assemblée générale'],1)['slug']);
    }
    public function testOptionalHoursAndExpiryBoundaryAndNonActivityClearing(): void
    {
        [$posts,$service]=$this->service();
        $event=$service->save(null,['type'=>'ACTIVITY','title'=>'Rencontre','summary'=>'Invitation','body'=>'Bienvenue','activity_start_date'=>'2026-10-30','location'=>'Dakar'],1,true);
        self::assertSame('2026-10-30 00:00:00',$event['activity_start_at']); self::assertSame('2026-10-30 23:59:59',$event['activity_end_at']);
        self::assertSame(0,$event['activity_start_time_set']); self::assertSame(0,$event['activity_end_time_set']);
        $roundtrip=$service->save($event['id'],['title'=>'Rencontre annuelle'],1);
        self::assertSame(0,$roundtrip['activity_end_time_set']); self::assertSame($event['activity_end_at'],$roundtrip['activity_end_at']);
        self::assertSame(1,$posts->findPublicPage('upcoming',1,20,'2026-10-30 23:59:59')['total']);
        self::assertSame(1,$posts->findPublicPage('archives',1,20,'2026-10-31 00:00:00')['total']);
        $notice=$service->save($event['id'],['type'=>'ANNOUNCEMENT'],1);
        self::assertNull($notice['activity_start_at']); self::assertNull($notice['activity_end_at']); self::assertNull($notice['location']);
    }
    public function testInvalidDraftDateRejected(): void
    {
        [, $service]=$this->service(); $this->expectException(\InvalidArgumentException::class);
        $service->save(null,['type'=>'ACTIVITY','title'=>'Rencontre','activity_start_date'=>'2026-02-30'],1);
    }
    public function testEndBeforeStartRejected(): void
    {
        [, $service]=$this->service(); $this->expectException(\InvalidArgumentException::class);
        $service->save(null,['type'=>'ACTIVITY','title'=>'Rencontre','activity_start_date'=>'2026-10-30','activity_start_time'=>'15:00','activity_end_time'=>'14:00'],1);
    }
    public function testIncompletePublicationRejected(): void
    {
        [, $service]=$this->service(); $this->expectException(\InvalidArgumentException::class);
        $service->save(null,['type'=>'REPORT','title'=>'Rapport'],1,true);
    }

    public function testInvalidPublishedEditDoesNotChangeThePublicRecord(): void
    {
        [$posts, $service] = $this->service();
        $post = $service->save(null, [
            'type' => 'ANNOUNCEMENT', 'title' => 'Information',
            'summary' => 'Résumé', 'body' => 'Texte publié',
        ], 1, true);
        try {
            $service->save($post['id'], ['body' => ''], 1);
            self::fail('An incomplete published edit must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertSame('Texte publié', $posts->findPublishedBySlug($post['slug'])['body']);
        }
    }

    public function testArrayValuedTitleIsRejectedWithoutCreatingAPost(): void
    {
        [$posts, $service] = $this->service();
        try {
            $service->save(null, ['type' => 'REPORT', 'title' => ['Texte']], 1);
            self::fail('An array cannot be a title.');
        } catch (\InvalidArgumentException) {
            self::assertSame(0, $posts->findAdminPage()['total']);
        }
    }

    public function testCoverRequiresAltTextBeforePublishingAndCanBeRemoved(): void
    {
        [$posts, $service] = $this->service();
        $post = $service->save(null, [
            'type' => 'REPORT', 'title' => 'Notre activité',
            'summary' => 'Résumé', 'body' => 'Un récit',
        ], 1, false, [
            'path' => 'uploads/journal/covers/' . str_repeat('a', 32) . '.png',
            'mime' => 'image/png',
        ]);
        try {
            $service->save($post['id'], [], 1, true);
            self::fail('A cover needs alternative text before publishing.');
        } catch (\InvalidArgumentException) {
            self::assertNull($posts->findPublishedBySlug($post['slug']));
        }
        $published = $service->save($post['id'], ['cover_alt' => 'Notre communauté'], 1, true);
        self::assertSame('image/png', $published['cover_mime']);
        $withoutCover = $service->save($post['id'], ['remove_cover' => '1'], 1);
        self::assertNull($withoutCover['cover_path']);
        self::assertSame('PUBLISHED', $withoutCover['status']);
    }

    public function testClockIsNormalizedToDakar(): void
    {
        $posts = new JournalRepository(makeTestDb());
        $service = new JournalService($posts, fn() => new \DateTimeImmutable('2026-10-30T15:00:00+03:00'));
        $post = $service->save(null, [
            'type' => 'ANNOUNCEMENT', 'title' => 'Information',
            'summary' => 'Résumé', 'body' => 'Texte',
        ], 1, true);
        self::assertSame('2026-10-30 12:00:00', $post['published_at']);
    }
}
