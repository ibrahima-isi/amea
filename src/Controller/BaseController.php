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

    public function __construct(bool $startSession = true)
    {
        $projectRoot = __DIR__ . '/../../';
        $this->view = new View($projectRoot);
        $this->session = new Session();
        if ($startSession) {
            $this->session->start();
        }
        $this->flash = new Flash($this->session);

        $this->injectGlobalVariables($startSession);
    }

    protected function render(string $template, array $data = []): void
    {
        echo $this->view->render($template, $data);
    }

    private function injectGlobalVariables(bool $includeSession): void
    {
        $twig = $this->view->getTwig();
        
        // Settings
        $settingRepo = new \Amea\Repository\SettingRepository(\Amea\Core\Container::db());
        
        $twig->addGlobal('public_logo_available', @getimagesize(__DIR__ . '/../../assets/img/logo.png') !== false);
        $publicOrigin = defined('AMEA_APP') && AMEA_APP === 'staff' ? \Amea\Core\StaffNavigation::publicOrigin() : null;
        $twig->addGlobal('public_home_url', $publicOrigin !== null ? $publicOrigin . '/' : '/');
        $twig->addGlobal('public_register_url', $publicOrigin !== null ? $publicOrigin . '/register.php' : 'register.php');
        $sessionData = $includeSession ? ($_SESSION ?? []) : [];
        $authenticated = !empty($sessionData['user_id']);
        $admin = $authenticated && ($sessionData['role'] ?? null) === 'admin';
        $twig->addGlobal('staff_navigation', [
            'dashboard' => $authenticated,
            'students' => $authenticated,
            'trash' => $admin && \hasPermission('students'),
            'export' => $authenticated && \hasPermission('export'),
            'kyc' => $admin,
            'users' => $admin && \hasPermission('users'),
            'journal' => $admin && \hasPermission('journal'),
            'slider' => $admin && \hasPermission('slider'),
            'upgrade' => $admin && \hasPermission('upgrade'),
            'communications' => $admin && \hasPermission('communications'),
            'settings' => $admin && \hasPermission('settings'),
        ]);
        $twig->addGlobal('staff_route', basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
        $twig->addGlobal('session', $sessionData);
        $twig->addGlobal('flash', $includeSession ? $this->flash->get() : null);
        $twig->addGlobal('csrf_token', $includeSession ? \generateCsrfToken() : '');
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
