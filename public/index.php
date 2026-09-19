<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ExtensionController;
use App\Controllers\RouteController;
use App\Controllers\ScenarioController;
use App\Controllers\TrunkController;
use App\Controllers\UserController;
use App\Core\Auth;
use App\Core\Env;
use App\Core\Router;

Env::load(__DIR__ . '/../.env');

ini_set('log_errors', '1');
if (Env::get('APP_DEBUG', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

session_name((string)Env::get('SESSION_NAME', 'pbxdoc_session'));
$cookieSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $cookieSecure,
]);
session_start();

date_default_timezone_set('Asia/Novosibirsk');

$router = new Router();

// ---- Аутентификация -------------------------------------------------------
$router->get('/login', function () {
    (new AuthController())->showLogin();
});
$router->post('/login', function () {
    (new AuthController())->login();
});
$router->post('/logout', function () {
    (new AuthController())->logout();
});

// ---- Главная ----------------------------------------------------------
$router->get('/', function () {
    (new DashboardController())->index();
});

// ---- Транки -----------------------------------------------------------
$router->get('/trunks', fn() => (new TrunkController())->index());
$router->get('/trunks/create', fn() => (new TrunkController())->create());
$router->post('/trunks', fn() => (new TrunkController())->store());
$router->get('/trunks/{id}/edit', fn($p) => (new TrunkController())->edit($p));
$router->post('/trunks/{id}/edit', fn($p) => (new TrunkController())->update($p));
$router->post('/trunks/{id}/delete', fn($p) => (new TrunkController())->destroy($p));
$router->get('/trunks/{id}', fn($p) => (new TrunkController())->show($p));

// ---- Маршруты ---------------------------------------------------------
$router->get('/routes', fn() => (new RouteController())->index());
$router->get('/routes/create', fn() => (new RouteController())->create());
$router->post('/routes', fn() => (new RouteController())->store());
$router->get('/routes/{id}/edit', fn($p) => (new RouteController())->edit($p));
$router->post('/routes/{id}/edit', fn($p) => (new RouteController())->update($p));
$router->post('/routes/{id}/delete', fn($p) => (new RouteController())->destroy($p));
$router->get('/routes/{id}', fn($p) => (new RouteController())->show($p));

// ---- Сценарии ---------------------------------------------------------
$router->get('/scenarios', fn() => (new ScenarioController())->index());
$router->get('/scenarios/create', fn() => (new ScenarioController())->create());
$router->post('/scenarios', fn() => (new ScenarioController())->store());
$router->get('/scenarios/{id}/edit', fn($p) => (new ScenarioController())->edit($p));
$router->post('/scenarios/{id}/edit', fn($p) => (new ScenarioController())->update($p));
$router->post('/scenarios/{id}/delete', fn($p) => (new ScenarioController())->destroy($p));
$router->get('/scenarios/{id}', fn($p) => (new ScenarioController())->show($p));

// ---- Номера -----------------------------------------------------------
$router->get('/extensions', fn() => (new ExtensionController())->index());
$router->get('/extensions/create', fn() => (new ExtensionController())->create());
$router->post('/extensions', fn() => (new ExtensionController())->store());
$router->get('/extensions/{id}/edit', fn($p) => (new ExtensionController())->edit($p));
$router->post('/extensions/{id}/edit', fn($p) => (new ExtensionController())->update($p));
$router->post('/extensions/{id}/delete', fn($p) => (new ExtensionController())->destroy($p));
$router->get('/extensions/{id}', fn($p) => (new ExtensionController())->show($p));

// ---- Пользователи (только администратор) -------------------------------
$router->get('/users', fn() => (new UserController())->index());
$router->get('/users/create', fn() => (new UserController())->create());
$router->post('/users', fn() => (new UserController())->store());
$router->get('/users/{id}/edit', fn($p) => (new UserController())->edit($p));
$router->post('/users/{id}/edit', fn($p) => (new UserController())->update($p));
$router->post('/users/{id}/delete', fn($p) => (new UserController())->destroy($p));

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);
