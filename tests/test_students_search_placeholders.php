<?php

declare(strict_types=1);

$studentsPhp = file_get_contents(dirname(__DIR__) . '/students.php');

if ($studentsPhp === false) {
    fwrite(STDERR, "Unable to read students.php\n");
    exit(1);
}

$expectedPlaceholders = [
    ':search_last_name',
    ':search_first_name',
    ':search_email',
    ':search_phone',
];

foreach ($expectedPlaceholders as $placeholder) {
    if (substr_count($studentsPhp, $placeholder) !== 2) {
        fwrite(STDERR, "{$placeholder} must appear once in SQL and once in the parameter map\n");
        exit(1);
    }
}

if (preg_match('/LIKE\s+:search(?:\s|\))/', $studentsPhp) === 1) {
    fwrite(STDERR, "The same :search placeholder cannot be reused with native PDO prepares\n");
    exit(1);
}

echo "Student search uses unique PDO placeholders.\n";
