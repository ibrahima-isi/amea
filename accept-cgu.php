<?php
/**
 * Public CGU acceptance / refusal page.
 * Students arrive here via a tokenised link sent by the admin.
 *
 * States:
 *   invalid          — token missing or not found in DB
 *   pending          — first-time acceptance (consent_privacy = 0)
 *   update           — already accepted before, receiving a CGU-update reminder
 *   accepted         — just accepted (POST)
 *   refused          — just refused (POST) — shows deletion-request option
 *   deletion_requested — deletion request recorded (POST)
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';
require_once 'functions/email-service.php';

// Self-healing columns (may not exist on older installs)
try { $conn->exec("ALTER TABLE personnes ADD COLUMN consent_refused_at DATETIME NULL"); }    catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE personnes ADD COLUMN deletion_requested_at DATETIME NULL"); } catch (PDOException $e) {}

$token = trim($_GET['token'] ?? '');

$student = null;
if ($token !== '') {
    $stmt = $conn->prepare(
        "SELECT id_personne, nom, prenom, email, consent_privacy
         FROM personnes WHERE cgu_token = ? LIMIT 1"
    );
    $stmt->execute([$token]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Derive initial state from DB record
if (!$student) {
    $state = 'invalid';
} elseif ($student['consent_privacy'] == 1) {
    $state = 'update';   // already accepted — this is a CGU update reminder
} else {
    $state = 'pending';  // first-time acceptance
}

// ─── POST actions ─────────────────────────────────────────────────────────────
$postAction = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student && $postAction !== '') {

    if ($postAction === 'accept') {
        $conn->prepare(
            "UPDATE personnes
             SET consent_privacy = 1, consent_privacy_date = NOW(),
                 consent_refused_at = NULL, cgu_token = NULL
             WHERE id_personne = ? AND cgu_token = ?"
        )->execute([$student['id_personne'], $token]);
        $state = 'accepted';

    } elseif ($postAction === 'refuse') {
        // Record refusal and revoke any previous consent
        $conn->prepare(
            "UPDATE personnes
             SET consent_privacy = 0, consent_refused_at = NOW()
             WHERE id_personne = ? AND cgu_token = ?"
        )->execute([$student['id_personne'], $token]);
        $state = 'refused';

    } elseif ($postAction === 'request_deletion') {
        // Mark deletion request and notify admin
        $conn->prepare(
            "UPDATE personnes
             SET deletion_requested_at = NOW(), cgu_token = NULL
             WHERE id_personne = ? AND cgu_token = ?"
        )->execute([$student['id_personne'], $token]);

        // Notify admin
        $adminEmail = $conn->query(
            "SELECT setting_value FROM settings WHERE setting_key = 'contact_email'"
        )->fetchColumn() ?: 'contact@aeesgs.org';

        $adminBody = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;padding:20px;">'
            . '<h2 style="color:#CE1126;">Demande de suppression de données</h2>'
            . '<p>Le membre <strong>' . htmlspecialchars($student['prenom'] . ' ' . $student['nom'], ENT_QUOTES, 'UTF-8') . '</strong>'
            . ' (email&nbsp;: <strong>' . htmlspecialchars($student['email'] ?? '—', ENT_QUOTES, 'UTF-8') . '</strong>,'
            . ' ID&nbsp;: #' . (int)$student['id_personne'] . ')'
            . ' a refusé les CGU et demande la suppression de ses données personnelles.</p>'
            . '<p>Veuillez traiter cette demande conformément à la politique de confidentialité de l\'AEESGS.</p>'
            . '</body></html>';

        sendMail(
            $adminEmail,
            'AEESGS – Demande de suppression de données (#' . (int)$student['id_personne'] . ')',
            $adminBody
        );

        $state = 'deletion_requested';
    }
}

// ─── Build page content per state ─────────────────────────────────────────────
$tokenHtml = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
$name      = $student ? htmlspecialchars($student['prenom'] . ' ' . $student['nom'], ENT_QUOTES, 'UTF-8') : '';

$formOpen  = '<form method="POST" action="accept-cgu.php?token=' . $tokenHtml . '">';
$formClose = '</form>';

switch ($state) {
    case 'pending':
        $icon    = '<i class="fas fa-file-contract" style="font-size:3rem;color:#009460;"></i>';
        $heading = 'Acceptation des CGU';
        $body    = '<p>Bonjour <strong>' . $name . '</strong>,</p>'
            . '<p>L\'AEESGS vous invite à accepter ses '
            . '<a href="legal-notice.php" target="_blank">Conditions Générales d\'Utilisation et politique de confidentialité</a>.'
            . ' Ces documents expliquent comment vos données personnelles sont collectées et protégées.</p>'
            . '<p>Votre consentement est nécessaire pour maintenir votre dossier actif.</p>';
        $action  = $formOpen
            . '<input type="hidden" name="action" value="accept">'
            . '<button type="submit" class="btn btn-success btn-lg px-5 me-3">'
            . '<i class="fas fa-check me-2"></i>J\'accepte les CGU</button>'
            . $formClose
            . $formOpen
            . '<input type="hidden" name="action" value="refuse">'
            . '<button type="submit" class="btn btn-outline-danger mt-2">'
            . '<i class="fas fa-times me-2"></i>Je refuse</button>'
            . $formClose;
        break;

    case 'update':
        $icon    = '<i class="fas fa-file-signature" style="font-size:3rem;color:#009460;"></i>';
        $heading = 'Mise à jour des CGU';
        $body    = '<p>Bonjour <strong>' . $name . '</strong>,</p>'
            . '<p>Nos <a href="legal-notice.php" target="_blank">Conditions Générales d\'Utilisation</a> ont été mises à jour.'
            . ' Veuillez confirmer votre accord pour continuer à bénéficier des services de l\'AEESGS.</p>';
        $action  = $formOpen
            . '<input type="hidden" name="action" value="accept">'
            . '<button type="submit" class="btn btn-success btn-lg px-5 me-3">'
            . '<i class="fas fa-check me-2"></i>Je confirme mon accord</button>'
            . $formClose
            . $formOpen
            . '<input type="hidden" name="action" value="refuse">'
            . '<button type="submit" class="btn btn-outline-danger mt-2">'
            . '<i class="fas fa-times me-2"></i>Je refuse la mise à jour</button>'
            . $formClose;
        break;

    case 'accepted':
        $icon    = '<i class="fas fa-check-circle" style="font-size:3rem;color:#009460;"></i>';
        $heading = 'Consentement enregistré';
        $body    = '<p class="text-muted">Bonjour <strong>' . $name . '</strong>,'
            . ' votre accord avec les CGU a bien été enregistré. Merci !</p>';
        $action  = '';
        break;

    case 'refused':
        $icon    = '<i class="fas fa-exclamation-circle" style="font-size:3rem;color:#CE1126;"></i>';
        $heading = 'Vous avez refusé les CGU';
        $body    = '<p>Bonjour <strong>' . $name . '</strong>,</p>'
            . '<div class="alert alert-warning">'
            . '<strong>Important :</strong> en refusant les CGU, votre dossier ne peut pas être maintenu actif.'
            . ' Conformément au RGPD, vous pouvez demander la suppression de vos données personnelles.'
            . ' L\'équipe AEESGS traitera votre demande dans les meilleurs délais.'
            . '</div>'
            . '<p>Que souhaitez-vous faire ?</p>';
        $action  = $formOpen
            . '<input type="hidden" name="action" value="request_deletion">'
            . '<button type="submit" class="btn btn-danger me-3">'
            . '<i class="fas fa-trash-alt me-2"></i>Demander la suppression de mes données</button>'
            . $formClose
            . '<a href="accept-cgu.php?token=' . $tokenHtml . '" class="btn btn-outline-success mt-2">'
            . '<i class="fas fa-undo me-2"></i>Finalement, j\'accepte les CGU</a>';
        break;

    case 'deletion_requested':
        $icon    = '<i class="fas fa-envelope-open-text" style="font-size:3rem;color:#6c757d;"></i>';
        $heading = 'Demande de suppression envoyée';
        $body    = '<p class="text-muted">Bonjour <strong>' . $name . '</strong>,'
            . ' votre demande de suppression de données a bien été transmise à l\'équipe AEESGS.'
            . ' Nous la traiterons dans les meilleurs délais et vous contacterons si nécessaire.</p>';
        $action  = '';
        break;

    default: // invalid
        $icon    = '<i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f0c040;"></i>';
        $heading = 'Lien invalide ou expiré';
        $body    = '<p class="text-muted">Ce lien est invalide ou a déjà été utilisé.'
            . ' Contactez l\'administration de l\'AEESGS si vous avez besoin d\'un nouveau lien.</p>';
        $action  = '';
        break;
}

// ─── Render ───────────────────────────────────────────────────────────────────
$headerTpl  = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl  = strtr(file_get_contents(__DIR__ . '/templates/partials/footer.html'), getFooterReplacements());
$headerHtml = strtr($headerTpl, [
    '{{index_active}}'    => '',
    '{{register_active}}' => '',
    '{{login_active}}'    => '',
]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AEESGS – CGU</title>
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<?= $headerHtml ?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
      <div class="card shadow-sm border-0 p-4">
        <div class="text-center mb-3"><?= $icon ?></div>
        <h2 class="h4 text-center mb-3"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="mb-4"><?= $body ?></div>
        <div class="d-flex flex-wrap gap-2"><?= $action ?></div>
      </div>
    </div>
  </div>
</div>
<?= $footerTpl ?>
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
