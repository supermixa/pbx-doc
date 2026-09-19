<?php
use App\Core\Auth;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Маршруты</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/routes/create" class="btn btn-primary">+ Добавить маршрут</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по названию, шаблону номера" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Направление</th>
                    <th>Шаблон номера</th>
                    <th>Транк</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($routes)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($routes as $r): ?>
                <tr style="cursor:pointer" onclick="location.href='/routes/<?= (int)$r['id'] ?>'">
                    <td><a href="/routes/<?= (int)$r['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($r['name']) ?></a></td>
                    <td><span class="badge <?= $r['direction'] === 'inbound' ? 'text-bg-info' : 'text-bg-warning' ?>"><?= View::e(Labels::directionLabel($r['direction'])) ?></span></td>
                    <td><code><?= View::e($r['pattern'] ?: '—') ?></code></td>
                    <td><?= View::e($r['trunk_name'] ?? '—') ?></td>
                    <td class="text-muted small"><?= View::e($r['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($r['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
