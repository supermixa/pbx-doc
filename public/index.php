<?php

declare(strict_types=1);

// При запуске через встроенный сервер PHP (php -S ... public/index.php,
// используется только локально для разработки/тестирования) все запросы,
// включая статику (assets/js/*.js, assets/css/*.css), идут через этот
// файл — в отличие от боевого окружения, где статику отдаёт сам веб-сервер
// (Apache/Nginx), минуя PHP. Без этой проверки любой JS/CSS-файл отдавал
// бы 404 от роутера приложения. На продакшене PHP_SAPI никогда не равен
// 'cli-server', так что это ничего не меняет в боевом поведении.
if (PHP_SAPI === 'cli-server') {
    $requestedFile = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($requestedFile !== __DIR__ . '/' && is_file($requestedFile)) {
        return false;
    }
}

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
use App\Controllers\CalltrackingGoalController;
use App\Controllers\CalltrackingTargetController;
use App\Controllers\DashboardController;
use App\Controllers\DepartmentController;
use App\Controllers\DivisionController;
use App\Controllers\EquipmentController;
use App\Controllers\EquipmentTypeController;
use App\Controllers\ExtensionController;
use App\Controllers\ExtensionImportController;
use App\Controllers\HistoryController;
use App\Controllers\LegalEntityController;
use App\Controllers\MusicController;
use App\Controllers\ProviderController;
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

// Часовой пояс приложения — параметр APP_TIMEZONE в .env (идентификатор
// IANA, например Asia/Novosibirsk, Europe/Moscow). По нему же выставляется
// часовой пояс сессии MySQL (см. Db::connection()), чтобы время записей
// (created_at/updated_at, журнал изменений) совпадало со временем приложения.
$appTimezone = trim((string)Env::get('APP_TIMEZONE', 'Asia/Novosibirsk'));
if (!in_array($appTimezone, timezone_identifiers_list(), true)) {
    $appTimezone = 'Asia/Novosibirsk';
}
date_default_timezone_set($appTimezone);

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
$router->get('/routes/department-members', fn() => (new RouteController())->departmentMembers());
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
$router->get('/extensions/search-targets', fn() => (new ExtensionController())->searchTargets());
$router->get('/extensions/check-number', fn() => (new ExtensionController())->checkNumber());
$router->get('/extensions/search-rows', fn() => (new ExtensionController())->searchRows());
$router->get('/extensions/export', fn() => (new ExtensionController())->export());
$router->get('/extensions/import', fn() => (new ExtensionImportController())->form());
$router->get('/extensions/import/template', fn() => (new ExtensionImportController())->template());
$router->post('/extensions/import', fn() => (new ExtensionImportController())->upload());
$router->post('/extensions/import/confirm', fn() => (new ExtensionImportController())->confirm());
$router->get('/extensions/create', fn() => (new ExtensionController())->create());
$router->post('/extensions', fn() => (new ExtensionController())->store());
$router->get('/extensions/{id}/edit', fn($p) => (new ExtensionController())->edit($p));
$router->post('/extensions/{id}/edit', fn($p) => (new ExtensionController())->update($p));
$router->post('/extensions/{id}/delete', fn($p) => (new ExtensionController())->destroy($p));
$router->get('/extensions/{id}', fn($p) => (new ExtensionController())->show($p));

// ---- Подразделения ------------------------------------------------------
$router->get('/departments', fn() => (new DepartmentController())->index());
$router->get('/departments/create', fn() => (new DepartmentController())->create());
$router->post('/departments', fn() => (new DepartmentController())->store());
$router->get('/departments/{id}/edit', fn($p) => (new DepartmentController())->edit($p));
$router->post('/departments/{id}/edit', fn($p) => (new DepartmentController())->update($p));
$router->post('/departments/{id}/delete', fn($p) => (new DepartmentController())->destroy($p));
$router->get('/departments/{id}/export', fn($p) => (new DepartmentController())->export($p));
$router->get('/departments/{id}', fn($p) => (new DepartmentController())->show($p));

// ---- Отделы -------------------------------------------------------------
$router->get('/divisions', fn() => (new DivisionController())->index());
$router->get('/divisions/create', fn() => (new DivisionController())->create());
$router->post('/divisions', fn() => (new DivisionController())->store());
$router->get('/divisions/{id}/edit', fn($p) => (new DivisionController())->edit($p));
$router->post('/divisions/{id}/edit', fn($p) => (new DivisionController())->update($p));
$router->post('/divisions/{id}/delete', fn($p) => (new DivisionController())->destroy($p));
$router->get('/divisions/{id}/export', fn($p) => (new DivisionController())->export($p));
$router->get('/divisions/{id}', fn($p) => (new DivisionController())->show($p));

// ---- Типы оборудования ---------------------------------------------------
$router->get('/equipment-types', fn() => (new EquipmentTypeController())->index());
$router->get('/equipment-types/create', fn() => (new EquipmentTypeController())->create());
$router->post('/equipment-types', fn() => (new EquipmentTypeController())->store());
$router->get('/equipment-types/{id}/edit', fn($p) => (new EquipmentTypeController())->edit($p));
$router->post('/equipment-types/{id}/edit', fn($p) => (new EquipmentTypeController())->update($p));
$router->post('/equipment-types/{id}/delete', fn($p) => (new EquipmentTypeController())->destroy($p));
$router->get('/equipment-types/{id}', fn($p) => (new EquipmentTypeController())->show($p));

// ---- Оборудование ---------------------------------------------------------
$router->get('/equipment', fn() => (new EquipmentController())->index());
$router->get('/equipment/create', fn() => (new EquipmentController())->create());
$router->post('/equipment', fn() => (new EquipmentController())->store());
$router->get('/equipment/{id}/edit', fn($p) => (new EquipmentController())->edit($p));
$router->post('/equipment/{id}/edit', fn($p) => (new EquipmentController())->update($p));
$router->post('/equipment/{id}/delete', fn($p) => (new EquipmentController())->destroy($p));
$router->get('/equipment/{id}', fn($p) => (new EquipmentController())->show($p));

// ---- Юрлица ---------------------------------------------------------------
$router->get('/legal-entities', fn() => (new LegalEntityController())->index());
$router->get('/legal-entities/create', fn() => (new LegalEntityController())->create());
$router->post('/legal-entities', fn() => (new LegalEntityController())->store());
$router->get('/legal-entities/{id}/edit', fn($p) => (new LegalEntityController())->edit($p));
$router->post('/legal-entities/{id}/edit', fn($p) => (new LegalEntityController())->update($p));
$router->post('/legal-entities/{id}/delete', fn($p) => (new LegalEntityController())->destroy($p));
$router->get('/legal-entities/{id}', fn($p) => (new LegalEntityController())->show($p));

// ---- Провайдеры -------------------------------------------------------------
$router->get('/providers', fn() => (new ProviderController())->index());
$router->get('/providers/create', fn() => (new ProviderController())->create());
$router->post('/providers', fn() => (new ProviderController())->store());
$router->get('/providers/{id}/edit', fn($p) => (new ProviderController())->edit($p));
$router->post('/providers/{id}/edit', fn($p) => (new ProviderController())->update($p));
$router->post('/providers/{id}/delete', fn($p) => (new ProviderController())->destroy($p));
$router->get('/providers/{id}', fn($p) => (new ProviderController())->show($p));

// ---- Музыка -------------------------------------------------------------
$router->get('/music', fn() => (new MusicController())->index());
$router->get('/music/create', fn() => (new MusicController())->create());
$router->post('/music', fn() => (new MusicController())->store());
$router->post('/music/quick-upload', fn() => (new MusicController())->quickUpload());
$router->get('/music/{id}/edit', fn($p) => (new MusicController())->edit($p));
$router->post('/music/{id}/edit', fn($p) => (new MusicController())->update($p));
$router->post('/music/{id}/delete', fn($p) => (new MusicController())->destroy($p));
$router->get('/music/{id}/stream', fn($p) => (new MusicController())->stream($p));
$router->get('/music/{id}', fn($p) => (new MusicController())->show($p));

// ---- Calltracking -----------------------------------------------------
// Подчинённый справочник «Цели» — литеральные пути до /calltracking/{id}
$router->get('/calltracking/goals', fn() => (new CalltrackingGoalController())->index());
$router->get('/calltracking/goals/create', fn() => (new CalltrackingGoalController())->create());
$router->post('/calltracking/goals', fn() => (new CalltrackingGoalController())->store());
$router->post('/calltracking/goals/quick-create', fn() => (new CalltrackingGoalController())->quickCreate());
$router->get('/calltracking/goals/{id}/edit', fn($p) => (new CalltrackingGoalController())->edit($p));
$router->post('/calltracking/goals/{id}/edit', fn($p) => (new CalltrackingGoalController())->update($p));
$router->post('/calltracking/goals/{id}/delete', fn($p) => (new CalltrackingGoalController())->destroy($p));
$router->get('/calltracking/goals/{id}', fn($p) => (new CalltrackingGoalController())->show($p));
// Подразделения и их цели
$router->get('/calltracking', fn() => (new CalltrackingTargetController())->index());
$router->get('/calltracking/create', fn() => (new CalltrackingTargetController())->create());
$router->post('/calltracking', fn() => (new CalltrackingTargetController())->store());
$router->get('/calltracking/departments/{id}/edit', fn($p) => (new CalltrackingTargetController())->edit($p));
$router->post('/calltracking/departments/{id}/edit', fn($p) => (new CalltrackingTargetController())->update($p));
$router->post('/calltracking/departments/{id}/delete', fn($p) => (new CalltrackingTargetController())->destroy($p));
$router->get('/calltracking/departments/{id}', fn($p) => (new CalltrackingTargetController())->show($p));
// Старые ссылки /calltracking/{id} (из журнала изменений) -> карточка цели
$router->get('/calltracking/{id}', fn($p) => (new CalltrackingTargetController())->legacyRedirect($p));

// ---- История изменений (всем ролям) --------------------------------------
$router->get('/history', fn() => (new HistoryController())->index());
$router->get('/history/export', fn() => (new HistoryController())->export());

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
