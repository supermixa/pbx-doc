<?php
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
use App\Models\User;

$isEdit = !empty($user['id']);
$action = $isEdit ? '/users/' . (int)$user['id'] . '/edit' : '/users';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card" style="max-width: 600px">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="mb-3">
            <label class="form-label">Имя *</label>
            <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($user['name'] ?? '') ?>" required>
            <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= View::e($user['email'] ?? '') ?>" required>
            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= View::e($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Роль *</label>
            <select name="role" class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>">
                <?php foreach (User::ROLES as $r): ?>
                    <option value="<?= $r ?>" <?= ($user['role'] ?? 'observer') === $r ? 'selected' : '' ?>><?= View::e(Labels::role($r)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['role'])): ?><div class="invalid-feedback"><?= View::e($errors['role']) ?></div><?php endif; ?>
            <div class="form-text">
                Администратор — полный доступ и управление пользователями.
                Пользователь — может добавлять и редактировать транки, маршруты, сценарии, номера.
                Наблюдатель — только просмотр.
            </div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?= (!$isEdit || (int)($user['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Учётная запись активна</label>
        </div>

        <div class="mb-3">
            <label class="form-label">Пароль <?= $isEdit ? '(оставьте пустым, чтобы не менять)' : '*' ?></label>
            <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" <?= $isEdit ? '' : 'required' ?> minlength="6">
            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= View::e($errors['password']) ?></div><?php endif; ?>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="/users" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
