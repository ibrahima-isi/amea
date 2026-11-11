<?php

namespace Amea\Core;

use Amea\Config\Environment;
use Amea\Controller\AcceptTermsController;
use Amea\Controller\AuthController;
use Amea\Controller\CommunicationController;
use Amea\Controller\CorrectionController;
use Amea\Controller\DashboardController;
use Amea\Controller\DownloadController;
use Amea\Controller\ExportController;
use Amea\Controller\HomeController;
use Amea\Controller\JournalAdminController;
use Amea\Controller\JournalPublicController;
use Amea\Controller\KYCController;
use Amea\Controller\LegalController;
use Amea\Controller\PasswordResetController;
use Amea\Controller\ProfileController;
use Amea\Controller\RegistrationController;
use Amea\Controller\SettingsController;
use Amea\Controller\SliderController;
use Amea\Controller\StudentController;
use Amea\Controller\TrashController;
use Amea\Controller\UpgradeController;
use Amea\Controller\UserController;

class Router
{
    private const PUBLIC_ROUTES = [
        '/' => ['controller' => HomeController::class, 'methods' => ['GET' => 'index']],
        '/journal.php' => ['controller' => JournalPublicController::class, 'methods' => ['GET' => 'index'], 'session' => false],
        '/journal-article.php' => ['controller' => JournalPublicController::class, 'methods' => ['GET' => 'show'], 'session' => false],
        '/journal-cover.php' => ['controller' => JournalPublicController::class, 'methods' => ['GET' => 'cover'], 'session' => false],
        '/register.php' => ['controller' => RegistrationController::class, 'methods' => ['GET' => 'showForm', 'POST' => 'register']],
        '/registration-details.php' => ['controller' => RegistrationController::class, 'methods' => ['GET' => 'review', 'POST' => 'confirm']],
        '/registration-preview.php' => ['controller' => RegistrationController::class, 'methods' => ['GET' => 'preview']],
        '/kyc-correction.php' => ['controller' => CorrectionController::class, 'methods' => ['GET' => 'edit', 'POST' => 'update']],
        '/accept-cgu.php' => ['controller' => AcceptTermsController::class, 'methods' => ['GET' => 'accept', 'POST' => 'accept']],
        '/legal-notice.php' => ['controller' => LegalController::class, 'methods' => ['GET' => 'index']],
    ];

    private const STAFF_ROUTES = [
        '/' => ['methods' => ['GET' => 'redirect'], 'redirect' => '/login.php'],
        '/login.php' => ['controller' => AuthController::class, 'methods' => ['GET' => 'login', 'POST' => 'login']],
        '/logout.php' => ['controller' => AuthController::class, 'methods' => ['GET' => 'logout']],
        '/forgot-password.php' => ['controller' => PasswordResetController::class, 'methods' => ['GET' => 'forgotForm', 'POST' => 'forgotSubmit']],
        '/reset-password.php' => ['controller' => PasswordResetController::class, 'methods' => ['GET' => 'resetForm', 'POST' => 'resetSubmit']],
        '/dashboard.php' => ['controller' => DashboardController::class, 'methods' => ['GET' => 'index']],
        '/students.php' => ['controller' => StudentController::class, 'methods' => ['GET' => 'index', 'POST' => 'delete']],
        '/student-details.php' => ['controller' => StudentController::class, 'methods' => ['GET' => 'show']],
        '/edit-student.php' => ['controller' => StudentController::class, 'methods' => ['GET' => 'edit', 'POST' => 'update']],
        '/kyc-list.php' => ['controller' => KYCController::class, 'methods' => ['GET' => 'index']],
        '/kyc-detail.php' => ['controller' => KYCController::class, 'methods' => ['GET' => 'review', 'POST' => 'decide']],
        '/trash.php' => ['controller' => TrashController::class, 'methods' => ['GET' => 'index', 'POST' => 'handlePost']],
        '/download.php' => ['controller' => DownloadController::class, 'methods' => ['GET' => 'serve']],
        '/manage-slider.php' => ['controller' => SliderController::class, 'methods' => ['GET' => 'index', 'POST' => 'handlePost']],
        '/manage-journal.php' => ['controller' => JournalAdminController::class, 'methods' => ['GET' => 'index']],
        '/edit-journal.php' => ['controller' => JournalAdminController::class, 'methods' => ['GET' => 'edit', 'POST' => 'save']],
        '/journal-preview.php' => ['controller' => JournalAdminController::class, 'methods' => ['GET' => 'preview']],
        '/journal-preview-cover.php' => ['controller' => JournalAdminController::class, 'methods' => ['GET' => 'cover']],
        '/communications.php' => ['controller' => CommunicationController::class, 'methods' => ['GET' => 'index', 'POST' => 'send']],
        '/communications-count.php' => ['controller' => CommunicationController::class, 'methods' => ['GET' => 'count']],
        '/export.php' => ['controller' => ExportController::class, 'methods' => ['GET' => 'index', 'POST' => 'download']],
        '/export-preview.php' => ['controller' => ExportController::class, 'methods' => ['GET' => 'preview']],
        '/settings.php' => ['controller' => SettingsController::class, 'methods' => ['GET' => 'index', 'POST' => 'update']],
        '/users.php' => ['controller' => UserController::class, 'methods' => ['GET' => 'index', 'POST' => 'delete']],
        '/add-user.php' => ['controller' => UserController::class, 'methods' => ['GET' => 'create', 'POST' => 'store']],
        '/edit-user.php' => ['controller' => UserController::class, 'methods' => ['GET' => 'edit', 'POST' => 'update']],
        '/profile.php' => ['controller' => ProfileController::class, 'methods' => ['GET' => 'show', 'POST' => 'update']],
        '/upgrade-levels.php' => ['controller' => UpgradeController::class, 'methods' => ['GET' => 'index']],
        '/confirm-upgrade.php' => ['controller' => UpgradeController::class, 'methods' => ['POST' => 'confirm']],
    ];

    public static function supports(string $application, string $path, string $method): bool
    {
        return isset(self::route($application, $path)['methods'][$method]);
    }

    private static function route(string $application, string $path): ?array
    {
        return match ($application) {
            'public' => self::PUBLIC_ROUTES[$path] ?? null,
            'staff' => self::STAFF_ROUTES[$path] ?? null,
        };
    }

    public function dispatch(string $application): void
    {
        $path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = $path === '/index.php' ? '/' : $path;
        $method = $_SERVER['REQUEST_METHOD'];

        $route = self::route($application, $path);
        if ($route === null) {
            http_response_code(404);
            echo 'Page introuvable.';
            return;
        }

        $action = $route['methods'][$method] ?? null;
        if ($action === null) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_keys($route['methods'])));
            echo 'Méthode non autorisée.';
            return;
        }

        if (isset($route['redirect'])) {
            header('Location: ' . $route['redirect']);
            return;
        }

        define('AMEA_APP', $application);
        Environment::load(dirname(__DIR__, 2));

        if ($route['session'] ?? true) {
            (new Session())->startRequest();
        }

        $controllerClass = $route['controller'];
        $controller = new $controllerClass();
        $controller->$action();
    }
}
