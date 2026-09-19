<?php
use App\Core\Labels;
use App\Core\View;
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Транки</div>
                <div class="stat-number"><?= (int)$counts['trunks'] ?></div>
                <a href="/trunks" class="stretched-link small">Перейти →</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Маршруты</div>
                <div class="stat-number"><?= (int)$counts['routes'] ?></div>
                <a href="/routes" class="stretched-link small">Перейти →</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Сценарии</div>
                <div class="stat-number"><?= (int)$counts['scenarios'] ?></div>
                <a href="/scenarios" class="stretched-link small">Перейти →</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Номера</div>
                <div class="stat-number"><?= (int)$counts['extensions'] ?></div>
                <a href="/extensions" class="stretched-link small">Перейти →</a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white"><strong>Последние изменения</strong></div>
    <div class="card-body p-0">
        <?php if (empty($recent)): ?>
            <p class="text-muted p-3 mb-0">Пока нет ни одной записи в журнале изменений.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($recent as $entry): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="badge <?= Labels::actionBadgeClass($entry['action']) ?>"><?= View::e(Labels::action($entry['action'])) ?></span>
                            <span class="ms-1"><?= View::e(Labels::entity($entry['entity_type'])) ?></span>
                            <a href="<?= View::e(Labels::entityUrl($entry['entity_type'], (int)$entry['entity_id'])) ?>">#<?= (int)$entry['entity_id'] ?></a>
                            &mdash;
                            <span class="text-muted"><?= View::e($entry['user_name'] ?? 'Пользователь удалён') ?></span>
                        </div>
                        <span class="text-muted small"><?= View::e(date('d.m.Y H:i', strtotime($entry['created_at']))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
