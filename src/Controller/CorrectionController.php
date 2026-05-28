<?php

namespace Amea\Controller;

use Amea\Repository\StudentRepository;
use Amea\Service\StudentService;
use Amea\Core\FileUploader;

class CorrectionController extends BaseController
{
    private StudentRepository $studentRepo;
    private FileUploader $fileUploader;
    private StudentService $studentService;

    public function __construct()
    {
        parent::__construct();
        $db = \Amea\Config\Database::fromEnv()->getConnection();
        $this->studentRepo = new StudentRepository($db);
        $this->fileUploader = new FileUploader(__DIR__ . '/../../');
        $this->studentService = new StudentService($this->studentRepo, $this->fileUploader);
    }

    public function edit(string $token): void
    {
        $student = $this->studentRepo->findByToken($token);
        if (!$student || $student->getKycStatus() !== 'NEEDS_CLARIFICATION') {
            $this->flash->add('error', 'Lien de correction invalide ou expiré.');
            $this->redirect('/');
            return;
        }

        $formData = $_SESSION['form_data'] ?? $this->mapStudentToFormData($student);
        $errors   = $_SESSION['form_errors'] ?? [];
        unset($_SESSION['form_data'], $_SESSION['form_errors']);

        $this->render('register.html.twig', [
            'formData' => $formData,
            'errors'   => $errors,
            'schools'  => $this->studentRepo->getDistinctEtablissements(),
            'domaines' => $this->studentRepo->getDistinctDomaines(),
            'niveaux'  => $this->studentRepo->getDistinctNiveaux(),
            'locations' => $this->studentRepo->getLocations(),
            'kyc_notes' => $student->getKycNotes(),
            'is_correction' => true,
            'token' => $token,
        ]);
    }

    public function update(string $token): void
    {
        $student = $this->studentRepo->findByToken($token);
        if (!$student || $student->getKycStatus() !== 'NEEDS_CLARIFICATION') {
            $this->redirect('/');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->redirect('kyc-correction.php?token=' . $token);
            return;
        }

        $data = $this->studentService->sanitizeAndValidate($_POST, $_FILES, $student->getId());

        if (!empty($data['errors'])) {
            $_SESSION['form_data']   = $data['input'];
            $_SESSION['form_errors'] = $data['errors'];
            $this->redirect('kyc-correction.php?token=' . $token);
            return;
        }

        // Update DB
        $dbData = $data['db_data'];
        $dbData['kyc_status'] = 'UNDER_REVIEW'; // Back to review
        $dbData['is_locked']  = 1;
        
        $this->studentRepo->update($student->getId(), $dbData);

        // Handle auxiliary tables
        if (!empty($dbData['etablissement'])) $this->studentRepo->ensureEtablissementExists($dbData['etablissement']);
        if (!empty($dbData['domaine_etudes'])) $this->studentRepo->ensureDomaineExists($dbData['domaine_etudes']);
        if (!empty($dbData['niveau_etudes']))  $this->studentRepo->ensureNiveauExists($dbData['niveau_etudes']);

        $this->flash->add('success', 'Votre dossier a été mis à jour et est à nouveau sous révision.');
        $this->redirect('/');
    }

    private function mapStudentToFormData($student): array
    {
        return [
            'nom' => $student->getNom(),
            'prenom' => $student->getPrenom(),
            'sexe' => $student->getSexe(),
            'date_naissance' => $student->getDateNaissance(),
            'lieu_residence' => $student->getLieuResidence(),
            'etablissement' => $student->getEtablissement(),
            'statut' => $student->getStatut(),
            'domaine_etudes' => $student->getDomaineEtudes(),
            'niveau_etudes' => $student->getNiveauEtudes(),
            'telephone' => $student->getTelephone(),
            'email' => $student->getEmail(),
            'nationalites' => json_encode(array_map(fn($n) => ['value' => $n], $student->getNationalites())),
            'annee_arrivee' => $student->getAnneeArrivee(),
            'type_logement' => $student->getTypeLogement(),
            'precision_logement' => $student->getPrecisionLogement(),
            'projet_apres_formation' => $student->getProjetApresFormation(),
            'consent_privacy' => $student->hasConsentPrivacy() ? 1 : 0
        ];
    }
}
