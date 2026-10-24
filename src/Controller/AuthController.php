<?php

namespace Amea\Controller;

use Amea\Core\Container;
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
        if ($this->session->isLoggedIn()) {
            if (defined('AMEA_APP') && AMEA_APP === 'staff') {
                $this->render('auth/ready.html.twig');
                return;
            }
            $this->redirect('dashboard.php');
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
                $this->redirect(defined('AMEA_APP') && AMEA_APP === 'staff' ? 'login.php' : 'dashboard.php');
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
