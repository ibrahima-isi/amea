<?php

namespace Amea\Tests\Unit\Model;

use Amea\Model\Student;
use PHPUnit\Framework\TestCase;

class StudentTest extends TestCase
{
    private array $row;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->row = [
            'id'                   => 10,
            'last_name'            => 'Barry',
            'first_name'           => 'Fatoumata',
            'gender'               => 'Féminin',
            'birth_date'           => '2000-06-15',
            'residence'            => 'Dakar',
            'institution'          => 'UCAD',
            'status'               => 'ETUDIANT',
            'study_field'          => 'Informatique',
            'study_level'          => 'Master 2',
            'phone'                => '+221770000000',
            'email'                => 'fatoumata@example.com',
            'arrival_year'         => 2020,
            'housing_type'         => 'Cité universitaire',
            'housing_details'      => null,
            'post_training_project' => null,
            'identity_document'    => 'passport.pdf',
            'nationalities'        => json_encode(['Guinéenne']),
            'cv_path'              => null,
            'registration_date'    => '2020-09-01',
            'graduation_date'      => null,
            'is_locked'            => 0,
            'consent_privacy'      => 1,
            'kyc_status'           => 'VALIDATED',
            'kyc_notes'            => null,
            'kyc_updated_at'       => null,
            'review_token'         => null,
        ];
    }

    public function testGettersReturnCorrectValues(): void
    {
        $s = Student::fromRow($this->row);

        $this->assertSame(10, $s->getId());
        $this->assertSame('Barry', $s->getLastName());
        $this->assertSame('Fatoumata', $s->getFirstName());
        $this->assertSame('Fatoumata Barry', $s->getFullName());
        $this->assertSame('Féminin', $s->getGender());
        $this->assertSame('2000-06-15', $s->getBirthDate());
        $this->assertSame('Dakar', $s->getResidence());
        $this->assertSame('UCAD', $s->getInstitution());
        $this->assertSame('ETUDIANT', $s->getStatus());
        $this->assertSame('Informatique', $s->getStudyField());
        $this->assertSame('Master 2', $s->getStudyLevel());
        $this->assertSame('+221770000000', $s->getPhone());
        $this->assertSame('fatoumata@example.com', $s->getEmail());
        $this->assertSame(2020, $s->getArrivalYear());
        $this->assertSame('Cité universitaire', $s->getHousingType());
        $this->assertNull($s->getHousingDetails());
        $this->assertNull($s->getPostTrainingProject());
        $this->assertSame('passport.pdf', $s->getIdentityDocument());
        $this->assertNull($s->getCvPath());
        $this->assertSame('2020-09-01', $s->getRegistrationDate());
        $this->assertNull($s->getGraduationDate());
        $this->assertSame('VALIDATED', $s->getKycStatus());
    }

    public function testIsLockedReturnsFalseByDefault(): void
    {
        $s = Student::fromRow($this->row);
        $this->assertFalse($s->isLocked());
    }

    public function testIsLockedReturnsTrueWhenLocked(): void
    {
        $s = Student::fromRow(array_merge($this->row, ['is_locked' => 1]));
        $this->assertTrue($s->isLocked());
    }

    public function testHasConsentPrivacy(): void
    {
        $s = Student::fromRow($this->row);
        $this->assertTrue($s->hasConsentPrivacy());
    }

    public function testNoConsentPrivacy(): void
    {
        $s = Student::fromRow(array_merge($this->row, ['consent_privacy' => 0]));
        $this->assertFalse($s->hasConsentPrivacy());
    }

    public function testGetNationalitiesDecodesJson(): void
    {
        $s = Student::fromRow($this->row);
        $this->assertSame(['Guinéenne'], $s->getNationalities());
    }

    public function testGetNationalitiesMultiple(): void
    {
        $s = Student::fromRow(array_merge($this->row, [
            'nationalities' => json_encode(['Guinéenne', 'Sénégalaise']),
        ]));
        $this->assertSame(['Guinéenne', 'Sénégalaise'], $s->getNationalities());
    }

    public function testNullNationalitiesReturnEmptyArray(): void
    {
        $s = Student::fromRow(array_merge($this->row, ['nationalities' => null]));
        $this->assertSame([], $s->getNationalities());
    }

    public function testMalformedNationalitiesReturnEmptyArray(): void
    {
        $s = Student::fromRow(array_merge($this->row, ['nationalities' => 'not-json']));
        $this->assertSame([], $s->getNationalities());
    }

    public function testGetAgeReturnsNonNegativeInteger(): void
    {
        $s = Student::fromRow($this->row);
        $age = $s->getAge();
        $this->assertIsInt($age);
        $this->assertGreaterThanOrEqual(0, $age);
    }

    public function testGetAgeReturnsZeroForEmptyBirthDate(): void
    {
        $s = Student::fromRow(array_merge($this->row, ['birth_date' => '']));
        $this->assertSame(0, $s->getAge());
    }

    public function testIdentityDocumentCanBeNull(): void
    {
        $s = Student::fromRow(array_merge($this->row, ['identity_document' => null]));
        $this->assertNull($s->getIdentityDocument());
    }

    public function testIsGraduateReturnsFalseForRegularStatus(): void
    {
        $s = Student::fromRow($this->row);
        $this->assertFalse($s->isGraduate());
    }

    public function testIsGraduateReturnsTrueForGraduateStatus(): void
    {
        $s = Student::fromRow(array_merge($this->row, ['status' => 'Diplômé(e)']));
        $this->assertTrue($s->isGraduate());
    }

    public function testFromRowUsesDefaults(): void
    {
        $s = Student::fromRow([]);
        $this->assertSame(0, $s->getId());
        $this->assertSame('', $s->getLastName());
        $this->assertSame('', $s->getFirstName());
        $this->assertFalse($s->isLocked());
        $this->assertFalse($s->hasConsentPrivacy());
        $this->assertSame('PENDING_CONFIRMATION', $s->getKycStatus());
        $this->assertNull($s->getArrivalYear());
    }
}
