<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/calltracking">← Calltracking</a></nav>
        <h3 class="mb-0"><?= View::e($department['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/calltracking/departments/<?= (int)$department['id'] ?>/edit" class="btn btn-outline-primary">Редактировать цели</a>
        <?php endif; ?>
        <?php if (Auth::canDelete() && !empty($links)): ?>
            <form method="post" action="/calltracking/departments/<?= (int)$department['id'] ?>/delete" onsubmit="return confirm('Убрать все цели подразделения «<?= View::e(addslashes($department['name'])) ?>» из справочника Calltracking?');">
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
                    <dd class="col-sm-8"><a href="/departments/<?= (int)$department['id'] ?>"><?= View::e($department['name']) ?></a></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Цели</strong></div>
            <div class="card-body p-0">
                <?php if (empty($links)): ?>
                    <p class="text-muted p-3 mb-0">Цели не заданы.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($links as $l): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <a href="/calltracking/goals/<?= (int)$l['goal_id'] ?>"><?= View::e($l['goal_name']) ?></a>
                                <span class="text-muted small">номеров: <?= (int)$l['extensions_count'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Номера CallTracking</strong></div>
            <div class="card-body p-0">
                <?php if (empty($extensions)): ?>
                    <p class="text-muted p-3 mb-0">Номера с целями этого подразделения не найдены.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($extensions as $e): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <a href="/extensions/<?= (int)$e['id'] ?>"><?= View::e($e['number']) ?></a>
                                <span class="text-muted small"><?= View::e($e['goal_name']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
