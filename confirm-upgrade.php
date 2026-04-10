<?php
/**
 * Public token-based grade upgrade confirmation page.
 * Students receive this URL by email and click to confirm their new level.
 */

require_once 'config/database.php';

$conn->exec("CREATE TABLE IF NOT EXISTS pending_level_upgrades (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personne_id   INT NOT NULL,
    ancien_niveau VARCHAR(100) NOT NULL,
    nouveau_niveau VARCHAR(100) NOT NULL,
    token         VARCHAR(100) NOT NULL UNIQUE,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at    DATETIME NOT NULL,
    confirmed_at  DATETIME NULL,
    INDEX idx_token    (token),
    INDEX idx_personne (personne_id),
    CONSTRAINT fk_plu_personne FOREIGN KEY (personne_id)
        REFERENCES personnes(id_personne) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$token = trim($_GET['token'] ?? '');
$status = 'invalid'; // invalid | expired | already_confirmed | success
$student = null;
$upgrade = null;

if (!empty($token)) {
    $stmt = $conn->prepare(
        'SELECT plu.*, p.prenom, p.nom, p.email
         FROM pending_level_upgrades plu
         JOIN personnes p ON p.id_personne = plu.personne_id
         WHERE plu.token = ?'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $status = 'invalid';
    } elseif ($row['confirmed_at'] !== null) {
        $status  = 'already_confirmed';
        $upgrade = $row;
    } elseif (new DateTime() > new DateTime($row['expires_at'])) {
        $status  = 'expired';
        $upgrade = $row;
    } else {
        // Valid — apply upgrade
        try {
            $conn->beginTransaction();

            $conn->prepare(
                'UPDATE personnes SET niveau_etudes = ? WHERE id_personne = ? AND niveau_etudes = ?'
            )->execute([$row['nouveau_niveau'], $row['personne_id'], $row['ancien_niveau']]);

            $conn->prepare(
                'UPDATE pending_level_upgrades SET confirmed_at = NOW() WHERE id = ?'
            )->execute([$row['id']]);

            $conn->commit();
            $status  = 'success';
            $upgrade = $row;
        } catch (Exception $e) {
            $conn->rollBack();
            $status = 'invalid';
        }
    }
}

// ─── Render minimal standalone page ───────────────────────────────────────
$messages = [
    'success' => [
        'icon'  => '✅',
        'title' => 'Niveau confirmé !',
        'color' => '#009460',
        'body'  => 'Votre niveau d\'études a bien été mis à jour.',
        'sub'   => fn($u) => '<strong>' . htmlspecialchars($u['ancien_niveau']) . '</strong> → <strong>' . htmlspecialchars($u['nouveau_niveau']) . '</strong>',
    ],
    'already_confirmed' => [
        'icon'  => 'ℹ️',
        'title' => 'Déjà confirmé',
        'color' => '#1a3c6e',
        'body'  => 'Vous avez déjà confirmé ce changement de niveau.',
        'sub'   => fn($u) => 'Votre niveau actuel est <strong>' . htmlspecialchars($u['nouveau_niveau']) . '</strong>.',
    ],
    'expired' => [
        'icon'  => '⏰',
        'title' => 'Lien expiré',
        'color' => '#CE1126',
        'body'  => 'Ce lien de confirmation a expiré.',
        'sub'   => fn($u) => 'Veuillez contacter l\'administration AEESGS.',
    ],
    'invalid' => [
        'icon'  => '❌',
        'title' => 'Lien invalide',
        'color' => '#CE1126',
        'body'  => 'Ce lien de confirmation est invalide ou inexistant.',
        'sub'   => fn($u) => 'Veuillez contacter l\'administration AEESGS.',
    ],
];

$m   = $messages[$status];
$sub = $upgrade ? ($m['sub'])($upgrade) : 'Veuillez contacter l\'administration AEESGS.';
$greeting = $upgrade ? '<p style="margin:0 0 8px;">Bonjour <strong>' . htmlspecialchars($upgrade['prenom'] . ' ' . $upgrade['nom']) . '</strong>,</p>' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirmation de niveau – AEESGS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/app.css">
  <style>
    body { background: #f4f4f4; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .card-confirm { max-width: 460px; width: 100%; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); }
    .confirm-header { background: <?= $m['color'] ?>; padding: 28px 30px; text-align: center; color: #fff; }
    .confirm-header .icon { font-size: 2.5rem; display: block; margin-bottom: 8px; }
    .confirm-header h1 { font-size: 1.25rem; font-weight: 700; margin: 0; }
    .confirm-body { padding: 28px 30px; color: #333; line-height: 1.7; }
    .confirm-footer { padding: 16px 30px; background: #f8f9fa; text-align: center; font-size: 12px; color: #999; }
  </style>
</head>
<body>
  <div class="card-confirm">
    <div class="confirm-header">
      <span class="icon"><?= $m['icon'] ?></span>
      <h1><?= $m['title'] ?></h1>
    </div>
    <div class="confirm-body">
      <?= $greeting ?>
      <p><?= $m['body'] ?></p>
      <p><?= $sub ?></p>
      <div class="text-center mt-4">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">Retour à l'accueil</a>
      </div>
    </div>
    <div class="confirm-footer">
      AEESGS &bull; <a href="mailto:contact@aeesgs.org" style="color:#009460;">contact@aeesgs.org</a>
    </div>
  </div>
</body>
</html>
