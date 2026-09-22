<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/extensions">← Все номера</a></nav>
        <h3 class="mb-0"><?= View::e($extension['number']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/extensions/<?= (int)$extension['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/extensions/<?= (int)$extension['id'] ?>/delete" onsubmit="return confirm('Удалить номер «<?= View::e(addslashes($extension['number'])) ?>»? Действие необратимо.');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline-danger">Удалить</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Категория</dt>
                    <dd class="col-sm-8"><span class="badge <?= $extension['category'] === 'external' ? 'text-bg-info' : 'text-bg-light text-dark border' ?>"><?= View::e(Labels::extensionCategoryLabel($extension['category'])) ?></span></dd>
                    <dt class="col-sm-4">Тип</dt><dd class="col-sm-8"><?= View::e(Labels::extensionTypeLabel($extension['type'], $extension['category'])) ?></dd>
                    <dt class="col-sm-4">Владелец</dt><dd class="col-sm-8"><?= View::e($extension['owner'] ?: '—') ?></dd>
                    <?php if ($extension['category'] === 'internal'): ?>
                        <dt class="col-sm-4">Подразделение</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($extension['department_id'])): ?>
                                <a href="/departments/<?= (int)$extension['department_id'] ?>"><?= View::e($extension['department_name']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                    <?php else: ?>
                        <dt class="col-sm-4">Юрлицо</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($extension['legal_entity_id'])): ?>
                                <a href="/legal-entities/<?= (int)$extension['legal_entity_id'] ?>"><?= View::e($extension['legal_entity_name']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Провайдер</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($extension['provider_id'])): ?>
                                <a href="/providers/<?= (int)$extension['provider_id'] ?>"><?= View::e($extension['provider_name']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                        <dt class="col-sm-4">Транк</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($extension['trunk_id'])): ?>
                                <a href="/trunks/<?= (int)$extension['trunk_id'] ?>"><?= View::e($extension['trunk_name']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                        <?php if ($extension['type'] === 'calltracking'): ?>
                            <dt class="col-sm-4">Цель</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($extension['calltracking_target_id'])): ?>
                                    <a href="/calltracking/<?= (int)$extension['calltracking_target_id'] ?>"><?= View::e($extension['calltracking_target_name']) ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </dd>
                        <?php endif; ?>
                    <?php endif; ?>
                    <dt class="col-sm-4">Сценарий</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($extension['scenario_id'])): ?>
                            <a href="/scenarios/<?= (int)$extension['scenario_id'] ?>"><?= View::e($extension['scenario_name']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt class="col-sm-4">Контекст</dt><dd class="col-sm-8"><?= View::e($extension['context'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Статус</dt>
                    <dd class="col-sm-8"><span class="badge <?= $extension['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= View::e(Labels::statusLabel($extension['status'])) ?></span></dd>
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($extension['description'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($extension['category'] === 'internal' && in_array($extension['type'], ['queue', 'ivr'], true)): ?>
            <?php $isQueue = $extension['type'] === 'queue'; ?>
            <div class="card mb-3">
                <div class="card-header bg-white"><strong><?= $isQueue ? 'Состав очереди' : 'Состав группы' ?></strong></div>
                <div class="card-body p-0">
                    <?php if (empty($groupMembers)): ?>
                        <p class="text-muted p-3 mb-0">Участники не добавлены.</p>
                    <?php else: ?>
                        <ol class="list-group list-group-numbered list-group-flush">
                            <?php foreach ($groupMembers as $m): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="/extensions/<?= (int)$m['id'] ?>"><?= View::e($m['number']) ?></a>
                                    <span class="text-muted small"><?= View::e($m['owner'] ?? '') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Настройки обзвона</strong></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Метод обзвона</dt>
                        <dd class="col-sm-7"><?= View::e($isQueue ? Labels::queueRingStrategyLabel($queueSettings['ring_strategy'] ?? 'ringall') : Labels::ringGroupRingStrategyLabel($queueSettings['ring_strategy'] ?? 'ringall')) ?></dd>
                        <dt class="col-sm-5">Время дозвона оператору</dt>
                        <dd class="col-sm-7"><?= !empty($queueSettings['agent_answer_timeout_sec']) ? View::e($queueSettings['agent_answer_timeout_sec']) . ' сек' : '—' ?></dd>
                        <dt class="col-sm-5">Время отдыха оператора</dt>
                        <dd class="col-sm-7"><?= !empty($queueSettings['agent_wrapup_time_sec']) ? View::e($queueSettings['agent_wrapup_time_sec']) . ' сек' : '—' ?></dd>
                        <?php if ($isQueue): ?>
                            <dt class="col-sm-5">Звонки во время разговора</dt>
                            <dd class="col-sm-7"><?= !empty($queueSettings['agent_ring_in_use']) ? 'Да' : 'Нет' ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <?php if ($isQueue): ?>
                <div class="card mb-3">
                    <div class="card-header bg-white"><strong>Настройки для вызывающего абонента</strong></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Абонент слышит</dt>
                            <dd class="col-sm-7"><?= View::e(Labels::callerWaitSoundLabel($queueSettings['caller_wait_sound'] ?? 'music')) ?></dd>
                            <dt class="col-sm-5">Фоновая музыка (MOH)</dt>
                            <dd class="col-sm-7"><?php if (!empty($queueSettings['moh_music_id'])): ?><a href="/music/<?= (int)$queueSettings['moh_music_id'] ?>"><?= View::e($queueSettings['moh_music_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                            <dt class="col-sm-5">Позиция в очереди</dt>
                            <dd class="col-sm-7"><?= !empty($queueSettings['announce_position']) ? 'Оповещать' : 'Не оповещать' ?></dd>
                            <dt class="col-sm-5">Расчётное время ожидания</dt>
                            <dd class="col-sm-7"><?= !empty($queueSettings['announce_hold_time']) ? 'Оповещать' : 'Не оповещать' ?></dd>
                            <dt class="col-sm-5">Рекламная фраза</dt>
                            <dd class="col-sm-7"><?php if (!empty($queueSettings['announcement_music_id'])): ?><a href="/music/<?= (int)$queueSettings['announcement_music_id'] ?>"><?= View::e($queueSettings['announcement_music_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                            <dt class="col-sm-5">Повтор оповещений</dt>
                            <dd class="col-sm-7"><?= !empty($queueSettings['announcement_repeat_sec']) ? View::e($queueSettings['announcement_repeat_sec']) . ' сек' : '—' ?></dd>
                        </dl>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white"><strong>Маршрутизация звонков при сбоях</strong></div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Сценарий №1:</strong>
                            <?php if (!empty($queueSettings['failover1_timeout_sec']) || !empty($queueSettings['failover1_target_label'])): ?>
                                если ожидание более <?= View::e($queueSettings['failover1_timeout_sec'] ?? '—') ?> сек — перевести на <?= View::e($queueSettings['failover1_target_label'] ?? '—') ?>
                            <?php else: ?>
                                не настроено
                            <?php endif; ?>
                        </p>
                        <p class="mb-0"><strong>Сценарий №2:</strong>
                            <?php if (!empty($queueSettings['failover2_target_label'])): ?>
                                если нет операторов — перевести на <?= View::e($queueSettings['failover2_target_label']) ?>
                            <?php else: ?>
                                не настроено
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <p class="text-muted small">
            Создан: <?= View::e($extension['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($extension['created_at']))) ?><br>
            Изменён: <?= View::e($extension['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($extension['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
