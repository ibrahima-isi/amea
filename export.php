<?php

/**
 * Data export page.
 * File: export.php
 */

require_once 'config/session.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
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

// Initialiser les variables d'erreur et de succès
$error = "";
$success = "";
$csrfToken = generateCsrfToken();

$allowedExportFields = [
    'id_personne' => '`id_personne`',
    'nom' => '`nom`',
    'prenom' => '`prenom`',
    'sexe' => '`sexe`',
    'age' => '`age`',
    'date_naissance' => '`date_naissance`',
    'telephone' => '`telephone`',
    'email' => '`email`',
    'etablissement' => '`etablissement`',
    'statut' => '`statut`',
    'domaine_etudes' => '`domaine_etudes`',
    'niveau_etudes' => '`niveau_etudes`',
    'lieu_residence' => '`lieu_residence`',
    'type_logement' => '`type_logement`',
    'precision_logement' => '`precision_logement`',
    'annee_arrivee' => '`annee_arrivee`',
    'projet_apres_formation' => '`projet_apres_formation`',
    'date_enregistrement' => '`date_enregistrement`'
];

// Traitement de l'exportation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
        header('Location: export.php');
        exit();
    } else {
        $exportFormat = $_POST['export_format'] ?? 'csv';
        $exportFormat = in_array($exportFormat, ['csv', 'json', 'pdf'], true) ? $exportFormat : 'csv';

        $selectedFields = $_POST['fields'] ?? [];
        $selectedFields = array_values(array_unique(array_filter($selectedFields, function ($field) use ($allowedExportFields) {
            return array_key_exists($field, $allowedExportFields);
        })));

        $allowedFilterKeys = ['sexe', 'statut', 'etablissement', 'niveau_etudes', 'type_logement'];
        $filters = [];
        foreach ($allowedFilterKeys as $key) {
            $filters[$key] = trim($_POST[$key] ?? '');
        }

        if (empty($selectedFields)) {
            $error = "Veuillez sélectionner au moins un champ à exporter.";
        } else {
            try {
                $selectedColumnList = array_map(function ($field) use ($allowedExportFields) {
                    return $allowedExportFields[$field];
                }, $selectedFields);

                $sql = "SELECT " . implode(', ', $selectedColumnList) . " FROM personnes";

                $whereClauses = [];
                $params = [];

                foreach ($filters as $key => $value) {
                    if ($value !== '') {
                        $whereClauses[] = "$key = :$key";
                        $params[":$key"] = $value;
                    }
                }

                if (!empty($whereClauses)) {
                    $sql .= " WHERE " . implode(' AND ', $whereClauses);
                }

                $stmt = $conn->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($data) === 0) {
                    setFlashMessage('warning', 'Aucune donnée ne correspond aux critères sélectionnés.');
                    header('Location: export.php');
                    exit();
                } else {
                    $headers = array_keys($data[0]);

                    if ($exportFormat === 'csv') {
                        $filename = 'export_etudiants_' . date('Y-m-d_H-i-s') . '.csv';
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');

                        $output = fopen('php://output', 'w');
                        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
                        fputcsv($output, $headers, ';', '"', '\\');

                        foreach ($data as $row) {
                            fputcsv($output, $row, ';', '"', '\\');
                        }

                        fclose($output);
                        exit();
                    } elseif ($exportFormat === 'json') {
                        $filename = 'export_etudiants_' . date('Y-m-d_H-i-s') . '.json';
                        header('Content-Type: application/json; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');

                        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        exit();
                    } elseif ($exportFormat === 'pdf') {
                        $_SESSION['export_data'] = $data;
                        $_SESSION['export_headers'] = $headers;
                        $_SESSION['export_filters'] = $filters;
                        header('Location: export-preview.php');
                        exit();
                    }
                }
            } catch (PDOException $e) {
                logError("Erreur lors de l'exportation des données", $e);
                $error = "Une erreur est survenue lors de l'exportation des données. Veuillez réessayer.";
            }
        }
    }

    $csrfToken = generateCsrfToken();
}

// Récupérer les options pour les filtres
try {
    // Établissements
    $etablissementSql = "SELECT DISTINCT etablissement FROM personnes ORDER BY etablissement";
    $etablissementStmt = $conn->prepare($etablissementSql);
    $etablissementStmt->execute();
    $etablissements = $etablissementStmt->fetchAll(PDO::FETCH_COLUMN);

    // Niveaux d'études
    $niveauEtudesSql = "SELECT DISTINCT niveau_etudes FROM personnes ORDER BY niveau_etudes";
    $niveauEtudesStmt = $conn->prepare($niveauEtudesSql);
    $niveauEtudesStmt->execute();
    $niveauxEtudes = $niveauEtudesStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    logError("Erreur lors de la récupération des options de filtrage", $e);
    $error = "Une erreur est survenue lors de la récupération des options de filtrage.";
}

// Titre de la page
// Rendu via layout + contenu
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/export.html';
if (!is_file($layoutPath) || !is_file($contentPath)) { http_response_code(500); exit('Template introuvable.'); }

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

// Blocs d'alerte
$errorBlock = '';
if (!empty($error)) {
    $errorBlock = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
        . '<i class="fas fa-exclamation-triangle me-2"></i> ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}
$successBlock = '';
if (!empty($success)) {
    $successBlock = '<div class="alert alert-success alert-dismissible fade show" role="alert">'
        . '<i class="fas fa-check-circle me-2"></i> ' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}

// Options pour les sélecteurs
$etabOptions = '';
foreach ($etablissements as $etablissement) {
    $etabOptions .= '<option value="' . htmlspecialchars($etablissement, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($etablissement, ENT_QUOTES, 'UTF-8') . '</option>';
}
$niveauOptions = '';
foreach ($niveauxEtudes as $niveau) {
    $niveauOptions .= '<option value="' . htmlspecialchars($niveau, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($niveau, ENT_QUOTES, 'UTF-8') . '</option>';
}

// Contenu
$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{error_block}}' => $errorBlock,
    '{{success_block}}' => $successBlock,
    '{{form_action}}' => htmlspecialchars($_SERVER['PHP_SELF'] ?? 'export.php', ENT_QUOTES, 'UTF-8'),
    '{{csrf_token}}' => htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'),
    '{{etablissement_options}}' => $etabOptions,
    '{{niveau_options}}' => $niveauOptions,
]);

$flash = getFlashMessage();
$flash_script = '';
if ($flash) {
    $flash_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '{$flash['type']}',
                    title: 'Notification',
                    text: '{$flash['message']}',
                });
            });
        </script>
    ";
}

$validation_script = '';
if (!empty($error)) {
    $validation_script = "<script>const validationErrors = " . json_encode(['form' => $error]) . ";</script>";
}

// Layout
$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{flash_script}}' => $flash_script,
    '{{validation_script}}' => $validation_script,
    '{{title}}' => 'AEESGS - Exporter les données',
    '{{sidebar}}' => $sidebarHtml,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);
exit();
