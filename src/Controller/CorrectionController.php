<?php

namespace Amea\Controller;

use Amea\Repository\StudentRepository;
use Amea\Core\FileUploader;

class CorrectionController extends BaseController
{
    private StudentRepository $studentRepo;
    private FileUploader $fileUploader;

    public function __construct()
    {
        parent::__construct();
        $db = \Amea\Config\Database::fromEnv()->getConnection();
        $this->studentRepo = new StudentRepository($db);
        $this->fileUploader = new FileUploader(__DIR__ . '/../../');
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

        $data = $this->sanitizeAndValidate($student, $_POST, $_FILES);

        if (!empty($data['errors'])) {
            $_SESSION['form_data']   = $data['input'];
            $_SESSION['form_errors'] = $data['errors'];
            $this->redirect('kyc-correction.php?token=' . $token);
            return;
        }

        // Update DB
        $this->studentRepo->update($student->getId(), $data['db_data']);

        // Handle auxiliary tables
        if (!empty($data['db_data']['etablissement'])) $this->studentRepo->ensureEtablissementExists($data['db_data']['etablissement']);
        if (!empty($data['db_data']['domaine_etudes'])) $this->studentRepo->ensureDomaineExists($data['db_data']['domaine_etudes']);
        if (!empty($data['db_data']['niveau_etudes']))  $this->studentRepo->ensureNiveauExists($data['db_data']['niveau_etudes']);

        $this->flash->add('success', 'Votre dossier a été mis à jour et est à nouveau sous révision.');
        $this->redirect('/');
    }

    private function mapStudentToFormData($student): array
    {
        return [
            'nom' => $student->getNom(),
            'prenom' => $student->getPrenom(),
            'numero_identite' => $student->getNumeroIdentite(),
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

    private function sanitizeAndValidate($student, array $post, array $files): array
    {
        // Re-use logic from RegistrationController or extract to a Trait/Service.
        // For brevity in this step, I'll implement a slightly adjusted version.
        
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
        ];

        $finalLieu = $input['lieu_residence'] === 'Autre' ? $input['autre_lieu_residence'] : $input['lieu_residence'];
        $finalEtab = $input['etablissement'] === 'Autre' ? $input['autre_etablissement'] : $input['etablissement'];
        $finalDom  = $input['domaine_etudes'] === 'Autre' ? $input['autre_domaine_etudes'] : $input['domaine_etudes'];
        $finalNiv  = $input['niveau_etudes'] === 'Autre' ? $input['autre_niveau_etudes'] : $input['niveau_etudes'];

        // Required
        if (empty($input['nom'])) $errors['nom'] = 'Le nom est requis.';
        if (empty($input['prenom'])) $errors['prenom'] = 'Le prénom est requis.';
        
        // Uniqueness (excluding self)
        if (!empty($input['email']) && $this->studentRepo->existsByEmail($input['email'], $student->getId())) {
            $errors['email'] = 'Cet email est déjà utilisé.';
        }
        if (!empty($input['numero_identite']) && $this->studentRepo->existsByIdentite($input['numero_identite'], $student->getId())) {
            $errors['numero_identite'] = 'Ce numéro d\'identité est déjà utilisé.';
        }

        // Files
        $identitePath = $student->getIdentitePath();
        if (!empty($files['photo']['name'])) {
            $res = $this->fileUploader->handle($files['photo'], ['jpg','jpeg','png','pdf'], 2*1024*1024, 'uploads/students');
            if ($res['success']) $identitePath = $res['filepath'];
            else $errors['identite'] = $res['message'];
        }

        $cvPath = $student->getCvPath();
        if (!empty($files['cv_file']['name'])) {
            $res = $this->fileUploader->handle($files['cv_file'], ['pdf','png'], 5*1024*1024, 'uploads/students/cvs');
            if ($res['success']) $cvPath = $res['filepath'];
            else $errors['cv'] = $res['message'];
        }

        return [
            'input' => $input,
            'errors' => $errors,
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
                'kyc_status' => 'UNDER_REVIEW', // Back to review
                'is_locked' => 1
            ]
        ];
    }
}
