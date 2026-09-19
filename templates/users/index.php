<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Пользователи</h3>
    <a href="/users/create" class="btn btn-primary">+ Добавить пользователя</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= View::e($u['name']) ?></td>
                    <td><?= View::e($u['email']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= View::e(Labels::role($u['role'])) ?></span></td>
                    <td>
                        <?php if ((int)$u['is_active'] === 1): ?>
                            <span class="badge text-bg-success">Активен</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Отключён</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/users/<?= (int)$u['id'] ?>/edit" class="btn btn-sm btn-outline-primary">Изменить</a>
                        <?php if ((int)$u['id'] !== Auth::id()): ?>
                            <form method="post" action="/users/<?= (int)$u['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Удалить пользователя «<?= View::e(addslashes($u['name'])) ?>»?');">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
