<?php
/**
 * AMEA - Application de Gestion des Étudiants
/**
 * Page d'exportation des données
 * Fichier: export.php
 */

// Démarrer la session
session_start();

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

// Traitement de l'exportation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    // Récupérer les paramètres d'exportation
    $exportFormat = $_POST['export_format'] ?? 'csv';
    $selectedFields = $_POST['fields'] ?? [];
    $filters = [
        'sexe' => $_POST['sexe'] ?? '',
        'statut' => $_POST['statut'] ?? '',
        'etablissement' => $_POST['etablissement'] ?? '',
        'niveau_etudes' => $_POST['niveau_etudes'] ?? '',
        'type_logement' => $_POST['type_logement'] ?? ''
    ];
    
    // Si aucun champ n'est sélectionné, afficher une erreur
    if (empty($selectedFields)) {
        $error = "Veuillez sélectionner au moins un champ à exporter.";
    } else {
        try {
            // Construire la requête SQL avec les champs sélectionnés
            $fields = implode(', ', $selectedFields);
            $sql = "SELECT $fields FROM personne";
            
            // Ajouter les filtres à la requête SQL
            $whereClauses = [];
            $params = [];
            
            foreach ($filters as $key => $value) {
                if (!empty($value)) {
                    $whereClauses[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }
            
            if (count($whereClauses) > 0) {
                $sql .= " WHERE " . implode(' AND ', $whereClauses);
            }
            
            // Exécuter la requête
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Si aucune donnée n'est trouvée, afficher un message
            if (count($data) === 0) {
                $error = "Aucune donnée ne correspond aux critères sélectionnés.";
            } else {
                // Préparer les en-têtes pour l'exportation
                $headers = array_keys($data[0]);
                
                // Effectuer l'exportation selon le format choisi
                if ($exportFormat === 'csv') {
                    // Exporter au format CSV
                    $filename = 'export_etudiants_' . date('Y-m-d_H-i-s') . '.csv';
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
                } elseif ($exportFormat === 'excel') {
                    // Pour l'exportation Excel, nous allons utiliser le format CSV
                    // car PHP natif ne supporte pas directement l'exportation Excel
                    // Dans une application réelle, vous pourriez utiliser des bibliothèques comme PhpSpreadsheet
                    $filename = 'export_etudiants_' . date('Y-m-d_H-i-s') . '.csv';
                    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
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
                } elseif ($exportFormat === 'json') {
                    // Exporter au format JSON
                    $filename = 'export_etudiants_' . date('Y-m-d_H-i-s') . '.json';
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    
                    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    exit();
                }
            }
        } catch(PDOException $e) {
            $error = "Erreur lors de l'exportation des données : " . $e->getMessage();
        }
    }
}

// Récupérer les options pour les filtres
try {
    // Établissements
    $etablissementSql = "SELECT DISTINCT etablissement FROM personne ORDER BY etablissement";
    $etablissementStmt = $conn->prepare($etablissementSql);
    $etablissementStmt->execute();
    $etablissements = $etablissementStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Niveaux d'études
    $niveauEtudesSql = "SELECT DISTINCT niveau_etudes FROM personne ORDER BY niveau_etudes";
    $niveauEtudesStmt = $conn->prepare($niveauEtudesSql);
    $niveauEtudesStmt->execute();
    $niveauxEtudes = $niveauEtudesStmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $error = "Erreur lors de la récupération des options de filtrage : " . $e->getMessage();
}

// Titre de la page
$pageTitle = "Exporter les données - AMEA";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Styles personnalisés -->
    <link rel="stylesheet" href="assets/css/export.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    
    <!-- Custom Color Palette CSS -->
   
</head>
<body>
    <div class="d-flex" id="wrapper">
       <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page content wrapper -->
        <div id="page-content-wrapper">
            <!-- Top navigation -->
            <nav class="navbar navbar-expand-lg navbar-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($prenom . ' ' . $nom); ?>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="profile.php">Mon profil</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="logout.php">Déconnexion</a>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Page content -->
            <div class="container-fluid p-4">
                <div class="row mb-4">
                    <div class="col">
                        <h1 class="h3">Exporter les données</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Exporter les données</li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold">Exporter les données des étudiants</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="row mb-4">
                                <div class="col-lg-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="m-0">Champs à exporter</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="select_all" onclick="toggleAllFields()">
                                                    <label class="form-check-label fw-bold" for="select_all">
                                                        Sélectionner tous les champs
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Informations personnelles</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="id_personne" id="field_id" checked>
                                                            <label class="form-check-label" for="field_id">ID</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="nom" id="field_nom" checked>
                                                            <label class="form-check-label" for="field_nom">Nom</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="prenom" id="field_prenom" checked>
                                                            <label class="form-check-label" for="field_prenom">Prénom</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="sexe" id="field_sexe" checked>
                                                            <label class="form-check-label" for="field_sexe">Sexe</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="age" id="field_age" checked>
                                                            <label class="form-check-label" for="field_age">Âge</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="date_naissance" id="field_date_naissance">
                                                            <label class="form-check-label" for="field_date_naissance">Date de naissance</label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Contact</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="telephone" id="field_telephone" checked>
                                                            <label class="form-check-label" for="field_telephone">Téléphone</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="email" id="field_email" checked>
                                                            <label class="form-check-label" for="field_email">Email</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Académique</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="etablissement" id="field_etablissement" checked>
                                                            <label class="form-check-label" for="field_etablissement">Établissement</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="statut" id="field_statut" checked>
                                                            <label class="form-check-label" for="field_statut">Statut</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="domaine_etudes" id="field_domaine_etudes">
                                                            <label class="form-check-label" for="field_domaine_etudes">Domaine d'études</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="niveau_etudes" id="field_niveau_etudes">
                                                            <label class="form-check-label" for="field_niveau_etudes">Niveau d'études</label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Logement</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="lieu_residence" id="field_lieu_residence">
                                                            <label class="form-check-label" for="field_lieu_residence">Lieu de résidence</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="type_logement" id="field_type_logement">
                                                            <label class="form-check-label" for="field_type_logement">Type de logement</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="precision_logement" id="field_precision_logement">
                                                            <label class="form-check-label" for="field_precision_logement">Précisions sur le logement</label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Autres</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="annee_arrivee" id="field_annee_arrivee">
                                                            <label class="form-check-label" for="field_annee_arrivee">Année d'arrivée</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="projet_apres_formation" id="field_projet_apres_formation">
                                                            <label class="form-check-label" for="field_projet_apres_formation">Projet après formation</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input field-checkbox" type="checkbox" name="fields[]" value="date_enregistrement" id="field_date_enregistrement">
                                                            <label class="form-check-label" for="field_date_enregistrement">Date d'enregistrement</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="m-0">Filtres et format d'exportation</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-4">
                                                <label class="form-label fw-bold">Filtrer les données</label>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label for="sexe" class="form-label">Sexe</label>
                                                        <select class="form-select" id="sexe" name="sexe">
                                                            <option value="">Tous</option>
                                                            <option value="Masculin">Masculin</option>
                                                            <option value="Féminin">Féminin</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="statut" class="form-label">Statut</label>
                                                        <select class="form-select" id="statut" name="statut">
                                                            <option value="">Tous</option>
                                                            <option value="Élève">Élève</option>
                                                            <option value="Étudiant">Étudiant</option>
                                                            <option value="Stagiaire">Stagiaire</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="etablissement" class="form-label">Établissement</label>
                                                        <select class="form-select" id="etablissement" name="etablissement">
                                                            <option value="">Tous</option>
                                                            <?php foreach ($etablissements as $etablissement): ?>
                                                                <option value="<?php echo htmlspecialchars($etablissement); ?>">
                                                                    <?php echo htmlspecialchars($etablissement); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="niveau_etudes" class="form-label">Niveau d'études</label>
                                                        <select class="form-select" id="niveau_etudes" name="niveau_etudes">
                                                            <option value="">Tous</option>
                                                            <?php foreach ($niveauxEtudes as $niveau): ?>
                                                                <option value="<?php echo htmlspecialchars($niveau); ?>">
                                                                    <?php echo htmlspecialchars($niveau); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="type_logement" class="form-label">Type de logement</label>
                                                        <select class="form-select" id="type_logement" name="type_logement">
                                                            <option value="">Tous</option>
                                                            <option value="En famille">En famille</option>
                                                            <option value="En colocation">En colocation</option>
                                                            <option value="En résidence universitaire">En résidence universitaire</option>
                                                            <option value="Autre">Autre</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <label class="form-label fw-bold">Format d'exportation</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="export_format" id="format_csv" value="csv" checked>
                                                    <label class="form-check-label" for="format_csv">
                                                        <i class="fas fa-file-csv me-2"></i> CSV (compatible Excel)
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="export_format" id="format_excel" value="excel">
                                                    <label class="form-check-label" for="format_excel">
                                                        <i class="fas fa-file-excel me-2"></i> Excel (CSV formaté pour Excel)
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="export_format" id="format_json" value="json">
                                                    <label class="form-check-label" for="format_json">
                                                        <i class="fas fa-file-code me-2"></i> JSON
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i> L'exportation inclura uniquement les champs sélectionnés et respectera les filtres définis ci-dessus.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                                </a>
                                <button type="submit" name="export" class="btn btn-primary">
                                    <i class="fas fa-file-export"></i> Exporter les données
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personnalisés -->
    <script src="assets/js/dashboard.js"></script>
    
    <script>
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('wrapper').classList.toggle('toggled');
        });
        
        // Fonction pour sélectionner/désélectionner tous les champs
        function toggleAllFields() {
            const selectAllCheckbox = document.getElementById('select_all');
            const fieldCheckboxes = document.querySelectorAll('.field-checkbox');
            
            fieldCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }
        
        // Mettre à jour l'état du checkbox "Sélectionner tous" en fonction des sélections individuelles
        document.querySelectorAll('.field-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.field-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.field-checkbox:checked');
                
                document.getElementById('select_all').checked = allCheckboxes.length === checkedCheckboxes.length;
                document.getElementById('select_all').indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            });
        });
    </script>
</body>
</html>