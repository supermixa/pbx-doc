<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/divisions">← Все отделы</a></nav>
        <h3 class="mb-0"><?= View::e($division['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/divisions/<?= (int)$division['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/divisions/<?= (int)$division['id'] ?>/delete" onsubmit="return confirm('Удалить отдел «<?= View::e(addslashes($division['name'])) ?>»? Действие необратимо.');">
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
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($division['description'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>
        <p class="text-muted small">
            Создан: <?= View::e($division['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($division['created_at']))) ?><br>
            Изменён: <?= View::e($division['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($division['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
