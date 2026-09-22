<?php
use App\Core\Auth;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Музыка</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/music/create" class="btn btn-primary">+ Добавить файл</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по названию" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Прослушать</th>
                    <th>Формат</th>
                    <th>Размер</th>
                    <th>Добавлен</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($files)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td><a href="/music/<?= (int)$f['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($f['title']) ?></a></td>
                    <td style="min-width: 260px">
                        <audio controls preload="none" style="height:32px; max-width: 260px;">
                            <source src="/music/<?= (int)$f['id'] ?>/stream" type="<?= View::e($f['mime_type']) ?>">
                        </audio>
                    </td>
                    <td class="text-uppercase text-muted small"><?= View::e(pathinfo($f['original_filename'], PATHINFO_EXTENSION)) ?></td>
                    <td class="text-muted small"><?= View::e(Labels::formatBytes((int)$f['size_bytes'])) ?></td>
                    <td class="text-muted small"><?= View::e(date('d.m.Y H:i', strtotime($f['created_at']))) ?><?php if (!empty($f['created_by_name'])): ?><br><?= View::e($f['created_by_name']) ?><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
