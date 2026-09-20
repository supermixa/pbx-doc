<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/legal-entities">← Все юрлица</a></nav>
        <h3 class="mb-0"><?= View::e($legalEntity['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/legal-entities/<?= (int)$legalEntity['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/legal-entities/<?= (int)$legalEntity['id'] ?>/delete" onsubmit="return confirm('Удалить юрлицо «<?= View::e(addslashes($legalEntity['name'])) ?>»? Действие необратимо.');">
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
                    <dt class="col-sm-4">ИНН</dt><dd class="col-sm-8"><?= View::e($legalEntity['inn'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Провайдеры, обслуживающие юрлицо</strong></div>
            <div class="card-body p-0">
                <?php if (empty($providers)): ?>
                    <p class="text-muted p-3 mb-0">Ни один провайдер пока не привязан к этому юрлицу.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($providers as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <a href="/providers/<?= (int)$p['id'] ?>"><?= View::e($p['name']) ?></a>
                                <span class="text-muted small">
                                    <?= !empty($p['manager_name']) ? View::e('Менеджер: ' . $p['manager_name'] . ($p['manager_phone'] ? ' (' . $p['manager_phone'] . ')' : '')) : 'Менеджер не назначен' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-muted small">
            Создан: <?= View::e($legalEntity['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($legalEntity['created_at']))) ?><br>
            Изменён: <?= View::e($legalEntity['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($legalEntity['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
