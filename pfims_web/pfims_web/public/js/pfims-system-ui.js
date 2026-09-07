(function () {
    'use strict';

    if (window.PFIMS_SYSTEM_UI_LOADED) return;
    window.PFIMS_SYSTEM_UI_LOADED = true;

    var AUTO_REFRESH_MS = 30000;
    var refreshActions = [];

    function filterLabel(control) {
        var explicitLabels = {
            budgetProjectFilter: 'Project',
            budgetSearch: 'Search',
            budgetStatusFilter: 'Status',
            expenseCategoryFilter: 'Category',
            expenseComponentFilter: 'Component',
            expenseScopeFilter: 'Expense type',
            historyEnd: 'To',
            historySearch: 'Search',
            historyStart: 'From',
            itemsCategoryFilter: 'Category',
            itemsSearchInput: 'Search',
            itemsStockFilter: 'Stock status',
            itemsSupplierFilter: 'Supplier',
            projectFilter: 'Project',
            projectSearch: 'Search',
            searchInput: 'Search',
            supplierSearch: 'Search',
            supplierSort: 'Sort by',
            transactionCategoryFilter: 'Category',
            transactionProjectFilter: 'Project',
            typeFilter: 'Transaction type'
        };
        if (explicitLabels[control.id]) return explicitLabels[control.id];
        if (control.type === 'date') return /end|to/i.test(control.id) ? 'To' : 'From';
        if (control.type === 'search') return 'Search';
        var firstOption = control.options && control.options[0];
        return firstOption ? firstOption.textContent.replace(/^All\s+|^Sort by\s+/i, '').trim() : 'Filter';
    }

    function installSharedFilters() {
        document.querySelectorAll('.project-filter-panel, .filters-bar, .filter-row, .filters-grid, .history-filters, section.filters').forEach(function (panel) {
            if (panel.dataset.pfimsFilters === 'ready') return;
            panel.classList.add('pfims-filter-panel');
            panel.dataset.pfimsFilters = 'ready';

            if (!panel.closest('.reports-page')) {
                var subtitle = document.createElement('p');
                subtitle.className = 'pfims-filter-subtitle';
                subtitle.textContent = 'Filters update the KPIs, live records, and graph together.';
                panel.prepend(subtitle);
            }

            Array.from(panel.children).forEach(function (child) {
                if (child.matches('button')) {
                    if (/clear|^x$|^✕$/i.test(((child.textContent || '') + ' ' + (child.className || '')).trim())) {
                        child.classList.add('pfims-clear-filters');
                        child.textContent = 'Clear filters';
                        child.setAttribute('aria-label', 'Clear filters');
                    }
                    return;
                }
                if (child.matches('label, .project-filter-field, .filter-control')) {
                    child.classList.add('pfims-filter-field');
                    if (child.querySelector('input[type="search"]')) child.classList.add('pfims-filter-search');
                    return;
                }
                if (!child.matches('input, select')) return;
                var field = document.createElement('label');
                field.className = 'pfims-filter-field';
                if (child.type === 'search') field.classList.add('pfims-filter-search');
                if (child.id) field.htmlFor = child.id;
                var text = document.createElement('span');
                text.textContent = filterLabel(child);
                panel.insertBefore(field, child);
                field.append(text, child);
            });
        });
    }

    function installQuietScrollbars() {
        document.querySelectorAll('.modal-container, dialog form, .table-wrapper, .table-wrap, .pfims-search-suggestions, .pfims-select-options, .pfims-column-chooser-menu').forEach(function (element) {
            if (element.dataset.pfimsScrollbar === 'ready') return;
            element.dataset.pfimsScrollbar = 'ready';
            var timer;
            element.addEventListener('scroll', function () {
                element.classList.add('pfims-is-scrolling');
                window.clearTimeout(timer);
                timer = window.setTimeout(function () { element.classList.remove('pfims-is-scrolling'); }, 700);
            }, { passive: true });
        });
    }

    function installCustomSelect(select) {
        if (select.dataset.pfimsSelect === 'ready' || select.multiple || Number(select.size || 0) > 1) return;
        select.dataset.pfimsSelect = 'ready';
        select.classList.add('pfims-native-select');

        var wrapper = document.createElement('span');
        wrapper.className = 'pfims-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'pfims-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        var menu = document.createElement('span');
        menu.className = 'pfims-select-options';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;
        wrapper.append(trigger, menu);

        function selectedText() {
            return select.options[select.selectedIndex]?.textContent || select.getAttribute('aria-label') || 'Select';
        }
        function close() {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }
        function rebuild() {
            trigger.textContent = selectedText();
            trigger.disabled = select.disabled;
            menu.replaceChildren();
            Array.from(select.options).forEach(function (nativeOption) {
                var option = document.createElement('button');
                option.type = 'button';
                option.className = 'pfims-select-option';
                option.textContent = nativeOption.textContent;
                option.title = nativeOption.textContent;
                option.disabled = nativeOption.disabled;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', String(nativeOption.selected));
                option.addEventListener('click', function () {
                    select.value = nativeOption.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    trigger.textContent = selectedText();
                    close();
                    trigger.focus();
                });
                menu.appendChild(option);
            });
        }

        trigger.addEventListener('click', function () {
            var opening = menu.hidden;
            document.querySelectorAll('.pfims-select-options:not([hidden])').forEach(function (other) { other.hidden = true; });
            if (opening) rebuild();
            menu.hidden = !opening;
            trigger.setAttribute('aria-expanded', String(opening));
        });
        select.addEventListener('change', function () { trigger.textContent = selectedText(); });
        document.addEventListener('click', function (event) { if (!wrapper.contains(event.target)) close(); });
        new MutationObserver(rebuild).observe(select, { childList: true, subtree: true, attributes: true });
        rebuild();
    }

    function installAutomaticRefresh() {
        document.querySelectorAll('button, a').forEach(function (control) {
            var label = ((control.textContent || '') + ' ' + (control.title || '') + ' ' + (control.getAttribute('aria-label') || '')).trim();
            if (!/^refresh(?:\s|$)/i.test(label)) return;
            refreshActions.push(function () { control.click(); });
            control.remove();
        });

        window.setInterval(function () {
            if (document.hidden || document.querySelector('.modal.show, .modal[style*="display: block"]')) return;
            if (refreshActions.length) {
                refreshActions.forEach(function (refresh) { refresh(); });
            } else {
                ['loadInventoryItems', 'fetchProjects', 'loadSuppliers', 'loadNotifications'].some(function (name) {
                    if (typeof window[name] !== 'function') return false;
                    window[name]();
                    return true;
                });
            }
            document.dispatchEvent(new CustomEvent('pfims:autorefresh'));
        }, AUTO_REFRESH_MS);
    }

    function installColumnChooser(table) {
        if (table.dataset.columnChooser === 'ready' || !table.tHead || !table.tBodies.length) return;
        var headings = Array.from(table.tHead.rows[0]?.cells || []);
        if (headings.length < 2) return;

        var chooser = document.createElement('details');
        chooser.className = 'pfims-column-chooser';
        var summary = document.createElement('summary');
        var menu = document.createElement('div');
        menu.className = 'pfims-column-chooser-menu';
        chooser.append(summary, menu);

        var selections = [];
        headings.forEach(function (heading, index) {
            var name = (heading.textContent || '').trim() || ('Column ' + (index + 1));
            if (/^actions?$/i.test(name)) return;

            var label = document.createElement('label');
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = true;
            checkbox.dataset.columnIndex = String(index);
            checkbox.addEventListener('change', applyColumnVisibility);
            label.append(checkbox, document.createTextNode(name));
            menu.appendChild(label);
            selections.push({ checkbox: checkbox, index: index });
        });
        if (!selections.length) return;

        var wrapper = table.closest('.table-wrapper, .table-wrap, .report-table-wrapper, .items-table-wrapper, .budget-table-wrapper, .table-responsive');
        if (wrapper && wrapper.parentElement) {
            wrapper.parentElement.insertBefore(chooser, wrapper);
        } else {
            table.parentElement.insertBefore(chooser, table);
        }
        table.dataset.columnChooser = 'ready';
        updateSummary();

        function updateSummary() {
            var visible = selections.filter(function (selection) { return selection.checkbox.checked; }).length;
            summary.textContent = 'Columns (' + visible + '/' + selections.length + ')';
        }

        function applyColumnVisibility(event) {
            if (!selections.some(function (selection) { return selection.checkbox.checked; })) {
                event?.target && (event.target.checked = true);
            }
            selections.forEach(function (selection) {
                var visible = selection.checkbox.checked;
                Array.from(table.rows).forEach(function (row) {
                    if (row.cells.length === 1 && row.cells[0].colSpan > 1) return;
                    if (row.cells[selection.index]) row.cells[selection.index].hidden = !visible;
                });
            });
            updateSummary();
        }

        new MutationObserver(function () { applyColumnVisibility(); })
            .observe(table.tBodies[0], { childList: true, subtree: true });
    }

    function installSingleClickNavigation() {
        document.querySelectorAll('.sidebar li').forEach(function (item) {
            if (item.dataset.pfimsNavigation === 'ready') return;
            var link = item.querySelector(':scope > a');
            if (!link) return;
            item.dataset.pfimsNavigation = 'ready';
            item.setAttribute('role', 'presentation');
            item.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea, form')) return;
                link.click();
            });
        });
    }

    function installSearchSuggestions(input) {
        if (input.dataset.pfimsSuggestions === 'ready') return;
        input.dataset.pfimsSuggestions = 'ready';
        input.setAttribute('autocomplete', 'off');

        var menu = document.createElement('div');
        menu.className = 'pfims-search-suggestions';
        menu.hidden = true;
        document.body.appendChild(menu);

        function close() { menu.hidden = true; menu.replaceChildren(); }
        function position() {
            var box = input.getBoundingClientRect();
            menu.style.left = (box.left + window.scrollX) + 'px';
            menu.style.top = (box.bottom + window.scrollY + 4) + 'px';
            menu.style.width = Math.min(Math.max(box.width, 220), 420) + 'px';
        }
        function candidates(query) {
            var scope = input.closest('main, .main-content, body') || document.body;
            var values = [];
            scope.querySelectorAll('tbody tr:not([hidden]) td').forEach(function (cell) {
                var value = (cell.textContent || '').replace(/\s+/g, ' ').trim();
                if (value.length < 2 || value.length > 100 || !value.toLowerCase().includes(query)) return;
                values.push(value.length > 58 ? value.slice(0, 57) + '…' : value);
            });
            return Array.from(new Set(values)).slice(0, 8);
        }
        input.addEventListener('input', function () {
            var query = input.value.trim().toLowerCase();
            if (query.length < 2) return close();
            var matches = candidates(query);
            if (!matches.length) return close();
            menu.replaceChildren();
            matches.forEach(function (value) {
                var option = document.createElement('button');
                option.type = 'button';
                option.textContent = value;
                option.title = value;
                option.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    input.value = value.replace(/…$/, '');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    close();
                });
                menu.appendChild(option);
            });
            position();
            menu.hidden = false;
        });
        input.addEventListener('blur', function () { window.setTimeout(close, 120); });
        window.addEventListener('resize', function () { if (!menu.hidden) position(); });
        window.addEventListener('scroll', function () { if (!menu.hidden) position(); }, true);
    }

    function openCapturedRowAction(row, handler, editMode) {
        handler.call(row, { currentTarget: row, target: row, preventDefault: function () {}, stopPropagation: function () {} });
        if (!editMode) return;
        window.setTimeout(function () {
            var visibleModal = Array.from(document.querySelectorAll('.modal.active, .modal-overlay.active, [role="dialog"]'))
                .filter(function (modal) { return !modal.hidden && getComputedStyle(modal).display !== 'none'; })
                .pop();
            if (!visibleModal) return;
            var editButton = Array.from(visibleModal.querySelectorAll('button')).find(function (button) {
                return /^edit(?:\s|$)/i.test((button.textContent || '').trim()) && !button.hidden && getComputedStyle(button).display !== 'none';
            });
            if (editButton) editButton.click();
        }, 0);
    }

    function makeRowAction(icon, label, callback) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'pfims-row-action';
        button.title = label;
        button.setAttribute('aria-label', label);
        var image = document.createElement('img');
        image.src = '/images/' + icon + '.jpg';
        image.alt = '';
        button.appendChild(image);
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            callback();
        });
        return button;
    }

    function installActionsForClickableRows(table) {
        if (table.classList.contains('analytics-table') || table.closest('.predictive-analytics-root') || table.closest('.dashboard-page')) return;
        Array.from(table.tBodies || []).forEach(function (body) {
            Array.from(body.rows).forEach(function (row) {
                if (row.dataset.pfimsActions === 'ready' || row.classList.contains('total-row')) return;
                if (row.cells.length === 1 && row.cells[0].colSpan > 1) return;
                if (typeof row.onclick !== 'function') return;

                var handler = row.onclick;
                row.onclick = null;
                row.style.cursor = 'default';
                row.removeAttribute('tabindex');
                row.removeAttribute('role');
                row.removeAttribute('aria-label');

                var headerRow = table.tHead && table.tHead.rows[0];
                var actionIndex = headerRow ? Array.from(headerRow.cells).findIndex(function (cell) {
                    return /^actions?$/i.test((cell.textContent || '').trim());
                }) : -1;
                if (headerRow && actionIndex < 0) {
                    var actionHeading = document.createElement('th');
                    actionHeading.textContent = 'Actions';
                    headerRow.appendChild(actionHeading);
                    actionIndex = headerRow.cells.length - 1;
                }

                var actionCell = actionIndex >= 0 && row.cells[actionIndex] ? row.cells[actionIndex] : row.insertCell(-1);
                actionCell.className = 'action-cell';
                actionCell.replaceChildren(
                    makeRowAction('view', 'View details', function () { openCapturedRowAction(row, handler, false); }),
                    makeRowAction('edit', 'Edit record', function () { openCapturedRowAction(row, handler, true); })
                );
                row.dataset.pfimsActions = 'ready';
            });
        });
    }

    function initializeSystemUi() {
        document.querySelectorAll('header a[href*="notification"] span, .top-header a[href*="notification"] span').forEach(function (label) {
            label.classList.add('sr-only');
            label.textContent = 'Open alerts';
        });
        installAutomaticRefresh();
        installSharedFilters();
        installSingleClickNavigation();
        document.querySelectorAll('input[type="search"]').forEach(installSearchSuggestions);
        document.querySelectorAll('select').forEach(installCustomSelect);
        installQuietScrollbars();
        document.querySelectorAll('table').forEach(installActionsForClickableRows);
        document.querySelectorAll('table').forEach(installColumnChooser);
        new MutationObserver(function () {
            installSharedFilters();
            installSingleClickNavigation();
            document.querySelectorAll('input[type="search"]').forEach(installSearchSuggestions);
            document.querySelectorAll('select').forEach(installCustomSelect);
            installQuietScrollbars();
            document.querySelectorAll('table').forEach(installActionsForClickableRows);
            document.querySelectorAll('table').forEach(installColumnChooser);
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSystemUi, { once: true });
    } else {
        initializeSystemUi();
    }
})();
