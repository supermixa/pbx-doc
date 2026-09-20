<?php
use App\Core\Auth;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Оборудование</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/equipment/create" class="btn btn-primary">+ Добавить оборудование</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по расположению, IP, типу" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Расположение</th>
                    <th>Тип</th>
                    <th>Модель / Бренд</th>
                    <th>IP</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($equipment)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($equipment as $e): ?>
                <tr style="cursor:pointer" onclick="location.href='/equipment/<?= (int)$e['id'] ?>'">
                    <td><a href="/equipment/<?= (int)$e['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($e['location'] ?: ('#' . $e['id'])) ?></a></td>
                    <td><?= !empty($e['equipment_type_category']) ? View::e(Labels::equipmentCategoryLabel($e['equipment_type_category'])) : '—' ?></td>
                    <td>
                        <?php
                        $modelBrand = trim(($e['equipment_type_model'] ?? '') . ' ' . ($e['equipment_type_brand'] ?? ''));
                        echo $modelBrand !== '' ? View::e($e['equipment_type_name'] . ' (' . $modelBrand . ')') : View::e($e['equipment_type_name'] ?? '—');
                        ?>
                    </td>
                    <td><?= View::e($e['ip_address'] ?: '—') ?></td>
                    <td class="text-muted small"><?= View::e($e['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($e['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
