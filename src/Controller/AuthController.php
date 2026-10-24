<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Core\StaffNavigation;
use Amea\Service\AuthService;
use Amea\Repository\UserRepository;

class AuthController extends BaseController
{
    private AuthService $auth;

    public function __construct()
    {
        parent::__construct();
        $userRepo = new UserRepository(Container::db());
        $this->auth = new AuthService($userRepo, $this->session, $this->flash);
    }

    public function login(): void
    {
        $staffApplication = defined('AMEA_APP') && AMEA_APP === 'staff';
        if ($staffApplication && array_key_exists('return_to', $_GET)) {
            $requested = $_GET['return_to'];
            $target = is_string($requested) ? StaffNavigation::localTarget($requested) : null;
            $this->session->remove('staff_return_to');
            if ($target !== null) {
                $this->session->set('staff_return_to', $target);
            }
        }
        if ($this->session->isLoggedIn()) {
            $this->redirect($staffApplication ? StaffNavigation::loginDestination($this->session) : 'dashboard.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
                $this->redirect('login.php');
                return;
            }

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($this->auth->attempt($username, $password)) {
                $this->redirect($staffApplication ? StaffNavigation::loginDestination($this->session) : 'dashboard.php');
            } else {
                $this->flash->set('error', 'Identifiants invalides.');
            }
        }

        $this->render('auth/login.html.twig');
    }

    public function logout(): void
    {
        $this->auth->logout();
        $this->redirect('login.php');
    }
}
