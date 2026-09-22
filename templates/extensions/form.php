<?php
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
use App\Models\ExtensionNumber;

require __DIR__ . '/_form_helpers.php';

$isEdit = !empty($extension['id']);
$action = $isEdit ? '/extensions/' . (int)$extension['id'] . '/edit' : '/extensions';
$currentExtensionId = $isEdit ? (int)$extension['id'] : null;
$qs = $queueSettings ?? null;
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card" id="extension-form">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Номер *</label>
                <input type="text" name="number" class="form-control <?= isset($errors['number']) ? 'is-invalid' : '' ?>" value="<?= View::e($extension['number'] ?? '') ?>" required>
                <?php if (isset($errors['number'])): ?><div class="invalid-feedback"><?= View::e($errors['number']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Категория *</label>
                <select name="category" id="category-select" class="form-select <?= isset($errors['category']) ? 'is-invalid' : '' ?>">
                    <?php foreach (ExtensionNumber::CATEGORIES as $c): ?>
                        <option value="<?= $c ?>" <?= ($extension['category'] ?? 'internal') === $c ? 'selected' : '' ?>><?= View::e(Labels::extensionCategoryLabel($c)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?><div class="invalid-feedback"><?= View::e($errors['category']) ?></div><?php endif; ?>
            </div>

            <div class="col-md-4" id="type-internal-field">
                <label class="form-label">Тип *</label>
                <select name="type" id="type-internal-select" class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>">
                    <?php foreach (ExtensionNumber::INTERNAL_TYPES as $t): ?>
                        <option value="<?= $t ?>" <?= ($extension['type'] ?? 'internal') === $t ? 'selected' : '' ?>><?= View::e(Labels::extensionTypeLabel($t, 'internal')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4" id="type-external-field">
                <label class="form-label">Тип *</label>
                <select name="type" id="type-external-select" class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>" disabled>
                    <?php foreach (ExtensionNumber::EXTERNAL_TYPES as $t): ?>
                        <option value="<?= $t ?>" <?= ($extension['type'] ?? '') === $t ? 'selected' : '' ?>><?= View::e(Labels::extensionTypeLabel($t, 'external')) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['type'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['type']) ?></div><?php endif; ?>
            </div>

            <div class="col-md-4">
                <label class="form-label">Владелец</label>
                <input type="text" name="owner" class="form-control" value="<?= View::e($extension['owner'] ?? '') ?>" placeholder="Иванов И.И. / Отдел продаж">
            </div>

            <div class="col-md-4" id="department-field-standard">
                <label class="form-label">Подразделение</label>
                <select name="department_id" id="department-select-standard" class="form-select">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)($extension['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= View::e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                    <?php foreach (ExtensionNumber::STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= ($extension['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= View::e(Labels::statusLabel($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 external-field" id="legal-entity-field">
                <label class="form-label">Юрлицо</label>
                <select name="legal_entity_id" id="legal-entity-select" class="form-select">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($legalEntities as $le): ?>
                        <option value="<?= (int)$le['id'] ?>" <?= (int)($extension['legal_entity_id'] ?? 0) === (int)$le['id'] ? 'selected' : '' ?>><?= View::e($le['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 external-field" id="provider-field">
                <label class="form-label">Провайдер</label>
                <select name="provider_id" id="provider-select" class="form-select">
                    <option value="">— сначала выберите юрлицо —</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" data-legal-entities="<?= View::e($p['legal_entity_ids'] ?? '') ?>" <?= (int)($extension['provider_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= View::e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 external-field" id="trunk-field">
                <label class="form-label">Транк</label>
                <select name="trunk_id" class="form-select">
                    <option value="">— не выбран —</option>
                    <?php foreach ($trunks as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (int)($extension['trunk_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= View::e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6" id="scenario-context-row-scenario">
                <label class="form-label">Связанный сценарий</label>
                <select name="scenario_id" class="form-select">
                    <option value="">— не выбран —</option>
                    <?php foreach ($scenarios as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)($extension['scenario_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= View::e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6" id="scenario-context-row-context">
                <label class="form-label">Контекст диалплана</label>
                <input type="text" name="context" class="form-control" value="<?= View::e($extension['context'] ?? '') ?>" placeholder="internal">
            </div>

            <div class="col-md-3 external-field calltracking-field" id="department-field-calltracking">
                <label class="form-label">Подразделение</label>
                <select name="department_id" id="department-select-calltracking" class="form-select" disabled>
                    <option value="">— не выбрано —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)($extension['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= View::e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5 external-field calltracking-field" id="calltracking-target-field">
                <label class="form-label">Цель</label>
                <select name="calltracking_target_id" id="calltracking-target-select" class="form-select">
                    <option value="">— сначала выберите подразделение —</option>
                    <?php foreach ($calltrackingTargets as $ct): ?>
                        <option value="<?= (int)$ct['id'] ?>" data-department="<?= (int)($ct['department_id'] ?? 0) ?>" <?= (int)($extension['calltracking_target_id'] ?? 0) === (int)$ct['id'] ? 'selected' : '' ?>><?= View::e($ct['target']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Список целей ограничен выбранным подразделением.</div>
            </div>

            <!-- ============================================================
                 Состав очереди / группы обзвона (queue | ivr)
                 ============================================================ -->
            <div class="col-12 queue-or-ringgroup-field">
                <hr>
                <label class="form-label fw-semibold" id="members-section-label">Состав очереди</label>
                <div class="position-relative mb-2">
                    <input type="text" id="member-search-input" class="form-control" autocomplete="off" placeholder="Начните вводить номер или владельца, чтобы добавить участника...">
                    <div id="member-search-results" class="list-group position-absolute w-100 shadow-sm" style="z-index: 20; max-height: 260px; overflow-y: auto; display:none;"></div>
                </div>
                <ul class="list-group" id="members-list">
                    <?php foreach (($groupMembers ?? []) as $m): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center" data-id="<?= (int)$m['id'] ?>">
                            <span class="member-label"><?= View::e($m['number']) ?><?= !empty($m['owner']) ? ' — ' . View::e($m['owner']) : '' ?></span>
                            <span class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary member-move-up" title="Вверх">↑</button>
                                <button type="button" class="btn btn-outline-secondary member-move-down" title="Вниз">↓</button>
                                <button type="button" class="btn btn-outline-danger member-remove" title="Удалить">✕</button>
                            </span>
                            <input type="hidden" name="member_extension_id[]" value="<?= (int)$m['id'] ?>">
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="form-text">Участником может быть только внутренний номер с типом «Внутренний». Порядок можно менять стрелками.</div>
                <?php if (isset($errors['member_extension_id'])): ?><div class="text-danger small mt-1"><?= View::e($errors['member_extension_id']) ?></div><?php endif; ?>
            </div>

            <!-- Метод обзвона: два независимых select'а (очередь / группа обзвона) -->
            <div class="col-md-8 queue-only-field" id="ring-strategy-queue-field">
                <label class="form-label">Метод обзвона участников</label>
                <select name="ring_strategy" id="ring-strategy-queue-select" class="form-select <?= isset($errors['ring_strategy']) ? 'is-invalid' : '' ?>">
                    <?php foreach (ExtensionNumber::QUEUE_RING_STRATEGIES as $rs): ?>
                        <option value="<?= $rs ?>" <?= ($qs['ring_strategy'] ?? 'ringall') === $rs ? 'selected' : '' ?>><?= View::e(Labels::queueRingStrategyLabel($rs)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['ring_strategy'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['ring_strategy']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-8 ringgroup-only-field" id="ring-strategy-ringgroup-field">
                <label class="form-label">Метод обзвона участников</label>
                <select name="ring_strategy" id="ring-strategy-ringgroup-select" class="form-select <?= isset($errors['ring_strategy']) ? 'is-invalid' : '' ?>" disabled>
                    <?php foreach (ExtensionNumber::RING_GROUP_RING_STRATEGIES as $rs): ?>
                        <option value="<?= $rs ?>" <?= ($qs['ring_strategy'] ?? 'ringall') === $rs ? 'selected' : '' ?>><?= View::e(Labels::ringGroupRingStrategyLabel($rs)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['ring_strategy'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['ring_strategy']) ?></div><?php endif; ?>
            </div>

            <!-- Настройки очереди для операторов (общие для queue и ivr) -->
            <div class="col-12 queue-or-ringgroup-field"><hr><h5 class="mb-0">Настройки очереди для операторов</h5></div>
            <div class="col-md-4 queue-or-ringgroup-field">
                <label class="form-label">Время попытки дозвона операторам, сек</label>
                <input type="number" min="0" max="86400" name="agent_answer_timeout_sec" class="form-control <?= isset($errors['agent_answer_timeout_sec']) ? 'is-invalid' : '' ?>" value="<?= View::e((string)($qs['agent_answer_timeout_sec'] ?? '')) ?>">
                <?php if (isset($errors['agent_answer_timeout_sec'])): ?><div class="invalid-feedback"><?= View::e($errors['agent_answer_timeout_sec']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 queue-or-ringgroup-field">
                <label class="form-label">Время отдыха оператора после звонка, сек</label>
                <input type="number" min="0" max="86400" name="agent_wrapup_time_sec" class="form-control <?= isset($errors['agent_wrapup_time_sec']) ? 'is-invalid' : '' ?>" value="<?= View::e((string)($qs['agent_wrapup_time_sec'] ?? '')) ?>">
                <?php if (isset($errors['agent_wrapup_time_sec'])): ?><div class="invalid-feedback"><?= View::e($errors['agent_wrapup_time_sec']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 queue-only-field d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="agent_ring_in_use" id="agent-ring-in-use" value="1" <?= !empty($qs['agent_ring_in_use']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="agent-ring-in-use">Получать новые звонки во время разговора</label>
                </div>
            </div>

            <!-- Настройки очереди для вызывающего абонента (только queue) -->
            <div class="col-12 queue-only-field"><hr><h5 class="mb-0">Настройки очереди для вызывающего абонента</h5></div>
            <div class="col-md-4 queue-only-field">
                <label class="form-label">Что слышит абонент во время ожидания</label>
                <select name="caller_wait_sound" id="caller-wait-sound-select" class="form-select <?= isset($errors['caller_wait_sound']) ? 'is-invalid' : '' ?>">
                    <?php foreach (ExtensionNumber::CALLER_WAIT_SOUNDS as $cw): ?>
                        <option value="<?= $cw ?>" <?= ($qs['caller_wait_sound'] ?? 'music') === $cw ? 'selected' : '' ?>><?= View::e(Labels::callerWaitSoundLabel($cw)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['caller_wait_sound'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['caller_wait_sound']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-8 queue-only-field" id="moh-music-field">
                <label class="form-label">Фоновая музыка (MOH)</label>
                <?= musicPicker('moh_music_id', $musicFiles, (int)($qs['moh_music_id'] ?? 0)) ?>
                <?php if (isset($errors['moh_music_id'])): ?><div class="text-danger small mt-1"><?= View::e($errors['moh_music_id']) ?></div><?php endif; ?>
            </div>

            <div class="col-md-6 queue-only-field">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="announce_position" id="announce-position" value="1" <?= !empty($qs['announce_position']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="announce-position">Оповещать о текущей позиции в очереди</label>
                </div>
            </div>
            <div class="col-md-6 queue-only-field">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="announce_hold_time" id="announce-hold-time" value="1" <?= !empty($qs['announce_hold_time']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="announce-hold-time">Оповещать о расчётном времени ожидания</label>
                </div>
            </div>
            <div class="col-md-8 queue-only-field">
                <label class="form-label">Рекламная фраза или дополнительное оповещение</label>
                <?= musicPicker('announcement_music_id', $musicFiles, (int)($qs['announcement_music_id'] ?? 0)) ?>
                <?php if (isset($errors['announcement_music_id'])): ?><div class="text-danger small mt-1"><?= View::e($errors['announcement_music_id']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 queue-only-field">
                <label class="form-label">Повтор оповещений, сек</label>
                <input type="number" min="0" max="86400" name="announcement_repeat_sec" class="form-control <?= isset($errors['announcement_repeat_sec']) ? 'is-invalid' : '' ?>" value="<?= View::e((string)($qs['announcement_repeat_sec'] ?? '')) ?>">
                <?php if (isset($errors['announcement_repeat_sec'])): ?><div class="invalid-feedback"><?= View::e($errors['announcement_repeat_sec']) ?></div><?php endif; ?>
            </div>

            <!-- Маршрутизация звонков при сбоях (только queue) -->
            <div class="col-12 queue-only-field"><hr><h5 class="mb-0">Маршрутизация звонков при сбоях</h5></div>
            <div class="col-md-6 queue-only-field">
                <div class="border rounded p-3 h-100">
                    <h6>Сценарий №1</h6>
                    <label class="form-label">Если абонент ожидает в очереди более, сек</label>
                    <input type="number" min="0" max="86400" name="failover1_timeout_sec" class="form-control mb-2 <?= isset($errors['failover1_timeout_sec']) ? 'is-invalid' : '' ?>" value="<?= View::e((string)($qs['failover1_timeout_sec'] ?? '')) ?>">
                    <?php if (isset($errors['failover1_timeout_sec'])): ?><div class="invalid-feedback"><?= View::e($errors['failover1_timeout_sec']) ?></div><?php endif; ?>
                    <label class="form-label">Перевести вызов на</label>
                    <?= failoverPicker('failover1', $qs['failover1_target_type'] ?? null, $qs['failover1_target_id'] ?? null, $qs['failover1_target_label'] ?? null) ?>
                    <?php if (isset($errors['failover1_target'])): ?><div class="text-danger small mt-1"><?= View::e($errors['failover1_target']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 queue-only-field">
                <div class="border rounded p-3 h-100">
                    <h6>Сценарий №2</h6>
                    <label class="form-label">Если в очереди нет операторов, перевести вызов на</label>
                    <?= failoverPicker('failover2', $qs['failover2_target_type'] ?? null, $qs['failover2_target_id'] ?? null, $qs['failover2_target_label'] ?? null) ?>
                    <?php if (isset($errors['failover2_target'])): ?><div class="text-danger small mt-1"><?= View::e($errors['failover2_target']) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="4"><?= View::e($extension['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/extensions/' . (int)$extension['id'] : '/extensions' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
<script>
var CURRENT_EXTENSION_ID = <?= $currentExtensionId !== null ? (int)$currentExtensionId : 'null' ?>;
</script>
<script src="/assets/js/extension-form.js"></script>
