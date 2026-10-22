<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/calltracking">← Calltracking</a> · <a href="/calltracking/goals">Все цели</a></nav>
        <h3 class="mb-0"><?= View::e($goal['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/calltracking/goals/<?= (int)$goal['id'] ?>/edit" class="btn btn-outline-primary">Переименовать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/calltracking/goals/<?= (int)$goal['id'] ?>/delete" onsubmit="return confirm('Удалить цель «<?= View::e(addslashes($goal['name'])) ?>»? Действие необратимо.');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline-danger" <?= $departments ? 'disabled title="Цель используется в подразделениях — сначала уберите её у них"' : '' ?>>Удалить</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Используется в подразделениях</strong></div>
            <div class="card-body p-0">
                <?php if (empty($departments)): ?>
                    <p class="text-muted p-3 mb-0">Цель пока не используется ни в одном подразделении.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($departments as $d): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <a href="/calltracking/departments/<?= (int)$d['id'] ?>"><?= View::e($d['name']) ?></a>
                                <span class="text-muted small">номеров: <?= (int)$d['extensions_count'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Номера CallTracking с этой целью</strong></div>
            <div class="card-body p-0">
                <?php if (empty($extensions)): ?>
                    <p class="text-muted p-3 mb-0">Номеров нет.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($extensions as $e): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <a href="/extensions/<?= (int)$e['id'] ?>"><?= View::e($e['number']) ?></a>
                                <span class="text-muted small"><?= View::e($e['department_name'] ?? '—') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-muted small">
            Создана: <?= View::e($goal['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($goal['created_at']))) ?><br>
            Изменена: <?= View::e($goal['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($goal['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
