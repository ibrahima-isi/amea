<?php

namespace Amea\Controller;

use Amea\Service\AuthService;
use Amea\Repository\UserRepository;
use Amea\Config\Database;

class AuthController extends BaseController
{
    private AuthService $auth;

    public function __construct()
    {
        parent::__construct();
        $db = Database::fromEnv()->getConnection();
        $userRepo = new UserRepository($db);
        $this->auth = new AuthService($userRepo, $this->session, $this->flash);
    }

    public function login(): void
    {
        if ($this->session->isLoggedIn()) {
            $this->redirect('dashboard.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($this->auth->attempt($username, $password)) {
                $this->redirect('dashboard.php');
            } else {
                $this->flash->set('error', 'Identifiants invalides.');
            }
        }

        $this->render('templates/login.html');
    }

    public function logout(): void
    {
        $this->auth->logout();
        $this->redirect('login.php');
    }
}
