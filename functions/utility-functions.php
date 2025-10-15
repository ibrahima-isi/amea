<?php
/**
 * Fonctions utilitaires pour la plateforme AMEA
 * Fichier: functions/utility-functions.php
 */

/**
 * Get environment variable value
 * @param string $key Environment variable name
 * @param mixed $default Default value if not found
 * @return mixed
 */
function env($key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

/**
 * Sécurise les données avant de les afficher
 *
 * @param string $data Les données à sécuriser
 * @return string Les données sécurisées
 */
function secureData($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Génère une chaîne aléatoire de la longueur spécifiée
 *
 * @param int $length Longueur de la chaîne aléatoire
 * @return string La chaîne aléatoire générée
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Calcule l'âge à partir d'une date de naissance
 *
 * @param string $dateNaissance Date de naissance au format YYYY-MM-DD
 * @return int L'âge calculé
 */
function calculateAge($dateNaissance) {
    $dateNaissanceObj = new DateTime($dateNaissance);
    $today = new DateTime('today');
    $age = $dateNaissanceObj->diff($today)->y;
    return $age;
}

/**
 * Formate une date en français
 *
 * @param string $date Date au format YYYY-MM-DD
 * @param boolean $includeTime Inclure l'heure dans le format
 * @return string La date formatée
 */
function formatDateFr($date, $includeTime = false) {
    if (empty($date)) {
        return '';
    }
    
    $format = $includeTime ? 'd/m/Y à H:i' : 'd/m/Y';
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * Vérifie si la chaîne est une adresse email valide
 *
 * @param string $email L'adresse email à vérifier
 * @return boolean Vrai si l'adresse email est valide, faux sinon
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Tronque une chaîne à la longueur spécifiée
 *
 * @param string $string La chaîne à tronquer
 * @param int $length La longueur maximale souhaitée
 * @param string $append Texte à ajouter si la chaîne est tronquée
 * @return string La chaîne tronquée
 */
function truncateString($string, $length = 100, $append = '...') {
    if (strlen($string) > $length) {
        $string = substr($string, 0, $length) . $append;
    }
    return $string;
}

/**
 * Valide un numéro de téléphone (format sénégalais ou guinéen)
 *
 * @param string $phone Le numéro de téléphone à valider
 * @return boolean Vrai si le numéro est valide, faux sinon
 */
function isValidPhone($phone) {
    // Format sénégalais: +221 XX XXX XX XX ou 7X XXX XX XX
    // Format guinéen: +224 XXX XX XX XX ou 6XX XX XX XX
    return preg_match('/^(\+221|221)?\s*[7][0-9]\s*[0-9]{3}\s*[0-9]{2}\s*[0-9]{2}$/', $phone) || 
           preg_match('/^(\+224|224)?\s*[6][0-9]{2}\s*[0-9]{2}\s*[0-9]{2}\s*[0-9]{2}$/', $phone);
}

/**
 * Crée un jeton CSRF pour sécuriser les formulaires
 *
 * @return string Le jeton CSRF généré
 */
function generateCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Vérifie si le jeton CSRF soumis est valide
 *
 * @param string $token Le jeton CSRF à vérifier
 * @return boolean Vrai si le jeton est valide, faux sinon
 */
function verifyCsrfToken($token) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }

    // Régénérer le jeton après une utilisation réussie pour limiter la réutilisation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    return true;
}

/**
 * Journalise une erreur dans le fichier de logs de l'application
 *
 * @param string $message Message d'erreur contextuel
 * @param \Throwable|null $exception Exception associée
 * @return void
 */
function logError($message, ?\Throwable $exception = null) {
    $logDirectory = __DIR__ . '/../storage/logs';

    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0775, true);
    }

    $logMessage = '[' . date('c') . "] " . $message;

    if ($exception !== null) {
        $logMessage .= ' | ' . $exception->getMessage();
    }

    file_put_contents($logDirectory . '/app.log', $logMessage . PHP_EOL, FILE_APPEND);
}

/**
 * Enregistre un message flash dans la session
 *
 * @param string $type Type de message (success, error, warning, info)
 * @param string $message Contenu du message
 * @return void
 */
function setFlashMessage($type, $message) {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Récupère et supprime le message flash de la session
 *
 * @return array|null Le message flash ou null s'il n'y en a pas
 */
function getFlashMessage() {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $flashMessage = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flashMessage;
    }
    
    return null;
}

/**
 * Génère une classe Bootstrap pour les différents types de messages flash
 *
 * @param string $type Type de message (success, error, warning, info)
 * @return string La classe Bootstrap correspondante
 */
function getFlashMessageClass($type) {
    $classes = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    return $classes[$type] ?? 'alert-info';
}

/**
 * Vérifie si l'utilisateur est connecté
 *
 * @return boolean Vrai si l'utilisateur est connecté, faux sinon
 */
function isLoggedIn() {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Vérifie si l'utilisateur connecté est un administrateur
 *
 * @return boolean Vrai si l'utilisateur est un administrateur, faux sinon
 */
function isAdmin() {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Redirige vers une autre page
 *
 * @param string $url L'URL de redirection
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Exporte les données au format CSV
 *
 * @param array $data Les données à exporter
 * @param array $headers Les en-têtes des colonnes
 * @param string $filename Nom du fichier à générer
 * @return void
 */
function exportToCsv($data, $headers, $filename = 'export.csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Ajouter le BOM UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Écrire les en-têtes
    fputcsv($output, $headers, ';');
    
    // Écrire les données
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit();
}