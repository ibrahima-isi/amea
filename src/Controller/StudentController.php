<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Core\FileUploader;
use Amea\Repository\StudentRepository;
use Amea\Service\EmailService;
use Amea\Service\StudentService;

class StudentController extends BaseController
{
    private StudentRepository $studentRepo;
    private StudentService $studentService;
    private \Amea\Core\FileUploader $uploader;
    private EmailService $emailService;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $db = Container::db();
        $this->studentRepo = new StudentRepository($db);
        $this->uploader = new FileUploader(__DIR__ . '/../../');
        $this->studentService = new StudentService(
            $this->studentRepo,
            $this->uploader
        );
        $this->emailService = new EmailService(
            $_ENV['MAIL_USER'] ?? '',
            $_ENV['MAIL_PASS'] ?? '',
            'noreply@aeesgs.org',
            'AEESGS Platform',
            $this->view
        );
    }

    public function index(): void
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['perPage'] ?? 25)));
        $filters = [];

        foreach (['search', 'gender', 'status', 'institution', 'study_level'] as $key) {
            $val = trim($_GET[$key] ?? '');
            if ($val !== '') {
                $filters[$key] = $val;
            }
        }

        $result = $this->studentRepo->findPaginated($page, $perPage, $filters);
        $total  = $result['total'];

        $this->render('admin/pages/students.html.twig', [
            'students'     => $result['items'],
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => (int) ceil(max(1, $total) / $perPage),
            'filters'      => $filters,
            'institutions' => $this->studentRepo->getDistinctEtablissements(),
            'niveaux'      => $this->studentRepo->getDistinctNiveaux(),
        ]);
    }

    public function show(): void
    {
        $id      = (int)($_GET['id'] ?? 0);
        $student = $this->studentRepo->findById($id);
        if (!$student) {
            $this->flash->add('error', 'Membre introuvable.');
            $this->redirect('students.php');
            return;
        }
        $this->render('admin/pages/student-details.html.twig', ['student' => $student]);
    }

    public function edit(): void
    {
        $this->requireAdmin();
        $id      = (int)($_GET['id'] ?? 0);
        $student = $this->studentRepo->findById($id);
        if (!$student) {
            $this->flash->add('error', 'Membre introuvable.');
            $this->redirect('students.php');
            return;
        }

        // Build formData array from student model for pre-filling the form
        // Detect "Autre" values for dropdowns
        $schools   = $this->studentRepo->getDistinctEtablissements();
        $domaines  = $this->studentRepo->getDistinctDomaines();
        $niveaux   = $this->studentRepo->getDistinctNiveaux();
        $locations = $this->studentRepo->getLocations();
        $allLocNames = array_merge(...array_values($locations));

        $institutionVal = $student->getInstitution();
        $otherInstitution = '';
        if ($institutionVal !== '' && !in_array($institutionVal, $schools, true)) {
            $otherInstitution = $institutionVal;
            $institutionVal   = 'Autre';
        }

        $studyFieldVal = $student->getStudyField();
        $otherStudyField = '';
        if ($studyFieldVal !== '' && !in_array($studyFieldVal, $domaines, true)) {
            $otherStudyField = $studyFieldVal;
            $studyFieldVal   = 'Autre';
        }

        $studyLevelVal = $student->getStudyLevel();
        $otherStudyLevel = '';
        if ($studyLevelVal !== '' && !in_array($studyLevelVal, $niveaux, true)) {
            $otherStudyLevel = $studyLevelVal;
            $studyLevelVal   = 'Autre';
        }

        $residenceVal = $student->getResidence();
        $otherResidence = '';
        if ($residenceVal !== '' && !in_array($residenceVal, $allLocNames, true)) {
            $otherResidence = $residenceVal;
            $residenceVal   = 'Autre';
        }

        // Nationalities: stored as JSON array of strings in the model
        $nats = $student->getNationalities();
        $nationalitiesValue = !empty($nats) ? json_encode($nats, JSON_UNESCAPED_UNICODE) : '';

        $formData = [
            'last_name'           => $student->getLastName(),
            'first_name'          => $student->getFirstName(),
            'gender'              => $student->getGender(),
            'birth_date'          => $student->getBirthDate(),
            'status'              => $student->getStatus(),
            'phone'               => $student->getPhone(),
            'email'               => $student->getEmail(),
            'residence'           => $residenceVal,
            'other_residence'     => $otherResidence,
            'institution'         => $institutionVal,
            'other_institution'   => $otherInstitution,
            'study_field'         => $studyFieldVal,
            'other_study_field'   => $otherStudyField,
            'study_level'         => $studyLevelVal,
            'other_study_level'   => $otherStudyLevel,
            'arrival_year'        => $student->getArrivalYear(),
            'housing_type'        => $student->getHousingType(),
            'housing_details'     => $student->getHousingDetails(),
            'post_training_project' => $student->getPostTrainingProject(),
            'nationalities'       => $nationalitiesValue,
            'graduation_date'     => $student->getGraduationDate(),
            'cv_path'             => $student->getCvPath(),
        ];

        // Merge with any previously flashed form data (after validation failure)
        $errors   = $_SESSION['form_errors'] ?? [];
        $flashData = $_SESSION['form_data'] ?? [];
        if (!empty($flashData)) {
            $formData = array_merge($formData, $flashData);
        }
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        $this->render('admin/pages/edit-student.html.twig', [
            'student'   => $student,
            'formData'  => $formData,
            'schools'   => $schools,
            'domaines'  => $domaines,
            'niveaux'   => $niveaux,
            'locations' => $locations,
            'errors'    => $errors,
        ]);
    }

    public function update(): void
    {
        $this->requireAdmin();
        $id = (int)($_GET['id'] ?? 0);

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->add('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('edit-student.php?id=' . $id);
            return;
        }

        $student = $this->studentRepo->findById($id);
        if (!$student) {
            $this->flash->add('error', 'Membre introuvable.');
            $this->redirect('students.php');
            return;
        }

        // sanitizeAndValidate returns ['input', 'errors', 'valid_pays_ids', 'db_data']
        $result = $this->studentService->sanitizeAndValidate($_POST, $_FILES, $id);

        if (!empty($result['errors'])) {
            $_SESSION['form_errors'] = $result['errors'];
            $_SESSION['form_data']   = $_POST;
            $this->redirect('edit-student.php?id=' . $id);
            return;
        }

        $dbData = $result['db_data'];

        // File handling: keep existing paths if no new file was uploaded
        if ($dbData['identity_document'] === null) {
            $dbData['identity_document'] = $student->getIdentityDocument();
        } else {
            // New file uploaded — delete old one
            if ($student->getIdentityDocument()) {
                $this->uploader->safeDelete($student->getIdentityDocument());
            }
        }

        if ($dbData['cv_path'] === null) {
            $dbData['cv_path'] = $student->getCvPath();
        } else {
            // New CV uploaded — delete old one
            if ($student->getCvPath()) {
                $this->uploader->safeDelete($student->getCvPath());
            }
        }

        // graduation_date logic: set to today if status is GRADUATE and no date given; null otherwise
        $finalGraduationDate = null;
        if ($dbData['status'] === 'GRADUATE' || $dbData['status'] === 'Diplômé') {
            $rawDate = trim($_POST['graduation_date'] ?? '');
            $validatedDate = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) ? $rawDate : null);
            $finalGraduationDate = $validatedDate ?? date('Y-m-d');
        }
        $dbData['graduation_date'] = $finalGraduationDate;

        // Ensure new institutions/fields/levels are added to lookup tables
        if (!empty($dbData['institution'])) {
            $this->studentRepo->ensureEtablissementExists($dbData['institution']);
        }
        if (!empty($dbData['study_field'])) {
            $this->studentRepo->ensureDomaineExists($dbData['study_field']);
        }
        if (!empty($dbData['study_level'])) {
            $this->studentRepo->ensureNiveauExists($dbData['study_level']);
        }

        $this->studentRepo->update($id, $dbData);

        // Update nationality pivot table
        $db = Container::db();
        $db->prepare("DELETE FROM student_country WHERE student_id = ?")->execute([$id]);
        if (!empty($result['valid_pays_ids'])) {
            $stmtPivot = $db->prepare("INSERT IGNORE INTO student_country (student_id, country_id) VALUES (?, ?)");
            foreach ($result['valid_pays_ids'] as $pid) {
                $stmtPivot->execute([$id, $pid]);
            }
        }

        // Send notification email (best-effort, non-blocking)
        $emailData = $result['db_data'];
        $this->sendUpdateNotification($emailData, $id);

        $this->flash->add('success', 'Membre mis à jour avec succès.');
        $this->redirect('student-details.php?id=' . $id);
    }

    public function delete(): void
    {
        $this->requireAdmin();

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->add('error', 'Session expirée.');
            $this->redirect('students.php');
            return;
        }

        $id      = (int)($_POST['id'] ?? 0);
        $student = $this->studentRepo->findById($id);
        if ($student) {
            if ($student->getIdentityDocument()) {
                $this->uploader->safeDelete($student->getIdentityDocument());
            }
            if ($student->getCvPath()) {
                $this->uploader->safeDelete($student->getCvPath());
            }
            $this->studentRepo->delete($id);
            $this->flash->add('success', 'Membre supprimé.');
        }

        $this->redirect('students.php');
    }

    private function sendUpdateNotification(array $data, int $studentId): void
    {
        if (empty($data['email'])) {
            return;
        }
        try {
            $templatePath = __DIR__ . '/../../templates/emails/admin-update-notification.html';
            if (!is_file($templatePath)) {
                return;
            }
            $this->emailService->sendFromTemplate(
                $data['email'],
                'Votre dossier a été mis à jour – AEESGS',
                $templatePath,
                [
                    'id'           => $studentId,
                    'first_name'   => $data['first_name']   ?? '',
                    'last_name'    => $data['last_name']    ?? '',
                    'email'        => $data['email']        ?? '',
                    'phone'        => $data['phone']        ?? '',
                    'status'       => $data['status']       ?? '',
                    'institution'  => $data['institution']  ?? '',
                    'study_field'  => $data['study_field']  ?? '',
                    'study_level'  => $data['study_level']  ?? '',
                    'residence'    => $data['residence']    ?? '',
                    'housing_type' => $data['housing_type'] ?? '',
                    'update_date'  => date('d/m/Y à H:i'),
                ]
            );
        } catch (\Throwable) {
            // Non-critical — log silently
        }
    }
}
