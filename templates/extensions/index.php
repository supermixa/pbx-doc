<?php
use App\Core\Auth;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Номера</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/extensions/create" class="btn btn-primary">+ Добавить номер</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по номеру, владельцу" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Номер</th>
                    <th>Тип</th>
                    <th>Владелец</th>
                    <th>Сценарий</th>
                    <th>Статус</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($extensions)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($extensions as $e): ?>
                <tr style="cursor:pointer" onclick="location.href='/extensions/<?= (int)$e['id'] ?>'">
                    <td><a href="/extensions/<?= (int)$e['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($e['number']) ?></a></td>
                    <td><?= View::e(Labels::extensionTypeLabel($e['type'])) ?></td>
                    <td><?= View::e($e['owner'] ?: '—') ?></td>
                    <td><?= View::e($e['scenario_name'] ?? '—') ?></td>
                    <td><span class="badge <?= $e['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= View::e(Labels::statusLabel($e['status'])) ?></span></td>
                    <td class="text-muted small"><?= View::e($e['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($e['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
