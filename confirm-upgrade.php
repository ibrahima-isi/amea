<?php
/**
 * Public confirmation page for level upgrades.
 * File: confirm-upgrade.php
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Self-healing: ensure the table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS pending_level_upgrades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        old_level VARCHAR(100) NOT NULL,
        new_level VARCHAR(100) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        confirmed_at DATETIME NULL,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {}

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    die('Lien invalide ou expiré.');
}

// Check if token exists and is valid
$stmt = $conn->prepare(
    "SELECT plu.*, p.first_name, p.last_name 
     FROM pending_level_upgrades plu
     JOIN students p ON p.id = plu.student_id
     WHERE plu.token = ? LIMIT 1"
);
$stmt->execute([$token]);
$upgrade = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$upgrade) {
    die('Ce lien de mise à niveau n\'existe pas.');
}

if ($upgrade['confirmed_at'] !== null) {
    die('Ce lien a déjà été utilisé.');
}

if (new DateTime($upgrade['expires_at']) < new DateTime()) {
    die('Ce lien de mise à niveau a expiré.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm') {
    try {
        $conn->beginTransaction();

        // 1. Update the student's level
        $updStmt = $conn->prepare(
            'UPDATE students SET study_level = ? WHERE id = ? AND study_level = ?'
        );
        $updStmt->execute([
            $upgrade['new_level'], 
            $upgrade['student_id'], 
            $upgrade['old_level']
        ]);

        if ($updStmt->rowCount() === 0) {
            $conn->rollBack();
            die('Impossible d\'appliquer la mise à niveau. Le niveau actuel de l\'étudiant a peut-être déjà été modifié.');
        }

        // 2. Mark token as confirmed
        $confStmt = $conn->prepare('UPDATE pending_level_upgrades SET confirmed_at = NOW() WHERE id = ?');
        $confStmt->execute([$upgrade['id']]);

        $conn->commit();
        $success = true;
    } catch (PDOException $e) {
        $conn->rollBack();
        logError("Erreur lors de l'application de la mise à niveau via token", $e);
        die('Une erreur système est survenue.');
    }
}

// --- RENDER ---
$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = strtr(file_get_contents(__DIR__ . '/templates/partials/footer.html'), getFooterReplacements());
$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => '',
    '{{register_active}}' => '',
    '{{login_active}}' => '',
]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AEESGS - Mise à niveau</title>
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<?= $headerHtml ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0 p-4 text-center">
                <?php if (isset($success) && $success): ?>
                    <i class="fas fa-check-circle text-success mb-3" style="font-size:4rem;"></i>
                    <h2 class="h4 mb-3">Mise à niveau réussie !</h2>
                    <p class="text-muted">
                        Votre niveau académique a été mis à jour avec succès :<br>
                        <strong><?= htmlspecialchars($upgrade['old_level'], ENT_QUOTES, 'UTF-8') ?></strong> 
                        <i class="fas fa-arrow-right mx-2 text-muted"></i> 
                        <strong class="text-success"><?= htmlspecialchars($upgrade['new_level'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </p>
                    <p class="mt-4 mb-0"><a href="index.php" class="btn btn-outline-primary">Retour à l'accueil</a></p>
                <?php else: ?>
                    <i class="fas fa-level-up-alt text-primary mb-3" style="font-size:4rem;"></i>
                    <h2 class="h4 mb-3">Confirmer votre mise à niveau</h2>
                    <p>Bonjour <strong><?= htmlspecialchars($upgrade['first_name'] . ' ' . $upgrade['last_name'], ENT_QUOTES, 'UTF-8') ?></strong>,</p>
                    <p class="text-muted mb-4">
                        Vous avez demandé à passer de <strong><?= htmlspecialchars($upgrade['old_level'], ENT_QUOTES, 'UTF-8') ?></strong> 
                        à <strong><?= htmlspecialchars($upgrade['new_level'], ENT_QUOTES, 'UTF-8') ?></strong>.
                        Veuillez confirmer cette modification.
                    </p>
                    <form method="POST" action="confirm-upgrade.php?token=<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-check me-2"></i>Je confirme la mise à niveau
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $footerTpl ?>
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>