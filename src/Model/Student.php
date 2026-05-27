<?php
namespace Amea\Model;

class Student
{
    public function __construct(
        private int     $id,
        private string  $nom,
        private string  $prenom,
        private string  $sexe,
        private string  $dateNaissance,
        private string  $lieuResidence,
        private string  $etablissement,
        private string  $statut,
        private string  $domaineEtudes,
        private string  $niveauEtudes,
        private string  $telephone,
        private string  $email,
        private ?int    $anneeArrivee,
        private string  $typeLogement,
        private ?string $precisionLogement,
        private ?string $projetApresFormation,
        private ?string $identite,
        private ?string $nationalites,
        private ?string $cvPath,
        private string  $dateEnregistrement,
        private ?string $dateDiplomation = null,
        private bool    $isLocked = false,
        private bool    $consentPrivacy = false,
        private string  $kycStatus = 'PENDING_CONFIRMATION',
        private ?string $kycNotes = null,
        private ?string $kycUpdatedAt = null,
        private ?string $reviewToken = null
    ) {}

    public function getId(): int                          { return $this->id; }
    public function getNom(): string                      { return $this->nom; }
    public function getPrenom(): string                   { return $this->prenom; }
    public function getFullName(): string                 { return $this->prenom . ' ' . $this->nom; }
    public function getSexe(): string                     { return $this->sexe; }
    public function getDateNaissance(): string            { return $this->dateNaissance; }
    public function getLieuResidence(): string            { return $this->lieuResidence; }
    public function getEtablissement(): string            { return $this->etablissement; }
    public function getStatut(): string                   { return $this->statut; }
    public function getDomaineEtudes(): string            { return $this->domaineEtudes; }
    public function getNiveauEtudes(): string             { return $this->niveauEtudes; }
    public function getTelephone(): string                { return $this->telephone; }
    public function getEmail(): string                    { return $this->email; }
    public function getAnneeArrivee(): ?int               { return $this->anneeArrivee; }
    public function getTypeLogement(): string             { return $this->typeLogement; }
    public function getPrecisionLogement(): ?string       { return $this->precisionLogement; }
    public function getProjetApresFormation(): ?string    { return $this->projetApresFormation; }
    public function getIdentitePath(): ?string            { return $this->identite; }
    public function getCvPath(): ?string                  { return $this->cvPath; }
    public function getDateEnregistrement(): string       { return $this->dateEnregistrement; }
    public function getDateDiplomation(): ?string         { return $this->dateDiplomation; }
    public function isLocked(): bool                      { return $this->isLocked; }
    public function hasConsentPrivacy(): bool             { return $this->consentPrivacy; }
    public function getKycStatus(): string                { return $this->kycStatus; }
    public function getKycNotes(): ?string                { return $this->kycNotes; }
    public function getKycUpdatedAt(): ?string            { return $this->kycUpdatedAt; }
    public function getReviewToken(): ?string             { return $this->reviewToken; }

    public function getNationalites(): array
    {
        if (empty($this->nationalites)) return [];
        $decoded = json_decode($this->nationalites, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isDiplome(): bool
    {
        return $this->statut === 'Diplômé(e)';
    }

    public function getAge(): int
    {
        if (empty($this->dateNaissance)) return 0;
        try {
            $dob  = new \DateTime($this->dateNaissance);
            $now  = new \DateTime();
            return (int)$now->diff($dob)->y;
        } catch (\Exception) {
            return 0;
        }
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id:                   (int)$row['id_personne'],
            nom:                  $row['nom'] ?? '',
            prenom:               $row['prenom'] ?? '',
            sexe:                 $row['sexe'] ?? '',
            dateNaissance:        $row['date_naissance'] ?? '',
            lieuResidence:        $row['lieu_residence'] ?? '',
            etablissement:        $row['etablissement'] ?? '',
            statut:               $row['statut'] ?? '',
            domaineEtudes:        $row['domaine_etudes'] ?? '',
            niveauEtudes:         $row['niveau_etudes'] ?? '',
            telephone:            $row['telephone'] ?? '',
            email:                $row['email'] ?? '',
            anneeArrivee:         isset($row['annee_arrivee']) ? (int)$row['annee_arrivee'] : null,
            typeLogement:         $row['type_logement'] ?? '',
            precisionLogement:    $row['precision_logement'] ?? null,
            projetApresFormation: $row['projet_apres_formation'] ?? null,
            identite:             $row['identite'] ?? null,
            nationalites:         $row['nationalites'] ?? null,
            cvPath:               $row['cv_path'] ?? null,
            dateEnregistrement:   $row['date_enregistrement'] ?? '',
            dateDiplomation:      $row['date_diplomation'] ?? null,
            isLocked:             (bool)($row['is_locked'] ?? false),
            consentPrivacy:       (bool)($row['consent_privacy'] ?? false),
            kycStatus:            (string)($row['kyc_status'] ?? 'PENDING_CONFIRMATION'),
            kycNotes:             $row['kyc_notes'] ?? null,
            kycUpdatedAt:         $row['kyc_updated_at'] ?? null,
            reviewToken:          $row['review_token'] ?? null
        );
    }
}
