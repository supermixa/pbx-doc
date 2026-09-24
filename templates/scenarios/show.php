<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/scenarios">← Все сценарии</a></nav>
        <h3 class="mb-0"><?= View::e($scenario['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/scenarios/<?= (int)$scenario['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/scenarios/<?= (int)$scenario['id'] ?>/delete" onsubmit="return confirm('Удалить сценарий «<?= View::e(addslashes($scenario['name'])) ?>»? Действие необратимо.');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline-danger">Удалить</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <?php if (!empty($scenario['description'])): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="text-muted small mb-1">Описание</div>
                    <div style="white-space: pre-wrap;"><?= View::e($scenario['description']) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="scenario-flow mb-3">
            <?php if (empty($flow)): ?>
                <p class="text-muted small">Логика сценария пока не настроена.</p>
            <?php else: ?>
                <?php foreach ($flow as $i => $block): ?>
                    <div class="scenario-flow-block">
                        <div class="scenario-flow-index"><?= (int)$i + 1 ?></div>
                        <div class="card scenario-flow-card">
                            <div class="card-body py-2 px-3">
                                <div class="fw-semibold mb-1"><?= View::e($block['typeLabel']) ?></div>
                                <?php if (!empty($block['fields'])): ?>
                                    <dl class="row mb-0 small">
                                        <?php foreach ($block['fields'] as $field): ?>
                                            <dt class="col-sm-4"><?= View::e($field['label']) ?></dt>
                                            <dd class="col-sm-8" style="white-space: pre-wrap; word-break: break-word;"><?= View::e($field['value']) ?></dd>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($i < count($flow) - 1): ?>
                        <div class="scenario-flow-connector">↓</div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <p class="text-muted small">
            Создан: <?= View::e($scenario['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($scenario['created_at']))) ?><br>
            Изменён: <?= View::e($scenario['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($scenario['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>

<style>
.scenario-flow-block { display: flex; align-items: stretch; gap: 0.75rem; }
.scenario-flow-index {
    flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%;
    background: #0d6efd; color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 600; margin-top: 0.5rem;
}
.scenario-flow-card { flex: 1 1 auto; }
.scenario-flow-connector { margin-left: 14px; padding: 0.15rem 0; color: #6c757d; }
</style>
