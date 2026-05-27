<?php

namespace Amea\Controller;

use Amea\Repository\StudentRepository;
use Amea\Service\EmailService;
use Amea\Service\StudentService;
use Amea\Core\FileUploader;

class RegistrationController extends BaseController
{
    private StudentRepository $studentRepo;
    private EmailService $emailService;
    private FileUploader $fileUploader;
    private StudentService $studentService;

    public function __construct()
    {
        parent::__construct();
        $db = \Amea\Config\Database::fromEnv()->getConnection();
        $this->studentRepo = new StudentRepository($db);
        
        // Setup EmailService
        $projectRoot = __DIR__ . '/../../';
        $this->emailService = new EmailService(
            $_ENV['MAIL_USER'] ?? '',
            $_ENV['MAIL_PASS'] ?? '',
            'noreply@aeesgs.org',
            'AEESGS Platform',
            $this->view
        );
        $this->fileUploader = new FileUploader($projectRoot);
        $this->studentService = new StudentService($this->studentRepo, $this->fileUploader);
    }

    public function showForm(): void
    {
        $formData = $_SESSION['form_data'] ?? [];
        $errors   = $_SESSION['form_errors'] ?? [];
        unset($_SESSION['form_data'], $_SESSION['form_errors']);

        $this->render('register.html.twig', [
            'formData' => $formData,
            'errors'   => $errors,
            'schools'  => $this->studentRepo->getDistinctEtablissements(),
            'domaines' => $this->studentRepo->getDistinctDomaines(),
            'niveaux'  => $this->studentRepo->getDistinctNiveaux(),
            'locations' => $this->studentRepo->getLocations(),
            'register_active' => true,
        ]);
    }

    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('register.php');
            return;
        }

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->add('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('register.php');
            return;
        }

        $data = $this->studentService->sanitizeAndValidate($_POST, $_FILES);

        if (!empty($data['errors'])) {
            $_SESSION['form_data']   = $data['input'];
            $_SESSION['form_errors'] = $data['errors'];
            $this->redirect('register.php');
            return;
        }

        // Insert into DB
        $dbData = $data['db_data'];
        $dbData['kyc_status'] = 'PENDING_CONFIRMATION';
        $id = $this->studentRepo->save($dbData);

        // Handle auxiliary tables
        if (!empty($dbData['etablissement'])) $this->studentRepo->ensureEtablissementExists($dbData['etablissement']);
        if (!empty($dbData['domaine_etudes'])) $this->studentRepo->ensureDomaineExists($dbData['domaine_etudes']);
        if (!empty($dbData['niveau_etudes']))  $this->studentRepo->ensureNiveauExists($dbData['niveau_etudes']);
        
        if (!empty($data['valid_pays_ids'])) {
            $this->studentRepo->savePersonnePays($id, $data['valid_pays_ids']);
        }

        $_SESSION['registration_student_id'] = $id;
        $this->redirect('registration-details.php');
    }

    public function review(): void
    {
        $id = $_SESSION['registration_student_id'] ?? null;
        if (!$id) {
            $this->redirect('/');
            return;
        }

        $student = $this->studentRepo->findById($id);
        if (!$student) {
            unset($_SESSION['registration_student_id']);
            $this->redirect('/');
            return;
        }

        $this->render('registration-details.html.twig', [
            'student' => $student,
        ]);
    }

    public function confirm(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('registration-details.php');
            return;
        }

        $id = $_SESSION['registration_student_id'] ?? null;
        if (!$id) {
            $this->redirect('/');
            return;
        }

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->add('error', 'La session a expiré.');
            $this->redirect('registration-details.php');
            return;
        }

        // Transition to UNDER_REVIEW
        $this->studentRepo->update($id, [
            'kyc_status' => 'UNDER_REVIEW',
            'is_locked'  => 1,
            'kyc_updated_at' => date('Y-m-d H:i:s')
        ]);

        // Send Email
        $student = $this->studentRepo->findById($id);
        if ($student && $student->getEmail()) {
            $this->emailService->sendFromTemplate(
                $student->getEmail(),
                'Dossier d\'inscription reçu – AEESGS',
                'emails/registration-received.html',
                ['student' => $student]
            );
        }

        unset($_SESSION['registration_student_id']);
        $this->flash->add('success', 'Votre dossier est maintenant en cours de révision. Vous recevrez un email prochainement.');
        $this->redirect('/');
    }
}
