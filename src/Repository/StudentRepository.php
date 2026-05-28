<?php
namespace Amea\Repository;

use Amea\Model\Student;

class StudentRepository
{
    public function __construct(private \PDO $pdo) {}

    public function findById(int $id): ?Student
    {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Student::fromRow($row) : null;
    }

    public function findByToken(string $token): ?Student
    {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE review_token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ? Student::fromRow($row) : null;
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

    /** @return string[] */
    public function getDistinctEtablissements(): array
    {
        return $this->pdo
            ->query("SELECT name FROM institutions ORDER BY name ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return string[] */
    public function getDistinctDomaines(): array
    {
        return $this->pdo
            ->query("SELECT name FROM study_fields ORDER BY name ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return string[] */
    public function getDistinctNiveaux(): array
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
                SUM(gender = 'Masculin') AS hommes,
                SUM(gender = 'Féminin') AS femmes,
                SUM(status = 'Diplômé(e)') AS diplomes,
                SUM(status = 'En cours') AS en_cours
             FROM students"
        )->fetch();
        return $row ?: ['total' => 0, 'hommes' => 0, 'femmes' => 0, 'diplomes' => 0, 'en_cours' => 0];
    }

    public function getRecentCount(int $days = 30): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM students WHERE registration_date >= DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array<array{nom: string, count: int}> */
    public function getTopEtablissements(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT institution AS nom, COUNT(*) AS count FROM students
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

    public function ensureEtablissementExists(string $nom): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM institutions WHERE name = ? LIMIT 1");
        $stmt->execute([$nom]);
        if (!$stmt->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO institutions (name) VALUES (?)")->execute([$nom]);
        }
    }

    public function ensureDomaineExists(string $nom): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM study_fields WHERE name = ? LIMIT 1");
        $stmt->execute([$nom]);
        if (!$stmt->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO study_fields (name) VALUES (?)")->execute([$nom]);
        }
    }

    public function ensureNiveauExists(string $nom): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM study_levels WHERE name = ? LIMIT 1");
        $stmt->execute([$nom]);
        if (!$stmt->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO study_levels (name) VALUES (?)")->execute([$nom]);
        }
    }

    public function savePersonnePays(int $personneId, array $paysIds): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO student_country (student_id, country_id) VALUES (?, ?)");
        foreach ($paysIds as $pid) {
            $stmt->execute([$personneId, $pid]);
        }
    }

    public function getPaysByName(array $names): array
    {
        if (empty($names)) return [];
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name_fr FROM countries WHERE name_fr IN ($placeholders)");
        $stmt->execute($names);
        return $stmt->fetchAll();
    }

    public function getGuineeCountry(): ?array
    {
        $guineeNames = ['Guinée', 'Guinee', 'Guinea'];
        $placeholders = implode(',', array_fill(0, count($guineeNames), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name_fr FROM countries WHERE name_fr IN ($placeholders) LIMIT 1");
        $stmt->execute($guineeNames);
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
        $conditions = [];
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
