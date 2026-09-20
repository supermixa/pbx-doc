<?php
use App\Core\Auth;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Calltracking</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/calltracking/create" class="btn btn-primary">+ Добавить цель</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по цели, подразделению" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Подразделение</th>
                    <th>Цель</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($targets)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($targets as $t): ?>
                <tr style="cursor:pointer" onclick="location.href='/calltracking/<?= (int)$t['id'] ?>'">
                    <td><?= View::e($t['department_name'] ?? '—') ?></td>
                    <td><a href="/calltracking/<?= (int)$t['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($t['target']) ?></a></td>
                    <td class="text-muted small"><?= View::e($t['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($t['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
