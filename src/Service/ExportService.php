<?php
namespace Amea\Service;

class ExportService
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Stream a CSV file directly to the browser.
     * $allowedFields maps form field name => SQL column/expression.
     */
    public function streamCsv(
        array  $selectedFields,
        array  $allowedFields,
        array  $filters = [],
        string $filename = 'export.csv'
    ): void {
        // Validate selected fields against whitelist
        $safeFields = array_intersect_key(
            $allowedFields,
            array_flip($selectedFields)
        );

        if (empty($safeFields)) {
            http_response_code(400);
            exit('Aucun champ valide sélectionné.');
        }

        $selectExprs = implode(', ', $safeFields);
        $headers     = array_keys($safeFields);

        [$where, $params] = $this->buildWhereClause($filters);
        $sql  = "SELECT {$selectExprs} FROM personnes{$where} ORDER BY date_enregistrement DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');

        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            fputcsv($out, array_map([$this, 'cleanForCsv'], $row), ';');
        }
        fclose($out);
        exit();
    }

    public function cleanForCsv(?string $data): string
    {
        if ($data === null) return '';
        return str_replace('"', '""', str_replace(["\r", "\n", "\t"], ' ', $data));
    }

    private function buildWhereClause(array $filters): array
    {
        $conditions = [];
        $params     = [];

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
