<?php
namespace Amea\Repository;

use Amea\Model\Student;

class StudentRepository
{
    public function __construct(private \PDO $pdo) {}

    /** Active dossiers awaiting staff review, oldest first. @return Student[] */
    public function findReviewQueue(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM students WHERE deleted_at IS NULL AND kyc_status = ? ORDER BY registration_date ASC, id ASC LIMIT ?'
        );
        $stmt->bindValue(1, 'UNDER_REVIEW', \PDO::PARAM_STR);
        $stmt->bindValue(2, max(1, min(50, $limit)), \PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($row) => Student::fromRow($row), $stmt->fetchAll());
    }

    public function findById(int $id): ?Student
    {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Student::fromRow($row) : null;
    }

    public function findByToken(string $token): ?Student
    {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE review_token = ? AND deleted_at IS NULL");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ? Student::fromRow($row) : null;
    }

    /** Active rows only — trashed students are fetched via findTrashed* methods. */
    public function findTrashedById(int $id): ?Student
    {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Student::fromRow($row) : null;
    }

    /**
     * Trashed (soft-deleted) students, most recently trashed first.
     * @return array{items: Student[], total: int}
     */
    public function findTrashedPaginated(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $total = (int)$this->pdo
            ->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NOT NULL")
            ->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM students WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);

        return [
            'items' => array_map(fn($row) => Student::fromRow($row), $stmt->fetchAll()),
            'total' => $total,
        ];
    }

    /**
     * Students whose trash retention window has elapsed and are ready for
     * permanent deletion. The cutoff is computed in PHP for MySQL/SQLite parity.
     * @return Student[]
     */
    public function findPurgeable(int $days): array
    {
        $cutoff = (new \DateTime())->modify("-{$days} days")->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "SELECT * FROM students WHERE deleted_at IS NOT NULL AND deleted_at < ? ORDER BY deleted_at ASC"
        );
        $stmt->execute([$cutoff]);
        return array_map(fn($row) => Student::fromRow($row), $stmt->fetchAll());
    }

    /** @return Student[] */
    public function findAll(array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($filters);
        $stmt = $this->pdo->prepare("SELECT * FROM students{$where} ORDER BY registration_date DESC");
        $stmt->execute($params);
        return array_map(fn($row) => Student::fromRow($row), $stmt->fetchAll());
    }

    /**
     * @return array{items: Student[], total: int}
     */
    public function findPaginated(int $page, int $perPage, array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($filters);
        $offset = ($page - 1) * $perPage;

        // Re-execute for count properly
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM students{$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Append LIMIT/OFFSET as positional params to avoid mixing named and positional
        $allParams = array_merge($params, [$perPage, $offset]);
        $dataStmt  = $this->pdo->prepare(
            "SELECT * FROM students{$where} ORDER BY registration_date DESC LIMIT ? OFFSET ?"
        );
        $dataStmt->execute($allParams);

        return [
            'items' => array_map(fn($row) => Student::fromRow($row), $dataStmt->fetchAll()),
            'total' => $total,
        ];
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildWhereClause($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM students{$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function save(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO students
                (last_name, first_name, gender, birth_date, residence, institution, status,
                 study_field, study_level, phone, email, arrival_year, housing_type,
                 housing_details, post_training_project, identity_document, nationalities, cv_path,
                 registration_date, consent_privacy, kyc_status, review_token)
             VALUES
                (:last_name, :first_name, :gender, :birth_date, :residence, :institution, :status,
                 :study_field, :study_level, :phone, :email, :arrival_year, :housing_type,
                 :housing_details, :post_training_project, :identity_document, :nationalities, :cv_path,
                 CURRENT_TIMESTAMP, :consent_privacy, :kyc_status, :review_token)"
        );

        // Ensure defaults for optional fields if not in array
        $data['kyc_status'] = $data['kyc_status'] ?? 'PENDING_CONFIRMATION';
        $data['review_token'] = $data['review_token'] ?? null;

        // Filter data to only include keys that are in the statement
        $allowed = [
            'last_name', 'first_name', 'gender', 'birth_date', 'residence', 'institution', 'status',
            'study_field', 'study_level', 'phone', 'email', 'arrival_year', 'housing_type',
            'housing_details', 'post_training_project', 'identity_document', 'nationalities', 'cv_path',
            'consent_privacy', 'kyc_status', 'review_token'
        ];
        $filtered = array_intersect_key($data, array_flip($allowed));

        $stmt->execute($filtered);
        return (int)$this->pdo->lastInsertId();
    }

    private const UPDATABLE_COLUMNS = [
        'last_name', 'first_name', 'gender', 'birth_date', 'residence', 'institution', 'status',
        'study_field', 'study_level', 'phone', 'email', 'arrival_year', 'housing_type',
        'housing_details', 'post_training_project', 'identity_document', 'nationalities', 'cv_path',
        'graduation_date', 'is_locked', 'consent_privacy', 'kyc_status', 'kyc_notes', 'review_token',
    ];

    public function update(int $id, array $data): bool
    {
        // Automatically set kyc_updated_at if not explicitly provided
        if (!isset($data['kyc_updated_at'])) {
            $data['kyc_updated_at'] = date('Y-m-d H:i:s');
        }

        $setClauses   = [];
        $filteredData = [];
        foreach ($data as $key => $val) {
            if ($key === 'kyc_updated_at') {
                 $setClauses[]       = "kyc_updated_at = :kyc_updated_at";
                 $filteredData['kyc_updated_at'] = $val;
                 continue;
            }
            if (!in_array($key, self::UPDATABLE_COLUMNS, true)) {
                throw new \InvalidArgumentException("Column '{$key}' is not updatable.");
            }
            $setClauses[]       = "{$key} = :{$key}";
            $filteredData[$key] = $val;
        }
        if (empty($setClauses)) {
            return false;
        }
        $filteredData['id'] = $id;
        $stmt = $this->pdo->prepare(
            "UPDATE students SET " . implode(', ', $setClauses) . " WHERE id = :id"
        );
        return $stmt->execute($filteredData);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM students WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /** Move an active student to the trash (soft delete). */
    public function softDelete(int $id, ?int $deletedBy = null): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE students SET deleted_at = ?, deleted_by = ? WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([date('Y-m-d H:i:s'), $deletedBy, $id]);
        return $stmt->rowCount() > 0;
    }

    /** Restore a trashed student back to active. */
    public function restore(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE students SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND deleted_at IS NOT NULL"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** Permanently delete a trashed student. Guarded so active rows are never hard-deleted here. */
    public function forceDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM students WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** @return string[] */
    public function getDistinctInstitutions(): array
    {
        return $this->pdo
            ->query("SELECT name FROM institutions ORDER BY name ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return string[] */
    public function getDistinctStudyFields(): array
    {
        return $this->pdo
            ->query("SELECT name FROM study_fields ORDER BY name ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return string[] */
    public function getDistinctStudyLevels(): array
    {
        return $this->pdo
            ->query("SELECT name FROM study_levels ORDER BY name ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getStats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN gender = 'Masculin'    THEN 1 ELSE 0 END) AS men,
                SUM(CASE WHEN gender = 'Féminin'     THEN 1 ELSE 0 END) AS women,
                SUM(CASE WHEN status = 'ELEVE'       THEN 1 ELSE 0 END) AS pupils,
                SUM(CASE WHEN status = 'ETUDIANT'    THEN 1 ELSE 0 END) AS students,
                SUM(CASE WHEN status = 'STAGIAIRE'   THEN 1 ELSE 0 END) AS interns,
                SUM(CASE WHEN status = 'Diplômé(e)'  THEN 1 ELSE 0 END) AS graduates
             FROM students WHERE deleted_at IS NULL"
        )->fetch();
        return $row ?: [
            'total'      => 0,
            'men'        => 0,
            'women'      => 0,
            'pupils'     => 0,
            'students'   => 0,
            'interns'    => 0,
            'graduates'  => 0,
        ];
    }

    public function getRecentCount(int $days = 30): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM students WHERE deleted_at IS NULL AND registration_date >= DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array<array{institution: string, count: int}> */
    public function getTopInstitutions(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT institution, COUNT(*) AS count FROM students
             WHERE deleted_at IS NULL
             GROUP BY institution ORDER BY count DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM students WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM students WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function existsByPhone(string $phone, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM students WHERE phone = ? AND id != ? LIMIT 1");
            $stmt->execute([$phone, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM students WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function ensureInstitutionExists(string $name): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM institutions WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        if (!$stmt->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO institutions (name) VALUES (?)")->execute([$name]);
        }
    }

    public function ensureStudyFieldExists(string $name): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM study_fields WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        if (!$stmt->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO study_fields (name) VALUES (?)")->execute([$name]);
        }
    }

    public function ensureStudyLevelExists(string $name): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM study_levels WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        if (!$stmt->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO study_levels (name) VALUES (?)")->execute([$name]);
        }
    }

    public function saveStudentCountries(int $studentId, array $countryIds): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO student_country (student_id, country_id) VALUES (?, ?)");
        foreach ($countryIds as $pid) {
            $stmt->execute([$studentId, $pid]);
        }
    }

    public function getCountriesByName(array $names): array
    {
        if (empty($names)) return [];
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name_fr FROM countries WHERE name_fr IN ($placeholders)");
        $stmt->execute($names);
        return $stmt->fetchAll();
    }

    public function getGuineaCountry(): ?array
    {
        $guineaNames = ['Guinée', 'Guinee', 'Guinea'];
        $placeholders = implode(',', array_fill(0, count($guineaNames), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name_fr FROM countries WHERE name_fr IN ($placeholders) LIMIT 1");
        $stmt->execute($guineaNames);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function getLocations(): array
    {
        $stmt = $this->pdo->query("SELECT region, name FROM locations ORDER BY CASE WHEN region LIKE 'Dakar%' THEN 0 ELSE 1 END, region ASC, name ASC");
        return $stmt->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_COLUMN);
    }

    /** @return array<array{id: int, field: string, db_path: string|null}> */
    public function getAllDocumentPaths(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, 'identity_document' AS field, identity_document AS db_path FROM students WHERE identity_document IS NOT NULL
             UNION ALL
             SELECT id, 'cv_path' AS field, cv_path AS db_path FROM students WHERE cv_path IS NOT NULL"
        );
        return $stmt->fetchAll();
    }

    public function updateField(int $id, string $field, mixed $value): bool
    {
        // field is never from user input in this codebase, but guard anyway
        $allowedFields = ['identity_document', 'cv_path', 'is_locked', 'status', 'study_level', 'graduation_date', 'nationalities'];
        if (!in_array($field, $allowedFields, true)) {
            throw new \InvalidArgumentException("Field '{$field}' is not updatable via this method.");
        }
        $stmt = $this->pdo->prepare("UPDATE students SET {$field} = ? WHERE id = ?");
        return $stmt->execute([$value, $id]);
    }

    private function buildWhereClause(array $filters): array
    {
        // Active students only — trashed rows are excluded from every list/count.
        $conditions = ['deleted_at IS NULL'];
        $params     = [];

        if (!empty($filters['kyc_status'])) {
            $conditions[] = "kyc_status = ?";
            $params[]     = $filters['kyc_status'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(last_name LIKE ? OR first_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            array_push($params, $s, $s, $s, $s);
        }
        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[]     = $filters['status'];
        }
        if (!empty($filters['gender'])) {
            $conditions[] = "gender = ?";
            $params[]     = $filters['gender'];
        }
        if (!empty($filters['institution'])) {
            $conditions[] = "institution = ?";
            $params[]     = $filters['institution'];
        }
        if (!empty($filters['study_level'])) {
            $conditions[] = "study_level = ?";
            $params[]     = $filters['study_level'];
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
