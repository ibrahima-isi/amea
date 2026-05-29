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
            'last_name' => trim($post['last_name'] ?? ''),
            'first_name' => trim($post['first_name'] ?? ''),
            'gender' => $post['gender'] ?? '',
            'birth_date' => $post['birth_date'] ?? '',
            'residence' => trim($post['residence'] ?? ''),
            'other_residence' => trim($post['other_residence'] ?? ''),
            'institution' => trim($post['institution'] ?? ''),
            'other_institution' => trim($post['other_institution'] ?? ''),
            'status' => $post['status'] ?? '',
            'study_field' => trim($post['study_field'] ?? ''),
            'other_study_field' => trim($post['other_study_field'] ?? ''),
            'study_level' => trim($post['study_level'] ?? ''),
            'other_study_level' => trim($post['other_study_level'] ?? ''),
            'phone' => trim($post['phone'] ?? ''),
            'email' => trim($post['email'] ?? ''),
            'nationalities' => $post['nationalities'] ?? '',
            'arrival_year' => $post['arrival_year'] ?? null,
            'housing_type' => $post['housing_type'] ?? '',
            'housing_details' => trim($post['housing_details'] ?? ''),
            'post_training_project' => trim($post['post_training_project'] ?? ''),
            'consent_privacy' => isset($post['consent_privacy']) ? 1 : 0
        ];

        // 1. Logic for 'Other' fields
        $finalLieu = $input['residence'] === 'Other' ? $input['other_residence'] : $input['residence'];
        $finalEtab = $input['institution'] === 'Other' ? $input['other_institution'] : $input['institution'];
        $finalDom  = $input['study_field'] === 'Other' ? $input['other_study_field'] : $input['study_field'];
        $finalNiv  = $input['study_level'] === 'Other' ? $input['other_study_level'] : $input['study_level'];

        // 2. Required fields
        $required = [
            'last_name' => 'Last name is required.',
            'first_name' => 'First name is required.',
            'gender' => 'Gender is required.',
            'status' => 'Status is required.',
            'phone' => 'Phone number is required.',
            'email' => 'Email is required.',
        ];
        // Only require consent for new registrations
        if ($excludeId === null) {
            $required['consent_privacy'] = 'You must accept the terms and conditions.';
        }

        foreach ($required as $f => $m) {
            if (empty($input[$f])) $errors[$f] = $m;
        }

        // 3. Uniqueness
        if (!empty($input['email']) && $this->studentRepo->existsByEmail($input['email'], $excludeId)) {
            $errors['email'] = 'This email is already in use.';
        }
        if (!empty($input['phone']) && $this->studentRepo->existsByPhone($input['phone'], $excludeId)) {
            $errors['phone'] = 'This phone number is already in use.';
        }
        // 4. Phone validation
        if (!empty($input['phone']) && !\isValidPhone($input['phone'])) {
            $errors['phone'] = 'Invalid phone number (9 digits expected).';
        }
        if (!empty($input['status']) && !in_array($input['status'], ['ELEVE', 'ETUDIANT', 'STAGIAIRE'], true)) {
            $errors['status'] = 'Invalid status.';
        }
        if (!empty($input['gender']) && !in_array($input['gender'], ['Masculin', 'Féminin'], true)) {
            $errors['gender'] = 'Invalid gender.';
        }

        // 5. Nationalities
        $validNats = [];
        $validIds = [];
        if (!empty($input['nationalities'])) {
            $decoded = json_decode($input['nationalities'], true);
            if (is_array($decoded)) {
                $names = array_map(fn($item) => $item['value'], $decoded);
                $paysRows = $this->studentRepo->getPaysByName($names);
                foreach ($paysRows as $row) {
                    $validNats[] = $row['name_fr'];
                    $validIds[]  = $row['id'];
                }
            }
        }
        $validNats = array_values(array_unique($validNats));
        
        // Always Guinée
        $guinee = $this->studentRepo->getGuineeCountry();
        if ($guinee && !in_array($guinee['name_fr'], $validNats, true)) {
             array_unshift($validNats, $guinee['name_fr']);
             array_unshift($validIds, $guinee['id']);
        }
        if (empty($validNats)) $errors['nationalities'] = 'Nationality is required.';
        if (count($validNats) > 5) $errors['nationalities'] = 'Maximum 5 nationalities allowed.';

        // 6. Age
        if (!empty($input['birth_date'])) {
            $age = \calculateAge($input['birth_date']);
            if ($age < 15) $errors['birth_date'] = 'Minimum age is 15 years old.';
        }

        // 7. Files
        $identityDocumentPath = null;
        if (!empty($files['photo']['name'])) {
            $res = $this->fileUploader->handle($files['photo'], ['jpg','jpeg','png','pdf'], 2*1024*1024, 'uploads/students');
            if ($res['success']) $identityDocumentPath = $res['filepath'];
            else $errors['identity_document'] = $res['message'];
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
                'last_name' => $input['last_name'],
                'first_name' => $input['first_name'],
                'gender' => $input['gender'],
                'birth_date' => $input['birth_date'] ?: null,
                'residence' => $finalLieu ?: null,
                'institution' => $finalEtab ?: null,
                'status' => $input['status'] ?: null,
                'study_field' => $finalDom ?: null,
                'study_level' => $finalNiv ?: null,
                'phone' => $input['phone'],
                'email' => $input['email'],
                'arrival_year' => $input['arrival_year'] ? (int)$input['arrival_year'] : null,
                'housing_type' => $input['housing_type'] ?: null,
                'housing_details' => $input['housing_details'] ?: null,
                'post_training_project' => $input['post_training_project'] ?: null,
                'identity_document' => $identityDocumentPath,
                'cv_path' => $cvPath,
                'nationalities' => json_encode($validNats, JSON_UNESCAPED_UNICODE),
                'consent_privacy' => $input['consent_privacy']
            ]
        ];
    }
}
