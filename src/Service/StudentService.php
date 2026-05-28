<?php

namespace Amea\Service;

use Amea\Repository\StudentRepository;
use Amea\Core\FileUploader;

class StudentService
{
    public function __construct(
        private StudentRepository $studentRepo,
        private FileUploader $fileUploader
    ) {}

    public function sanitizeAndValidate(array $post, array $files, ?int $excludeId = null): array
    {
        $errors = [];
        $input = [
            'nom' => trim($post['nom'] ?? ''),
            'prenom' => trim($post['prenom'] ?? ''),
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
            'telephone' => 'Le téléphone est requis.',
            'email' => 'L\'email est requis.',
        ];
        // Only require consent for new registrations (not corrections, where it's already accepted)
        if ($excludeId === null) {
            $required['consent_privacy'] = 'Vous devez accepter les conditions.';
        }

        foreach ($required as $f => $m) {
            if (empty($input[$f])) $errors[$f] = $m;
        }

        // 3. Uniqueness
        if (!empty($input['email']) && $this->studentRepo->existsByEmail($input['email'], $excludeId)) {
            $errors['email'] = 'Cet email est déjà utilisé.';
        }
        if (!empty($input['telephone']) && $this->studentRepo->existsByPhone($input['telephone'], $excludeId)) {
            $errors['telephone'] = 'Ce numéro de téléphone est déjà utilisé.';
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
                'date_naissance' => $input['date_naissance'] ?: null,
                'lieu_residence' => $finalLieu ?: null,
                'etablissement' => $finalEtab ?: null,
                'statut' => $input['statut'] ?: null,
                'domaine_etudes' => $finalDom ?: null,
                'niveau_etudes' => $finalNiv ?: null,
                'telephone' => $input['telephone'],
                'email' => $input['email'],
                'annee_arrivee' => $input['annee_arrivee'] ? (int)$input['annee_arrivee'] : null,
                'type_logement' => $input['type_logement'] ?: null,
                'precision_logement' => $input['precision_logement'] ?: null,
                'projet_apres_formation' => $input['projet_apres_formation'] ?: null,
                'identite' => $identitePath,
                'cv_path' => $cvPath,
                'nationalites' => json_encode($validNats, JSON_UNESCAPED_UNICODE),
                'consent_privacy' => $input['consent_privacy']
            ]
        ];
    }
}
