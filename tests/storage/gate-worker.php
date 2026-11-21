<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amea\Core\FileWriteGate;

[$script, $mode, $directory] = $argv;
$gate = new FileWriteGate($directory . '/gate');
if ($mode === 'pause') {
    $gate->pause();
    exit;
}
if ($mode === 'session-exit') {
    putenv('FILE_WRITE_GATE_PATH=' . $directory . '/gate');
    FileWriteGate::admitProcess();
    mkdir($directory . '/sessions', 0700);
    session_save_path($directory . '/sessions');
    session_id('synthetic');
    session_start();
    $_SESSION['pending_registration'] = ['db_data' => ['identity_document' => 'uploads/students/pending/synthetic.pdf']];
    touch($directory . '/admitted');
    while (!is_file($directory . '/continue')) {
        usleep(10000);
        clearstatcache();
    }
    exit;
}
$gate->mutate(function () use ($gate, $directory) {
    touch($directory . '/admitted');
    $deadline = microtime(true) + 10;
    while (!is_file($directory . '/continue') && microtime(true) < $deadline) {
        usleep(10000);
        clearstatcache();
    }
    $gate->mutate(static fn () => file_put_contents($directory . '/committed', 'committed reference and bytes'));
});
