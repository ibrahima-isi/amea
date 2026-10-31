<?php

namespace Amea\Tests\Integration\Repository;

use Amea\Core\Container;
use Amea\Model\Student;
use Amea\Repository\StudentRepository;
use PHPUnit\Framework\TestCase;

class StudentRepositoryTest extends TestCase
{
    private \PDO $db;
    private StudentRepository $repo;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->db = makeTestDb();
        Container::setDb($this->db);
        $this->repo = new StudentRepository($this->db);
    }

    protected function tearDown(): void
    {
        Container::reset();
    }

    private function makeStudent(array $overrides = []): array
    {
        return array_merge([
            'last_name'       => 'Ndiaye',
            'first_name'      => 'Fatou',
            'gender'          => 'Féminin',
            'phone'           => '771110001',
            'email'           => 'fatou@test.sn',
            'status'          => 'ETUDIANT',
            'nationalities'   => json_encode(['Guinéenne']),
            'consent_privacy' => 1,
        ], $overrides);
    }

    public function testStatisticsRetainFrenchBusinessValuesAndExcludeTrashedMembers(): void
    {
        foreach (['ELEVE', 'ETUDIANT', 'STAGIAIRE', 'Diplômé(e)'] as $index => $status) {
            $this->repo->save($this->makeStudent([
                'status' => $status,
                'gender' => $index % 2 === 0 ? 'Masculin' : 'Féminin',
                'phone' => '77900000' . $index,
                'email' => "stats{$index}@example.invalid",
            ]));
        }
        $trashed = $this->repo->save($this->makeStudent([
            'status' => 'ETUDIANT', 'phone' => '779000009', 'email' => 'trashed@example.invalid',
        ]));
        $this->repo->softDelete($trashed);

        $stats = array_map('intval', $this->repo->getStats());
        $this->assertSame([
            'total' => 4, 'men' => 2, 'women' => 2, 'pupils' => 1,
            'students' => 1, 'interns' => 1, 'graduates' => 1,
        ], $stats);
        $this->assertSame('Diplômé(e)', $this->repo->findAll(['status' => 'Diplômé(e)'])[0]->getStatus());
    }

    public function testSharedLookupEntriesRemainSortedAndReusable(): void
    {
        $this->db->exec('CREATE TABLE study_fields (id INTEGER PRIMARY KEY, name TEXT UNIQUE);
            CREATE TABLE study_levels (id INTEGER PRIMARY KEY, name TEXT UNIQUE);
            CREATE TABLE countries (id INTEGER PRIMARY KEY, name_fr TEXT)');
        foreach (['Zoologie', 'Biologie', 'Biologie'] as $name) {
            $this->repo->ensureInstitutionExists($name);
            $this->repo->ensureStudyFieldExists($name);
            $this->repo->ensureStudyLevelExists($name);
        }
        $this->assertSame(['Biologie', 'Zoologie'], $this->repo->getDistinctInstitutions());
        $this->assertSame(['Biologie', 'Zoologie'], $this->repo->getDistinctStudyFields());
        $this->assertSame(['Biologie', 'Zoologie'], $this->repo->getDistinctStudyLevels());
        $this->db->exec("INSERT INTO countries VALUES (1, 'Guinée'), (2, 'Sénégal')");
        $this->assertSame(['id' => 1, 'name_fr' => 'Guinée'], $this->repo->getGuineaCountry());
        $this->assertSame([['id' => 2, 'name_fr' => 'Sénégal']], $this->repo->getCountriesByName(['Sénégal']));
        $this->assertSame([], $this->repo->getCountriesByName([]));
    }

    public function testInstitutionReportKeepsNamesCountsAndActiveMemberBoundary(): void
    {
        foreach (['Université A', 'Université A', 'Université B'] as $index => $institution) {
            $this->repo->save($this->makeStudent([
                'institution' => $institution, 'phone' => '77800000' . $index,
                'email' => "report{$index}@example.invalid",
            ]));
        }
        $trashed = $this->repo->save($this->makeStudent([
            'institution' => 'Université B', 'phone' => '778000009', 'email' => 'old@example.invalid',
        ]));
        $this->repo->softDelete($trashed);
        $this->assertSame([
            ['institution' => 'Université A', 'count' => 2],
            ['institution' => 'Université B', 'count' => 1],
        ], $this->repo->getTopInstitutions());
    }

    public function testReviewQueueFiltersOrdersAndLimitsActiveDossiers(): void
    {
        $ids = [];
        foreach (['UNDER_REVIEW', 'UNDER_REVIEW', 'UNDER_REVIEW', 'NEEDS_CLARIFICATION', 'APPROVED', 'REJECTED', 'PENDING_CONFIRMATION', 'UNDER_REVIEW'] as $index => $status) {
            $ids[] = $this->repo->save($this->makeStudent([
                'phone' => '7711100' . sprintf('%02d', $index),
                'email' => "queue{$index}@test.sn",
                'kyc_status' => $status,
            ]));
        }
        $dates = ['2026-01-03', '2026-01-01', '2026-01-01'];
        foreach ($ids as $index => $id) {
            $this->db->prepare('UPDATE students SET registration_date = ? WHERE id = ?')
                ->execute([$dates[$index] ?? '2025-01-01', $id]);
        }
        $this->repo->delete($ids[7]);
        $queue = $this->repo->findReviewQueue();
        $this->assertContainsOnlyInstancesOf(Student::class, $queue);
        $this->assertSame([$ids[1], $ids[2], $ids[0]], array_map(fn(Student $student) => $student->getId(), $queue));
        $this->assertSame([$ids[1], $ids[2]], array_map(fn(Student $student) => $student->getId(), $this->repo->findReviewQueue(2)));
        $this->assertSame(3, $this->repo->countAll(['kyc_status' => 'UNDER_REVIEW']));
        $this->assertSame(1, $this->repo->countAll(['kyc_status' => 'NEEDS_CLARIFICATION']));
    }

    public function testReviewQueueClampsLimit(): void
    {
        for ($index = 0; $index < 52; $index++) {
            $this->repo->save($this->makeStudent([
                'phone' => '772' . sprintf('%06d', $index),
                'email' => "limit{$index}@test.sn",
                'kyc_status' => 'UNDER_REVIEW',
            ]));
        }
        $this->assertCount(1, $this->repo->findReviewQueue(0));
        $this->assertCount(1, $this->repo->findReviewQueue(-5));
        $this->assertCount(6, $this->repo->findReviewQueue());
        $this->assertCount(50, $this->repo->findReviewQueue(999));
    }

    public function testSaveAndFindById(): void
    {
        $id = $this->repo->save($this->makeStudent());
        $student = $this->repo->findById($id);

        $this->assertInstanceOf(Student::class, $student);
        $this->assertSame('Ndiaye', $student->getLastName());
        $this->assertSame('Fatou', $student->getFirstName());
        $this->assertSame('Féminin', $student->getGender());
        $this->assertSame('ETUDIANT', $student->getStatus());
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(9999));
    }

    public function testSaveAssignsDefaultKycStatus(): void
    {
        $id = $this->repo->save($this->makeStudent());
        $student = $this->repo->findById($id);
        $this->assertSame('PENDING_CONFIRMATION', $student->getKycStatus());
    }

    public function testFindAll(): void
    {
        $this->repo->save($this->makeStudent());
        $this->repo->save($this->makeStudent(['phone' => '771110002', 'email' => 'b@test.sn']));

        $all = $this->repo->findAll();
        $this->assertCount(2, $all);
        $this->assertContainsOnlyInstancesOf(Student::class, $all);
    }

    public function testFindAllWithFilters(): void
    {
        $this->repo->save($this->makeStudent(['status' => 'ETUDIANT']));
        $this->repo->save($this->makeStudent([
            'phone'  => '771110002',
            'email'  => 'b@test.sn',
            'status' => 'STAGIAIRE',
        ]));

        $filtered = $this->repo->findAll(['status' => 'ETUDIANT']);
        $this->assertCount(1, $filtered);
        $this->assertSame('ETUDIANT', $filtered[0]->getStatus());
    }

    public function testFindPaginated(): void
    {
        $this->repo->save($this->makeStudent());
        $this->repo->save($this->makeStudent(['phone' => '771110002', 'email' => 'b@test.sn']));

        $result = $this->repo->findPaginated(1, 1);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(1, $result['items']);
        $this->assertSame(2, $result['total']);
        $this->assertInstanceOf(Student::class, $result['items'][0]);
    }

    public function testFindPaginatedPage2(): void
    {
        $this->repo->save($this->makeStudent());
        $this->repo->save($this->makeStudent(['phone' => '771110002', 'email' => 'b@test.sn']));
        $this->repo->save($this->makeStudent(['phone' => '771110003', 'email' => 'c@test.sn']));

        $result = $this->repo->findPaginated(2, 2);
        $this->assertSame(3, $result['total']);
        $this->assertCount(1, $result['items']);
    }

    public function testUpdate(): void
    {
        $id = $this->repo->save($this->makeStudent());
        // update() accepts partial arrays — only columns in UPDATABLE_COLUMNS
        $this->repo->update($id, ['status' => 'STAGIAIRE']);
        $this->assertSame('STAGIAIRE', $this->repo->findById($id)->getStatus());
    }

    public function testUpdateKycStatus(): void
    {
        $id = $this->repo->save($this->makeStudent());
        $this->repo->update($id, ['kyc_status' => 'APPROVED']);
        $this->assertSame('APPROVED', $this->repo->findById($id)->getKycStatus());
    }

    public function testUpdateWithInvalidColumnThrows(): void
    {
        $id = $this->repo->save($this->makeStudent());
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->update($id, ['non_existent_column' => 'value']);
    }

    public function testDelete(): void
    {
        $id = $this->repo->save($this->makeStudent());
        $this->assertTrue($this->repo->delete($id));
        $this->assertNull($this->repo->findById($id));
    }

    public function testDeleteNonExistentReturnsFalseOrTrue(): void
    {
        // PDO execute() on no-match DELETE still returns true; just ensure no exception
        $result = $this->repo->delete(9999);
        $this->assertIsBool($result);
    }

    public function testExistsByEmail(): void
    {
        $id = $this->repo->save($this->makeStudent());
        $this->assertTrue($this->repo->existsByEmail('fatou@test.sn'));
        // Excluding the same id should return false (no other row has this email)
        $this->assertFalse($this->repo->existsByEmail('fatou@test.sn', $id));
    }

    public function testExistsByEmailReturnsFalseForUnknown(): void
    {
        $this->assertFalse($this->repo->existsByEmail('ghost@test.sn'));
    }

    public function testExistsByPhone(): void
    {
        $id = $this->repo->save($this->makeStudent());
        $this->assertTrue($this->repo->existsByPhone('771110001'));
        $this->assertFalse($this->repo->existsByPhone('771110001', $id));
    }

    public function testCountAll(): void
    {
        $this->assertSame(0, $this->repo->countAll());
        $this->repo->save($this->makeStudent());
        $this->assertSame(1, $this->repo->countAll());
        $this->repo->save($this->makeStudent(['phone' => '771110002', 'email' => 'b@test.sn']));
        $this->assertSame(2, $this->repo->countAll());
    }

    public function testFindByToken(): void
    {
        $id = $this->repo->save($this->makeStudent(['review_token' => 'abc123token']));
        $student = $this->repo->findByToken('abc123token');
        $this->assertInstanceOf(\Amea\Model\Student::class, $student);
        $this->assertSame($id, $student->getId());
    }

    public function testFindByTokenReturnsNullForWrongToken(): void
    {
        $this->repo->save($this->makeStudent(['review_token' => 'abc123token']));
        $this->assertNull($this->repo->findByToken('wrongtoken'));
    }

    public function testFindByTokenReturnsNullForEmptyToken(): void
    {
        $this->repo->save($this->makeStudent(['review_token' => 'abc123token']));
        $this->assertNull($this->repo->findByToken(''));
    }
}
