<?php

namespace Amea\Controller;

use Amea\Cache\CachePolicy;
use Amea\Cache\HttpCache;
use Amea\Core\Container;
use Amea\Core\CsrfGuard;
use Amea\Core\Flash;
use Amea\Core\Session;
use Amea\Core\StaffNavigation;
use Amea\Core\View;
use Amea\Repository\SettingRepository;
use Amea\Repository\UserRepository;
use Amea\Service\AuthService;

abstract class BaseController
{
    /** @var array<string, bool> */
    private static array $logoCache = [];

    protected View $view;
    protected Session $session;
    protected Flash $flash;
    protected CsrfGuard $csrf;
    protected AuthService $auth;
    private bool $viewInitialized = false;
    private bool $includeSession;
    private string $projectRoot;

    public function __construct(bool $startSession = true, bool $deferView = false)
    {
        $this->projectRoot = __DIR__ . '/../../';
        $this->includeSession = $startSession;
        $this->session = new Session();
        if ($startSession) {
            $this->session->start();
            HttpCache::sendNoStore(true);
        }
        $this->flash = new Flash($this->session);
        $this->csrf = new CsrfGuard($this->session);
        $this->auth = new AuthService(new UserRepository(Container::db()), $this->session, $this->flash);

        if (!$deferView) {
            $this->ensureView();
        }
    }

    protected function ensureView(): void
    {
        if ($this->viewInitialized) {
            return;
        }
        $this->view = new View($this->projectRoot);
        $this->injectGlobalVariables($this->includeSession);
        $this->viewInitialized = true;
    }

    protected function render(string $template, array $data = []): void
    {
        $this->ensureView();
        echo $this->view->render($template, $data);
    }

    protected function renderPublicCached(
        string $template,
        array $data,
        CachePolicy $policy,
        bool $cacheable = true
    ): void {
        $this->ensureView();
        $html = $this->view->render($template, $data);
        HttpCache::respondPublicHtml($html, $policy, cacheable: $cacheable);
    }

    private function injectGlobalVariables(bool $includeSession): void
    {
        $twig = $this->view->getTwig();

        // Single cached query for all settings (0 queries on AppCache hit).
        $settingsMap = (new SettingRepository(Container::db()))->getAllMap();

        $logoPath = $this->projectRoot . 'assets/img/logo.png';
        if (!array_key_exists($logoPath, self::$logoCache)) {
            self::$logoCache[$logoPath] = is_file($logoPath) && @getimagesize($logoPath) !== false;
        }
        $twig->addGlobal('public_logo_available', self::$logoCache[$logoPath]);
        $publicOrigin = defined('AMEA_APP') && AMEA_APP === 'staff' ? StaffNavigation::publicOrigin() : null;
        $twig->addGlobal('public_home_url', $publicOrigin !== null ? $publicOrigin . '/' : '/');
        $twig->addGlobal('public_register_url', $publicOrigin !== null ? $publicOrigin . '/register.php' : 'register.php');
        $sessionData = $includeSession ? ($_SESSION ?? []) : [];
        $authenticated = !empty($sessionData['user_id']);
        $admin = $authenticated && ($sessionData['role'] ?? null) === 'admin';
        $twig->addGlobal('staff_navigation', [
            'dashboard' => $authenticated,
            'students' => $authenticated,
            'trash' => $admin && $this->auth->hasPermission('students'),
            'export' => $authenticated && $this->auth->hasPermission('export'),
            'kyc' => $admin,
            'users' => $admin && $this->auth->hasPermission('users'),
            'journal' => $admin && $this->auth->hasPermission('journal'),
            'slider' => $admin && $this->auth->hasPermission('slider'),
            'upgrade' => $admin && $this->auth->hasPermission('upgrade'),
            'communications' => $admin && $this->auth->hasPermission('communications'),
            'settings' => $admin && $this->auth->hasPermission('settings'),
        ]);
        $twig->addGlobal('staff_route', basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
        $twig->addGlobal('session', $sessionData);
        $twig->addGlobal('flash', $includeSession ? $this->flash->get() : null);
        $twig->addGlobal('csrf_token', $includeSession ? $this->csrf->getToken() : '');
        $twig->addGlobal('settings', [
            'contact_email' => $settingsMap['contact_email'] ?? '',
            'contact_phone' => $settingsMap['contact_phone'] ?? '',
            'association_name' => $settingsMap['association_name'] ?? 'AEESGS',
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
            $target = StaffNavigation::requestedTarget();
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
