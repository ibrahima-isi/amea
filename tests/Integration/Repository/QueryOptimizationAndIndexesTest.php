<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Repository;

use Amea\Core\Container;
use Amea\Core\View;
use Amea\Repository\FileOperationRepository;
use Amea\Repository\JournalRepository;
use Amea\Repository\SettingRepository;
use Amea\Repository\StudentRepository;
use Amea\Service\ExportService;
use PHPUnit\Framework\TestCase;

final class QueryOptimizationAndIndexesTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->db = makeTestDb();
        Container::setDb($this->db);
    }

    protected function tearDown(): void
    {
        Container::reset();
    }

    public function testDashboardSummaryAndConsentStatsMatchIndividualMetrics(): void
    {
        $repo = new StudentRepository($this->db);
        $recent = (new \DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s');
        $twoWeeksAgo = (new \DateTimeImmutable('-14 days'))->format('Y-m-d H:i:s');
        $old = (new \DateTimeImmutable('-60 days'))->format('Y-m-d H:i:s');

        $rows = [
            ['gender' => 'Masculin', 'status' => 'ETUDIANT', 'kyc_status' => 'UNDER_REVIEW', 'consent_privacy' => 1, 'date' => $recent],
            ['gender' => 'Féminin', 'status' => 'ELEVE', 'kyc_status' => 'NEEDS_CLARIFICATION', 'consent_privacy' => 0, 'date' => $twoWeeksAgo],
            ['gender' => 'Féminin', 'status' => 'STAGIAIRE', 'kyc_status' => 'APPROVED', 'consent_privacy' => 1, 'date' => $old],
            ['gender' => 'Masculin', 'status' => 'Diplômé(e)', 'kyc_status' => 'UNDER_REVIEW', 'consent_privacy' => 1, 'date' => $recent],
        ];
        foreach ($rows as $i => $row) {
            $id = $repo->save([
                'last_name' => 'Test',
                'first_name' => 'Member' . $i,
                'gender' => $row['gender'],
                'status' => $row['status'],
                'phone' => '77400000' . $i,
                'email' => "opt{$i}@example.invalid",
                'kyc_status' => $row['kyc_status'],
                'consent_privacy' => $row['consent_privacy'],
            ]);
            $this->db->prepare('UPDATE students SET registration_date = ? WHERE id = ?')->execute([$row['date'], $id]);
        }

        // Trashed student must be excluded from every metric.
        $trashedId = $repo->save([
            'last_name' => 'Trashed',
            'first_name' => 'Row',
            'gender' => 'Masculin',
            'status' => 'ETUDIANT',
            'phone' => '774000099',
            'email' => 'trashed-opt@example.invalid',
            'kyc_status' => 'UNDER_REVIEW',
            'consent_privacy' => 1,
        ]);
        $repo->softDelete($trashedId);

        $summary = $repo->getDashboardSummary();
        self::assertSame(array_map('intval', $repo->getStats()), $summary['stats']);
        self::assertSame(2, $summary['recent_week']);
        self::assertSame($repo->getRecentCount(7), $summary['recent_week']);
        self::assertSame(3, $summary['recent_month']);
        self::assertSame($repo->getRecentCount(30), $summary['recent_month']);
        self::assertSame(2, $summary['review_count']);
        self::assertSame(1, $summary['clarification_count']);

        $consent = $repo->getConsentStats();
        self::assertSame(['total' => 4, 'consented' => 3, 'not_consented' => 1], $consent);
    }

    public function testSettingsAndLookupCachingInvalidatesImmediatelyOnWrite(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->upsert('contact_email', 'initial@aeesgs.org');
        $settings->upsert('association_name', 'AEESGS');

        $map = $settings->getAllMap();
        self::assertSame('initial@aeesgs.org', $map['contact_email']);
        self::assertSame('AEESGS', $settings->getValue('association_name'));

        $settings->upsert('contact_email', 'updated@aeesgs.org');
        self::assertSame('updated@aeesgs.org', $settings->getValue('contact_email'));

        $students = new StudentRepository($this->db);
        $students->ensureInstitutionExists('UCAD');
        self::assertSame(['UCAD'], $students->getDistinctInstitutions());
        $students->ensureInstitutionExists('ESP');
        self::assertSame(['ESP', 'UCAD'], $students->getDistinctInstitutions());
    }

    public function testSqliteQueryPlannerUsesPerformanceIndexes(): void
    {
        $plans = [
            'idx_students_active_kyc_reg' => "EXPLAIN QUERY PLAN SELECT * FROM students WHERE deleted_at IS NULL AND kyc_status = 'UNDER_REVIEW' ORDER BY registration_date ASC, id ASC LIMIT 6",
            'idx_students_active_reg_stats' => "EXPLAIN QUERY PLAN SELECT COUNT(*), SUM(CASE WHEN consent_privacy = 1 THEN 1 ELSE 0 END) FROM students WHERE deleted_at IS NULL",
            'idx_students_identity_document' => "EXPLAIN QUERY PLAN SELECT 1 FROM students WHERE identity_document = 'uploads/students/id.pdf' LIMIT 1",
            'idx_students_cv_path' => "EXPLAIN QUERY PLAN SELECT 1 FROM students WHERE cv_path = 'uploads/students/cvs/cv.pdf' LIMIT 1",
            'idx_slider_images_image_path' => "EXPLAIN QUERY PLAN SELECT * FROM slider_images WHERE image_path = 'uploads/slider/a.jpg' ORDER BY id LIMIT 1",
            'idx_journal_type_publication' => "EXPLAIN QUERY PLAN SELECT * FROM journal_posts WHERE status = 'PUBLISHED' AND type = 'REPORT' ORDER BY published_at DESC, id DESC LIMIT 12",
            'idx_journal_cover_path' => "EXPLAIN QUERY PLAN SELECT 1 FROM journal_posts WHERE cover_path = 'uploads/journal/covers/a.jpg' LIMIT 1",
        ];

        foreach ($plans as $expectedIndex => $sql) {
            $details = implode(' | ', array_column($this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC), 'detail'));
            self::assertStringContainsString($expectedIndex, $details, "Expected SQLite plan to use {$expectedIndex}: {$details}");
        }
    }

    public function testFileOperationReferencedChecksAllIndexedPathsAndExcludesStudent(): void
    {
        $students = new StudentRepository($this->db);
        $id1 = $students->save([
            'last_name' => 'A', 'first_name' => 'B', 'gender' => 'Masculin', 'status' => 'ETUDIANT',
            'phone' => '775000001', 'email' => 'ref1@example.invalid', 'consent_privacy' => 1,
            'identity_document' => 'uploads/students/id-1.pdf', 'cv_path' => 'uploads/students/cvs/cv-1.pdf',
        ]);
        $ops = new FileOperationRepository($this->db);

        self::assertTrue($ops->referenced('uploads/students/id-1.pdf'));
        self::assertTrue($ops->referenced('uploads/students/cvs/cv-1.pdf'));
        self::assertFalse($ops->referenced('uploads/students/id-1.pdf', $id1));
        self::assertFalse($ops->referenced('uploads/students/cvs/cv-1.pdf', $id1));
        self::assertFalse($ops->referenced('uploads/students/missing.pdf'));
    }

    public function testFindRecentPublishedCachesAndInvalidatesOnInsertAndUpdate(): void
    {
        $journal = new JournalRepository($this->db);
        $ids = [];
        for ($i = 1; $i <= 4; $i++) {
            $ids[$i] = $journal->insert([
                'type' => 'ANNOUNCEMENT',
                'status' => $i === 4 ? 'DRAFT' : 'PUBLISHED',
                'slug' => "post-{$i}",
                'title' => "Post {$i}",
                'summary' => 'Summary',
                'body' => 'Body',
                'published_at' => sprintf('2026-11-0%d 10:00:00', $i),
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => sprintf('2026-11-0%d 10:00:00', $i),
                'updated_at' => sprintf('2026-11-0%d 10:00:00', $i),
            ]);
        }

        $recent = $journal->findRecentPublished(2);
        self::assertCount(2, $recent);
        self::assertSame(['post-3', 'post-2'], array_column($recent, 'slug'));

        // Updating post-4 to PUBLISHED must immediately invalidate the journal AppCache namespace.
        self::assertTrue($journal->update($ids[4], [
            'status' => 'PUBLISHED',
            'published_at' => '2026-11-05 10:00:00',
            'updated_at' => '2026-11-05 10:00:00',
        ], 1));
        self::assertSame(['post-4', 'post-3'], array_column($journal->findRecentPublished(2), 'slug'));
    }

    public function testExportServiceExcludesTrashedMembersAndUsesActiveIndex(): void
    {
        $repo = new StudentRepository($this->db);
        $activeId = $repo->save([
            'last_name' => 'Active', 'first_name' => 'Member', 'gender' => 'Masculin',
            'status' => 'ETUDIANT', 'institution' => 'UCAD', 'phone' => '776000001',
            'email' => 'export-active@example.invalid', 'consent_privacy' => 1,
        ]);
        $trashedId = $repo->save([
            'last_name' => 'Trashed', 'first_name' => 'Member', 'gender' => 'Féminin',
            'status' => 'ETUDIANT', 'institution' => 'UCAD', 'phone' => '776000002',
            'email' => 'export-trashed@example.invalid', 'consent_privacy' => 1,
        ]);
        self::assertGreaterThan(0, $activeId);
        self::assertTrue($repo->softDelete($trashedId));

        $export = new ExportService($this->db);
        $result = $export->getExportData(
            ['last_name', 'email'],
            ['last_name' => 'last_name', 'email' => 'email'],
            ['institution' => 'UCAD']
        );
        self::assertSame([['Active', 'export-active@example.invalid']], $result['rows']);

        $plan = implode(' | ', array_column(
            $this->db->query("EXPLAIN QUERY PLAN SELECT last_name FROM students WHERE deleted_at IS NULL AND institution = 'UCAD' ORDER BY registration_date DESC")->fetchAll(\PDO::FETCH_ASSOC),
            'detail'
        ));
        self::assertStringContainsString('USING INDEX idx_students_active_', $plan);
        self::assertStringNotContainsString('SCAN students', $plan);
    }

    public function testViewDisablesAutoReloadAndScopesCachePerReleaseInProduction(): void
    {
        $saved = $_ENV['APP_ENV'] ?? null;
        $savedGet = getenv('APP_ENV');
        try {
            $_ENV['APP_ENV'] = 'production';
            putenv('APP_ENV=production');
            $prodView = new View(dirname(__DIR__, 3));
            self::assertFalse($prodView->getTwig()->isAutoReload());
            $cachePath = $prodView->getTwig()->getCache(true);
            self::assertIsString($cachePath);
            self::assertMatchesRegularExpression('#/twig/[a-f0-9]{12}$#', $cachePath);

            $_ENV['APP_ENV'] = 'test';
            putenv('APP_ENV=test');
            $testView = new View(dirname(__DIR__, 3));
            self::assertTrue($testView->getTwig()->isAutoReload());
        } finally {
            if ($saved === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $saved;
            }
            putenv($savedGet === false ? 'APP_ENV' : 'APP_ENV=' . $savedGet);
        }
    }
}
