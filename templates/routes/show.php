<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;

$isInbound = $route['direction'] === 'inbound';
$selectionType = $route['selection_type'] ?? null;
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
                    <dd class="col-sm-8"><span class="badge <?= $isInbound ? 'text-bg-info' : 'text-bg-warning' ?>"><?= View::e(Labels::directionLabel($route['direction'])) ?></span></dd>

                    <?php if ($isInbound): ?>
                        <dt class="col-sm-4">Сценарий</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($route['scenario_id'])): ?>
                                <a href="/scenarios/<?= (int)$route['scenario_id'] ?>"><?= View::e($route['scenario_name']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Шаблон номера звонящего</dt><dd class="col-sm-8"><code><?= View::e($route['pattern'] ?: '—') ?></code></dd>
                    <?php else: ?>
                        <dt class="col-sm-4">Тип отбора</dt>
                        <dd class="col-sm-8"><?= View::e(Labels::routeSelectionTypeLabel($selectionType)) ?></dd>

                        <?php if ($selectionType === 'dialed_number'): ?>
                            <dt class="col-sm-4">Шаблон набираемого номера</dt><dd class="col-sm-8"><code><?= View::e($route['pattern'] ?: '—') ?></code></dd>
                        <?php endif; ?>

                        <?php if ($selectionType === 'departments'): ?>
                            <dt class="col-sm-4">Подразделение</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($route['department_id'])): ?>
                                    <a href="/departments/<?= (int)$route['department_id'] ?>"><?= View::e($route['department_name']) ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </dd>
                        <?php endif; ?>

                        <?php if (in_array($selectionType, ['internal_numbers', 'departments', 'dialed_number'], true)): ?>
                            <dt class="col-sm-4">Исходящий АОН</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($route['outbound_caller_id_id'])): ?>
                                    <a href="/extensions/<?= (int)$route['outbound_caller_id_id'] ?>"><?= View::e($route['outbound_caller_id_number']) ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Исходящий транк</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($route['trunk_id'])): ?>
                                    <a href="/trunks/<?= (int)$route['trunk_id'] ?>"><?= View::e($route['trunk_name']) ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </dd>
                        <?php endif; ?>
                    <?php endif; ?>

                    <dt class="col-sm-4">Контекст</dt><dd class="col-sm-8"><?= View::e($route['context'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($route['description'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>

        <?php if (!$isInbound && $selectionType === 'internal_numbers'): ?>
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Внутренние номера</strong></div>
                <div class="card-body p-0">
                    <?php if (empty($routeMembers)): ?>
                        <p class="text-muted p-3 mb-0">Номера не добавлены.</p>
                    <?php else: ?>
                        <ol class="list-group list-group-numbered list-group-flush">
                            <?php foreach ($routeMembers as $m): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="/extensions/<?= (int)$m['id'] ?>"><?= View::e($m['number']) ?></a>
                                    <span class="text-muted small"><?= View::e($m['owner'] ?? '') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$isInbound && $selectionType === 'departments'): ?>
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Внутренние номера подразделения</strong></div>
                <div class="card-body p-0">
                    <?php if (empty($departmentMembers)): ?>
                        <p class="text-muted p-3 mb-0">В этом подразделении нет внутренних номеров.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($departmentMembers as $m): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="/extensions/<?= (int)$m['id'] ?>"><?= View::e($m['number']) ?></a>
                                    <span class="text-muted small"><?= View::e($m['owner'] ?? '') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <p class="text-muted small">
            Создан: <?= View::e($route['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($route['created_at']))) ?><br>
            Изменён: <?= View::e($route['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($route['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
