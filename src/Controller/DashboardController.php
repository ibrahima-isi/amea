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
        $summary         = $this->students->getDashboardSummary();
        $topInstitutions = $this->students->getTopInstitutions(5);

        $role      = $this->session->get('role');
        $canReview = $role === 'admin';
        $firstName = $this->session->get('first_name') ?? '';
        $lastName  = $this->session->get('last_name') ?? '';

        // Build chart data
        $schoolLabels = [];
        $schoolValues = [];
        foreach ($topInstitutions as $row) {
            $schoolLabels[] = $row['institution'] ?? 'Non renseigné';
            $schoolValues[] = (int)($row['count'] ?? 0);
        }

        $this->render('admin/pages/dashboard.html.twig', [
            'user_fullname'       => trim($firstName . ' ' . $lastName),
            'role_label'          => $role === 'admin' ? 'Administrateur' : 'Utilisateur',
            'can_review'          => $canReview,
            'review_queue'        => $canReview ? $this->students->findReviewQueue() : [],
            'review_count'        => $canReview ? $summary['review_count'] : 0,
            'clarification_count' => $canReview ? $summary['clarification_count'] : 0,
            'stats'               => $summary['stats'],
            'recent_week'         => $summary['recent_week'],
            'recent_month'        => $summary['recent_month'],
            'school_labels'       => $schoolLabels,
            'school_values'       => $schoolValues,
        ]);
    }
}
