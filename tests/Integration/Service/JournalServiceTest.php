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

    public function testConcurrentEditCannotUndoWithdrawal(): void
    {
        [$posts, $writer, $racing] = $this->interleavedServices();
        $post = $writer->save(null, [
            'type' => 'ANNOUNCEMENT', 'title' => 'Information',
            'summary' => 'Résumé', 'body' => 'Texte publié',
        ], 1, true);
        $racing->afterRead = fn() => $writer->withdraw($post['id'], 1);
        $editor = new JournalService($racing);
        $conflict = false;
        try {
            $editor->save($post['id'], ['body' => 'Modification concurrente'], 1);
        } catch (\RuntimeException) {
            $conflict = true;
        }
        self::assertTrue($conflict, 'A stale editorial operation must be rejected.');
        self::assertNull($posts->findPublishedBySlug($post['slug']));
    }

    public function testConcurrentFirstPublicationsCannotReplaceFirstDate(): void
    {
        [$posts, $writer, $racing] = $this->interleavedServices();
        $post = $writer->save(null, [
            'type' => 'REPORT', 'title' => 'Notre activité',
            'summary' => 'Résumé', 'body' => 'Texte',
        ], 1);
        $racing->afterRead = fn() => $writer->save($post['id'], [], 1, true);
        $later = new JournalService($racing, fn() => new \DateTimeImmutable('2026-11-02 12:00:00', new \DateTimeZone('Africa/Dakar')));
        $conflict = false;
        try {
            $later->save($post['id'], [], 1, true);
        } catch (\RuntimeException) {
            $conflict = true;
        }
        self::assertTrue($conflict);
        self::assertSame('2026-10-30 12:00:00', $posts->findPublishedBySlug($post['slug'])['published_at']);
    }

    public function testStaleFormRevisionCannotOverwriteAnotherEdit(): void
    {
        [$posts, $service] = $this->service();
        $draft = $service->save(null, ['type' => 'REPORT', 'title' => 'Premier titre'], 1);
        $latest = $service->save($draft['id'], ['title' => 'Titre corrigé', 'revision' => $draft['revision']], 1);
        self::assertSame(2, $latest['revision']);
        try {
            $service->save($draft['id'], ['title' => 'Ancien formulaire', 'revision' => $draft['revision']], 1);
            self::fail('A stale form revision must be rejected.');
        } catch (\Amea\Service\JournalConflictException) {
            self::assertSame('Titre corrigé', $posts->findById($draft['id'])['title']);
        }
    }

    public function testValidationBeforeUploadDoesNotPersistContent(): void
    {
        [$posts, $service] = $this->service();
        $service->validate(null, ['type' => 'REPORT', 'title' => 'Un brouillon'], 1);
        self::assertSame(0, $posts->findAdminPage()['total']);
        $post = $service->save(null, ['type' => 'REPORT', 'title' => 'Titre original'], 1);
        $service->validate($post['id'], ['title' => 'Validation seule'], 1);
        self::assertSame('Titre original', $posts->findById($post['id'])['title']);
        self::assertSame(1, $posts->findById($post['id'])['revision']);
    }

    private function interleavedServices(): array
    {
        $db = makeTestDb();
        $posts = new JournalRepository($db);
        $writer = new JournalService($posts, fn() => new \DateTimeImmutable('2026-10-30 12:00:00', new \DateTimeZone('Africa/Dakar')));
        $racing = new class($db) extends JournalRepository {
            public ?\Closure $afterRead = null;
            public function findById(int $id): ?array
            {
                $snapshot = parent::findById($id);
                if ($this->afterRead !== null) {
                    $callback = $this->afterRead;
                    $this->afterRead = null;
                    $callback();
                }
                return $snapshot;
            }
        };
        return [$posts, $writer, $racing];
    }
}
