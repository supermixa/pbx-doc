<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/equipment">← Всё оборудование</a></nav>
        <h3 class="mb-0"><?= View::e($item['location'] ?: ('Оборудование #' . $item['id'])) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/equipment/<?= (int)$item['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/equipment/<?= (int)$item['id'] ?>/delete" onsubmit="return confirm('Удалить оборудование? Действие необратимо.');">
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
                    <dt class="col-sm-4">Тип</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($item['equipment_type_id'])): ?>
                            <a href="/equipment-types/<?= (int)$item['equipment_type_id'] ?>"><?= View::e($item['equipment_type_name']) ?></a>
                            <?php if (!empty($item['equipment_type_category'])): ?>
                                <span class="badge text-bg-light text-dark border ms-1"><?= View::e(Labels::equipmentCategoryLabel($item['equipment_type_category'])) ?></span>
                            <?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt class="col-sm-4">Модель</dt><dd class="col-sm-8"><?= View::e($item['equipment_type_model'] ?? '') ?: '—' ?></dd>
                    <dt class="col-sm-4">Бренд</dt><dd class="col-sm-8"><?= View::e($item['equipment_type_brand'] ?? '') ?: '—' ?></dd>
                    <dt class="col-sm-4">Расположение</dt><dd class="col-sm-8"><?= View::e($item['location'] ?: '—') ?></dd>
                    <dt class="col-sm-4">IP</dt><dd class="col-sm-8"><?= View::e($item['ip_address'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($item['description'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>
        <p class="text-muted small">
            Создан: <?= View::e($item['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($item['created_at']))) ?><br>
            Изменён: <?= View::e($item['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($item['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
