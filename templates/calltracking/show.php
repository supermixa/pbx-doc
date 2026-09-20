<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/calltracking">← Все цели Calltracking</a></nav>
        <h3 class="mb-0"><?= View::e($target['target']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/calltracking/<?= (int)$target['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/calltracking/<?= (int)$target['id'] ?>/delete" onsubmit="return confirm('Удалить цель «<?= View::e(addslashes($target['target'])) ?>»? Действие необратимо.');">
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
                    <dt class="col-sm-4">Подразделение</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($target['department_id'])): ?>
                            <a href="/departments/<?= (int)$target['department_id'] ?>"><?= View::e($target['department_name'] ?? '') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
        <p class="text-muted small">
            Создан: <?= View::e($target['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($target['created_at']))) ?><br>
            Изменён: <?= View::e($target['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($target['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
