<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../functions/utility-functions.php';

// Load .env into $_ENV
$_envFile = __DIR__ . '/../.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_contains($_line, '=') && !str_starts_with(ltrim($_line), '#')) {
            [$_k, $_v] = explode('=', $_line, 2);
            $_ENV[trim($_k)] = trim($_v);
        }
    }
    unset($_envFile, $_line, $_k, $_v);
}

use Amea\Core\Router;

$router = new Router();

// Define routes
$router->add('GET', '/', 'HomeController@index');
$router->add('GET', '/login.php', 'AuthController@login');
$router->add('POST', '/login.php', 'AuthController@login');
$router->add('GET', '/logout.php', 'AuthController@logout');

// KYC Registration Workflow
$router->add('GET', '/register.php', 'RegistrationController@showForm');
$router->add('POST', '/register.php', 'RegistrationController@register');
$router->add('GET', '/registration-details.php', 'RegistrationController@review');
$router->add('POST', '/registration-details.php', 'RegistrationController@confirm');

// Admin KYC Dashboard
$router->add('GET', '/kyc-list.php', 'KYCController@index');
$router->add('GET', '/kyc-detail.php', 'KYCController@review');
$router->add('POST', '/kyc-detail.php', 'KYCController@decide');

// KYC Correction Loop
$router->add('GET', '/kyc-correction.php', 'CorrectionController@edit');
$router->add('POST', '/kyc-correction.php', 'CorrectionController@update');

$router->add('GET', '/dashboard.php', 'DashboardController@index');

// Student management
$router->add('GET',  '/students.php',        'StudentController@index');
$router->add('POST', '/students.php',        'StudentController@delete');
$router->add('GET',  '/student-details.php', 'StudentController@show');
$router->add('GET',  '/edit-student.php',    'StudentController@edit');
$router->add('POST', '/edit-student.php',    'StudentController@update');

// Trash / archived students
$router->add('GET',  '/trash.php', 'TrashController@index');
$router->add('POST', '/trash.php', 'TrashController@handlePost');

// User management
$router->add('GET',  '/users.php',     'UserController@index');
$router->add('POST', '/users.php',     'UserController@delete');
$router->add('GET',  '/add-user.php',  'UserController@create');
$router->add('POST', '/add-user.php',  'UserController@store');
$router->add('GET',  '/edit-user.php', 'UserController@edit');
$router->add('POST', '/edit-user.php', 'UserController@update');

// Profile
$router->add('GET',  '/profile.php', 'ProfileController@show');
$router->add('POST', '/profile.php', 'ProfileController@update');

// Export
$router->add('GET',  '/export.php',         'ExportController@index');
$router->add('POST', '/export.php',         'ExportController@download');
$router->add('GET',  '/export-preview.php', 'ExportController@preview');

// Settings
$router->add('GET',  '/settings.php', 'SettingsController@index');
$router->add('POST', '/settings.php', 'SettingsController@update');

// Slider
$router->add('GET',  '/manage-slider.php', 'SliderController@index');
$router->add('POST', '/manage-slider.php', 'SliderController@handlePost');

// Upgrade levels
$router->add('GET',  '/upgrade-levels.php',  'UpgradeController@index');
$router->add('POST', '/confirm-upgrade.php', 'UpgradeController@confirm');

// Communications
$router->add('GET',  '/communications.php',       'CommunicationController@index');
$router->add('POST', '/communications.php',       'CommunicationController@send');
$router->add('GET',  '/communications-count.php', 'CommunicationController@count');

// Password reset
$router->add('GET',  '/forgot-password.php', 'PasswordResetController@forgotForm');
$router->add('POST', '/forgot-password.php', 'PasswordResetController@forgotSubmit');
$router->add('GET',  '/reset-password.php',  'PasswordResetController@resetForm');
$router->add('POST', '/reset-password.php',  'PasswordResetController@resetSubmit');

// Download, legal, CGU
$router->add('GET',  '/download.php',     'DownloadController@serve');
$router->add('GET',  '/legal-notice.php', 'LegalController@index');
$router->add('GET',  '/accept-cgu.php',   'AcceptCguController@accept');
$router->add('POST', '/accept-cgu.php',   'AcceptCguController@accept');

return $router;
