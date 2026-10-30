<?php
// CLI-only synthetic public sessions; pipe into the public container, never ship.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') {
    exit(1);
}
ini_set('session.save_path', '/var/lib/amea/sessions');
session_name('AMEA_PUBLIC');
foreach (['syntheticexpiredjournal', 'syntheticoldjournalidentity'] as $id) {
    session_id($id);
    session_start();
    $_SESSION = ['last_activity' => time() - 1801, 'private_marker' => 'never-show-session-marker'];
    if ($id === 'syntheticoldjournalidentity') {
        $_SESSION['user_id'] = 999999;
        $_SESSION['session_version'] = 0;
        $_SESSION['role'] = 'admin';
        $_SESSION['last_activity'] = time();
    }
    session_write_close();
}
