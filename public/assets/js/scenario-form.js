(function () {
    'use strict';

    var form = document.getElementById('scenario-form');
    if (!form) {
        return;
    }
    var csrfToken = form.querySelector('input[name="_csrf"]').value;
    var blocksContainer = document.getElementById('scenario-blocks');
    var emptyHint = document.getElementById('scenario-blocks-empty');
    var addBlockMenu = document.getElementById('add-block-menu');

    var BLOCK_TYPE_LABELS = {
        play_file: 'Проиграть файл',
        ai_agent: 'ИИ агент',
        queue_transfer: 'Переключить на очередь',
        number_transfer: 'Переключить на номер',
        script: 'Скрипт',
        notify_tg: 'Оповещение TG',
        notify_email: 'Оповещение E-mail',
        hangup: 'Положить трубку',
        external_transfer: 'Перевести на внешний номер',
        business_hours: 'Время работы сценария'
    };

    var WEEKDAYS = [
        { key: 'mon', label: 'Пн' }, { key: 'tue', label: 'Вт' }, { key: 'wed', label: 'Ср' },
        { key: 'thu', label: 'Чт' }, { key: 'fri', label: 'Пт' }, { key: 'sat', label: 'Сб' },
        { key: 'sun', label: 'Вс' }
    ];

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function optionsHtml(list, selectedId) {
        var sel = selectedId == null ? '' : String(selectedId);
        return (list || []).map(function (o) {
            var isSel = String(o.id) === sel ? ' selected' : '';
            return '<option value="' + o.id + '"' + isSel + '>' + escapeHtml(o.label) + '</option>';
        }).join('');
    }

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, delay);
        };
    }

    // ------------------------------------------------------------------
    // "При неудаче" — общий под-блок для "Переключить на очередь"/"Переключить
    // на номер": очередь/группа/внутренний номер (готовый справочник),
    // ссылка на блок оповещения TG/E-mail этого же сценария, либо "положить
    // трубку". Список блоков-оповещений досчитывается динамически —
    // см. refreshBlockRefSelects().
    // ------------------------------------------------------------------
    function onFailureHtml(onFailure) {
        var kind = (onFailure && onFailure.kind) || '';
        var targetId = onFailure ? onFailure.target_id : null;
        var blockRef = onFailure ? onFailure.block_ref : '';
        return (
            '<label class="form-label">При неудаче</label>' +
            '<select class="form-select form-select-sm onfail-kind-select mb-2">' +
                '<option value=""' + (kind === '' ? ' selected' : '') + '>— не настроено —</option>' +
                '<option value="extension"' + (kind === 'extension' ? ' selected' : '') + '>Очередь / группа обзвона / внутренний номер</option>' +
                '<option value="block"' + (kind === 'block' ? ' selected' : '') + '>Оповещение в этом сценарии (TG/E-mail)</option>' +
                '<option value="hangup"' + (kind === 'hangup' ? ' selected' : '') + '>Положить трубку</option>' +
            '</select>' +
            '<div class="onfail-section" data-onfail-section="extension" style="' + (kind === 'extension' ? '' : 'display:none') + '">' +
                '<select class="form-select form-select-sm onfail-extension-select">' +
                    '<option value="">— не выбран —</option>' + optionsHtml(SCENARIO_FAILOVER_TARGETS, targetId) +
                '</select>' +
            '</div>' +
            '<div class="onfail-section" data-onfail-section="block" style="' + (kind === 'block' ? '' : 'display:none') + '">' +
                '<select class="form-select form-select-sm onfail-blockref-select" data-selected="' + escapeHtml(blockRef || '') + '">' +
                    '<option value="">— выберите блок —</option>' +
                '</select>' +
            '</div>'
        );
    }

    // ------------------------------------------------------------------
    // Поля по типу блока
    // ------------------------------------------------------------------
    var FIELD_RENDERERS = {
        play_file: function (id, f) {
            var selectId = 'music-select-' + id;
            return (
                '<div class="row g-2">' +
                    '<div class="col-md-6">' +
                        '<label class="form-label">Файл</label>' +
                        '<select class="form-select form-select-sm" data-field="music_id" id="' + selectId + '">' +
                            '<option value="">— не выбрано —</option>' + optionsHtml(SCENARIO_MUSIC_OPTIONS, f.music_id) +
                        '</select>' +
                        '<div class="input-group input-group-sm mt-1">' +
                            '<input type="file" class="form-control music-quick-upload" data-target-select="' + selectId + '" accept=".wav,.mp3,audio/wav,audio/mpeg">' +
                            '<span class="input-group-text music-quick-upload-status text-muted" style="display:none;"></span>' +
                        '</div>' +
                        '<div class="form-text">Выберите файл из справочника «Музыка» или загрузите свой.</div>' +
                    '</div>' +
                '</div>'
            );
        },
        ai_agent: function (id, f) {
            return (
                '<div class="row g-2">' +
                    '<div class="col-md-4"><label class="form-label">Сервер</label><input type="text" class="form-control form-control-sm" data-field="server" value="' + escapeHtml(f.server) + '"></div>' +
                    '<div class="col-md-4"><label class="form-label">Запрос</label><input type="text" class="form-control form-control-sm" data-field="query" value="' + escapeHtml(f.query) + '"></div>' +
                    '<div class="col-md-4"><label class="form-label">Описание</label><input type="text" class="form-control form-control-sm" data-field="description" value="' + escapeHtml(f.description) + '"></div>' +
                '</div>'
            );
        },
        queue_transfer: function (id, f) {
            return (
                '<div class="row g-2 mb-2">' +
                    '<div class="col-md-6"><label class="form-label">Номер очереди</label><select class="form-select form-select-sm" data-field="queue_id"><option value="">— не выбран —</option>' + optionsHtml(SCENARIO_QUEUE_OPTIONS, f.queue_id) + '</select></div>' +
                    '<div class="col-md-4"><label class="form-label">Время ожидания, сек</label><input type="number" min="0" max="3600" class="form-control form-control-sm" data-field="wait_sec" value="' + escapeHtml(f.wait_sec) + '"></div>' +
                '</div>' +
                onFailureHtml(f.on_failure)
            );
        },
        number_transfer: function (id, f) {
            return (
                '<div class="row g-2 mb-2">' +
                    '<div class="col-md-6"><label class="form-label">Внутренний номер</label><select class="form-select form-select-sm" data-field="internal_id"><option value="">— не выбран —</option>' + optionsHtml(SCENARIO_INTERNAL_OPTIONS, f.internal_id) + '</select></div>' +
                    '<div class="col-md-4"><label class="form-label">Время ожидания, сек</label><input type="number" min="0" max="3600" class="form-control form-control-sm" data-field="wait_sec" value="' + escapeHtml(f.wait_sec) + '"></div>' +
                '</div>' +
                onFailureHtml(f.on_failure)
            );
        },
        script: function (id, f) {
            return (
                '<div class="row g-2">' +
                    '<div class="col-md-4"><label class="form-label">Название</label><input type="text" class="form-control form-control-sm" data-field="name" value="' + escapeHtml(f.name) + '"></div>' +
                    '<div class="col-md-8"><label class="form-label">Скрипт</label><textarea class="form-control form-control-sm font-monospace" data-field="script" rows="3">' + escapeHtml(f.script) + '</textarea></div>' +
                '</div>'
            );
        },
        notify_tg: function (id, f) {
            return (
                '<div class="row g-2">' +
                    '<div class="col-md-4"><label class="form-label">Id группы</label><input type="text" class="form-control form-control-sm notify-key-field" data-field="group_id" value="' + escapeHtml(f.group_id) + '" placeholder="-1001234567890"></div>' +
                    '<div class="col-md-8"><label class="form-label">Пример текста</label><input type="text" class="form-control form-control-sm" data-field="example_text" value="' + escapeHtml(f.example_text) + '"></div>' +
                '</div>'
            );
        },
        notify_email: function (id, f) {
            return (
                '<div class="row g-2">' +
                    '<div class="col-md-4"><label class="form-label">E-mail получателя</label><input type="email" class="form-control form-control-sm notify-key-field" data-field="email" value="' + escapeHtml(f.email) + '"></div>' +
                    '<div class="col-md-4"><label class="form-label">Почта отправителя</label><input type="email" class="form-control form-control-sm" data-field="from_email" value="' + escapeHtml(f.from_email) + '"></div>' +
                    '<div class="col-md-4"><label class="form-label">Пример текста</label><input type="text" class="form-control form-control-sm" data-field="example_text" value="' + escapeHtml(f.example_text) + '"></div>' +
                '</div>'
            );
        },
        hangup: function () {
            return '<p class="text-muted small mb-0">Звонок завершается — дополнительных настроек нет.</p>';
        },
        external_transfer: function (id, f) {
            return (
                '<div class="row g-2">' +
                    '<div class="col-md-5"><label class="form-label">Номер</label><input type="text" class="form-control form-control-sm" data-field="number" value="' + escapeHtml(f.number) + '"></div>' +
                    '<div class="col-md-7"><label class="form-label">Маршрут на выход</label><select class="form-select form-select-sm" data-field="route_id"><option value="">— не выбран —</option>' + optionsHtml(SCENARIO_ROUTE_OPTIONS, f.route_id) + '</select></div>' +
                '</div>'
            );
        },
        business_hours: function (id, f) {
            var days = f.days || [];
            var dayCheckboxes = WEEKDAYS.map(function (d) {
                var checked = days.indexOf(d.key) !== -1 ? ' checked' : '';
                var cbId = 'day-' + id + '-' + d.key;
                return (
                    '<div class="form-check form-check-inline">' +
                        '<input class="form-check-input" type="checkbox" data-field-day="' + d.key + '"' + checked + ' id="' + cbId + '">' +
                        '<label class="form-check-label" for="' + cbId + '">' + d.label + '</label>' +
                    '</div>'
                );
            }).join('');
            return (
                '<div class="row g-2 align-items-end">' +
                    '<div class="col-md-3"><label class="form-label">С</label><input type="time" class="form-control form-control-sm" data-field="from_time" value="' + escapeHtml(f.from_time) + '"></div>' +
                    '<div class="col-md-3"><label class="form-label">До</label><input type="time" class="form-control form-control-sm" data-field="to_time" value="' + escapeHtml(f.to_time) + '"></div>' +
                    '<div class="col-md-6"><label class="form-label d-block">Дни недели</label>' + dayCheckboxes + '</div>' +
                '</div>'
            );
        }
    };

    // ------------------------------------------------------------------
    // Добавление / удаление / перестановка блоков
    // ------------------------------------------------------------------
    var idCounter = 0;
    function generateBlockId() {
        idCounter++;
        return 'b' + Date.now().toString(36) + idCounter;
    }

    function renderBlockCard(id, type, fields) {
        var renderer = FIELD_RENDERERS[type];
        var fieldsHtml = renderer ? renderer(id, fields || {}) : '<p class="text-danger small mb-0">Неизвестный тип блока</p>';
        return (
            '<div class="card mb-2 scenario-block" data-block-id="' + id + '" data-type="' + type + '">' +
                '<div class="card-header bg-white d-flex justify-content-between align-items-center py-2">' +
                    '<span><span class="badge text-bg-secondary me-2 block-index-badge">#</span><strong>' + escapeHtml(BLOCK_TYPE_LABELS[type] || type) + '</strong></span>' +
                    '<span class="btn-group btn-group-sm">' +
                        '<button type="button" class="btn btn-outline-secondary block-move-up" title="Вверх">↑</button>' +
                        '<button type="button" class="btn btn-outline-secondary block-move-down" title="Вниз">↓</button>' +
                        '<button type="button" class="btn btn-outline-danger block-remove" title="Удалить">✕</button>' +
                    '</span>' +
                '</div>' +
                '<div class="card-body py-2">' + fieldsHtml + '</div>' +
            '</div>'
        );
    }

    function addBlock(type, id, fields) {
        var wrapper = document.createElement('div');
        wrapper.innerHTML = renderBlockCard(id, type, fields || {});
        var card = wrapper.firstElementChild;
        blocksContainer.appendChild(card);
        renumberBlocks();
        refreshBlockRefSelects();
        emptyHint.style.display = 'none';
    }

    function renumberBlocks() {
        var cards = blocksContainer.querySelectorAll('.scenario-block');
        cards.forEach(function (card, idx) {
            var badge = card.querySelector('.block-index-badge');
            if (badge) {
                badge.textContent = '#' + (idx + 1);
            }
        });
    }

    /**
     * Пересобирает варианты во всех select'ах "При неудаче -> Оповещение в
     * этом сценарии" на основе актуального списка блоков TG/E-mail (включая
     * их текущие Id группы/E-mail — они попадают в подпись варианта).
     */
    function refreshBlockRefSelects() {
        var notifyBlocks = [];
        var cards = blocksContainer.querySelectorAll('.scenario-block');
        cards.forEach(function (card, idx) {
            var type = card.getAttribute('data-type');
            if (type !== 'notify_tg' && type !== 'notify_email') {
                return;
            }
            var keyField = card.querySelector('.notify-key-field');
            var keyVal = keyField ? keyField.value.trim() : '';
            var typeLabel = type === 'notify_tg' ? 'Оповещение TG' : 'Оповещение E-mail';
            notifyBlocks.push({
                id: card.getAttribute('data-block-id'),
                label: '#' + (idx + 1) + ' ' + typeLabel + (keyVal ? ' (' + keyVal + ')' : '')
            });
        });

        blocksContainer.querySelectorAll('.onfail-blockref-select').forEach(function (sel) {
            var desired = sel.getAttribute('data-selected');
            if (desired === null) {
                desired = sel.value;
            }
            sel.innerHTML = '<option value="">— выберите блок —</option>' + notifyBlocks.map(function (b) {
                var isSel = b.id === desired ? ' selected' : '';
                return '<option value="' + b.id + '"' + isSel + '>' + escapeHtml(b.label) + '</option>';
            }).join('');
            sel.removeAttribute('data-selected');
        });
    }

    addBlockMenu && SCENARIO_BLOCK_TYPES.forEach(function (type) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.className = 'dropdown-item';
        a.href = '#';
        a.textContent = BLOCK_TYPE_LABELS[type] || type;
        a.addEventListener('click', function (e) {
            e.preventDefault();
            addBlock(type, generateBlockId(), type === 'business_hours' ? { days: [] } : {});
        });
        li.appendChild(a);
        addBlockMenu.appendChild(li);
    });

    blocksContainer.addEventListener('click', function (e) {
        var card = e.target.closest('.scenario-block');
        if (!card) {
            return;
        }
        if (e.target.classList.contains('block-remove')) {
            card.remove();
            renumberBlocks();
            refreshBlockRefSelects();
            if (!blocksContainer.querySelector('.scenario-block')) {
                emptyHint.style.display = '';
            }
        } else if (e.target.classList.contains('block-move-up')) {
            var prev = card.previousElementSibling;
            if (prev) {
                blocksContainer.insertBefore(card, prev);
                renumberBlocks();
            }
        } else if (e.target.classList.contains('block-move-down')) {
            var next = card.nextElementSibling;
            if (next) {
                blocksContainer.insertBefore(next, card);
                renumberBlocks();
            }
        }
    });

    blocksContainer.addEventListener('change', function (e) {
        if (e.target.classList.contains('onfail-kind-select')) {
            var card = e.target.closest('.scenario-block');
            var kind = e.target.value;
            card.querySelectorAll('.onfail-section').forEach(function (sec) {
                sec.style.display = sec.getAttribute('data-onfail-section') === kind ? '' : 'none';
            });
            if (kind === 'block') {
                refreshBlockRefSelects();
            }
            return;
        }
        if (e.target.classList.contains('music-quick-upload')) {
            handleMusicQuickUpload(e.target);
        }
    });

    blocksContainer.addEventListener('input', debounce(function (e) {
        if (e.target.classList.contains('notify-key-field')) {
            refreshBlockRefSelects();
        }
    }, 200));

    function handleMusicQuickUpload(input) {
        var file = input.files && input.files[0];
        if (!file) {
            return;
        }
        var status = input.parentElement.querySelector('.music-quick-upload-status');
        var select = document.getElementById(input.getAttribute('data-target-select'));
        if (status) {
            status.style.display = '';
            status.textContent = 'Загрузка...';
        }
        var fd = new FormData();
        fd.append('_csrf', csrfToken);
        fd.append('file', file);
        fd.append('title', file.name.replace(/\.[^.]+$/, ''));

        fetch('/music/quick-upload', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    if (status) { status.textContent = 'Ошибка: ' + data.error; }
                    return;
                }
                var opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.title;
                opt.selected = true;
                if (select) { select.appendChild(opt); }
                SCENARIO_MUSIC_OPTIONS.push({ id: data.id, label: data.title });
                if (status) { status.textContent = 'Добавлено: ' + data.title; }
                input.value = '';
            })
            .catch(function () {
                if (status) { status.textContent = 'Ошибка загрузки'; }
            });
    }

    // ------------------------------------------------------------------
    // Сборка flow_json перед отправкой формы
    // ------------------------------------------------------------------
    form.addEventListener('submit', function () {
        var blocks = [];
        blocksContainer.querySelectorAll('.scenario-block').forEach(function (card) {
            var type = card.getAttribute('data-type');
            var id = card.getAttribute('data-block-id');
            var fields = {};
            card.querySelectorAll('[data-field]').forEach(function (el) {
                fields[el.getAttribute('data-field')] = el.value;
            });
            if (type === 'business_hours') {
                var days = [];
                card.querySelectorAll('[data-field-day]').forEach(function (cb) {
                    if (cb.checked) {
                        days.push(cb.getAttribute('data-field-day'));
                    }
                });
                fields.days = days;
            }
            if (type === 'queue_transfer' || type === 'number_transfer') {
                var kindSel = card.querySelector('.onfail-kind-select');
                var kind = kindSel ? kindSel.value : '';
                var onFailure = { kind: kind, target_id: null, block_ref: null };
                if (kind === 'extension') {
                    var extSel = card.querySelector('.onfail-extension-select');
                    onFailure.target_id = extSel && extSel.value ? extSel.value : null;
                } else if (kind === 'block') {
                    var refSel = card.querySelector('.onfail-blockref-select');
                    onFailure.block_ref = refSel && refSel.value ? refSel.value : null;
                }
                fields.on_failure = onFailure;
            }
            blocks.push({ id: id, type: type, fields: fields });
        });
        document.getElementById('flow-json-input').value = JSON.stringify(blocks);
    });

    // ------------------------------------------------------------------
    // Начальное состояние (редактирование существующего сценария)
    // ------------------------------------------------------------------
    (SCENARIO_INITIAL_BLOCKS || []).forEach(function (b) {
        addBlock(b.type, b.id || generateBlockId(), b.fields || {});
    });
})();
