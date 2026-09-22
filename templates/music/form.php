<?php
use App\Core\Csrf;
use App\Core\View;

$isEdit = !empty($file['id']);
$action = $isEdit ? '/music/' . (int)$file['id'] . '/edit' : '/music';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card" enctype="multipart/form-data">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Название *</label>
                <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= View::e($file['title'] ?? '') ?>" required placeholder="Например: Фоновая музыка — джаз">
                <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= View::e($errors['title']) ?></div><?php endif; ?>
                <div class="form-text">Поддерживаются русские названия.</div>
            </div>

            <?php if (!$isEdit): ?>
                <div class="col-md-8">
                    <label class="form-label">Файл (WAV или MP3) *</label>
                    <input type="file" name="file" class="form-control <?= isset($errors['file']) ? 'is-invalid' : '' ?>" accept=".wav,.mp3,audio/wav,audio/mpeg" required>
                    <?php if (isset($errors['file'])): ?><div class="invalid-feedback"><?= View::e($errors['file']) ?></div><?php endif; ?>
                    <div class="form-text">Максимальный размер файла — <?= (int)$maxUploadMb ?> МБ.</div>
                </div>
            <?php else: ?>
                <div class="col-md-8">
                    <div class="form-text">
                        Файл: <code><?= View::e($file['original_filename'] ?? '') ?></code> — чтобы заменить сам аудиофайл,
                        удалите эту запись (если она не используется) и загрузите новую.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/music/' . (int)$file['id'] : '/music' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
