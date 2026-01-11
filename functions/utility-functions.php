<?php
/**
 * Utility functions for the AMEA platform.
 * File: functions/utility-functions.php
 */

/**
 * Get environment variable value.
 *
 * @param string $key Environment variable name.
 * @param mixed $default Default value if not found.
 * @return mixed The environment variable value or the default.
 */
function env($key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

/**
 * Generates a random string of the specified length.
 *
 * @param int $length Length of the random string.
 * @return string The generated random string.
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
 * Calculates age from a date of birth.
 *
 * @param string $dateNaissance Date of birth in YYYY-MM-DD format.
 * @return int The calculated age.
 */
function calculateAge($dateNaissance) {
    $dateNaissanceObj = new DateTime($dateNaissance);
    $today = new DateTime('today');
    $age = $dateNaissanceObj->diff($today)->y;
    return $age;
}

/**
 * Formats a date into French format.
 *
 * @param string $date Date in YYYY-MM-DD format.
 * @param boolean $includeTime Whether to include the time in the format.
 * @return string The formatted date.
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
 * Checks if the string is a valid email address.
 *
 * @param string $email The email address to check.
 * @return boolean True if the email is valid, false otherwise.
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Truncates a string to the specified length.
 *
 * @param string $string The string to truncate.
 * @param int $length The maximum desired length.
 * @param string $append Text to append if the string is truncated.
 * @return string The truncated string.
 */
function truncateString($string, $length = 100, $append = '...') {
    if (strlen($string) > $length) {
        $string = substr($string, 0, $length) . $append;
    }
    return $string;
}

/**
 * Validates a phone number to ensure it contains exactly 9 digits.
 *
 * @param string $phone The phone number to validate.
 * @return boolean True if the number contains exactly 9 digits, false otherwise.
 */
function isValidPhone($phone) {
    // Remove all non-numeric characters
    $digits = preg_replace('/[^0-9]/', '', $phone);
    // Check if the number contains exactly 9 digits
    return strlen($digits) === 9;
}

/**
 * Generates a CSRF token to secure forms.
 *
 * @return string The generated CSRF token.
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
 * Verifies if the submitted CSRF token is valid.
 *
 * @param string $token The CSRF token to verify.
 * @return boolean True if the token is valid, false otherwise.
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
 * Logs an error in the default PHP error log.
 *
 * @param string $message Contextual error message.
 * @param \Throwable|null $exception Associated exception.
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
 * Exports data to CSV format.
 *
 * @param array $data The data to export.
 * @param array $headers The column headers.
 * @param string $filename The name of the file to generate.
 * @return void
 */
function exportToCsv($data, $headers, $filename = 'export.csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers, ';');
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit();
}

/**
 * Retrieves a configuration value from the database.
 *
 * @param string $key The configuration key.
 * @param string $default The default value if the key does not exist.
 * @return string The configuration value.
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
 * Sets a flash message in the session.
 *
 * @param string $type The message type (e.g., success, error, warning).
 * @param string $message The message to display.
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
 * Retrieves and clears the flash message from the session.
 *
 * @return array|null The flash message or null if none exists.
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
 * Returns the Bootstrap CSS class corresponding to the flash message type.
 *
 * @param string $type The message type.
 * @return string The CSS class.
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
 * Retrieves common replacements for the footer (email, phone, year).
 *
 * @return array The replacements for the template.
 */
function getFooterReplacements() {
    return [
        '{{contact_email}}' => htmlspecialchars(getSetting('contact_email', 'admin@aeesgs.org')),
        '{{contact_phone}}' => htmlspecialchars(getSetting('contact_phone', '+221 XX XXX XX XX')),
        '{{year}}' => date('Y'),
    ];
}

/**
 * Cleans a string for CSV export.
 *
 * @param string|null $data The string to clean.
 * @return string The cleaned string.
 */
function cleanData($data) {
    if ($data === null) {
        return '';
    }
    // Remove line breaks and tabs
    $data = str_replace(["\r", "\n", "\t"], ' ', $data);
    // Escape double quotes
    $data = str_replace('"', '""', $data);
    return $data;
}

/**
 * Handles file upload, validates, and moves it.
 *
 * @param array $file_input The $_FILES input array for the file.
 * @param array $allowed_extensions Allowed file extensions (e.g., ['pdf', 'png']).
 * @param int $max_size Maximum allowed size in bytes (e.g., 5 * 1024 * 1024 for 5MB).
 * @param string $upload_dir The destination directory for the upload.
 * @return array An array containing 'success' (bool) and 'message' (string) or 'filepath' (string).
 */
function handleFileUpload($file_input, $allowed_extensions, $max_size, $upload_dir) {
    if (!isset($file_input) || $file_input['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filepath' => null]; // No file uploaded is not an error for optional fields
    }

    if ($file_input['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => "Upload error: " . $file_input['error']];
    }

    $filename = $file_input['name'];
    $file_size = $file_input['size'];
    $file_tmp = $file_input['tmp_name'];
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Validate extension
    if (!in_array($file_ext, $allowed_extensions)) {
        return ['success' => false, 'message' => "File extension not allowed. Accepted extensions: " . implode(', ', $allowed_extensions)];
    }

    // Validate size
    if ($file_size > $max_size) {
        return ['success' => false, 'message' => "File is too large. Maximum size: " . ($max_size / (1024 * 1024)) . "MB"];
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
        return ['success' => false, 'message' => "Error moving uploaded file."];
    }
}

/**
 * Appends a version parameter to asset URLs (CSS, JS) in HTML content.
 * The version is based on the file modification time to bust browser cache.
 *
 * @param string $html The HTML content to process.
 * @return string The processed HTML with versioned asset URLs.
 */
function addVersionToAssets($html) {
    // Regex to find href="..." or src="..." pointing to assets/
    // It captures:
    // 1. The attribute (href or src)
    // 2. The quote (" or ')
    // 3. The path starting with assets/
    // 4. The quote
    return preg_replace_callback(
        '/(href|src)=("|\')(assets\/[^"\']+)\2/i',
        function ($matches) {
            $attribute = $matches[1];
            $quote = $matches[2];
            $path = $matches[3];

            // Resolve the real file path relative to the document root (current working dir)
            // Assuming the script is running from the root or we can resolve it.
            // In this project structure, 'assets/' is at the root.
            $realPath = __DIR__ . '/../' . $path;

            // Remove any existing query string from the path for checking file existence
            $cleanPath = explode('?', $realPath)[0];

            if (file_exists($cleanPath)) {
                $version = filemtime($cleanPath);
                // Check if there is already a query string
                if (strpos($path, '?') !== false) {
                    $newPath = $path . '&v=' . $version;
                } else {
                    $newPath = $path . '?v=' . $version;
                }
                return $attribute . '=' . $quote . $newPath . $quote;
            }

            return $matches[0];
        },
        $html
    );
}
