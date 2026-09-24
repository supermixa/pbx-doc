<?php
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
use App\Models\RouteEntity;

$isEdit = !empty($route['id']);
$action = $isEdit ? '/routes/' . (int)$route['id'] . '/edit' : '/routes';
$currentDirection = $route['direction'] ?? 'outbound';
$currentSelectionType = $route['selection_type'] ?? '';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card" id="route-form">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($route['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">Направление *</label>
                <select name="direction" id="direction-select" class="form-select">
                    <?php foreach (RouteEntity::DIRECTIONS as $d): ?>
                        <option value="<?= $d ?>" <?= $currentDirection === $d ? 'selected' : '' ?>><?= View::e(Labels::directionLabel($d)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Входящий: Сценарий -->
            <div class="col-md-3 direction-field inbound-field" id="scenario-field">
                <label class="form-label">Сценарий</label>
                <select name="scenario_id" id="scenario-select" class="form-select <?= isset($errors['scenario_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— не выбран —</option>
                    <?php foreach ($scenarios as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)($route['scenario_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= View::e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['scenario_id'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['scenario_id']) ?></div><?php endif; ?>
            </div>

            <!-- Исходящий: Тип отбора (на месте прежнего поля "Транк") -->
            <div class="col-md-3 direction-field outbound-field" id="selection-type-field">
                <label class="form-label">Тип отбора *</label>
                <select name="selection_type" id="selection-type-select" class="form-select <?= isset($errors['selection_type']) ? 'is-invalid' : '' ?>">
                    <option value="">— не выбран —</option>
                    <?php foreach (RouteEntity::SELECTION_TYPES as $st): ?>
                        <option value="<?= $st ?>" <?= $currentSelectionType === $st ? 'selected' : '' ?>><?= View::e(Labels::routeSelectionTypeLabel($st)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['selection_type'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['selection_type']) ?></div><?php endif; ?>
            </div>

            <!-- Шаблон номера: подпись и видимость зависят от направления/типа отбора -->
            <div class="col-md-6" id="pattern-field">
                <label class="form-label" id="pattern-label">Шаблон номера звонящего</label>
                <input type="text" name="pattern" class="form-control <?= isset($errors['pattern']) ? 'is-invalid' : '' ?>" value="<?= View::e($route['pattern'] ?? '') ?>" placeholder="_8ZZZZZZZZZZ или _XXX">
                <?php if (isset($errors['pattern'])): ?><div class="invalid-feedback"><?= View::e($errors['pattern']) ?></div><?php endif; ?>
            </div>

            <!-- Контекст диалплана: для входящих остаётся здесь по потоку формы
                 (домашняя позиция — сразу после context-home-inbound), для
                 исходящих JS физически переносит этот же узел к маркеру
                 context-anchor-outbound (в конец, перед Описанием) — см.
                 routes-form.js. Перенос делается через insertBefore самим DOM-узлом,
                 а не через CSS order, т.к. order работает только при
                 display:flex у .row, а локально (без Bootstrap CDN) .row не flex —
                 такое решение работает независимо от того, загрузился ли Bootstrap. -->
            <span id="context-home-inbound" style="display:none"></span>
            <div class="col-md-6" id="context-field">
                <label class="form-label">Контекст диалплана</label>
                <input type="text" name="context" class="form-control <?= isset($errors['context']) ? 'is-invalid' : '' ?>" value="<?= View::e($route['context'] ?? '') ?>" placeholder="outbound-office">
                <?php if (isset($errors['context'])): ?><div class="invalid-feedback"><?= View::e($errors['context']) ?></div><?php endif; ?>
            </div>

            <!-- Исходящий + Внутренние номера: состав внутренних номеров -->
            <div class="col-12 direction-field outbound-field selection-field sel-internal-numbers" id="members-field">
                <hr>
                <label class="form-label fw-semibold">Внутренние номера</label>
                <div class="position-relative mb-2">
                    <input type="text" id="route-member-search-input" class="form-control" autocomplete="off" placeholder="Начните вводить номер или владельца, чтобы добавить...">
                    <div id="route-member-search-results" class="list-group position-absolute w-100 shadow-sm" style="z-index: 20; max-height: 260px; overflow-y: auto; display:none;"></div>
                </div>
                <ul class="list-group" id="route-members-list">
                    <?php foreach (($routeMembers ?? []) as $m): ?>
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
                <div class="form-text">Добавлять можно только внутренние номера с типом «Внутренний». Порядок можно менять стрелками.</div>
                <?php if (isset($errors['member_extension_id'])): ?><div class="text-danger small mt-1"><?= View::e($errors['member_extension_id']) ?></div><?php endif; ?>
            </div>

            <!-- Исходящий + Подразделения: выбор подразделения и его состав (только для чтения) -->
            <div class="col-md-6 direction-field outbound-field selection-field sel-departments" id="department-field">
                <hr class="d-md-none">
                <label class="form-label">Подразделение *</label>
                <select name="department_id" id="department-select" class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)($route['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= View::e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['department_id'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['department_id']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 direction-field outbound-field selection-field sel-departments" id="department-members-field">
                <label class="form-label">Внутренние номера подразделения</label>
                <ul class="list-group" id="department-members-list">
                    <li class="list-group-item text-muted" id="department-members-empty">— сначала выберите подразделение —</li>
                </ul>
            </div>

            <!-- Исходящий (кроме "Сложный"): Исходящий АОН + Исходящий транк -->
            <div class="col-md-4 direction-field outbound-field selection-field sel-aon-trunk" id="aon-field">
                <label class="form-label">Исходящий АОН</label>
                <select name="outbound_caller_id_id" id="aon-select" class="form-select <?= isset($errors['outbound_caller_id_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— не выбран —</option>
                    <?php foreach ($externalNumbers as $en): ?>
                        <option value="<?= (int)$en['id'] ?>" data-trunk="<?= (int)($en['trunk_id'] ?? 0) ?: '' ?>" <?= (int)($route['outbound_caller_id_id'] ?? 0) === (int)$en['id'] ? 'selected' : '' ?>><?= View::e($en['number']) ?><?= !empty($en['owner']) ? ' — ' . View::e($en['owner']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['outbound_caller_id_id'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['outbound_caller_id_id']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4 direction-field outbound-field selection-field sel-aon-trunk" id="trunk-field">
                <label class="form-label">Исходящий транк</label>
                <select name="trunk_id" id="trunk-select" class="form-select <?= isset($errors['trunk_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— не выбран —</option>
                    <?php foreach ($trunks as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (int)($route['trunk_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= View::e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Если сначала выбрать транк, в поле «Исходящий АОН» останутся только номера этого транка.</div>
                <?php if (isset($errors['trunk_id'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['trunk_id']) ?></div><?php endif; ?>
            </div>

            <!-- Исходящий + Сложный: пока без дополнительных настроек -->
            <div class="col-12 direction-field outbound-field selection-field sel-complex" id="complex-field">
                <hr>
                <p class="text-muted mb-0">Для типа отбора «Сложный» дополнительные настройки пока не предусмотрены.</p>
            </div>

            <span id="context-anchor-outbound" style="display:none"></span>

            <div class="col-12" id="description-field">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="5" placeholder="Логика маршрутизации, приоритеты, условия..."><?= View::e($route['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/routes/' . (int)$route['id'] : '/routes' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
<script src="/assets/js/routes-form.js"></script>
