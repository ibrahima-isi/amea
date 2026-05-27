<?php

namespace Amea\Controller;

use Amea\Repository\StudentRepository;
use Amea\Service\EmailService;
use Amea\Core\FileUploader;

class RegistrationController extends BaseController
{
    private StudentRepository $studentRepo;
    private EmailService $emailService;
    private FileUploader $fileUploader;

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
            new \Amea\Core\TemplateEngine($projectRoot) // We still have EmailService using TemplateEngine for now
        );
        $this->fileUploader = new FileUploader($projectRoot);
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

        $data = $this->sanitizeAndValidate($_POST, $_FILES);

        if (!empty($data['errors'])) {
            $_SESSION['form_data']   = $data['input'];
            $_SESSION['form_errors'] = $data['errors'];
            $this->redirect('register.php');
            return;
        }

        // Insert into DB
        $id = $this->studentRepo->save($data['db_data']);

        // Handle auxiliary tables
        if (!empty($data['db_data']['etablissement'])) $this->studentRepo->ensureEtablissementExists($data['db_data']['etablissement']);
        if (!empty($data['db_data']['domaine_etudes'])) $this->studentRepo->ensureDomaineExists($data['db_data']['domaine_etudes']);
        if (!empty($data['db_data']['niveau_etudes']))  $this->studentRepo->ensureNiveauExists($data['db_data']['niveau_etudes']);
        
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
                'emails/registration-received.html', // Need to create this
                ['student' => $student]
            );
        }

        unset($_SESSION['registration_student_id']);
        $this->flash->add('success', 'Votre dossier est maintenant en cours de révision. Vous recevrez un email prochainement.');
        $this->redirect('/');
    }

    private function sanitizeAndValidate(array $post, array $files): array
    {
        $errors = [];
        $input = [
            'nom' => trim($post['nom'] ?? ''),
            'prenom' => trim($post['prenom'] ?? ''),
            'numero_identite' => trim($post['numero_identite'] ?? ''),
            'sexe' => $post['sexe'] ?? '',
            'date_naissance' => $post['date_naissance'] ?? '',
            'lieu_residence' => trim($post['lieu_residence'] ?? ''),
            'autre_lieu_residence' => trim($post['autre_lieu_residence'] ?? ''),
            'etablissement' => trim($post['etablissement'] ?? ''),
            'autre_etablissement' => trim($post['autre_etablissement'] ?? ''),
            'statut' => $post['statut'] ?? '',
            'domaine_etudes' => trim($post['domaine_etudes'] ?? ''),
            'autre_domaine_etudes' => trim($post['autre_domaine_etudes'] ?? ''),
            'niveau_etudes' => trim($post['niveau_etudes'] ?? ''),
            'autre_niveau_etudes' => trim($post['autre_niveau_etudes'] ?? ''),
            'telephone' => trim($post['telephone'] ?? ''),
            'email' => trim($post['email'] ?? ''),
            'nationalites' => $post['nationalites'] ?? '',
            'annee_arrivee' => $post['annee_arrivee'] ?? null,
            'type_logement' => $post['type_logement'] ?? '',
            'precision_logement' => trim($post['precision_logement'] ?? ''),
            'projet_apres_formation' => trim($post['projet_apres_formation'] ?? ''),
            'consent_privacy' => isset($post['consent_privacy']) ? 1 : 0
        ];

        // 1. Logic for 'Other' fields
        $finalLieu = $input['lieu_residence'] === 'Autre' ? $input['autre_lieu_residence'] : $input['lieu_residence'];
        $finalEtab = $input['etablissement'] === 'Autre' ? $input['autre_etablissement'] : $input['etablissement'];
        $finalDom  = $input['domaine_etudes'] === 'Autre' ? $input['autre_domaine_etudes'] : $input['domaine_etudes'];
        $finalNiv  = $input['niveau_etudes'] === 'Autre' ? $input['autre_niveau_etudes'] : $input['niveau_etudes'];

        // 2. Required fields
        $required = [
            'nom' => 'Le nom est requis.',
            'prenom' => 'Le prénom est requis.',
            'sexe' => 'Le sexe est requis.',
            'date_naissance' => 'La date de naissance est requise.',
            'telephone' => 'Le téléphone est requis.',
            'email' => 'L\'email est requis.',
            'numero_identite' => 'Le numéro d\'identité est requis.',
            'consent_privacy' => 'Vous devez accepter les conditions.'
        ];
        foreach ($required as $f => $m) {
            if (empty($input[$f])) $errors[$f] = $m;
        }

        // 3. Uniqueness
        if (!empty($input['email']) && $this->studentRepo->existsByEmail($input['email'])) {
            $errors['email'] = 'Cet email est déjà utilisé.';
        }
        if (!empty($input['telephone']) && $this->studentRepo->existsByPhone($input['telephone'])) {
            $errors['telephone'] = 'Ce numéro de téléphone est déjà utilisé.';
        }
        if (!empty($input['numero_identite']) && $this->studentRepo->existsByIdentite($input['numero_identite'])) {
            $errors['numero_identite'] = 'Ce numéro d\'identité est déjà utilisé.';
        }

        // 4. Phone validation
        if (!empty($input['telephone']) && !\isValidPhone($input['telephone'])) {
            $errors['telephone'] = 'Numéro invalide (9 chiffres attendus).';
        }

        // 5. Nationalities
        $validNats = [];
        $validIds = [];
        if (!empty($input['nationalites'])) {
            $decoded = json_decode($input['nationalites'], true);
            if (is_array($decoded)) {
                $names = array_map(fn($item) => $item['value'], $decoded);
                $paysRows = $this->studentRepo->getPaysByName($names);
                foreach ($paysRows as $row) {
                    $validNats[] = $row['nom_fr'];
                    $validIds[]  = $row['id_pays'];
                }
            }
        }
        $validNats = array_values(array_unique($validNats));
        
        // Always Guinée
        $guinee = $this->studentRepo->getGuineeCountry();
        if ($guinee && !in_array($guinee['nom_fr'], $validNats, true)) {
             array_unshift($validNats, $guinee['nom_fr']);
             array_unshift($validIds, $guinee['id_pays']);
        }
        if (empty($validNats)) $errors['nationalites'] = 'La nationalité est requise.';
        if (count($validNats) > 5) $errors['nationalites'] = 'Max 5 nationalités.';

        // 6. Age
        $age = null;
        if (!empty($input['date_naissance'])) {
            $age = \calculateAge($input['date_naissance']);
            if ($age < 15) $errors['date_naissance'] = 'L\'âge minimum est 15 ans.';
        }

        // 7. Files
        $identitePath = null;
        if (!empty($files['photo']['name'])) {
            $res = $this->fileUploader->handle($files['photo'], ['jpg','jpeg','png','pdf'], 2*1024*1024, 'uploads/students');
            if ($res['success']) $identitePath = $res['filepath'];
            else $errors['identite'] = $res['message'];
        }

        $cvPath = null;
        if (!empty($files['cv_file']['name'])) {
            $res = $this->fileUploader->handle($files['cv_file'], ['pdf','png'], 5*1024*1024, 'uploads/students/cvs');
            if ($res['success']) $cvPath = $res['filepath'];
            else $errors['cv'] = $res['message'];
        }

        return [
            'input' => $input,
            'errors' => $errors,
            'valid_pays_ids' => $validIds,
            'db_data' => [
                'nom' => $input['nom'],
                'prenom' => $input['prenom'],
                'sexe' => $input['sexe'],
                'date_naissance' => $input['date_naissance'],
                'lieu_residence' => $finalLieu ?: null,
                'etablissement' => $finalEtab ?: null,
                'statut' => $input['statut'] ?: null,
                'domaine_etudes' => $finalDom ?: null,
                'niveau_etudes' => $finalNiv ?: null,
                'telephone' => $input['telephone'],
                'email' => $input['email'],
                'numero_identite' => $input['numero_identite'],
                'annee_arrivee' => $input['annee_arrivee'] ? (int)$input['annee_arrivee'] : null,
                'type_logement' => $input['type_logement'] ?: null,
                'precision_logement' => $input['precision_logement'] ?: null,
                'projet_apres_formation' => $input['projet_apres_formation'] ?: null,
                'identite' => $identitePath,
                'cv_path' => $cvPath,
                'nationalites' => json_encode($validNats, JSON_UNESCAPED_UNICODE),
                'consent_privacy' => $input['consent_privacy'],
                'kyc_status' => 'PENDING_CONFIRMATION'
            ]
        ];
    }
}
