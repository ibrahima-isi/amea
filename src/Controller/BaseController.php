<?php

namespace Amea\Controller;

use Amea\Core\View;
use Amea\Core\Session;
use Amea\Core\Flash;

abstract class BaseController
{
    protected View $view;
    protected Session $session;
    protected Flash $flash;

    public function __construct()
    {
        $projectRoot = __DIR__ . '/../../';
        $this->view = new View($projectRoot);
        $this->session = new Session();
        $this->session->start();
        $this->flash = new Flash($this->session);

        $this->injectGlobalVariables();
    }

    protected function render(string $template, array $data = []): void
    {
        echo $this->view->render($template, $data);
    }

    private function injectGlobalVariables(): void
    {
        $twig = $this->view->getTwig();
        
        // Settings
        $db = \Amea\Config\Database::fromEnv()->getConnection();
        $settingRepo = new \Amea\Repository\SettingRepository($db);
        
        $twig->addGlobal('session', $_SESSION);
        $twig->addGlobal('flash', $this->flash->get());
        $twig->addGlobal('csrf_token', \generateCsrfToken());
        $twig->addGlobal('settings', [
            'contact_email' => $settingRepo->getValue('contact_email'),
            'contact_phone' => $settingRepo->getValue('contact_phone'),
            'association_name' => $settingRepo->getValue('association_name', 'AEESGS'),
            'year' => date('Y')
        ]);
    }

    protected function redirect(string $url): void
    {
        header("Location: $url");
        exit();
    }

    protected function requireAuth(): void
    {
        if (!$this->session->get('user_id')) {
            $this->redirect('login.php');
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if ($this->session->get('role') !== 'admin') {
            $this->flash->set('error', 'Accès non autorisé.');
            $this->redirect('dashboard.php');
        }
    }
}
