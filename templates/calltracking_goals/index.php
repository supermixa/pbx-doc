<?php
use App\Core\Auth;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/calltracking">← Calltracking</a></nav>
        <h3 class="mb-0">Цели Calltracking</h3>
    </div>
    <?php if (Auth::canEdit()): ?>
        <a href="/calltracking/goals/create" class="btn btn-primary">+ Добавить цель</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по названию цели" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 30%">Цель</th>
                    <th>Подразделения</th>
                    <th>Номеров</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($goals)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($goals as $g): ?>
                <tr>
                    <td><a href="/calltracking/goals/<?= (int)$g['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($g['name']) ?></a></td>
                    <td>
                        <?php if (empty($g['departments'])): ?>
                            <span class="text-muted">не используется</span>
                        <?php else: ?>
                            <?php foreach ($g['departments'] as $i => $d): ?><?= $i > 0 ? ', ' : '' ?><a href="/calltracking/departments/<?= (int)$d['id'] ?>"><?= View::e($d['name']) ?></a><?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$g['extensions_count'] ?></td>
                    <td class="text-muted small"><?= View::e($g['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($g['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
