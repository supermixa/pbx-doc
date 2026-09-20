<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/equipment-types">← Все типы оборудования</a></nav>
        <h3 class="mb-0"><?= View::e($equipmentType['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/equipment-types/<?= (int)$equipmentType['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/equipment-types/<?= (int)$equipmentType['id'] ?>/delete" onsubmit="return confirm('Удалить тип оборудования «<?= View::e(addslashes($equipmentType['name'])) ?>»? Действие необратимо.');">
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
                    <dt class="col-sm-4">Категория</dt><dd class="col-sm-8"><?= View::e(Labels::equipmentCategoryLabel($equipmentType['category'])) ?></dd>
                    <dt class="col-sm-4">Модель</dt><dd class="col-sm-8"><?= View::e($equipmentType['model'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Бренд</dt><dd class="col-sm-8"><?= View::e($equipmentType['brand'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>
        <p class="text-muted small">
            Создан: <?= View::e($equipmentType['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($equipmentType['created_at']))) ?><br>
            Изменён: <?= View::e($equipmentType['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($equipmentType['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
