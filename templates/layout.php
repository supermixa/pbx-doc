<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Labels;
use App\Core\View;

$currentUser = Auth::user();
$flash = Flash::all();
$pageTitle = isset($title) ? $title . ' — PBX Doc' : 'PBX Doc';

function nav_active(string $path): string
{
    $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($path === '/') {
        return $current === '/' ? ' active' : '';
    }
    return str_starts_with((string)$current, $path) ? ' active' : '';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php if ($currentUser): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="/">☎ PBX Doc</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link<?= nav_active('/') ?>" href="/">Главная</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('/extensions') ?>" href="/extensions">Номера</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('/trunks') ?>" href="/trunks">Транки</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('/routes') ?>" href="/routes">Маршруты</a></li>
                <li class="nav-item"><a class="nav-link<?= nav_active('/scenarios') ?>" href="/scenarios">Сценарии</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= (nav_active('/departments') || nav_active('/equipment') || nav_active('/legal-entities') || nav_active('/providers') || nav_active('/calltracking')) ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">Справочники</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/departments">Подразделения</a></li>
                        <li><a class="dropdown-item" href="/equipment">Оборудование</a></li>
                        <li><a class="dropdown-item" href="/equipment-types">Типы оборудования</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/legal-entities">Юрлица</a></li>
                        <li><a class="dropdown-item" href="/providers">Провайдеры</a></li>
                        <li><a class="dropdown-item" href="/calltracking">Calltracking</a></li>
                    </ul>
                </li>
                <?php if (Auth::isAdmin()): ?>
                <li class="nav-item"><a class="nav-link<?= nav_active('/users') ?>" href="/users">Пользователи</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item d-flex align-items-center me-3">
                    <span class="text-light small">
                        <?= View::e($currentUser['name']) ?>
                        <span class="badge text-bg-secondary ms-1"><?= View::e(Labels::role($currentUser['role'])) ?></span>
                    </span>
                </li>
                <li class="nav-item">
                    <form method="post" action="/logout" class="m-0">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-outline-light btn-sm">Выйти</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container pb-5">
    <?php foreach (($flash['success'] ?? []) as $msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= View::e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
    <?php foreach (($flash['error'] ?? []) as $msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= View::e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

    <?php $content(); ?>
</main>

<footer class="text-center text-muted small pb-4">
    PBX Doc — документация Asterisk
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
