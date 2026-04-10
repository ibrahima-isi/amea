<?php

/**
 * Student details page.
 * File: student-details.php
 */

// Démarrer la session
require_once 'config/session.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    header("Location: dashboard.php");
    exit();
}

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Récupérer les informations de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Vérifier si un ID étudiant est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$student_id = (int)$_GET['id'];

// Récupérer les détails de l'étudiant depuis la base de données
try {
    $sql = "SELECT * FROM personnes WHERE id_personne = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        setFlashMessage('error', 'L\'étudiant demandé n\'existe pas.');
        header("Location: dashboard.php");
        exit();
    }

    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError('Erreur lors de la récupération des détails de l\'étudiant', $e);
    setFlashMessage('error', 'Une erreur est survenue. Veuillez réessayer.');
    header("Location: dashboard.php");
    exit();
}

$layoutPath  = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/student-details.html';
if (!is_file($layoutPath) || !is_file($contentPath)) { http_response_code(500); exit('Template introuvable.'); }

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

$flash      = getFlashMessage();
$flash_json = $flash ? json_encode($flash) : '';

$template = file_get_contents($contentPath);

// =====================================================================
// Resolve identity file (stored as truncated prefix without extension)
// =====================================================================
$identitePath     = $student['identite'] ?? '';
$resolvedIdentity = '';
$identityIsImage  = false;
$identityIsPdf    = false;
$modalHtml        = '';

if (!empty($identitePath)) {
    $ext = strtolower(pathinfo($identitePath, PATHINFO_EXTENSION));
    $identityIsPdf   = ($ext === 'pdf');
    $identityIsImage = in_array($ext, ['png', 'jpg', 'jpeg', 'gif']);

    if (!$identityIsPdf && !$identityIsImage) {
        // No extension stored — locate the real file by prefix
        if (file_exists($identitePath)) {
            $resolvedIdentity = $identitePath;
        } else {
            $hits = glob($identitePath . '*');
            if (!empty($hits)) {
                $resolvedIdentity = $hits[0];
            }
        }
        if (!empty($resolvedIdentity)) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $resolvedIdentity);
            finfo_close($finfo);
            $identityIsPdf   = ($mimeType === 'application/pdf');
            $identityIsImage = (strncmp($mimeType, 'image/', 6) === 0);
        }
    } else {
        $resolvedIdentity = $identitePath;
    }
}

$hasIdentityDoc  = !empty($resolvedIdentity) && ($identityIsImage || $identityIsPdf);
$identiteModalId = 'identiteModal' . $student['id_personne'];
$isLocked        = !empty($student['is_locked']);

// =====================================================================
// Build details block
// =====================================================================
$detailsHtml = '<div class="row">';

// ── LEFT COLUMN ───────────────────────────────────────────────────────
$detailsHtml .= '<div class="col-lg-4 mb-4">';

// Profile card
$detailsHtml .= '<div class="card shadow-sm border-0 mb-4"><div class="card-body text-center pt-4">';

if ($identityIsImage && !empty($resolvedIdentity)) {
    $detailsHtml .= '<a href="#" data-bs-toggle="modal" data-bs-target="#' . $identiteModalId . '">';
    $detailsHtml .= '<img src="' . htmlspecialchars($resolvedIdentity) . '" alt="Photo" class="rounded-circle img-thumbnail mb-3" style="width:150px;height:150px;object-fit:cover;">';
    $detailsHtml .= '</a>';
} elseif ($identityIsPdf && !empty($resolvedIdentity)) {
    $detailsHtml .= '<a href="#" data-bs-toggle="modal" data-bs-target="#' . $identiteModalId . '" class="d-inline-block mb-3 text-decoration-none">';
    $detailsHtml .= '<div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width:150px;height:150px;">';
    $detailsHtml .= '<i class="fas fa-file-pdf fa-4x text-danger"></i></div></a>';
} else {
    $detailsHtml .= '<img src="assets/img/placeholder.png" alt="Profil" class="rounded-circle img-thumbnail mb-3" style="width:150px;height:150px;object-fit:cover;">';
}

$detailsHtml .= '<h4 class="card-title mb-1">' . htmlspecialchars(($student['prenom'] ?? '') . ' ' . ($student['nom'] ?? '')) . '</h4>';

$statusColor = 'secondary';
$statut = $student['statut'] ?? '';
if (in_array($statut, ['Étudiant', 'ETUDIANT']))       $statusColor = 'primary';
elseif (in_array($statut, ['Élève', 'ELEVE']))         $statusColor = 'info';
elseif (in_array($statut, ['Stagiaire', 'STAGIAIRE'])) $statusColor = 'warning';
elseif (in_array($statut, ['Diplômé', 'DIPLOME']))     $statusColor = 'success';

$detailsHtml .= '<div class="mb-2">';
$detailsHtml .= '<span class="badge bg-' . $statusColor . ' me-1">' . htmlspecialchars($statut ?: 'Inconnu') . '</span>';
if (in_array($statut, ['Diplômé', 'DIPLOME']) && !empty($student['date_diplomation'])) {
    $promoYear = date('Y', strtotime($student['date_diplomation']));
    $detailsHtml .= '<span class="badge bg-warning text-dark me-1"><i class="fas fa-graduation-cap me-1"></i>Promo ' . $promoYear . '</span>';
}
$detailsHtml .= '<span class="badge ' . ($isLocked ? 'bg-success' : 'bg-warning text-dark') . '">';
$detailsHtml .= '<i class="fas ' . ($isLocked ? 'fa-lock' : 'fa-lock-open') . ' me-1"></i>';
$detailsHtml .= ($isLocked ? 'Finalisé' : 'En attente') . '</span>';
$detailsHtml .= '</div>';

$detailsHtml .= '<div class="d-flex justify-content-center gap-3 text-muted mt-1">';
$detailsHtml .= '<div><i class="fas fa-venus-mars me-1"></i>' . htmlspecialchars($student['sexe'] ?? '?') . '</div>';
$detailsHtml .= '<div><i class="fas fa-birthday-cake me-1"></i>' . calculateAge($student['date_naissance']) . ' ans</div>';
$detailsHtml .= '</div>';
$detailsHtml .= '</div></div>'; // end profile card

// Contact card
$detailsHtml .= '<div class="card shadow-sm border-0 mb-4">';
$detailsHtml .= '<div class="card-header bg-white border-0 pt-3 pb-0"><h6 class="fw-bold text-uppercase text-muted small"><i class="fas fa-address-book me-2"></i>Coordonnées</h6></div>';
$detailsHtml .= '<div class="card-body"><ul class="list-unstyled mb-0">';
$detailsHtml .= '<li class="mb-3 d-flex align-items-start"><i class="fas fa-envelope text-primary mt-1 me-3"></i><div><span class="d-block small text-muted">Email</span><a href="mailto:' . htmlspecialchars($student['email'] ?? '') . '" class="text-dark text-decoration-none">' . htmlspecialchars($student['email'] ?? 'N/A') . '</a></div></li>';
$detailsHtml .= '<li class="mb-3 d-flex align-items-start"><i class="fas fa-phone text-success mt-1 me-3"></i><div><span class="d-block small text-muted">Téléphone</span><a href="tel:' . htmlspecialchars($student['telephone'] ?? '') . '" class="text-dark text-decoration-none">' . htmlspecialchars($student['telephone'] ?? 'N/A') . '</a></div></li>';
$detailsHtml .= '<li class="d-flex align-items-start"><i class="fas fa-map-marker-alt text-danger mt-1 me-3"></i><div><span class="d-block small text-muted">Lieu de résidence</span>' . htmlspecialchars($student['lieu_residence'] ?? 'N/A') . '</div></li>';
$detailsHtml .= '</ul></div></div>'; // end contact card

// Registration info card
$detailsHtml .= '<div class="card shadow-sm border-0">';
$detailsHtml .= '<div class="card-header bg-white border-0 pt-3 pb-0"><h6 class="fw-bold text-uppercase text-muted small"><i class="fas fa-clipboard-list me-2"></i>Inscription</h6></div>';
$detailsHtml .= '<div class="card-body"><ul class="list-unstyled mb-0">';
$detailsHtml .= '<li class="mb-3"><span class="d-block small text-muted">Enregistré le</span><span class="fw-bold">' . formatDateFr($student['date_enregistrement'], true) . '</span></li>';
$detailsHtml .= '<li class="mb-3"><span class="d-block small text-muted">Statut du dossier</span>';
$detailsHtml .= '<span class="badge ' . ($isLocked ? 'bg-success' : 'bg-warning text-dark') . '">' . ($isLocked ? 'Finalisé' : 'En cours') . '</span></li>';
if (in_array($statut, ['Diplômé', 'DIPLOME']) && !empty($student['date_diplomation'])) {
    $detailsHtml .= '<li class="mb-3"><span class="d-block small text-muted">Date de diplomation</span><span class="fw-bold text-success">' . formatDateFr($student['date_diplomation']) . '</span></li>';
}
$hasConsent = !empty($student['consent_privacy']);
$detailsHtml .= '<li><span class="d-block small text-muted">Consentement RGPD</span>';
if ($hasConsent) {
    $detailsHtml .= '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Accordé</span>';
    if (!empty($student['consent_privacy_date'])) {
        $detailsHtml .= '<span class="d-block small text-muted">le ' . formatDateFr($student['consent_privacy_date'], true) . '</span>';
    }
} else {
    $detailsHtml .= '<span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i>Non accordé</span>';
}
$detailsHtml .= '</li></ul></div></div>'; // end registration card

$detailsHtml .= '</div>'; // end left col

// ── RIGHT COLUMN ──────────────────────────────────────────────────────
$detailsHtml .= '<div class="col-lg-8">';

// Academic card
$detailsHtml .= '<div class="card shadow-sm border-0 mb-4">';
$detailsHtml .= '<div class="card-header bg-transparent border-bottom py-3"><h5 class="mb-0 text-white"><i class="fas fa-graduation-cap me-2"></i>Parcours Académique</h5></div>';
$detailsHtml .= '<div class="card-body"><div class="row g-3">';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Établissement</small><span class="fw-bold text-dark">' . htmlspecialchars($student['etablissement'] ?? 'Non spécifié') . '</span></div></div>';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Domaine d\'Études</small><span class="fw-bold text-dark">' . htmlspecialchars($student['domaine_etudes'] ?? 'Non spécifié') . '</span></div></div>';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Niveau d\'Études</small><span class="fw-bold text-dark">' . htmlspecialchars($student['niveau_etudes'] ?? 'Non spécifié') . '</span></div></div>';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Année d\'arrivée au Sénégal</small><span class="fw-bold text-dark">' . htmlspecialchars($student['annee_arrivee'] ?? 'N/A') . '</span></div></div>';
$detailsHtml .= '</div></div></div>'; // end academic card

// Personal info card
$detailsHtml .= '<div class="card shadow-sm border-0 mb-4">';
$detailsHtml .= '<div class="card-header bg-transparent border-bottom py-3"><h5 class="mb-0 text-white"><i class="fas fa-user me-2"></i>Informations Personnelles</h5></div>';
$detailsHtml .= '<div class="card-body"><div class="row g-3">';

// Nationalities
$stmtNats = $conn->prepare("SELECT p.nom_fr FROM pays p JOIN personne_pays pp ON p.id_pays = pp.id_pays WHERE pp.id_personne = :id");
$stmtNats->execute([':id' => $student_id]);
$nationalities = $stmtNats->fetchAll(PDO::FETCH_COLUMN);
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-2">Nationalité(s)</small>';
if (!empty($nationalities)) {
    foreach ($nationalities as $nat) {
        $detailsHtml .= '<span class="badge bg-secondary me-1">' . htmlspecialchars($nat, ENT_QUOTES, 'UTF-8') . '</span>';
    }
} elseif (!empty($student['nationalites'])) {
    $nats = json_decode($student['nationalites'], true);
    if (is_array($nats)) {
        foreach ($nats as $nat) {
            $val = is_array($nat) ? ($nat['value'] ?? '') : $nat;
            if ($val) $detailsHtml .= '<span class="badge bg-secondary me-1">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</span>';
        }
    } else {
        $detailsHtml .= '<span class="text-muted">N/A</span>';
    }
} else {
    $detailsHtml .= '<span class="text-muted">N/A</span>';
}
$detailsHtml .= '</div></div>';

$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Date de Naissance</small><span class="fw-bold text-dark">' . formatDateFr($student['date_naissance']) . '</span></div></div>';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Numéro ID / Passeport</small><span class="fw-bold text-dark">' . htmlspecialchars($student['numero_identite'] ?? 'N/A') . '</span></div></div>';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Type de Logement</small><span class="fw-bold text-dark">' . htmlspecialchars($student['type_logement'] ?? 'N/A') . '</span></div></div>';
if (!empty($student['precision_logement'])) {
    $detailsHtml .= '<div class="col-12"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Précision Logement</small><span class="fw-bold text-dark">' . htmlspecialchars($student['precision_logement']) . '</span></div></div>';
}
$detailsHtml .= '</div></div></div>'; // end personal card

// Documents card
$detailsHtml .= '<div class="card shadow-sm border-0 mb-4">';
$detailsHtml .= '<div class="card-header bg-transparent border-bottom py-3"><h5 class="mb-0 text-white"><i class="fas fa-folder-open me-2"></i>Documents</h5></div>';
$detailsHtml .= '<div class="card-body">';

// Identity document
$detailsHtml .= '<div class="mb-4">';
$detailsHtml .= '<strong class="d-block text-muted small mb-2"><i class="fas fa-id-card me-1"></i>Pièce d\'identité / Photo</strong>';
if ($hasIdentityDoc) {
    $detailsHtml .= '<div class="d-flex flex-wrap gap-2">';
    $detailsHtml .= '<a href="download.php?id=' . $student['id_personne'] . '&type=identite" class="btn btn-sm btn-info"><i class="fas fa-download me-1"></i>Télécharger</a>';
    $detailsHtml .= '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#' . $identiteModalId . '"><i class="fas fa-eye me-1"></i>Voir</button>';
    $detailsHtml .= '</div>';
    if ($identityIsImage) {
        $modalHtml .= '<div class="modal fade" id="' . $identiteModalId . '" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Pièce d\'identité</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body text-center"><img src="' . htmlspecialchars($resolvedIdentity) . '" class="img-fluid"></div></div></div></div>';
    } else {
        $modalHtml .= '<div class="modal fade" id="' . $identiteModalId . '" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content" style="height:90vh;"><div class="modal-header"><h5 class="modal-title">Pièce d\'identité</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body p-0"><iframe src="' . htmlspecialchars($resolvedIdentity) . '" width="100%" height="100%" style="border:none;"></iframe></div></div></div></div>';
    }
} else {
    $detailsHtml .= '<span class="text-muted fst-italic">Aucun document fourni</span>';
}
$detailsHtml .= '</div>';

// CV
$detailsHtml .= '<div>';
$detailsHtml .= '<strong class="d-block text-muted small mb-2"><i class="fas fa-file-alt me-1"></i>Curriculum Vitae (CV)</strong>';
if (!empty($student['cv_path'])) {
    $cvPath    = $student['cv_path'];
    $cvExt     = strtolower(pathinfo($cvPath, PATHINFO_EXTENSION));
    $isCvPdf   = ($cvExt === 'pdf');
    $isCvImage = in_array($cvExt, ['png', 'jpg', 'jpeg', 'gif']);
    $cvModalId = 'cvModal' . $student['id_personne'];
    $detailsHtml .= '<div class="d-flex flex-wrap gap-2">';
    $detailsHtml .= '<a href="download.php?id=' . $student['id_personne'] . '&type=cv" class="btn btn-sm btn-info"><i class="fas fa-download me-1"></i>Télécharger</a>';
    if ($isCvPdf || $isCvImage) {
        $detailsHtml .= '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#' . $cvModalId . '"><i class="fas fa-eye me-1"></i>Voir</button>';
        if ($isCvPdf) {
            $detailsHtml .= '<button type="button" class="btn btn-sm btn-secondary" onclick="printPdf(\'' . htmlspecialchars($cvPath, ENT_QUOTES) . '\')"><i class="fas fa-print me-1"></i>Imprimer</button>';
        }
        $modalHtml .= '<div class="modal fade" id="' . $cvModalId . '" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content" style="height:90vh;"><div class="modal-header"><h5 class="modal-title">CV — ' . htmlspecialchars($student['prenom'] . ' ' . $student['nom']) . '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body text-center p-0">';
        $modalHtml .= $isCvPdf
            ? '<iframe src="' . htmlspecialchars($cvPath) . '" width="100%" height="100%" style="border:none;"></iframe>'
            : '<img src="' . htmlspecialchars($cvPath) . '" class="img-fluid" style="max-height:100%;max-width:100%;">';
        $modalHtml .= '</div></div></div></div>';
    }
    $detailsHtml .= '</div>';
} else {
    $detailsHtml .= '<span class="text-muted fst-italic">Aucun CV fourni</span>';
}
$detailsHtml .= '</div>';

$detailsHtml .= '</div></div>'; // end documents card

// Project card
if (!empty($student['projet_apres_formation'])) {
    $detailsHtml .= '<div class="card shadow-sm border-0">';
    $detailsHtml .= '<div class="card-header bg-transparent border-bottom py-3"><h5 class="mb-0 text-white"><i class="fas fa-rocket me-2"></i>Projet Professionnel</h5></div>';
    $detailsHtml .= '<div class="card-body"><p class="card-text text-dark" style="line-height:1.6;">' . nl2br(htmlspecialchars($student['projet_apres_formation'])) . '</p></div></div>';
}

$detailsHtml .= '</div>'; // end right col
$detailsHtml .= '</div>'; // end row

$detailsHtml .= '<script>
function printPdf(url) {
    var iframe = document.createElement("iframe");
    iframe.style.display = "none";
    iframe.src = url;
    document.body.appendChild(iframe);
    iframe.onload = function() {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(function() { document.body.removeChild(iframe); }, 1000);
    };
}
</script>';
$detailsHtml .= $modalHtml;

// Prepare final replacements
$replacements = [
    '{{flash_block}}' => '',
    '{{details_block}}' => $detailsHtml,
    '{{student_id}}' => $student_id,
    '{{display_name}}' => htmlspecialchars(($student['prenom'] ?? '') . ' ' . ($student['nom'] ?? '')),
    '{{email}}' => htmlspecialchars($student['email'] ?? ''),
    '{{csrf_token}}' => generateCsrfToken()
];

// Perform content replacement
$contentHtml = strtr($template, $replacements);

// Layout
$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{flash_json}}' => $flash_json,
    '{{title}}' => 'AEESGS - Détails de ' . htmlspecialchars($student['prenom'] . ' ' . $student['nom'], ENT_QUOTES, 'UTF-8'),
    '{{sidebar}}' => $sidebarHtml,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);
exit();
