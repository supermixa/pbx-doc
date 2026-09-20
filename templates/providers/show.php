<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/providers">← Все провайдеры</a></nav>
        <h3 class="mb-0"><?= View::e($provider['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/providers/<?= (int)$provider['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/providers/<?= (int)$provider['id'] ?>/delete" onsubmit="return confirm('Удалить провайдера «<?= View::e(addslashes($provider['name'])) ?>»? Действие необратимо.');">
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
                    <dt class="col-sm-4">№ договора</dt><dd class="col-sm-8"><?= View::e($provider['contract_number'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Телефон ТП</dt><dd class="col-sm-8"><?= View::e($provider['support_phone'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Примечание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($provider['note'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Менеджеры</strong></div>
            <div class="card-body p-0">
                <?php if (empty($managers)): ?>
                    <p class="text-muted p-3 mb-0">Менеджеры не указаны.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($managers as $m): ?>
                            <li class="list-group-item">
                                <?= View::e($m['name'] ?: '—') ?>
                                <?php if (!empty($m['phone'])): ?>
                                    <span class="text-muted">· <?= View::e($m['phone']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Обслуживаемые юрлица</strong></div>
            <div class="card-body p-0">
                <?php if (empty($legalEntityLinks)): ?>
                    <p class="text-muted p-3 mb-0">Юрлица не привязаны.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($legalEntityLinks as $l): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <a href="/legal-entities/<?= (int)$l['legal_entity_id'] ?>"><?= View::e($l['legal_entity_name']) ?></a>
                                <span class="text-muted small">
                                    <?= !empty($l['manager_name']) ? View::e('Менеджер: ' . $l['manager_name'] . ($l['manager_phone'] ? ' (' . $l['manager_phone'] . ')' : '')) : 'Менеджер не назначен' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-muted small">
            Создан: <?= View::e($provider['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($provider['created_at']))) ?><br>
            Изменён: <?= View::e($provider['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($provider['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
