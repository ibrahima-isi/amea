<?php

/** Private synthetic migration data. Never points at application uploads. */
function migrationFixture(string $directory): array
{
    mkdir($directory, 0700, true);
    $directory = realpath($directory);
    foreach (['source', 'destination', 'sessions', 'evidence'] as $name) {
        mkdir($directory . '/' . $name, 0700);
    }
    $files = [
        'uploads/students/cvs/shared.pdf' => "%PDF-1.4\nsynthetic CV\n",
        'uploads/students/identity/shared.pdf' => "%PDF-1.4\nsynthetic ID\n",
        'uploads/journal/historical-cover.png' => "\x89PNG\r\nretained cover",
        'uploads/unknown/unreferenced.bin' => 'retained attachment',
        'uploads/students/pending/old.pdf' => "%PDF-1.4\nold pending\n",
    ];
    for ($index = 0; $index < 7; $index++) {
        $files['uploads/unknown/page-' . $index . '.bin'] = 'page ' . $index;
    }
    foreach ($files as $key => $bytes) {
        $path = $directory . '/source/' . $key;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $bytes);
        touch($path, 1600000000);
    }
    symlink('/dev/null', $directory . '/source/uploads/unknown/link');
    $pending = ['db_data' => ['identity_document' => 'uploads/students/pending/old.pdf', 'cv_path' => null]];
    file_put_contents($directory . '/sessions/sess_synthetic-secret-token', 'csrf_token|s:12:"never-export";pending_registration|' . serialize($pending));
    return ['root' => $directory, 'source' => $directory . '/source', 'destination' => $directory . '/destination',
        'sessions' => $directory . '/sessions', 'manifest' => $directory . '/evidence/migration.jsonl', 'files' => $files];
}
