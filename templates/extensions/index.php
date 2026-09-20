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

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link<?= $category === '' ? ' active' : '' ?>" href="/extensions<?= $search !== '' ? '?q=' . urlencode($search) : '' ?>">Все (<?= (int)$countInternal + (int)$countExternal ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $category === 'internal' ? ' active' : '' ?>" href="/extensions?category=internal<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">Внутренние (<?= (int)$countInternal ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $category === 'external' ? ' active' : '' ?>" href="/extensions?category=external<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">Внешние (<?= (int)$countExternal ?>)</a>
    </li>
</ul>

<form method="get" class="mb-3">
    <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= View::e($category) ?>"><?php endif; ?>
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
                    <th>Категория</th>
                    <th>Тип</th>
                    <th>Владелец</th>
                    <th>Подразделение / Юрлицо</th>
                    <th>Сценарий</th>
                    <th>Статус</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($extensions)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($extensions as $e): ?>
                <tr style="cursor:pointer" onclick="location.href='/extensions/<?= (int)$e['id'] ?>'">
                    <td><a href="/extensions/<?= (int)$e['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($e['number']) ?></a></td>
                    <td><span class="badge <?= $e['category'] === 'external' ? 'text-bg-info' : 'text-bg-light text-dark border' ?>"><?= View::e(Labels::extensionCategoryLabel($e['category'])) ?></span></td>
                    <td><?= View::e(Labels::extensionTypeLabel($e['type'])) ?></td>
                    <td><?= View::e($e['owner'] ?: '—') ?></td>
                    <td><?= View::e(($e['category'] === 'internal' ? $e['department_name'] : $e['legal_entity_name']) ?? '—') ?></td>
                    <td><?= View::e($e['scenario_name'] ?? '—') ?></td>
                    <td><span class="badge <?= $e['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= View::e(Labels::statusLabel($e['status'])) ?></span></td>
                    <td class="text-muted small"><?= View::e($e['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($e['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
