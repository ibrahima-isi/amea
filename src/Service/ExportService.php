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
        $sql  = "SELECT {$selectExprs} FROM students{$where} ORDER BY registration_date DESC";
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
