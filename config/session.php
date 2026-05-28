<?php
/**
 * config/session.php — backward-compat bootstrap
 * Instantiates Session and starts it; exposes $session for controllers that use it.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$session = new \Amea\Core\Session();
$session->start();

if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/database.php';

        $repo = new \Amea\Repository\UserRepository($conn);
        $sessionVersion = (int)($_SESSION['session_version'] ?? 0);

        if (!$repo->isSessionVersionCurrent((int)$_SESSION['user_id'], $sessionVersion)) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
            $_SESSION['flash_message'] = [
                'type' => 'warning',
                'message' => 'Votre session a expiré. Veuillez vous reconnecter.',
            ];

            if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'login.php') {
                header('Location: login.php');
                exit();
            }
        }
    } catch (\Throwable $e) {
        error_log('[session] Session version check skipped: ' . $e->getMessage());
    }
}
