<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\StudentRepository;
use Amea\Service\ExportService;

class ExportController extends BaseController
{
    private const ALLOWED_FIELDS = [
        'id'                   => '`id`',
        'last_name'            => '`last_name`',
        'first_name'           => '`first_name`',
        'gender'               => '`gender`',
        'birth_date'           => '`birth_date`',
        'phone'                => '`phone`',
        'email'                => '`email`',
        'institution'          => '`institution`',
        'status'               => '`status`',
        'study_field'          => '`study_field`',
        'study_level'          => '`study_level`',
        'residence'            => '`residence`',
        'housing_type'         => '`housing_type`',
        'housing_details'      => '`housing_details`',
        'arrival_year'         => '`arrival_year`',
        'post_training_project'=> '`post_training_project`',
        'registration_date'    => '`registration_date`',
        'graduation_date'      => '`graduation_date`',
    ];

    private ExportService    $exportService;
    private StudentRepository $studentRepo;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        if (!\hasPermission('export')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission d\'exporter des données.');
            $this->redirect('dashboard.php');
            return;
        }
        $db = Container::db();
        $this->exportService = new ExportService($db);
        $this->studentRepo   = new StudentRepository($db);
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->render('admin/pages/export.html.twig', [
            'institutions' => $this->studentRepo->getDistinctInstitutions(),
            'study_levels' => $this->studentRepo->getDistinctStudyLevels(),
            'fields'       => self::ALLOWED_FIELDS,
        ]);
    }

    // ─── preview ───────────────────────────────────────────────────────────────

    public function preview(): void
    {
        $data    = $_SESSION['export_data']    ?? null;
        $headers = $_SESSION['export_headers'] ?? null;
        $filters = $_SESSION['export_filters'] ?? [];

        unset($_SESSION['export_data'], $_SESSION['export_headers'], $_SESSION['export_filters']);

        if (!$data || !$headers) {
            $this->flash->set('error', 'Aucune donnée à prévisualiser ou la session a expiré.');
            $this->redirect('export.php');
            return;
        }

        $this->render('export-preview.html.twig', [
            'headers' => $headers,
            'rows'    => $data,
            'filters' => $filters,
        ]);
    }

    // ─── download ──────────────────────────────────────────────────────────────

    public function download(): void
    {
        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('export.php');
            return;
        }

        $selectedFields = array_values(array_filter(
            $_POST['fields'] ?? [],
            fn($f) => array_key_exists($f, self::ALLOWED_FIELDS)
        ));

        if (empty($selectedFields)) {
            $this->flash->set('error', 'Veuillez sélectionner au moins un champ à exporter.');
            $this->redirect('export.php');
            return;
        }

        $filters = array_filter([
            'gender'       => $_POST['gender'] ?? '',
            'status'       => $_POST['status'] ?? '',
            'institution'  => $_POST['institution'] ?? '',
            'study_level'  => $_POST['study_level'] ?? '',
        ]);

        $format = $_POST['export_format'] ?? 'csv';

        if ($format === 'pdf') {
            $result = $this->exportService->getExportData($selectedFields, self::ALLOWED_FIELDS, $filters);
            $_SESSION['export_data']    = $result['rows'];
            $_SESSION['export_headers'] = $result['headers'];
            $_SESSION['export_filters'] = $filters;
            $this->redirect('export-preview.php');
            return;
        }

        $filename = 'export_aeesgs_' . date('Y-m-d') . '.csv';
        $this->exportService->streamCsv($selectedFields, self::ALLOWED_FIELDS, $filters, $filename);
    }
}
