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

    public function edit(): void
    {
        $token = (string)($_GET['token'] ?? '');

        $student = $token !== '' ? $this->studentRepo->findByToken($token) : null;
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

    public function update(): void
    {
        // The correction form posts to kyc-correction.php?token=..., so the
        // token arrives in the query string even on POST.
        $token = (string)($_GET['token'] ?? '');

        $student = $token !== '' ? $this->studentRepo->findByToken($token) : null;
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
        if (!empty($dbData['institution'])) $this->studentRepo->ensureEtablissementExists($dbData['institution']);
        if (!empty($dbData['study_field'])) $this->studentRepo->ensureDomaineExists($dbData['study_field']);
        if (!empty($dbData['study_level']))  $this->studentRepo->ensureNiveauExists($dbData['study_level']);

        $this->flash->add('success', 'Votre dossier a été mis à jour et est à nouveau sous révision.');
        $this->redirect('/');
    }

    private function mapStudentToFormData($student): array
    {
        return [
            'last_name' => $student->getLastName(),
            'first_name' => $student->getFirstName(),
            'gender' => $student->getGender(),
            'birth_date' => $student->getBirthDate(),
            'residence' => $student->getResidence(),
            'institution' => $student->getInstitution(),
            'status' => $student->getStatus(),
            'study_field' => $student->getStudyField(),
            'study_level' => $student->getStudyLevel(),
            'phone' => $student->getPhone(),
            'email' => $student->getEmail(),
            'nationalities' => json_encode(array_map(fn($n) => ['value' => $n], $student->getNationalities())),
            'arrival_year' => $student->getAnneeArrivee(),
            'housing_type' => $student->getHousingType(),
            'housing_details' => $student->getHousingDetails(),
            'post_training_project' => $student->getPostTrainingProject(),
            'consent_privacy' => $student->hasConsentPrivacy() ? 1 : 0
        ];
    }
}
