<?php
/**
 * Public CGU acceptance page.
 * Students arrive here via a tokenised link sent by the admin.
 * GET  ?token=xxx  — show the CGU summary + Accept button
 * POST ?token=xxx  — record acceptance, show confirmation
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

$token = trim($_GET['token'] ?? '');

// Validate token
$student = null;
if ($token !== '') {
    $stmt = $conn->prepare(
        "SELECT id_personne, nom, prenom, email, consent_privacy
         FROM personnes WHERE cgu_token = ? LIMIT 1"
    );
    $stmt->execute([$token]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

$state = 'invalid'; // invalid | already_accepted | pending | accepted

if ($student) {
    if ($student['consent_privacy'] == 1) {
        $state = 'already_accepted';
    } else {
        $state = 'pending';
    }
}

// Handle acceptance POST
if ($state === 'pending' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->prepare(
        "UPDATE personnes
         SET consent_privacy = 1, consent_privacy_date = NOW(), cgu_token = NULL
         WHERE id_personne = ? AND cgu_token = ?"
    )->execute([$student['id_personne'], $token]);
    $state = 'accepted';
}

// ─── Render ───────────────────────────────────────────────────────────────────
$headerTpl  = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl  = strtr(file_get_contents(__DIR__ . '/templates/partials/footer.html'), getFooterReplacements());
$headerHtml = strtr($headerTpl, [
    '{{index_active}}'    => '',
    '{{register_active}}' => '',
    '{{login_active}}'    => '',
]);

$name = $student ? htmlspecialchars($student['prenom'] . ' ' . $student['nom'], ENT_QUOTES, 'UTF-8') : '';

switch ($state) {
    case 'already_accepted':
        $icon    = '<i class="fas fa-check-circle text-success" style="font-size:3rem;"></i>';
        $heading = 'Vous avez déjà accepté les CGU';
        $body    = '<p class="text-muted">Bonjour <strong>' . $name . '</strong>, votre consentement a déjà été enregistré. Merci !</p>';
        $action  = '';
        break;

    case 'accepted':
        $icon    = '<i class="fas fa-check-circle text-success" style="font-size:3rem;"></i>';
        $heading = 'Consentement enregistré';
        $body    = '<p class="text-muted">Bonjour <strong>' . $name . '</strong>, votre acceptation des CGU et de la politique de confidentialité a bien été prise en compte. Merci !</p>';
        $action  = '';
        break;

    case 'pending':
        $icon    = '<i class="fas fa-file-contract text-primary" style="font-size:3rem;"></i>';
        $heading = 'Acceptation des CGU';
        $body    = '<p>Bonjour <strong>' . $name . '</strong>,</p>'
            . '<p>En cliquant sur le bouton ci-dessous, vous confirmez avoir lu et accepté les '
            . '<a href="legal-notice.php" target="_blank">Conditions Générales d\'Utilisation et la politique de confidentialité</a> '
            . 'de l\'AEESGS.</p>';
        $action  = '<form method="POST" action="accept-cgu.php?token=' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
            . '<button type="submit" class="btn btn-success btn-lg px-5">'
            . '<i class="fas fa-check me-2"></i>J\'accepte les CGU</button>'
            . '</form>';
        break;

    default: // invalid
        $icon    = '<i class="fas fa-exclamation-triangle text-warning" style="font-size:3rem;"></i>';
        $heading = 'Lien invalide ou expiré';
        $body    = '<p class="text-muted">Ce lien est invalide ou a expiré. Veuillez contacter l\'administration de l\'AEESGS si vous avez besoin d\'un nouveau lien.</p>';
        $action  = '';
        break;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AEESGS – Acceptation des CGU</title>
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<?= $headerHtml ?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
      <div class="card shadow-sm border-0 text-center p-4">
        <div class="mb-3"><?= $icon ?></div>
        <h2 class="h4 mb-3"><?= htmlspecialchars($heading) ?></h2>
        <div class="text-start mb-4"><?= $body ?></div>
        <?= $action ?>
      </div>
    </div>
  </div>
</div>
<?= $footerTpl ?>
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
