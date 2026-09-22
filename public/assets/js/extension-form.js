(function () {
    'use strict';

    var form = document.getElementById('extension-form');
    if (!form) {
        return;
    }
    var csrfToken = form.querySelector('input[name="_csrf"]').value;

    // ------------------------------------------------------------------
    // Категория / тип — показ/скрытие полей (существующая логика)
    // ------------------------------------------------------------------
    var categorySelect = document.getElementById('category-select');
    var internalFields = document.querySelectorAll('.internal-field');
    var externalFields = document.querySelectorAll('.external-field');
    var typeInternalField = document.getElementById('type-internal-field');
    var typeExternalField = document.getElementById('type-external-field');
    var typeInternalSelect = document.getElementById('type-internal-select');
    var typeExternalSelect = document.getElementById('type-external-select');
    var calltrackingField = document.getElementById('calltracking-target-field');
    var departmentFieldStandard = document.getElementById('department-field-standard');
    var departmentSelectStandard = document.getElementById('department-select-standard');
    var departmentFieldCalltracking = document.getElementById('department-field-calltracking');
    var departmentSelectCalltracking = document.getElementById('department-select-calltracking');

    var scenarioRowScenario = document.getElementById('scenario-context-row-scenario');
    var scenarioRowContext = document.getElementById('scenario-context-row-context');

    var queueOrRingGroupFields = document.querySelectorAll('.queue-or-ringgroup-field');
    var queueOnlyFields = document.querySelectorAll('.queue-only-field');
    var ringGroupOnlyFields = document.querySelectorAll('.ringgroup-only-field');

    var ringStrategyQueueSelect = document.getElementById('ring-strategy-queue-select');
    var ringStrategyRingGroupSelect = document.getElementById('ring-strategy-ringgroup-select');
    var membersSectionLabel = document.getElementById('members-section-label');

    function toggleCalltrackingField() {
        var isExternal = categorySelect.value === 'external';
        var isCalltracking = isExternal && typeExternalSelect.value === 'calltracking';
        calltrackingField.style.display = isCalltracking ? '' : 'none';

        // Подразделение: обычное поле — для внутренних и внешних (кроме
        // Calltracking); у Calltracking своё поле "Подразделение", парное
        // с выбором Цели (фильтрует список целей).
        departmentFieldCalltracking.style.display = isCalltracking ? '' : 'none';
        departmentSelectCalltracking.disabled = !isCalltracking;

        var showStandardDepartment = !isCalltracking;
        departmentFieldStandard.style.display = showStandardDepartment ? '' : 'none';
        departmentSelectStandard.disabled = !showStandardDepartment;

        filterCalltrackingTargets();
    }

    function toggleQueueRingGroupSections() {
        var isInternal = categorySelect.value === 'internal';
        var type = isInternal ? typeInternalSelect.value : '';
        var isQueue = type === 'queue';
        var isRingGroup = type === 'ivr';
        var isEither = isQueue || isRingGroup;

        // Сценарий/Контекст скрываются только для типа "Очередь"
        var hideScenarioContext = isQueue;
        scenarioRowScenario.style.display = hideScenarioContext ? 'none' : '';
        scenarioRowContext.style.display = hideScenarioContext ? 'none' : '';

        queueOrRingGroupFields.forEach(function (el) { el.style.display = isEither ? '' : 'none'; });
        queueOnlyFields.forEach(function (el) { el.style.display = isQueue ? '' : 'none'; });
        ringGroupOnlyFields.forEach(function (el) { el.style.display = isRingGroup ? '' : 'none'; });

        ringStrategyQueueSelect.disabled = !isQueue;
        ringStrategyRingGroupSelect.disabled = !isRingGroup;

        if (membersSectionLabel) {
            membersSectionLabel.textContent = isRingGroup ? 'Состав группы' : 'Состав очереди';
        }

        toggleMohVisibility();
    }

    function toggleByCategory() {
        var isExternal = categorySelect.value === 'external';

        internalFields.forEach(function (el) { el.style.display = isExternal ? 'none' : ''; });
        externalFields.forEach(function (el) { el.style.display = isExternal ? '' : 'none'; });

        typeInternalField.style.display = isExternal ? 'none' : '';
        typeExternalField.style.display = isExternal ? '' : 'none';
        typeInternalSelect.disabled = isExternal;
        typeExternalSelect.disabled = !isExternal;

        toggleCalltrackingField();
        toggleQueueRingGroupSections();
    }

    categorySelect.addEventListener('change', toggleByCategory);
    typeInternalSelect.addEventListener('change', toggleQueueRingGroupSections);
    typeExternalSelect.addEventListener('change', toggleCalltrackingField);
    toggleByCategory();

    var legalEntitySelect = document.getElementById('legal-entity-select');
    var providerSelect = document.getElementById('provider-select');
    var providerOptions = Array.prototype.slice.call(providerSelect.querySelectorAll('option[value]:not([value=""])'));
    var providerPlaceholder = providerSelect.querySelector('option[value=""]');

    function filterProviders() {
        var legalEntityId = legalEntitySelect.value;
        var anyVisible = false;
        providerOptions.forEach(function (opt) {
            var ids = (opt.getAttribute('data-legal-entities') || '').split(',');
            var visible = legalEntityId !== '' && ids.indexOf(legalEntityId) !== -1;
            opt.style.display = visible ? '' : 'none';
            opt.disabled = !visible;
            if (visible) {
                anyVisible = true;
            }
        });
        if (legalEntityId === '') {
            providerPlaceholder.textContent = '— сначала выберите юрлицо —';
        } else if (!anyVisible) {
            providerPlaceholder.textContent = '— нет привязанных провайдеров —';
        } else {
            providerPlaceholder.textContent = '— не выбран —';
        }
        var currentOpt = providerSelect.options[providerSelect.selectedIndex];
        if (currentOpt && currentOpt.value !== '' && currentOpt.style.display === 'none') {
            providerSelect.value = '';
        }
    }

    legalEntitySelect.addEventListener('change', filterProviders);
    filterProviders();

    // ------------------------------------------------------------------
    // Calltracking: подразделение -> фильтрация доступных целей
    // ------------------------------------------------------------------
    var calltrackingTargetSelect = document.getElementById('calltracking-target-select');
    var calltrackingTargetOptions = calltrackingTargetSelect
        ? Array.prototype.slice.call(calltrackingTargetSelect.querySelectorAll('option[value]:not([value=""])'))
        : [];
    var calltrackingTargetPlaceholder = calltrackingTargetSelect
        ? calltrackingTargetSelect.querySelector('option[value=""]')
        : null;

    function filterCalltrackingTargets() {
        if (!calltrackingTargetSelect || !departmentSelectCalltracking) {
            return;
        }
        var departmentId = departmentSelectCalltracking.value;
        var anyVisible = false;
        calltrackingTargetOptions.forEach(function (opt) {
            var visible = departmentId !== '' && opt.getAttribute('data-department') === departmentId;
            opt.style.display = visible ? '' : 'none';
            opt.disabled = !visible;
            if (visible) {
                anyVisible = true;
            }
        });
        if (calltrackingTargetPlaceholder) {
            if (departmentId === '') {
                calltrackingTargetPlaceholder.textContent = '— сначала выберите подразделение —';
            } else if (!anyVisible) {
                calltrackingTargetPlaceholder.textContent = '— нет целей для этого подразделения —';
            } else {
                calltrackingTargetPlaceholder.textContent = '— не выбрана —';
            }
        }
        var currentOpt = calltrackingTargetSelect.options[calltrackingTargetSelect.selectedIndex];
        if (currentOpt && currentOpt.value !== '' && currentOpt.style.display === 'none') {
            calltrackingTargetSelect.value = '';
        }
    }

    if (departmentSelectCalltracking) {
        departmentSelectCalltracking.addEventListener('change', filterCalltrackingTargets);
    }
    filterCalltrackingTargets();

    // ------------------------------------------------------------------
    // Что слышит абонент во время ожидания -> показ/скрытие MOH
    // ------------------------------------------------------------------
    var callerWaitSoundSelect = document.getElementById('caller-wait-sound-select');
    var mohMusicField = document.getElementById('moh-music-field');

    function toggleMohVisibility() {
        if (!callerWaitSoundSelect || !mohMusicField) {
            return;
        }
        // "Фоновая музыка (MOH)" — поле только для Очереди (queue-only-field);
        // без этой проверки поле оставалось видимым для любого номера
        // (служебные, групповые, все внешние), т.к. раньше видимость решалась
        // только значением "Что слышит абонент", без учёта типа номера.
        var isInternal = categorySelect.value === 'internal';
        var isQueueNow = isInternal && typeInternalSelect.value === 'queue';
        mohMusicField.style.display = (isQueueNow && callerWaitSoundSelect.value === 'music') ? '' : 'none';
    }

    if (callerWaitSoundSelect) {
        callerWaitSoundSelect.addEventListener('change', toggleMohVisibility);
    }

    // ------------------------------------------------------------------
    // Состав очереди / группы обзвона — поиск и добавление участников
    // ------------------------------------------------------------------
    var memberSearchInput = document.getElementById('member-search-input');
    var memberSearchResults = document.getElementById('member-search-results');
    var membersList = document.getElementById('members-list');

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, delay);
        };
    }

    function currentMemberIds() {
        return Array.prototype.map.call(membersList.querySelectorAll('li[data-id]'), function (li) {
            return li.getAttribute('data-id');
        });
    }

    function addMemberRow(item) {
        if (currentMemberIds().indexOf(String(item.id)) !== -1) {
            return;
        }
        var li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.setAttribute('data-id', item.id);

        var label = document.createElement('span');
        label.className = 'member-label';
        label.textContent = item.label;

        var btnGroup = document.createElement('span');
        btnGroup.className = 'btn-group btn-group-sm';
        btnGroup.innerHTML =
            '<button type="button" class="btn btn-outline-secondary member-move-up" title="Вверх">↑</button>' +
            '<button type="button" class="btn btn-outline-secondary member-move-down" title="Вниз">↓</button>' +
            '<button type="button" class="btn btn-outline-danger member-remove" title="Удалить">✕</button>';

        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'member_extension_id[]';
        hidden.value = item.id;

        li.appendChild(label);
        li.appendChild(btnGroup);
        li.appendChild(hidden);
        membersList.appendChild(li);
    }

    if (memberSearchInput) {
        var memberSearch = debounce(function () {
            var q = memberSearchInput.value.trim();
            if (q.length < 1) {
                memberSearchResults.style.display = 'none';
                memberSearchResults.innerHTML = '';
                return;
            }
            var url = '/extensions/search-targets?mode=member&q=' + encodeURIComponent(q);
            if (CURRENT_EXTENSION_ID) {
                url += '&exclude=' + CURRENT_EXTENSION_ID;
            }
            fetch(url).then(function (r) { return r.json(); }).then(function (items) {
                memberSearchResults.innerHTML = '';
                if (!items.length) {
                    memberSearchResults.style.display = 'none';
                    return;
                }
                items.forEach(function (item) {
                    var a = document.createElement('button');
                    a.type = 'button';
                    a.className = 'list-group-item list-group-item-action';
                    a.textContent = item.label;
                    a.addEventListener('click', function () {
                        addMemberRow(item);
                        memberSearchInput.value = '';
                        memberSearchResults.style.display = 'none';
                        memberSearchResults.innerHTML = '';
                    });
                    memberSearchResults.appendChild(a);
                });
                memberSearchResults.style.display = '';
            }).catch(function () { memberSearchResults.style.display = 'none'; });
        }, 250);

        memberSearchInput.addEventListener('input', memberSearch);
        document.addEventListener('click', function (e) {
            if (e.target !== memberSearchInput && !memberSearchResults.contains(e.target)) {
                memberSearchResults.style.display = 'none';
            }
        });
    }

    if (membersList) {
        membersList.addEventListener('click', function (e) {
            var li = e.target.closest('li[data-id]');
            if (!li) {
                return;
            }
            if (e.target.classList.contains('member-remove')) {
                li.remove();
            } else if (e.target.classList.contains('member-move-up')) {
                var prev = li.previousElementSibling;
                if (prev) {
                    membersList.insertBefore(li, prev);
                }
            } else if (e.target.classList.contains('member-move-down')) {
                var next = li.nextElementSibling;
                if (next) {
                    membersList.insertBefore(next, li);
                }
            }
        });
    }

    // ------------------------------------------------------------------
    // Маршрутизация при сбоях — интерактивный поиск цели (сценарий/номер)
    // ------------------------------------------------------------------
    var failoverInputs = document.querySelectorAll('.failover-search-input');
    failoverInputs.forEach(function (input) {
        var prefix = input.getAttribute('data-prefix');
        var results = document.querySelector('.failover-search-results[data-prefix="' + prefix + '"]');
        var selectedBox = document.getElementById(prefix + '-selected');
        var typeField = document.getElementById(prefix + '_target_type');
        var idField = document.getElementById(prefix + '_target_id');

        var search = debounce(function () {
            var q = input.value.trim();
            if (q.length < 1) {
                results.style.display = 'none';
                results.innerHTML = '';
                return;
            }
            var url = '/extensions/search-targets?mode=failover&q=' + encodeURIComponent(q);
            if (CURRENT_EXTENSION_ID) {
                url += '&exclude=' + CURRENT_EXTENSION_ID;
            }
            fetch(url).then(function (r) { return r.json(); }).then(function (items) {
                results.innerHTML = '';
                if (!items.length) {
                    results.style.display = 'none';
                    return;
                }
                items.forEach(function (item) {
                    var a = document.createElement('button');
                    a.type = 'button';
                    a.className = 'list-group-item list-group-item-action';
                    a.textContent = item.label;
                    a.addEventListener('click', function () {
                        typeField.value = item.type;
                        idField.value = item.id;
                        selectedBox.innerHTML = '<span class="badge text-bg-light text-dark border">' +
                            escapeHtml(item.label) + ' <a href="#" class="failover-clear text-danger text-decoration-none" data-prefix="' + prefix + '">✕</a></span>';
                        input.value = '';
                        results.style.display = 'none';
                        results.innerHTML = '';
                    });
                    results.appendChild(a);
                });
                results.style.display = '';
            }).catch(function () { results.style.display = 'none'; });
        }, 250);

        input.addEventListener('input', search);
        document.addEventListener('click', function (e) {
            if (e.target !== input && !results.contains(e.target)) {
                results.style.display = 'none';
            }
            if (e.target.classList && e.target.classList.contains('failover-clear') && e.target.getAttribute('data-prefix') === prefix) {
                e.preventDefault();
                typeField.value = '';
                idField.value = '';
                selectedBox.innerHTML = '';
            }
        });
    });

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ------------------------------------------------------------------
    // Быстрая загрузка своего файла музыки прямо из формы номера
    // ------------------------------------------------------------------
    var quickUploadInputs = document.querySelectorAll('.music-quick-upload');
    quickUploadInputs.forEach(function (input) {
        input.addEventListener('change', function () {
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
                    select.appendChild(opt);
                    if (status) { status.textContent = 'Добавлено: ' + data.title; }
                    input.value = '';
                })
                .catch(function () {
                    if (status) { status.textContent = 'Ошибка загрузки'; }
                });
        });
    });
})();
