<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/routes">← Все маршруты</a></nav>
        <h3 class="mb-0"><?= View::e($route['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/routes/<?= (int)$route['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/routes/<?= (int)$route['id'] ?>/delete" onsubmit="return confirm('Удалить маршрут «<?= View::e(addslashes($route['name'])) ?>»? Действие необратимо.');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline-danger">Удалить</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Направление</dt>
                    <dd class="col-sm-8"><span class="badge <?= $route['direction'] === 'inbound' ? 'text-bg-info' : 'text-bg-warning' ?>"><?= View::e(Labels::directionLabel($route['direction'])) ?></span></dd>
                    <dt class="col-sm-4">Шаблон номера</dt><dd class="col-sm-8"><code><?= View::e($route['pattern'] ?: '—') ?></code></dd>
                    <dt class="col-sm-4">Транк</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($route['trunk_id'])): ?>
                            <a href="/trunks/<?= (int)$route['trunk_id'] ?>"><?= View::e($route['trunk_name']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt class="col-sm-4">Контекст</dt><dd class="col-sm-8"><?= View::e($route['context'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($route['description'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>
        <p class="text-muted small">
            Создан: <?= View::e($route['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($route['created_at']))) ?><br>
            Изменён: <?= View::e($route['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($route['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
