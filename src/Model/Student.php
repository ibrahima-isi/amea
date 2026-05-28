<?php
namespace Amea\Model;

class Student
{
    public function __construct(
        private int     $id,
        private string  $lastName,
        private string  $firstName,
        private string  $gender,
        private string  $birthDate,
        private string  $residence,
        private string  $institution,
        private string  $status,
        private string  $studyField,
        private string  $studyLevel,
        private string  $phone,
        private string  $email,
        private ?int    $arrivalYear,
        private string  $housingType,
        private ?string $housingDetails,
        private ?string $postTrainingProject,
        private ?string $identityDocument,
        private ?string $nationalities,
        private ?string $cvPath,
        private string  $registrationDate,
        private ?string $graduationDate = null,
        private bool    $isLocked = false,
        private bool    $consentPrivacy = false,
        private string  $kycStatus = 'PENDING_CONFIRMATION',
        private ?string $kycNotes = null,
        private ?string $kycUpdatedAt = null,
        private ?string $reviewToken = null
    ) {}

    public function getId(): int                          { return $this->id; }
    public function getLastName(): string                 { return $this->lastName; }
    public function getFirstName(): string                { return $this->firstName; }
    public function getFullName(): string                 { return $this->firstName . ' ' . $this->lastName; }
    public function getGender(): string                   { return $this->gender; }
    public function getBirthDate(): string                { return $this->birthDate; }
    public function getResidence(): string                { return $this->residence; }
    public function getInstitution(): string              { return $this->institution; }
    public function getStatus(): string                   { return $this->status; }
    public function getStudyField(): string               { return $this->studyField; }
    public function getStudyLevel(): string               { return $this->studyLevel; }
    public function getPhone(): string                    { return $this->phone; }
    public function getEmail(): string                    { return $this->email; }
    public function getArrivalYear(): ?int                { return $this->arrivalYear; }
    public function getHousingType(): string              { return $this->housingType; }
    public function getHousingDetails(): ?string          { return $this->housingDetails; }
    public function getPostTrainingProject(): ?string     { return $this->postTrainingProject; }
    public function getIdentityDocument(): ?string        { return $this->identityDocument; }
    public function getCvPath(): ?string                  { return $this->cvPath; }
    public function getRegistrationDate(): string         { return $this->registrationDate; }
    public function getGraduationDate(): ?string          { return $this->graduationDate; }
    public function isLocked(): bool                      { return $this->isLocked; }
    public function hasConsentPrivacy(): bool             { return $this->consentPrivacy; }
    public function getKycStatus(): string                { return $this->kycStatus; }
    public function getKycNotes(): ?string                { return $this->kycNotes; }
    public function getKycUpdatedAt(): ?string            { return $this->kycUpdatedAt; }
    public function getReviewToken(): ?string             { return $this->reviewToken; }

    public function getNationalities(): array
    {
        if (empty($this->nationalities)) return [];
        $decoded = json_decode($this->nationalities, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isDiplome(): bool
    {
        return $this->status === 'Diplômé(e)';
    }

    public function getAge(): int
    {
        if (empty($this->birthDate)) return 0;
        try {
            $dob  = new \DateTime($this->birthDate);
            $now  = new \DateTime();
            return (int)$now->diff($dob)->y;
        } catch (\Exception) {
            return 0;
        }
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id:                   (int)($row['id'] ?? 0),
            lastName:             $row['last_name'] ?? '',
            firstName:            $row['first_name'] ?? '',
            gender:               $row['gender'] ?? '',
            birthDate:            $row['birth_date'] ?? '',
            residence:            $row['residence'] ?? '',
            institution:          $row['institution'] ?? '',
            status:               $row['status'] ?? '',
            studyField:           $row['study_field'] ?? '',
            studyLevel:           $row['study_level'] ?? '',
            phone:                $row['phone'] ?? '',
            email:                $row['email'] ?? '',
            arrivalYear:          isset($row['arrival_year']) ? (int)$row['arrival_year'] : null,
            housingType:          $row['housing_type'] ?? '',
            housingDetails:       $row['housing_details'] ?? null,
            postTrainingProject:  $row['post_training_project'] ?? null,
            identityDocument:     $row['identity_document'] ?? null,
            nationalities:        $row['nationalities'] ?? null,
            cvPath:               $row['cv_path'] ?? null,
            registrationDate:     $row['registration_date'] ?? '',
            graduationDate:       $row['graduation_date'] ?? null,
            isLocked:             (bool)($row['is_locked'] ?? false),
            consentPrivacy:       (bool)($row['consent_privacy'] ?? false),
            kycStatus:            (string)($row['kyc_status'] ?? 'PENDING_CONFIRMATION'),
            kycNotes:             $row['kyc_notes'] ?? null,
            kycUpdatedAt:         $row['kyc_updated_at'] ?? null,
            reviewToken:          $row['review_token'] ?? null
        );
    }
}
