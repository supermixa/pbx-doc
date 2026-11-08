<?php
use App\Core\Auth;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Отделы</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/divisions/create" class="btn btn-primary">+ Добавить отдел</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по наименованию" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Наименование</th>
                    <th>Описание</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($divisions)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($divisions as $d): ?>
                <tr style="cursor:pointer" onclick="location.href='/divisions/<?= (int)$d['id'] ?>'">
                    <td><a href="/divisions/<?= (int)$d['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($d['name']) ?></a></td>
                    <td><?= View::e($d['description'] ?: '—') ?></td>
                    <td class="text-muted small"><?= View::e($d['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($d['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
