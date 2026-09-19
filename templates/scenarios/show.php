<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/scenarios">← Все сценарии</a></nav>
        <h3 class="mb-0"><?= View::e($scenario['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/scenarios/<?= (int)$scenario['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/scenarios/<?= (int)$scenario['id'] ?>/delete" onsubmit="return confirm('Удалить сценарий «<?= View::e(addslashes($scenario['name'])) ?>»? Действие необратимо.');">
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
                    <dt class="col-sm-4">Контекст</dt><dd class="col-sm-8"><?= View::e($scenario['context'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($scenario['description'] ?: '—') ?></dd>
                </dl>
                <?php if (!empty($scenario['dialplan_code'])): ?>
                    <div class="mt-3">
                        <div class="text-muted small mb-1">Код диалплана</div>
                        <pre class="dialplan-code"><?= View::e($scenario['dialplan_code']) ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-muted small">
            Создан: <?= View::e($scenario['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($scenario['created_at']))) ?><br>
            Изменён: <?= View::e($scenario['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($scenario['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
