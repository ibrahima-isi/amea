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
 * Valide un numéro de téléphone pour qu'il contienne exactement 9 chiffres.
 *
 * @param string $phone Le numéro de téléphone à valider
 * @return boolean Vrai si le numéro contient 9 chiffres, faux sinon
 */
function isValidPhone($phone) {
    // Supprimer tous les caractères non numériques
    $digits = preg_replace('/[^0-9]/', '', $phone);
    // Vérifier si le numéro contient exactement 9 chiffres
    return strlen($digits) === 9;
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

    // Do NOT regenerate the token here. It should be stable for the session or form lifespan.
    // Regeneration should happen on privilege escalation (login) or session rotation.

    return true;
}



/**
 * Journalise une erreur dans le journal PHP par défaut.
 *
 * @param string $message Message d'erreur contextuel
 * @param \Throwable|null $exception Exception associée
 * @return void
 */
function logError($message, ?\Throwable $exception = null) {
    $logMessage = '[' . date('c') . "] " . $message;

    if ($exception !== null) {
        $logMessage .= ' | ' . $exception->getMessage();
    }

    error_log($logMessage);
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

/**
 * Récupère une valeur de configuration depuis la base de données.
 *
 * @param string $key La clé de configuration
 * @param string $default La valeur par défaut si la clé n'existe pas
 * @return string La valeur de la configuration
 */
function getSetting($key, $default = '') {
    global $conn;
    
    // Ensure connection is available
    if (!isset($conn)) {
        require_once __DIR__ . '/../config/database.php';
    }

    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['setting_value'];
        }
    } catch (PDOException $e) {
        // Silently fail and return default in case of DB error to avoid breaking pages
        logError("Error fetching setting '$key'", $e);
    }

    return $default;
}

/**
 * Définit un message flash en session.
 *
 * @param string $type Le type de message (ex: success, error, warning)
 * @param string $message Le message à afficher
 * @return void
 */
function setFlashMessage($type, $message) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Récupère et efface le message flash de la session.
 *
 * @return array|null Le message flash ou null s'il n'y en a pas
 */
function getFlashMessage() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Retourne la classe CSS Bootstrap correspondant au type de message flash.
 *
 * @param string $type Le type de message
 * @return string La classe CSS
 */
function getFlashMessageClass($type) {
    switch ($type) {
        case 'success':
            return 'alert-success';
        case 'error':
            return 'alert-danger';
        case 'warning':
            return 'alert-warning';
        default:
            return 'alert-info';
    }
}

/**
 * Récupère les remplacements communs pour le pied de page (email, téléphone, année).
 *
 * @return array Les remplacements pour le template
 */
function getFooterReplacements() {
    return [
        '{{contact_email}}' => htmlspecialchars(getSetting('contact_email', 'admin@aeesgs.org')),
        '{{contact_phone}}' => htmlspecialchars(getSetting('contact_phone', '+221 XX XXX XX XX')),
        '{{year}}' => date('Y'),
    ];
}

/**
 * Nettoie une chaîne de caractères pour l'exportation CSV.
 *
 * @param string|null $data La chaîne à nettoyer
 * @return string La chaîne nettoyée
 */
function cleanData($data) {
    if ($data === null) {
        return '';
    }
    // Supprimer les sauts de ligne et les tabulations
    $data = str_replace(["\r", "\n", "\t"], ' ', $data);
    // Échapper les guillemets doubles
    $data = str_replace('"', '""', $data);
    return $data;
}

/**
 * Gère le téléchargement d'un fichier, le valide et le déplace.
 *
 * @param array $file_input L'entrée du tableau $_FILES pour le fichier.
 * @param array $allowed_extensions Extensions de fichier autorisées (ex: ['pdf', 'png']).
 * @param int $max_size Taille maximale autorisée en octets (ex: 5 * 1024 * 1024 pour 5MB).
 * @param string $upload_dir Le répertoire de destination du téléchargement.
 * @return array Un tableau contenant 'success' (bool) et 'message' (string) ou 'filepath' (string).
 */
function handleFileUpload($file_input, $allowed_extensions, $max_size, $upload_dir) {
    if (!isset($file_input) || $file_input['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filepath' => null]; // No file uploaded is not an error for optional fields
    }

    if ($file_input['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => "Erreur lors de l'upload du fichier: " . $file_input['error']];
    }

    $filename = $file_input['name'];
    $file_size = $file_input['size'];
    $file_tmp = $file_input['tmp_name'];
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Validate extension
    if (!in_array($file_ext, $allowed_extensions)) {
        return ['success' => false, 'message' => "Extension de fichier non autorisée. Extensions acceptées: " . implode(', ', $allowed_extensions)];
    }

    // Validate size
    if ($file_size > $max_size) {
        return ['success' => false, 'message' => "Le fichier est trop volumineux. Taille maximale: " . ($max_size / (1024 * 1024)) . "MB"];
    }

    // Generate unique filename and move
    $new_file_name = uniqid('', true) . '.' . $file_ext;
    $destination = rtrim($upload_dir, '/') . '/' . $new_file_name;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
    }

    if (move_uploaded_file($file_tmp, $destination)) {
        return ['success' => true, 'filepath' => $destination];
    } else {
        return ['success' => false, 'message' => "Erreur lors du déplacement du fichier téléchargé."];
    }
}