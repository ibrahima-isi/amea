<?php
// TODO: implementer une reinitialisation de mot de passe oublier
/**
 * Page de connexion administrateur (logique seulement)
 * La présentation est rendue via templates/login.html
 */

// Démarrer la session
require_once 'config/session.php';

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Traitement du formulaire lors de la soumission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validation des entrées
        if (empty($username) || empty($password)) {
            setFlashMessage('error', "Veuillez entrer un nom d'utilisateur et un mot de passe.");
        } else {
            try {
                // Rechercher l'utilisateur dans la base de données
                $sql = "SELECT * FROM users WHERE username = :username AND est_actif = 1";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->execute();

                if ($stmt->rowCount() == 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Vérifier le mot de passe
                    if (password_verify($password, $user['password'])) {
                        // Connexion réussie, enregistrer les informations dans la session
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id_user'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['nom'] = $user['nom'];
                        $_SESSION['prenom'] = $user['prenom'];
                        
                        // Regenerate CSRF token for the new authenticated session
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        // Mettre à jour la dernière connexion
                        $updateSql = "UPDATE users SET derniere_connexion = NOW() WHERE id_user = :id_user";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bindParam(':id_user', $user['id_user']);
                        $updateStmt->execute();

                        // Rediriger vers le tableau de bord
                        session_write_close();
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        setFlashMessage('error', "Nom d'utilisateur ou mot de passe incorrect.");
                    }
                } else {
                    // Check if user exists but is inactive
                    $checkSql = "SELECT est_actif FROM users WHERE username = :username";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->execute([':username' => $username]);
                    if ($checkStmt->rowCount() > 0) {
                        setFlashMessage('error', "Ce compte est désactivé.");
                    } else {
                        setFlashMessage('error', "Nom d'utilisateur ou mot de passe incorrect.");
                    }
                }
            } catch (PDOException $e) {
                logError('Erreur lors de la tentative de connexion', $e);
                setFlashMessage('danger', "Une erreur est survenue lors de la tentative de connexion. Veuillez réessayer plus tard.");
            }
        }
    }

    header('Location: login.php');
    exit();
}

$csrfToken = generateCsrfToken();
// Rendu du template HTML
$templatePath = __DIR__ . '/templates/login.html';
if (!is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

$template = file_get_contents($templatePath);

$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

// Inject header/footer partials
$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = strtr(file_get_contents(__DIR__ . '/templates/partials/footer.html'), getFooterReplacements());
$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => '',
    '{{register_active}}' => '',
    '{{login_active}}' => 'active',
]);

$output = strtr($template, [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
    '{{error_block}}' => '', // Handled by flash messages
    '{{csrf_token}}' => htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'),
    '{{form_action}}' => htmlspecialchars($_SERVER['PHP_SELF'] ?? 'login.php', ENT_QUOTES, 'UTF-8'),
    '{{flash_json}}' => $flash_json,
]);

echo $output;

