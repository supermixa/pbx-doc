<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\Scenario;

$isEdit = !empty($scenario['id']);
$action = $isEdit ? '/scenarios/' . (int)$scenario['id'] . '/edit' : '/scenarios';
$initialFlow = $isEdit ? Scenario::decodeFlow($scenario['flow_json'] ?? null) : [];
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card" id="scenario-form">
    <div class="card-body">
        <?= Csrf::field() ?>
        <input type="hidden" name="flow_json" id="flow-json-input">

        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($scenario['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="col-12">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Что делает сценарий, для какого отдела/линии..."><?= View::e($scenario['description'] ?? '') ?></textarea>
            </div>
        </div>

        <hr>

        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <label class="form-label fw-semibold mb-0">Логика сценария</label>
            <div class="dropdown">
                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="add-block-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    + Добавить блок
                </button>
                <ul class="dropdown-menu" id="add-block-menu"></ul>
            </div>
        </div>
        <div class="form-text mb-2">Блоки выполняются по порядку сверху вниз. Порядок можно менять стрелками.</div>

        <div id="scenario-blocks"></div>
        <p class="text-muted small" id="scenario-blocks-empty">Блоков пока нет — добавьте первый через кнопку выше.</p>
        <?php if (isset($errors['flow_json'])): ?><div class="text-danger small mt-1"><?= View::e($errors['flow_json']) ?></div><?php endif; ?>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/scenarios/' . (int)$scenario['id'] : '/scenarios' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
<script>
var SCENARIO_BLOCK_TYPES = <?= json_encode(Scenario::BLOCK_TYPES) ?>;
var SCENARIO_MUSIC_OPTIONS = <?= json_encode(array_map(fn($m) => ['id' => (int)$m['id'], 'label' => $m['title']], $musicFiles), JSON_UNESCAPED_UNICODE) ?>;
var SCENARIO_QUEUE_OPTIONS = <?= json_encode(array_map(fn($e) => ['id' => (int)$e['id'], 'label' => $e['number'] . ($e['owner'] ? ' — ' . $e['owner'] : '')], $queueOptions), JSON_UNESCAPED_UNICODE) ?>;
var SCENARIO_INTERNAL_OPTIONS = <?= json_encode(array_map(fn($e) => ['id' => (int)$e['id'], 'label' => $e['number'] . ($e['owner'] ? ' — ' . $e['owner'] : '')], $internalOptions), JSON_UNESCAPED_UNICODE) ?>;
var SCENARIO_FAILOVER_TARGETS = <?= json_encode(array_map(fn($e) => ['id' => (int)$e['id'], 'label' => $e['number'] . ($e['owner'] ? ' — ' . $e['owner'] : '')], $failoverTargets), JSON_UNESCAPED_UNICODE) ?>;
var SCENARIO_ROUTE_OPTIONS = <?= json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'label' => $r['name']], $routeOptions), JSON_UNESCAPED_UNICODE) ?>;
var SCENARIO_INITIAL_BLOCKS = <?= json_encode($initialFlow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="/assets/js/scenario-form.js"></script>
