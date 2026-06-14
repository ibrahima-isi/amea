<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\StudentRepository;

class DashboardController extends BaseController
{
    private StudentRepository $students;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->students = new StudentRepository(Container::db());
    }

    public function index(): void
    {
        $stats       = $this->students->getStats();
        $recentWeek  = $this->students->getRecentCount(7);
        $recentMonth = $this->students->getRecentCount(30);
        $topEtabs    = $this->students->getTopEtablissements(5);

        $role      = $this->session->get('role');
        $firstName = $this->session->get('first_name') ?? '';
        $lastName  = $this->session->get('last_name') ?? '';

        // Build chart data
        $schoolLabels = [];
        $schoolValues = [];
        foreach ($topEtabs as $row) {
            $schoolLabels[] = json_encode($row['nom'] ?? 'Non renseigné');
            $schoolValues[] = (int)($row['count'] ?? 0);
        }

        $this->render('admin/pages/dashboard.html.twig', [
            'user_fullname'   => trim($firstName . ' ' . $lastName),
            'role_label'      => $role === 'admin' ? 'Administrateur' : 'Utilisateur',
            'stats'           => $stats,
            'recent_week'     => $recentWeek,
            'recent_month'    => $recentMonth,
            'school_labels'   => implode(', ', $schoolLabels),
            'school_values'   => implode(', ', $schoolValues),
            'can_see_students' => true,
            'can_export'      => $role === 'admin',
        ]);
    }
}
