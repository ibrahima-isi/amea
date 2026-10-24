<?php
/** Expand only: preserve legacy English values and enum ordering for rolling releases. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/database.php';
try {
    $additions = [
        'gender' => ['Masculin', 'Féminin'],
        'status' => ['ELEVE', 'ETUDIANT', 'STAGIAIRE', 'GRADUATE', 'Diplômé', 'Diplômé(e)'],
        'housing_type' => ['Colocation', 'Famille', 'Hébergement temporaire', 'Location', 'Résidence universitaire', 'Autre'],
    ];
    foreach ($additions as $column => $required) {
        $definition = $conn->query('SHOW FULL COLUMNS FROM students LIKE ' . $conn->quote($column))->fetch(PDO::FETCH_ASSOC);
        if (!$definition || !str_starts_with(strtolower($definition['Type']), 'enum(')) {
            throw new RuntimeException("Expected students.{$column} to be an ENUM; review schema manually.");
        }
        preg_match_all("/'((?:[^']|'')*)'/", $definition['Type'], $matches);
        $existing = array_map(static fn($value) => str_replace("''", "'", $value), $matches[1]);
        $missing = array_values(array_diff($required, $existing));
        if (!$missing) {
            echo "{$column}: already compatible\n";
            continue;
        }
        $values = implode(',', array_map([$conn, 'quote'], array_merge($existing, $missing)));
        $nullability = $definition['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $definition['Default'] !== null ? ' DEFAULT ' . $conn->quote($definition['Default']) : ($definition['Null'] === 'YES' ? ' DEFAULT NULL' : '');
        $collation = $definition['Collation'];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$collation)) {
            throw new RuntimeException('Unexpected enum collation; review schema manually.');
        }
        $comment = ' COMMENT ' . $conn->quote($definition['Comment']);
        $conn->exec("ALTER TABLE students MODIFY COLUMN `{$column}` ENUM({$values}) COLLATE {$collation} {$nullability}{$default}{$comment}");
        echo "{$column}: appended compatible values\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . "\n");
    exit(1);
}
