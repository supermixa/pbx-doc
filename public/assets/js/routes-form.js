(function () {
    'use strict';

    var form = document.getElementById('route-form');
    if (!form) {
        return;
    }

    // ------------------------------------------------------------------
    // Направление / Тип отбора — показ/скрытие и позиционирование полей
    // ------------------------------------------------------------------
    var directionSelect = document.getElementById('direction-select');
    var selectionTypeSelect = document.getElementById('selection-type-select');
    var scenarioSelect = document.getElementById('scenario-select');

    var inboundFields = document.querySelectorAll('.inbound-field');
    var outboundFields = document.querySelectorAll('.outbound-field');
    var selectionFields = document.querySelectorAll('.selection-field');

    var patternField = document.getElementById('pattern-field');
    var patternLabel = document.getElementById('pattern-label');
    var contextField = document.getElementById('context-field');
    var contextHomeInbound = document.getElementById('context-home-inbound');
    var contextAnchorOutbound = document.getElementById('context-anchor-outbound');

    function setDisplay(nodeList, visible) {
        nodeList.forEach(function (el) { el.style.display = visible ? '' : 'none'; });
    }

    function toggleSelectionType() {
        var isInbound = directionSelect.value === 'inbound';
        var st = isInbound ? '' : selectionTypeSelect.value;

        setDisplay(document.querySelectorAll('.sel-internal-numbers'), st === 'internal_numbers');
        setDisplay(document.querySelectorAll('.sel-departments'), st === 'departments');
        setDisplay(document.querySelectorAll('.sel-aon-trunk'), st === 'internal_numbers' || st === 'departments' || st === 'dialed_number');
        setDisplay(document.querySelectorAll('.sel-complex'), st === 'complex');

        // Поле "Шаблон номера" — для входящих это "Шаблон номера звонящего",
        // для исходящего типа "Набранный номер" — "Шаблон набираемого номера",
        // для остальных типов отбора исходящих маршрутов поле не нужно.
        if (isInbound) {
            patternField.style.display = '';
            patternLabel.textContent = 'Шаблон номера звонящего';
        } else if (st === 'dialed_number') {
            patternField.style.display = '';
            patternLabel.textContent = 'Шаблон набираемого номера';
        } else {
            patternField.style.display = 'none';
        }

        if (!isInbound && st === 'departments') {
            fetchDepartmentMembers();
        }
    }

    function toggleDirection() {
        var isInbound = directionSelect.value === 'inbound';

        setDisplay(inboundFields, isInbound);
        setDisplay(outboundFields, !isInbound);
        if (isInbound) {
            // Пока направление не "исходящий", все секции, зависящие от типа
            // отбора, скрыты — toggleSelectionType() сама в этом убедится.
            setDisplay(selectionFields, false);
        }

        scenarioSelect.disabled = !isInbound;
        selectionTypeSelect.disabled = isInbound;

        // Контекст диалплана: для входящих остаётся на своей обычной позиции
        // (после Шаблона номера), для исходящих физически переносится в
        // конец формы, перед Описанием. Делается перемещением самого
        // DOM-узла (insertBefore после маркера-якоря), а не через CSS order —
        // order работает только когда .row является flex-контейнером, а
        // локально (без загруженного Bootstrap с CDN) это не так; перенос
        // DOM-узла работает независимо от того, какой CSS загружен.
        var anchor = isInbound ? contextHomeInbound : contextAnchorOutbound;
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(contextField, anchor.nextSibling);
        }

        toggleSelectionType();
    }

    directionSelect.addEventListener('change', toggleDirection);
    selectionTypeSelect.addEventListener('change', toggleSelectionType);
    toggleDirection();

    // ------------------------------------------------------------------
    // Исходящий АОН <-> Исходящий транк — двусторонний каскад:
    // выбор номера подтягивает его транк; выбор транка сначала сужает
    // список номеров до принадлежащих этому транку.
    // ------------------------------------------------------------------
    var aonSelect = document.getElementById('aon-select');
    var trunkSelect = document.getElementById('trunk-select');
    var aonOptions = aonSelect
        ? Array.prototype.slice.call(aonSelect.querySelectorAll('option[value]:not([value=""])'))
        : [];

    function filterAonByTrunk() {
        if (!aonSelect || !trunkSelect) {
            return;
        }
        var trunkId = trunkSelect.value;
        aonOptions.forEach(function (opt) {
            var dataTrunk = opt.getAttribute('data-trunk') || '';
            var visible = trunkId === '' || dataTrunk === trunkId;
            opt.style.display = visible ? '' : 'none';
            opt.disabled = !visible;
        });
        var current = aonSelect.options[aonSelect.selectedIndex];
        if (current && current.value !== '' && current.style.display === 'none') {
            aonSelect.value = '';
        }
    }

    if (aonSelect && trunkSelect) {
        aonSelect.addEventListener('change', function () {
            var opt = aonSelect.options[aonSelect.selectedIndex];
            var dataTrunk = opt ? (opt.getAttribute('data-trunk') || '') : '';
            if (dataTrunk !== '') {
                // Выставляем значение напрямую, не через dispatchEvent('change'),
                // чтобы не вызвать рекурсивный цикл событий между полями.
                trunkSelect.value = dataTrunk;
            }
            filterAonByTrunk();
        });
        trunkSelect.addEventListener('change', filterAonByTrunk);
        filterAonByTrunk();
    }

    // ------------------------------------------------------------------
    // Подразделение -> живой список его внутренних номеров (только чтение)
    // ------------------------------------------------------------------
    var departmentSelect = document.getElementById('department-select');
    var departmentMembersList = document.getElementById('department-members-list');

    function fetchDepartmentMembers() {
        if (!departmentSelect || !departmentMembersList) {
            return;
        }
        var departmentId = departmentSelect.value;
        if (departmentId === '') {
            departmentMembersList.innerHTML = '<li class="list-group-item text-muted">— сначала выберите подразделение —</li>';
            return;
        }
        departmentMembersList.innerHTML = '<li class="list-group-item text-muted">Загрузка...</li>';
        fetch('/routes/department-members?department_id=' + encodeURIComponent(departmentId))
            .then(function (r) { return r.json(); })
            .then(function (items) {
                if (!items.length) {
                    departmentMembersList.innerHTML = '<li class="list-group-item text-muted">В этом подразделении нет внутренних номеров</li>';
                    return;
                }
                departmentMembersList.innerHTML = '';
                items.forEach(function (item) {
                    var li = document.createElement('li');
                    li.className = 'list-group-item';
                    li.textContent = item.label;
                    departmentMembersList.appendChild(li);
                });
            })
            .catch(function () {
                departmentMembersList.innerHTML = '<li class="list-group-item text-danger">Не удалось загрузить список</li>';
            });
    }

    if (departmentSelect) {
        departmentSelect.addEventListener('change', fetchDepartmentMembers);
        if (departmentSelect.value !== '' && !selectionTypeSelect.disabled && selectionTypeSelect.value === 'departments') {
            fetchDepartmentMembers();
        }
    }

    // ------------------------------------------------------------------
    // Внутренние номера (состав для типа отбора "Внутренние номера") —
    // поиск, добавление, перестановка и удаление (та же логика, что и
    // для состава очереди/группы обзвона в extension-form.js).
    // ------------------------------------------------------------------
    var memberSearchInput = document.getElementById('route-member-search-input');
    var memberSearchResults = document.getElementById('route-member-search-results');
    var membersList = document.getElementById('route-members-list');

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
})();
