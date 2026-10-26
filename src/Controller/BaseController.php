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
        $settingRepo = new \Amea\Repository\SettingRepository(\Amea\Core\Container::db());
        
        $twig->addGlobal('public_logo_available', @getimagesize(__DIR__ . '/../../assets/img/logo.png') !== false);
        $staffOrigin = \Amea\Core\StaffNavigation::origin();
        $publicOrigin = defined('AMEA_APP') && AMEA_APP === 'staff' ? \Amea\Core\StaffNavigation::publicOrigin() : null;
        $twig->addGlobal('staff_login_url', $staffOrigin !== null ? $staffOrigin . '/login.php' : 'login.php');
        $twig->addGlobal('public_home_url', $publicOrigin !== null ? $publicOrigin . '/' : '/');
        $twig->addGlobal('public_register_url', $publicOrigin !== null ? $publicOrigin . '/register.php' : 'register.php');
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
            $target = \Amea\Core\StaffNavigation::requestedTarget();
            if ($target !== null) {
                $this->session->set('staff_return_to', $target);
            }
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
