<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/departments">← Все подразделения</a></nav>
        <h3 class="mb-0"><?= View::e($department['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/departments/<?= (int)$department['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/departments/<?= (int)$department['id'] ?>/delete" onsubmit="return confirm('Удалить подразделение «<?= View::e(addslashes($department['name'])) ?>»? Действие необратимо.');">
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
                    <dt class="col-sm-4">Приветствие оператора</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e(($department['operator_greeting'] ?? '') ?: '—') ?></dd>
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($department['description'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>

        <?php
        $numbersTitle = 'Номера подразделения';
        $exportUrl = '/departments/' . (int)$department['id'] . '/export';
        $emptyText = 'К подразделению не привязан ни один номер.';
        $omitColumns = ['department'];
        require __DIR__ . '/../partials/numbers_by_type.php';
        ?>
        <p class="text-muted small">
            Создан: <?= View::e($department['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($department['created_at']))) ?><br>
            Изменён: <?= View::e($department['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($department['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
