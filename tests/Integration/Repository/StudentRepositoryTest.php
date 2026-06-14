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
