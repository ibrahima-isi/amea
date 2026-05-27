<?php

namespace Amea\Controller;

use Amea\Core\TemplateEngine;
use Amea\Core\Session;
use Amea\Core\Flash;

abstract class BaseController
{
    protected TemplateEngine $templateEngine;
    protected Session $session;
    protected Flash $flash;

    public function __construct()
    {
        $this->templateEngine = new TemplateEngine(__DIR__ . '/../../');
        $this->session = new Session();
        $this->session->start();
        $this->flash = new Flash($this->session);
    }

    protected function render(string $templatePath, array $data = []): void
    {
        // Inject Flash message
        $flash = $this->flash->get();
        $data['flash_json'] = $flash ? json_encode($flash) : '';

        // Handle Header/Footer
        $headerTpl = file_get_contents(__DIR__ . '/../../templates/partials/header.html');
        $footerTpl = file_get_contents(__DIR__ . '/../../templates/partials/footer.html');

        $db = \Amea\Config\Database::fromEnv()->getConnection();
        $settingRepo = new \Amea\Repository\SettingRepository($db);
        $footerReplacements = $settingRepo->getFooterReplacements();
        
        $data['footer'] = strtr($footerTpl, $footerReplacements);
        $data['header'] = strtr($headerTpl, [
            '{{index_active}}' => $data['index_active'] ?? '',
            '{{register_active}}' => $data['register_active'] ?? '',
            '{{login_active}}' => $data['login_active'] ?? '',
        ]);

        echo $this->templateEngine->render($templatePath, $data);
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
