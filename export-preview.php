<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$data = $_SESSION['export_data'] ?? null;
$headers = $_SESSION['export_headers'] ?? null;
$filters = $_SESSION['export_filters'] ?? null;

// Clear session data to prevent re-using old exports
unset($_SESSION['export_data']);
unset($_SESSION['export_headers']);
unset($_SESSION['export_filters']);

if (!$data || !$headers) {
    setFlashMessage('error', 'Aucune donnée à exporter ou la session a expiré.');
    header('Location: export.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aperçu avant impression - Export des étudiants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 10pt;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
            }
            .table th, .table td {
                border: 1px solid #dee2e6;
                padding: 0.4rem;
            }
            a {
                text-decoration: none;
                color: #000;
            }
        }
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
        body {
            background-color: #f8f9fa;
        }
        .container {
            background-color: #fff;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="no-print container-fluid bg-light p-3 mb-4 border-bottom">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h4">Aperçu avant impression</h1>
                <div>
                    <a href="export.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
                    <button onclick="window.print();" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer (ou Enregistrer en PDF)</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container p-4">
        <header class="mb-4">
            <h2 class="h3">Liste des Étudiants Exportés</h2>
            <p class="text-muted">Export généré le: <?php echo date('d/m/Y H:i'); ?></p>
        </header>

        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $header))); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?php echo htmlspecialchars($cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
