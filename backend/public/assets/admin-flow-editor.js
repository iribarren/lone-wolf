/*
 * Lone Wolf backoffice — campaign flow editor glue.
 *
 * The structured flow editor renders Symfony collections whose entries are
 * named …[stages][i][name|guidance] and …[transitions][i][from|to], plus a
 * …[starting_stage] select. Symfony ships no JS for collection add/remove,
 * so this script provides:
 *   1. "Add" / "Remove" buttons for the two collections,
 *   2. keeping every stage-name <select> populated with the current stages,
 *   3. re-syncing selects whenever a stage row is added, removed or renamed.
 *
 * Unknown submitted values are tolerated server-side by design — the domain
 * produces the actionable error message.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function isStagesCollection(holder) {
        return holder.querySelector('[name*="[stages]"]') !== null
            || holder.getAttribute('data-prototype-name') === '__stage__';
    }

    function stageNames(scope) {
        var names = [];
        scope.querySelectorAll('input[name$="[name]"]').forEach(function (input) {
            var value = input.value.trim();
            if (value !== '' && names.indexOf(value) === -1) {
                names.push(value);
            }
        });

        return names;
    }

    function syncSelects(fromElement) {
        var form = fromElement.closest('form');
        if (!form) {
            return;
        }

        var names = stageNames(form);

        form.querySelectorAll('select[name$="[starting_stage]"], select[name$="[from]"], select[name$="[to]"]')
            .forEach(function (select) {
                var current = select.value;

                Array.prototype.slice.call(select.options).forEach(function (option) {
                    if (option.value !== '') {
                        select.removeChild(option);
                    }
                });

                names.forEach(function (name) {
                    var option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    select.appendChild(option);
                });

                if (names.indexOf(current) !== -1) {
                    select.value = current;
                }
            });
    }

    function ensureDeleteButton(row) {
        if (row.querySelector('.js-flow-delete')) {
            return;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-link text-danger btn-sm js-flow-delete';
        button.textContent = 'Remove';
        row.appendChild(button);
    }

    function wireCollection(holder) {
        var label = isStagesCollection(holder) ? 'Add stage' : 'Add transition';

        var addButtonWrap = document.createElement('div');
        addButtonWrap.className = 'text-center my-2';
        var addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'btn btn-secondary btn-sm js-flow-add';
        addButton.textContent = '+ ' + label;
        addButtonWrap.appendChild(addButton);
        holder.appendChild(addButtonWrap);

        addButton.addEventListener('click', function () {
            var prototype = holder.getAttribute('data-prototype');
            if (!prototype) {
                return;
            }

            var counters = Number(holder.getAttribute('data-flow-counter') || '0') + 1;
            holder.setAttribute('data-flow-counter', String(counters));
            var token = holder.getAttribute('data-prototype-name') || '__name__';

            var temp = document.createElement('div');
            temp.innerHTML = prototype.replace(new RegExp(token, 'g'), 'new_' + String(counters));

            var row = temp.firstElementChild;
            if (!row) {
                return;
            }
            row.setAttribute('data-flow-row', '');
            ensureDeleteButton(row);
            holder.insertBefore(row, addButtonWrap);
            syncSelects(row);
        });

        holder.addEventListener('click', function (event) {
            var target = event.target instanceof Element ? event.target : null;
            var button = target ? target.closest('.js-flow-delete') : null;
            if (!button) {
                return;
            }

            var row = button.closest('[data-flow-row]');
            if (row && row.parentNode) {
                row.parentNode.removeChild(row);
                syncSelects(holder);
            }
        });
    }

    function enhance() {
        document.querySelectorAll('[data-prototype]').forEach(function (holder) {
            if (holder.querySelector('[name*="[stages]"]') === null
                && holder.querySelector('[name*="[transitions]"]') === null) {
                return;
            }
            if (holder.getAttribute('data-flow-enhanced')) {
                return;
            }

            holder.setAttribute('data-flow-enhanced', '1');

            Array.prototype.forEach.call(holder.children, function (child) {
                if (child.nodeType === 1 && child.matches('div, fieldset')) {
                    child.setAttribute('data-flow-row', '');
                    ensureDeleteButton(child);
                }
            });

            wireCollection(holder);
        });

        document.querySelectorAll('input[name*="[stages]"][name$="[name]"]')
            .forEach(function (input) {
                if (input.getAttribute('data-flow-wired')) {
                    return;
                }

                input.setAttribute('data-flow-wired', '1');
                input.addEventListener('change', function () {
                    syncSelects(input);
                });
                input.addEventListener('blur', function () {
                    syncSelects(input);
                });
            });

        // syncSelects() resolves its scope with closest('form'), which is null
        // for document.body — the initial population has to start from the
        // forms themselves or it silently no-ops (audit A2).
        document.querySelectorAll('form').forEach(function (form) {
            syncSelects(form);
        });
    }

    ready(enhance);
})();
