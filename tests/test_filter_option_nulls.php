<?php

declare(strict_types=1);

$passed = 0;
$failed = 0;

function expect(string $label, bool $result): void
{
    global $passed, $failed;

    if ($result) {
        echo "\033[32m  ✓ {$label}\033[0m\n";
        $passed++;
        return;
    }

    echo "\033[31m  ✗ {$label}\033[0m\n";
    $failed++;
}

echo "\nFilter option null handling\n";

$root = dirname(__DIR__);
$studentsPhp = file_get_contents($root . '/students.php');
$exportPhp = file_get_contents($root . '/export.php');

expect(
    'students.php excludes null and blank etablissements from options',
    $studentsPhp !== false
        && preg_match("/SELECT DISTINCT etablissement FROM personnes WHERE etablissement IS NOT NULL AND etablissement <> '' ORDER BY etablissement/", $studentsPhp) === 1
);

expect(
    'export.php excludes null and blank etablissements from options',
    $exportPhp !== false
        && preg_match("/SELECT DISTINCT etablissement FROM personnes WHERE etablissement IS NOT NULL AND etablissement <> '' ORDER BY etablissement/", $exportPhp) === 1
);

expect(
    'export.php excludes null and blank niveaux from options',
    $exportPhp !== false
        && preg_match("/SELECT DISTINCT niveau_etudes FROM personnes WHERE niveau_etudes IS NOT NULL AND niveau_etudes <> '' ORDER BY niveau_etudes/", $exportPhp) === 1
);

echo "\n";
$total = $passed + $failed;
echo "\033[" . ($failed > 0 ? '31' : '32') . "m  {$passed}/{$total} tests passed\033[0m\n\n";
exit($failed > 0 ? 1 : 0);
