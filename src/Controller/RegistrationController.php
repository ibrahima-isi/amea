<?php

namespace Amea\Controller;

use Amea\Model\Student;
use Amea\Repository\StudentRepository;
use Amea\Service\EmailService;
use Amea\Service\StudentService;
use Amea\Core\FileUploader;

class RegistrationController extends BaseController
{
    /** Uploads are staged here until the registration is confirmed. */
    private const PENDING_DIR = 'uploads/students/pending';
    private const FINAL_PHOTO_DIR = 'uploads/students';
    private const FINAL_CV_DIR    = 'uploads/students/cvs';

    private StudentRepository $studentRepo;
    private EmailService $emailService;
    private FileUploader $fileUploader;
    private StudentService $studentService;

    public function __construct()
    {
        parent::__construct();
        $this->studentRepo = new StudentRepository(\Amea\Core\Container::db());

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
        // Prefill order: a just-failed submit (form_data) wins; otherwise the
        // staged registration the user is coming back to edit (pending_registration).
        $pending  = $_SESSION['pending_registration'] ?? null;
        $formData = $_SESSION['form_data'] ?? ($pending['input'] ?? []);
        $errors   = $_SESSION['form_errors'] ?? [];
        unset($_SESSION['form_data'], $_SESSION['form_errors']);

        $this->render('register.html.twig', [
            'formData' => $formData,
            'errors'   => $errors,
            'schools'  => $this->studentRepo->getDistinctInstitutions(),
            'study_fields' => $this->studentRepo->getDistinctStudyFields(),
            'study_levels'  => $this->studentRepo->getDistinctStudyLevels(),
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

        // Uploads are staged into PENDING_DIR; nothing is written to the database
        // until the user confirms on the review page.
        $data = $this->studentService->sanitizeAndValidate($_POST, $_FILES, null, self::PENDING_DIR);

        if (!empty($data['errors'])) {
            $_SESSION['form_data']   = $data['input'];
            $_SESSION['form_errors'] = $data['errors'];
            $this->redirect('register.php');
            return;
        }

        $dbData = $data['db_data'];

        // Across edits, keep the previously staged file when no new one is uploaded,
        // and delete the old staged file when it is being replaced.
        $previous = $_SESSION['pending_registration']['db_data'] ?? [];
        $dbData['identity_document'] = $this->reconcileStagedFile(
            $dbData['identity_document'], $previous['identity_document'] ?? null
        );
        $dbData['cv_path'] = $this->reconcileStagedFile(
            $dbData['cv_path'], $previous['cv_path'] ?? null
        );

        $_SESSION['pending_registration'] = [
            'input'          => $data['input'],
            'db_data'        => $dbData,
            'valid_pays_ids' => $data['valid_pays_ids'],
        ];

        $this->redirect('registration-details.php');
    }

    public function review(): void
    {
        $pending = $_SESSION['pending_registration'] ?? null;
        if (!$pending || empty($pending['db_data'])) {
            $this->redirect('register.php');
            return;
        }

        // Transient model built from the staged data — not persisted.
        $student = Student::fromRow($pending['db_data']);

        $this->render('registration-details.html.twig', [
            'student' => $student,
        ]);
    }

    /**
     * Preview only files staged by this browser's pending registration.
     * Does not require an account; possession of the active registration session
     * is the authorization boundary.
     */
    public function preview(): void
    {
        $type = (string)($_GET['type'] ?? '');
        if (!in_array($type, ['identite', 'cv'], true)) {
            http_response_code(400);
            return;
        }

        $pending = $_SESSION['pending_registration']['db_data'] ?? [];
        $relative = $type === 'identite'
            ? ($pending['identity_document'] ?? null)
            : ($pending['cv_path'] ?? null);

        if (!is_string($relative) || $relative === '') {
            http_response_code(404);
            return;
        }

        $root = realpath(__DIR__ . '/../../uploads/students/pending');
        $file = realpath(__DIR__ . '/../../' . ltrim($relative, '/'));
        if ($root === false || $file === false
            || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)
            || !is_file($file)) {
            http_response_code(404);
            return;
        }

        $mime = mime_content_type($file);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            http_response_code(415);
            return;
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
    }

    public function confirm(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('registration-details.php');
            return;
        }

        $pending = $_SESSION['pending_registration'] ?? null;
        if (!$pending || empty($pending['db_data'])) {
            $this->redirect('register.php');
            return;
        }

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->add('error', 'La session a expiré.');
            $this->redirect('registration-details.php');
            return;
        }

        $dbData = $pending['db_data'];

        // Final uniqueness guard: another registration may have taken the same
        // email/phone while this one sat on the review page. Send them back to edit.
        if (!empty($dbData['email']) && $this->studentRepo->existsByEmail($dbData['email'])) {
            $_SESSION['form_errors'] = ['email' => 'Cet email est déjà utilisé.'];
            $this->flash->add('error', 'Cet email est déjà utilisé. Veuillez corriger vos informations.');
            $this->redirect('register.php');
            return;
        }
        if (!empty($dbData['phone']) && $this->studentRepo->existsByPhone($dbData['phone'])) {
            $_SESSION['form_errors'] = ['phone' => 'Ce numéro de téléphone est déjà utilisé.'];
            $this->flash->add('error', 'Ce numéro de téléphone est déjà utilisé. Veuillez corriger vos informations.');
            $this->redirect('register.php');
            return;
        }

        // Move staged files to their final home so the pending-uploads cron won't reap them.
        $dbData['identity_document'] = $this->fileUploader->finalize($dbData['identity_document'] ?? null, self::FINAL_PHOTO_DIR);
        $dbData['cv_path']           = $this->fileUploader->finalize($dbData['cv_path'] ?? null, self::FINAL_CV_DIR);

        // Persist now — the dossier goes straight to UNDER_REVIEW and is locked.
        $dbData['kyc_status'] = 'UNDER_REVIEW';
        $id = $this->studentRepo->save($dbData);
        $this->studentRepo->update($id, ['is_locked' => 1, 'kyc_updated_at' => date('Y-m-d H:i:s')]);

        if (!empty($dbData['institution'])) $this->studentRepo->ensureInstitutionExists($dbData['institution']);
        if (!empty($dbData['study_field'])) $this->studentRepo->ensureStudyFieldExists($dbData['study_field']);
        if (!empty($dbData['study_level']))  $this->studentRepo->ensureStudyLevelExists($dbData['study_level']);
        if (!empty($pending['valid_pays_ids'])) {
            $this->studentRepo->saveStudentCountries($id, $pending['valid_pays_ids']);
        }

        $student = $this->studentRepo->findById($id);
        if ($student && $student->getEmail()) {
            $this->emailService->sendFromTemplate(
                $student->getEmail(),
                'Dossier d\'inscription reçu – AEESGS',
                'emails/registration-received.html',
                ['student' => $student]
            );
        }

        unset($_SESSION['pending_registration']);
        $this->flash->add('success', 'Votre dossier est maintenant en cours de révision. Vous recevrez un email prochainement.');
        $this->redirect('/');
    }

    /**
     * Decide which staged file path to keep after a (re)submission:
     * a freshly uploaded file replaces (and deletes) the previously staged one;
     * no new upload keeps whatever was staged before.
     */
    private function reconcileStagedFile(?string $new, ?string $previous): ?string
    {
        if ($new !== null) {
            if ($previous !== null && $previous !== $new) {
                $this->fileUploader->safeDelete($previous);
            }
            return $new;
        }
        return $previous;
    }
}
