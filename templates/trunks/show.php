<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/trunks">← Все транки</a></nav>
        <h3 class="mb-0"><?= View::e($trunk['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/trunks/<?= (int)$trunk['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/trunks/<?= (int)$trunk['id'] ?>/delete" onsubmit="return confirm('Удалить транк «<?= View::e(addslashes($trunk['name'])) ?>»? Действие необратимо.');">
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
                    <dt class="col-sm-4">Тип</dt><dd class="col-sm-8"><?= View::e($trunk['type']) ?></dd>
                    <dt class="col-sm-4">Провайдер</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($trunk['provider_name'])): ?>
                            <a href="/providers/<?= (int)$trunk['provider_id'] ?>"><?= View::e($trunk['provider_name']) ?></a>
                        <?php elseif (!empty($trunk['provider'])): ?>
                            <span class="fst-italic"><?= View::e($trunk['provider']) ?></span>
                            <span class="badge text-bg-warning">введён вручную</span>
                            <div class="text-muted small">Не связан со справочником «Провайдеры» — отредактируйте транк и выберите провайдера из списка.</div>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                    <dt class="col-sm-4">Емкость СЛ</dt><dd class="col-sm-8"><?= $trunk['line_capacity'] !== null ? (int)$trunk['line_capacity'] : '—' ?></dd>
                    <dt class="col-sm-4">Хост</dt><dd class="col-sm-8"><?= View::e($trunk['host'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Контекст</dt><dd class="col-sm-8"><?= View::e($trunk['context'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Статус</dt>
                    <dd class="col-sm-8">
                        <span class="badge <?= $trunk['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= View::e(Labels::statusLabel($trunk['status'])) ?></span>
                    </dd>
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($trunk['description'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>
        <p class="text-muted small">
            Создан: <?= View::e($trunk['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($trunk['created_at']))) ?><br>
            Изменён: <?= View::e($trunk['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($trunk['updated_at']))) ?>
        </p>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Номера на этом транке</strong></div>
            <div class="card-body p-0">
                <?php if (empty($extensions)): ?>
                    <p class="text-muted p-3 mb-0">Номера не привязаны.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($extensions as $e): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <a href="/extensions/<?= (int)$e['id'] ?>"><?= View::e($e['number']) ?></a>
                                <span class="text-muted small"><?= View::e(Labels::extensionTypeLabel($e['type'], $e['category'])) ?></span>
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
