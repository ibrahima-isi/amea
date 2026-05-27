<?php
namespace Amea\Repository;

use Amea\Model\Student;

class StudentRepository
{
    public function __construct(private \PDO $pdo) {}

    public function findById(int $id): ?Student
    {
        $stmt = $this->pdo->prepare("SELECT * FROM personnes WHERE id_personne = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Student::fromRow($row) : null;
    }

    /** @return Student[] */
    public function findAll(array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($filters);
        $stmt = $this->pdo->prepare("SELECT * FROM personnes{$where} ORDER BY date_enregistrement DESC");
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
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM personnes{$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Append LIMIT/OFFSET as positional params to avoid mixing named and positional
        $allParams = array_merge($params, [$perPage, $offset]);
        $dataStmt  = $this->pdo->prepare(
            "SELECT * FROM personnes{$where} ORDER BY date_enregistrement DESC LIMIT ? OFFSET ?"
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
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM personnes{$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function save(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO personnes
                (nom, prenom, sexe, date_naissance, lieu_residence, etablissement, statut,
                 domaine_etudes, niveau_etudes, telephone, email, annee_arrivee, type_logement,
                 precision_logement, projet_apres_formation, identite, nationalites, cv_path,
                 date_enregistrement, consent_privacy)
             VALUES
                (:nom, :prenom, :sexe, :date_naissance, :lieu_residence, :etablissement, :statut,
                 :domaine_etudes, :niveau_etudes, :telephone, :email, :annee_arrivee, :type_logement,
                 :precision_logement, :projet_apres_formation, :identite, :nationalites, :cv_path,
                 CURRENT_TIMESTAMP, :consent_privacy)"
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    private const UPDATABLE_COLUMNS = [
        'nom', 'prenom', 'sexe', 'date_naissance', 'lieu_residence', 'etablissement', 'statut',
        'domaine_etudes', 'niveau_etudes', 'telephone', 'email', 'annee_arrivee', 'type_logement',
        'precision_logement', 'projet_apres_formation', 'identite', 'nationalites', 'cv_path',
        'date_diplomation', 'is_locked', 'consent_privacy', 'kyc_status', 'kyc_notes', 'review_token',
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
        $filteredData['id_personne'] = $id;
        $stmt = $this->pdo->prepare(
            "UPDATE personnes SET " . implode(', ', $setClauses) . " WHERE id_personne = :id_personne"
        );
        return $stmt->execute($filteredData);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM personnes WHERE id_personne = ?");
        return $stmt->execute([$id]);
    }

    /** @return string[] */
    public function getDistinctEtablissements(): array
    {
        return $this->pdo
            ->query("SELECT nom FROM etablissements ORDER BY nom ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return string[] */
    public function getDistinctDomaines(): array
    {
        return $this->pdo
            ->query("SELECT nom FROM domaines_etudes ORDER BY nom ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return string[] */
    public function getDistinctNiveaux(): array
    {
        return $this->pdo
            ->query("SELECT nom FROM niveaux_etudes ORDER BY nom ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getStats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(sexe = 'Masculin') AS hommes,
                SUM(sexe = 'Féminin') AS femmes,
                SUM(statut = 'Diplômé(e)') AS diplomes,
                SUM(statut = 'En cours') AS en_cours
             FROM personnes"
        )->fetch();
        return $row ?: ['total' => 0, 'hommes' => 0, 'femmes' => 0, 'diplomes' => 0, 'en_cours' => 0];
    }

    public function getRecentCount(int $days = 30): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM personnes WHERE date_enregistrement >= DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array<array{nom: string, count: int}> */
    public function getTopEtablissements(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT etablissement AS nom, COUNT(*) AS count FROM personnes
             GROUP BY etablissement ORDER BY count DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM personnes WHERE email = ? AND id_personne != ? LIMIT 1");
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM personnes WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function existsByPhone(string $phone, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM personnes WHERE telephone = ? AND id_personne != ? LIMIT 1");
            $stmt->execute([$phone, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM personnes WHERE telephone = ? LIMIT 1");
            $stmt->execute([$phone]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function existsByIdentite(string $numeroIdentite, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM personnes WHERE numero_identite = ? AND id_personne != ? LIMIT 1");
            $stmt->execute([$numeroIdentite, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM personnes WHERE numero_identite = ? LIMIT 1");
            $stmt->execute([$numeroIdentite]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function ensureEtablissementExists(string $nom): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM etablissements WHERE nom = ? LIMIT 1");
        $stmt->execute([$nom]);
        if (!$stmt->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO etablissements (nom) VALUES (?)")->execute([$nom]);
        }
    }

    public function ensureDomaineExists(string $nom): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM domaines_etudes WHERE nom = ? LIMIT 1");
        $stmt->execute([$nom]);
        if (!$stmt->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO domaines_etudes (nom) VALUES (?)")->execute([$nom]);
        }
    }

    public function ensureNiveauExists(string $nom): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM niveaux_etudes WHERE nom = ? LIMIT 1");
        $stmt->execute([$nom]);
        if (!$stmt->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO niveaux_etudes (nom) VALUES (?)")->execute([$nom]);
        }
    }

    public function savePersonnePays(int $personneId, array $paysIds): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO personne_pays (id_personne, id_pays) VALUES (?, ?)");
        foreach ($paysIds as $pid) {
            $stmt->execute([$personneId, $pid]);
        }
    }

    public function getPaysByName(array $names): array
    {
        if (empty($names)) return [];
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $this->pdo->prepare("SELECT id_pays, nom_fr FROM pays WHERE nom_fr IN ($placeholders)");
        $stmt->execute($names);
        return $stmt->fetchAll();
    }

    public function getGuineeCountry(): ?array
    {
        $guineeNames = ['Guinée', 'Guinee', 'Guinea'];
        $placeholders = implode(',', array_fill(0, count($guineeNames), '?'));
        $stmt = $this->pdo->prepare("SELECT id_pays, nom_fr FROM pays WHERE nom_fr IN ($placeholders) LIMIT 1");
        $stmt->execute($guineeNames);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function getLocations(): array
    {
        $stmt = $this->pdo->query("SELECT region, name FROM locations ORDER BY CASE WHEN region LIKE 'Dakar%' THEN 0 ELSE 1 END, region ASC, name ASC");
        return $stmt->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_COLUMN);
    }

    /** @return array<array{id_personne: int, field: string, db_path: string|null}> */
    public function getAllDocumentPaths(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id_personne, 'identite' AS field, identite AS db_path FROM personnes WHERE identite IS NOT NULL
             UNION ALL
             SELECT id_personne, 'cv_path' AS field, cv_path AS db_path FROM personnes WHERE cv_path IS NOT NULL"
        );
        return $stmt->fetchAll();
    }

    public function updateField(int $id, string $field, mixed $value): bool
    {
        // field is never from user input in this codebase, but guard anyway
        $allowedFields = ['identite', 'cv_path', 'is_locked', 'statut', 'niveau_etudes', 'date_diplomation', 'nationalites'];
        if (!in_array($field, $allowedFields, true)) {
            throw new \InvalidArgumentException("Field '{$field}' is not updatable via this method.");
        }
        $stmt = $this->pdo->prepare("UPDATE personnes SET {$field} = ? WHERE id_personne = ?");
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

        if (!empty($filters['sexe'])) {
            $conditions[] = "(nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR telephone LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            array_push($params, $s, $s, $s, $s);
        }
        if (!empty($filters['statut'])) {
            $conditions[] = "statut = ?";
            $params[]     = $filters['statut'];
        }
        if (!empty($filters['sexe'])) {
            $conditions[] = "sexe = ?";
            $params[]     = $filters['sexe'];
        }
        if (!empty($filters['etablissement'])) {
            $conditions[] = "etablissement = ?";
            $params[]     = $filters['etablissement'];
        }
        if (!empty($filters['niveau_etudes'])) {
            $conditions[] = "niveau_etudes = ?";
            $params[]     = $filters['niveau_etudes'];
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
