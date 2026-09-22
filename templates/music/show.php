<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;

$inUse = !empty($mohUsages) || !empty($announcementUsages);
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/music">← Вся музыка</a></nav>
        <h3 class="mb-0"><?= View::e($file['title']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/music/<?= (int)$file['id'] ?>/edit" class="btn btn-outline-primary">Переименовать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/music/<?= (int)$file['id'] ?>/delete" onsubmit="return confirm('Удалить файл «<?= View::e(addslashes($file['title'])) ?>»? Действие необратимо.');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline-danger" <?= $inUse ? 'disabled title="Файл используется — сначала отвяжите его"' : '' ?>>Удалить</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <audio controls preload="metadata" style="width: 100%;">
                    <source src="/music/<?= (int)$file['id'] ?>/stream" type="<?= View::e($file['mime_type']) ?>">
                    Ваш браузер не поддерживает воспроизведение аудио.
                </audio>
                <dl class="row mb-0 mt-3">
                    <dt class="col-sm-4">Имя файла</dt><dd class="col-sm-8"><?= View::e($file['original_filename']) ?></dd>
                    <dt class="col-sm-4">Формат</dt><dd class="col-sm-8 text-uppercase"><?= View::e(pathinfo($file['original_filename'], PATHINFO_EXTENSION)) ?></dd>
                    <dt class="col-sm-4">Размер</dt><dd class="col-sm-8"><?= View::e(Labels::formatBytes((int)$file['size_bytes'])) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Используется</strong></div>
            <div class="card-body">
                <?php if (!$inUse): ?>
                    <p class="text-muted mb-0">Файл нигде не используется — можно удалить.</p>
                <?php else: ?>
                    <?php if (!empty($mohUsages)): ?>
                        <p class="mb-1 fw-semibold">Как фоновая музыка (MOH):</p>
                        <ul class="mb-3">
                            <?php foreach ($mohUsages as $u): ?>
                                <li><a href="/extensions/<?= (int)$u['id'] ?>"><?= View::e($u['number']) ?></a> — <?= View::e(Labels::extensionTypeLabel($u['type'], $u['category'])) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($announcementUsages)): ?>
                        <p class="mb-1 fw-semibold">Как рекламная фраза / оповещение:</p>
                        <ul class="mb-0">
                            <?php foreach ($announcementUsages as $u): ?>
                                <li><a href="/extensions/<?= (int)$u['id'] ?>"><?= View::e($u['number']) ?></a> — <?= View::e(Labels::extensionTypeLabel($u['type'], $u['category'])) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-muted small">
            Добавлен: <?= View::e($file['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($file['created_at']))) ?><br>
            Изменён: <?= View::e($file['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($file['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
