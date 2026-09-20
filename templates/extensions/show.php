<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/extensions">← Все номера</a></nav>
        <h3 class="mb-0"><?= View::e($extension['number']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/extensions/<?= (int)$extension['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/extensions/<?= (int)$extension['id'] ?>/delete" onsubmit="return confirm('Удалить номер «<?= View::e(addslashes($extension['number'])) ?>»? Действие необратимо.');">
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
                    <dt class="col-sm-4">Категория</dt>
                    <dd class="col-sm-8"><span class="badge <?= $extension['category'] === 'external' ? 'text-bg-info' : 'text-bg-light text-dark border' ?>"><?= View::e(Labels::extensionCategoryLabel($extension['category'])) ?></span></dd>
                    <dt class="col-sm-4">Тип</dt><dd class="col-sm-8"><?= View::e(Labels::extensionTypeLabel($extension['type'])) ?></dd>
                    <dt class="col-sm-4">Владелец</dt><dd class="col-sm-8"><?= View::e($extension['owner'] ?: '—') ?></dd>
                    <?php if ($extension['category'] === 'internal'): ?>
                        <dt class="col-sm-4">Подразделение</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($extension['department_id'])): ?>
                                <a href="/departments/<?= (int)$extension['department_id'] ?>"><?= View::e($extension['department_name']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                    <?php else: ?>
                        <dt class="col-sm-4">Юрлицо</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($extension['legal_entity_id'])): ?>
                                <a href="/legal-entities/<?= (int)$extension['legal_entity_id'] ?>"><?= View::e($extension['legal_entity_name']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Провайдер</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($extension['provider_id'])): ?>
                                <a href="/providers/<?= (int)$extension['provider_id'] ?>"><?= View::e($extension['provider_name']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Транк</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($extension['trunk_id'])): ?>
                                <a href="/trunks/<?= (int)$extension['trunk_id'] ?>"><?= View::e($extension['trunk_name']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                        <?php if ($extension['type'] === 'calltracking'): ?>
                            <dt class="col-sm-4">Цель</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($extension['calltracking_target_id'])): ?>
                                    <a href="/calltracking/<?= (int)$extension['calltracking_target_id'] ?>"><?= View::e($extension['calltracking_target_name']) ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </dd>
                        <?php endif; ?>
                    <?php endif; ?>
                    <dt class="col-sm-4">Сценарий</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($extension['scenario_id'])): ?>
                            <a href="/scenarios/<?= (int)$extension['scenario_id'] ?>"><?= View::e($extension['scenario_name']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt class="col-sm-4">Контекст</dt><dd class="col-sm-8"><?= View::e($extension['context'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Статус</dt>
                    <dd class="col-sm-8"><span class="badge <?= $extension['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= View::e(Labels::statusLabel($extension['status'])) ?></span></dd>
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($extension['description'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>
        <p class="text-muted small">
            Создан: <?= View::e($extension['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($extension['created_at']))) ?><br>
            Изменён: <?= View::e($extension['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($extension['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
