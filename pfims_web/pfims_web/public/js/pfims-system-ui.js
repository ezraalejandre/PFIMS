(function () {
    'use strict';

    if (window.PFIMS_SYSTEM_UI_LOADED) return;
    window.PFIMS_SYSTEM_UI_LOADED = true;

    var alertTimer = null;

    function installHeaderClock() {
        var headerActions = document.querySelector('.top-header .right');
        if (!headerActions || headerActions.dataset.pfimsClock === 'ready') return;
        headerActions.dataset.pfimsClock = 'ready';
        var clock = headerActions.querySelector('.header-clock');
        if (!clock) {
            clock = document.createElement('span');
            clock.className = 'header-clock';
            clock.setAttribute('aria-label', 'Current Philippine date and time');
            headerActions.prepend(clock);
        } else if (!clock.matches('span')) {
            var replacement = document.createElement('span');
            replacement.className = 'header-clock';
            replacement.setAttribute('aria-label', 'Current Philippine date and time');
            clock.replaceWith(replacement);
            clock = replacement;
        }
        clock.replaceChildren();
        var date = document.createElement('span');
        date.className = 'header-clock-date';
        var time = document.createElement('time');
        time.className = 'header-clock-time';
        clock.append(date, time);
        function update() {
            var now = new Date();
            date.textContent = new Intl.DateTimeFormat('en-PH', {
                timeZone: 'Asia/Manila',
                weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
            }).format(now) + ' ';
            time.dateTime = now.toISOString();
            time.textContent = new Intl.DateTimeFormat('en-PH', {
                timeZone: 'Asia/Manila',
                hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true
            }).format(now);
        }
        update();
        window.setInterval(update, 1000);
    }
    window.showPfimsAlert = function (message, type, duration) {
        var tone = ['success', 'error', 'info', 'warning'].includes(type) ? type : 'info';
        var alert = document.getElementById('pfimsGlobalAlert');
        if (!alert) {
            alert = document.createElement('div');
            alert.id = 'pfimsGlobalAlert';
            alert.setAttribute('role', 'alert');
            alert.setAttribute('aria-live', 'polite');
            alert.innerHTML = '<span class="pfims-alert-icon" aria-hidden="true"></span><span class="pfims-alert-message"></span><button type="button" class="pfims-alert-close" aria-label="Close alert">&times;</button>';
            alert.querySelector('.pfims-alert-close').addEventListener('click', function () { alert.removeAttribute('data-visible'); });
            document.body.appendChild(alert);
        }
        alert.className = 'pfims-alert-popup ' + tone;
        alert.querySelector('.pfims-alert-icon').textContent = tone === 'success' ? '✓' : (tone === 'error' ? '!' : 'i');
        alert.querySelector('.pfims-alert-message').textContent = message || 'Action completed.';
        alert.setAttribute('data-visible', 'true');
        window.clearTimeout(alertTimer);
        alertTimer = window.setTimeout(function () { alert.removeAttribute('data-visible'); }, Number(duration) || 5000);
        return alert;
    };

    if (typeof window.showSuccess === 'function') {
        window.showSuccess = function (message) {
            return window.showPfimsAlert(message || 'Action completed successfully.', 'success');
        };
    }

    document.addEventListener('mousedown', function (event) {
        var alert = document.getElementById('pfimsGlobalAlert');
        if (alert?.dataset.visible === 'true' && !alert.contains(event.target)) {
            alert.removeAttribute('data-visible');
            window.clearTimeout(alertTimer);
        }
    });

    var ROLE_PATHS = {
        admin: {
            '/dashboard': '/dashboard', '/projects': '/projects', '/finance': '/finance',
            '/inventory': '/inventory', '/suppliers': '/suppliers', '/reports': '/reports',
            '/notifications': '/notifications', '/profile': '/profile', '/settings': '/settings'
        },
        accounting: {
            '/dashboard': '/adashboard', '/projects': null, '/finance': '/afinance',
            '/inventory': null, '/suppliers': null, '/reports': '/areports',
            '/notifications': '/anotifications', '/profile': '/aprofile', '/settings': '/asettings'
        },
        operations: {
            '/dashboard': '/odashboard', '/projects': '/oprojects', '/finance': null,
            '/inventory': '/oinventory', '/suppliers': '/osuppliers', '/reports': '/oreports',
            '/notifications': '/onotifications', '/profile': '/oprofile', '/settings': '/osettings'
        }
    };

    function installRoleNavigation() {
        var portal = document.body?.dataset.portal || 'admin';
        var paths = ROLE_PATHS[portal] || ROLE_PATHS.admin;
        document.querySelectorAll('a[href]').forEach(function (link) {
            var url;
            try { url = new URL(link.href, window.location.origin); } catch (_) { return; }
            if (!Object.prototype.hasOwnProperty.call(paths, url.pathname)) return;
            var replacement = paths[url.pathname];
            if (replacement === null) {
                var navigationItem = link.closest('.sidebar li');
                if (navigationItem) navigationItem.hidden = true;
                return;
            }
            link.href = replacement + url.search + url.hash;
        });
    }

    var MODULE_NAVIGATION = {
        projects: [
            ['Project Records', ''],
            ['Project Cost Prediction', '/ml-dashboard-test?section=predictive']
        ],
        finance: [
            ['Expenses', ''], ['Budgets', '?section=budgets'], ['Contracts', '?section=contracts'],
            ['AR / AP', '?section=ar-ap'], ['Cash Position', '?section=cash-position'],
            ['Equipment', '?section=equipment'], ['Bonds', '?section=bonds'],
            ['Budget-Spending Comparison', '/ml-dashboard-test?section=budget-comparison']
        ],
        inventory: [
            ['Items', ''], ['Suppliers', '/suppliers'], ['Transactions', '?section=transactions'],
            ['Material Projection', '/ml-dashboard-test?section=material-projection']
        ]
    };

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

    function currentDefaultFilterModule() {
        if (document.body?.dataset.defaultFilterModule) return document.body.dataset.defaultFilterModule;
        var path = window.location.pathname.replace(/^\/(a|o)(?=dashboard|finance|projects|inventory|suppliers|reports)/, '/');
        var section = new URLSearchParams(window.location.search).get('section') || '';
        if (path === '/dashboard') return 'dashboard';
        if (path === '/projects') return 'projects';
        if (path === '/suppliers') return 'suppliers';
        if (path === '/reports') return 'reports';
        if (path === '/audit-logs') return 'audit-logs';
        if (path === '/finance') {
            if (/budget/i.test(section)) return 'finance.budgets';
            if (/bond/i.test(section)) return 'finance.bonds';
            return 'finance.expenses';
        }
        if (path === '/inventory') return /transaction/i.test(section) ? 'inventory.transactions' : 'inventory.items';
        if (path === '/ml-dashboard-test') return /budget/i.test(section) ? 'analytics.budget' : 'analytics.material';
        return null;
    }

    function installDefaultFilters() {
        if (document.body?.dataset.pfimsDefaultFilters === 'loading' || document.body?.dataset.pfimsDefaultFilters === 'ready') return;
        var module = currentDefaultFilterModule();
        if (!module || document.body.classList.contains('settings-page')) return;
        document.body.dataset.pfimsDefaultFilters = 'loading';
        fetch('/api/default-filters', { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : {}; })
            .then(function (allDefaults) {
                var defaults = allDefaults[module] || {};
                document.body.dataset.pfimsDefaultFilters = 'ready';
                if (!Object.keys(defaults).length) return;
                var controls = [];
                var resetButtons = new Set();

                function syncResetButton(button) {
                    var differs = controls.some(function (control) {
                        var expected = defaults[control.id || control.name];
                        if (control.multiple) expected = Array.isArray(expected) ? expected : [expected];
                        var actual = control.multiple
                            ? Array.from(control.selectedOptions).map(function (option) { return option.value; })
                            : control.value;
                        return JSON.stringify(actual) !== JSON.stringify(expected);
                    });
                    button.hidden = !differs;
                }

                function applyAvailableControls() {
                    Object.keys(defaults).forEach(function (key) {
                        var control = document.getElementById(key) || document.querySelector('[name="' + CSS.escape(key) + '"]');
                        if (!control || control.dataset.pfimsDefaultApplied === 'ready') return;
                        var value = defaults[key];
                        if (control.tagName === 'SELECT' && !control.multiple && value !== ''
                            && !Array.from(control.options).some(function (option) { return option.value === String(value); })) return;
                        control.dataset.pfimsDefaultApplied = 'ready';
                        if (control.multiple) {
                            var selected = Array.isArray(value) ? value.map(String) : [String(value)];
                            Array.from(control.options).forEach(function (option) { option.selected = selected.includes(option.value); });
                        } else control.value = value;
                        controls.push(control);
                        control.dispatchEvent(new Event(control.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
                        var panel = control.closest('.pfims-filter-panel, .filters-bar, .filter-row, .filters-grid, .history-filters, section.filters');
                        if (!panel) return;
                        panel.classList.add('pfims-using-defaults');
                        if (!panel.querySelector('.pfims-default-filter-note')) {
                            var note = document.createElement('span');
                            note.className = 'pfims-default-filter-note';
                            note.textContent = 'Using saved defaults';
                            panel.prepend(note);
                        }
                        var reset = panel.querySelector('.pfims-reset-defaults');
                        if (!reset) {
                            reset = document.createElement('button');
                            reset.type = 'button';
                            reset.className = 'pfims-reset-defaults';
                            reset.textContent = 'Reset Filters to Default';
                            reset.hidden = true;
                            var clear = panel.querySelector('.pfims-clear-filters');
                            if (clear) clear.insertAdjacentElement('afterend', reset); else panel.appendChild(reset);
                            reset.addEventListener('click', function () {
                                controls.filter(function (item) { return panel.contains(item); }).forEach(function (item) {
                                    var expected = defaults[item.id || item.name];
                                    item.value = expected;
                                    item.dispatchEvent(new Event(item.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
                                });
                                syncResetButton(reset);
                            });
                        }
                        resetButtons.add(reset);
                        if (control.dataset.pfimsDefaultWatch !== 'ready') {
                            control.dataset.pfimsDefaultWatch = 'ready';
                            ['input', 'change'].forEach(function (eventName) {
                                control.addEventListener(eventName, function () {
                                    window.setTimeout(function () { resetButtons.forEach(syncResetButton); }, 0);
                                });
                            });
                        }
                    });
                    resetButtons.forEach(syncResetButton);
                }
                applyAvailableControls();
                new MutationObserver(applyAvailableControls).observe(document.body, { childList: true, subtree: true });
            })
            .catch(function () { document.body.dataset.pfimsDefaultFilters = 'unavailable'; });
    }

    function installAdminAuditNavigation() {
        if (document.body?.dataset.portal !== 'admin' || document.querySelector('.sidebar a[href$="/audit-logs"]')) return;
        var list = document.querySelector('.sidebar nav ul');
        if (!list) return;
        var item = document.createElement('li');
        if (window.location.pathname === '/audit-logs') item.className = 'active';
        item.innerHTML = '<a href="/audit-logs"><span class="nav-link-icon pfims-audit-icon" aria-hidden="true">≡</span>AUDIT LOGS</a>';
        list.appendChild(item);
    }

    function installAuditDeepLink() {
        var subject = new URLSearchParams(window.location.search).get('audit_subject');
        if (!subject || document.body.dataset.pfimsAuditDeepLink === 'ready') return;
        document.body.dataset.pfimsAuditDeepLink = 'ready';
        var attempts = 0;
        function tryOpen() {
            attempts += 1;
            var candidates = Array.from(document.querySelectorAll('[data-id], [data-record-id], [data-project-id], [data-item-id], [data-supplier-id]'));
            var target = candidates.find(function (element) {
                return Array.from(element.attributes).some(function (attribute) { return /^data-.*id$/.test(attribute.name) && attribute.value === subject; });
            });
            var row = target?.closest('tr') || target;
            var view = row && Array.from(row.querySelectorAll('button, a')).find(function (control) {
                return /^view/i.test(control.getAttribute('aria-label') || control.title || control.textContent || '');
            });
            if (view) { view.click(); return; }
            if (attempts < 30) window.setTimeout(tryOpen, 150);
        }
        tryOpen();
    }

    function installSharedFilters() {
        document.querySelectorAll('.project-filter-panel, .filters-bar, .filter-row, .filters-grid, .history-filters, section.filters').forEach(function (panel) {
            if (panel.dataset.pfimsFilters === 'ready') return;
            panel.classList.add('pfims-filter-panel');
            if (!panel.closest('.modal, .modal-overlay, dialog')) panel.classList.add('pfims-sticky-filter');
            panel.dataset.pfimsFilters = 'ready';

            if (!panel.closest('.reports-page')) {
                var subtitle = document.createElement('p');
                subtitle.className = 'pfims-filter-subtitle';
                subtitle.textContent = panel.dataset.filterDescription || 'Filters update the KPIs, live records, and graph together.';
                panel.prepend(subtitle);
            }

            Array.from(panel.children).forEach(function (child) {
                if (child.matches('button')) {
                    if (/clear|^x$|^✕$/i.test(((child.textContent || '') + ' ' + (child.className || '')).trim())) {
                        child.classList.add('pfims-clear-filters');
                        child.textContent = 'Clear filters';
                        child.setAttribute('aria-label', 'Clear filters');
                        child.addEventListener('click', function () {
                            window.setTimeout(function () {
                                panel.querySelectorAll('select').forEach(function (select) {
                                    select.dispatchEvent(new Event('change', { bubbles: true }));
                                });
                            }, 0);
                        });
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

            var clear = panel.querySelector(':scope > .pfims-clear-filters');
            if (clear) {
                var reportHeading = panel.closest('.filter-panel')?.querySelector(':scope > .panel-heading');
                if (reportHeading) {
                    reportHeading.classList.add('pfims-filter-heading');
                    reportHeading.appendChild(clear);
                } else {
                    var subtitle = panel.querySelector(':scope > .pfims-filter-subtitle');
                    if (subtitle) {
                        var heading = document.createElement('div');
                        heading.className = 'pfims-filter-heading';
                        panel.prepend(heading);
                        heading.append(subtitle, clear);
                    }
                }
            }

        });
    }

    function removeProjectFilterControls() {
        ['project', 'projectFilter', 'budgetProjectFilter', 'transactionProjectFilter', 'filterProject', 'budgetVarianceProject', 'bondProjectFilter']
            .forEach(function (id) {
                var control = document.getElementById(id);
                if (!control || control.closest('.modal-overlay, dialog')) return;
                var field = control.closest('label, .filter-control, .project-filter-field, .pfims-filter-field') || control;
                field.hidden = true;
                field.setAttribute('aria-hidden', 'true');
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

    function installTableScrollFades() {
        var selector = '.table-wrapper, .table-wrap, .table-container, .table-responsive, .budget-table-wrapper, .items-table-wrapper, .forecast-table-wrapper, .report-table-wrapper, .analytics-table-wrapper';
        function update(wrapper) {
            var max = Math.max(0, wrapper.scrollWidth - wrapper.clientWidth);
            var scrollable = max > 1;
            wrapper.classList.toggle('is-at-start', !scrollable || wrapper.scrollLeft <= 1);
            wrapper.classList.toggle('is-at-end', !scrollable || wrapper.scrollLeft >= max - 1);
        }
        document.querySelectorAll(selector).forEach(function (wrapper) {
            update(wrapper);
            if (wrapper.dataset.pfimsScrollFade === 'ready') return;
            wrapper.dataset.pfimsScrollFade = 'ready';
            wrapper.addEventListener('scroll', function () { update(wrapper); }, { passive: true });
            if (typeof ResizeObserver === 'function') new ResizeObserver(function () { update(wrapper); }).observe(wrapper);
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
        if (document.body?.dataset.pfimsAutoRefresh === 'ready') return;
        document.body.dataset.pfimsAutoRefresh = 'ready';
        // Data views refresh from their own successful create/update handlers or
        // an explicit user action. Never poll every page: that can invoke
        // role-incompatible endpoints and interrupt an active form.
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
        document.querySelectorAll('.nav-parent-toggle').forEach(function (toggle) {
            if (toggle.dataset.pfimsDropdown === 'ready') return;
            toggle.dataset.pfimsDropdown = 'ready';
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                var parent = toggle.closest('.nav-parent');
                var open = parent.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', String(open));
                var module = parent.dataset.pfimsModule;
                if (module) {
                    try { window.localStorage.setItem('pfims-nav-' + module, open ? 'open' : 'closed'); } catch (error) {}
                }
            });
        });
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

    function installModuleNavigation() {
        var portal = document.body?.dataset.portal || 'admin';
        document.querySelectorAll('.sidebar li > a').forEach(function (link) {
            var label = (link.textContent || '').trim().toLowerCase();
            var module = Object.keys(MODULE_NAVIGATION).find(function (name) {
                return label === name || label.startsWith(name + ' ');
            });
            if (!module || link.classList.contains('nav-parent-toggle')) return;
            var item = link.closest('li');
            var rawHref = link.getAttribute('href') || '';
            if (!item || !rawHref || rawHref === '#') return;
            var base = rawHref.split('?')[0];
            item.dataset.pfimsModule = module;
            item.classList.add('nav-parent');
            link.dataset.moduleHref = base;
            link.href = '#';
            link.classList.add('nav-parent-toggle');
            link.setAttribute('aria-expanded', 'false');
            var chevron = document.createElement('span');
            chevron.className = 'nav-chevron';
            chevron.setAttribute('aria-hidden', 'true');
            chevron.textContent = '▾';
            link.appendChild(chevron);
            var menu = document.createElement('div');
            menu.className = 'nav-dropdown';
            MODULE_NAVIGATION[module]
                .filter(function (entry) {
                    return !(entry[0] === 'Project Cost Prediction' && portal !== 'admin');
                })
                .forEach(function (entry) {
                var child = document.createElement('a');
                var childHref = entry[1].charAt(0) === '/' ? entry[1] : base + entry[1];
                var childUrl = new URL(childHref, window.location.origin);
                var rolePaths = ROLE_PATHS[portal] || ROLE_PATHS.admin;
                if (Object.prototype.hasOwnProperty.call(rolePaths, childUrl.pathname)) {
                    var rolePath = rolePaths[childUrl.pathname];
                    if (rolePath === null) return;
                    childUrl.pathname = rolePath;
                }
                child.href = childUrl.pathname + childUrl.search + childUrl.hash;
                child.textContent = entry[0];
                menu.appendChild(child);
            });
            item.appendChild(menu);
        });

        document.querySelectorAll('.sidebar .nav-parent').forEach(function (item) {
            if (item.dataset.pfimsModuleState === 'ready') return;
            var toggle = item.querySelector(':scope > .nav-parent-toggle');
            var module = item.dataset.pfimsModule || (toggle && (toggle.textContent || '').trim().split(/\s+/)[0].toLowerCase());
            if (!toggle || !module) return;
            item.dataset.pfimsModule = module;

            var currentUrl = new URL(window.location.href);
            var activeChild = Array.from(item.querySelectorAll(':scope > .nav-dropdown > a')).find(function (child) {
                var childUrl = new URL(child.href, window.location.origin);
                return childUrl.pathname.replace(/\/$/, '') === currentUrl.pathname.replace(/\/$/, '')
                    && childUrl.search === currentUrl.search;
            });
            item.classList.toggle('has-active-child', !!activeChild);
            item.querySelectorAll(':scope > .nav-dropdown > a').forEach(function (child) {
                child.classList.toggle('active', child === activeChild);
                child.addEventListener('click', function () {
                    document.querySelectorAll('.sidebar .nav-parent').forEach(function (parent) {
                        var parentModule = parent.dataset.pfimsModule;
                        if (!parentModule) return;
                        var selected = parent === item;
                        parent.classList.toggle('is-open', selected);
                        parent.querySelector(':scope > .nav-parent-toggle')?.setAttribute('aria-expanded', String(selected));
                        try { window.localStorage.setItem('pfims-nav-' + parentModule, selected ? 'open' : 'closed'); } catch (error) {}
                    });
                });
            });

            var savedState = null;
            try { savedState = window.localStorage.getItem('pfims-nav-' + module); } catch (error) {}
            if (activeChild) {
                document.querySelectorAll('.sidebar .nav-parent').forEach(function (parent) {
                    if (parent === item) return;
                    parent.classList.remove('is-open');
                    parent.querySelector(':scope > .nav-parent-toggle')?.setAttribute('aria-expanded', 'false');
                    var parentModule = parent.dataset.pfimsModule;
                    if (parentModule) {
                        try { window.localStorage.setItem('pfims-nav-' + parentModule, 'closed'); } catch (error) {}
                    }
                });
                try { window.localStorage.setItem('pfims-nav-' + module, 'open'); } catch (error) {}
            }
            var shouldOpen = !!activeChild || savedState === 'open';
            item.classList.toggle('is-open', shouldOpen);
            toggle.setAttribute('aria-expanded', String(shouldOpen));
            item.dataset.pfimsModuleState = 'ready';
        });
    }

    function actionIcon(type) {
        if (type === 'add') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
        }
        if (type === 'import') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v11m0 0 4-4m-4 4-4-4M5 17v3h14v-3"/></svg>';
        }
        if (type === 'export') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M12 17V11m0 0-3 3m3-3 3 3"/></svg>';
        }
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
    }

    function installExpandableActionButtons() {
        document.querySelectorAll('button, a').forEach(function (control) {
            if (control.dataset.pfimsActionLabel === 'ready'
                || control.closest('.sidebar, .pagination-links, .pfims-select-options, .pfims-column-chooser-menu')) return;

            var visibleText = (control.textContent || '').replace(/\s+/g, ' ').trim();
            var accessibleText = (control.getAttribute('aria-label') || control.title || visibleText).replace(/\s+/g, ' ').trim();
            var candidate = (visibleText + ' ' + accessibleText).trim();
            var type = /(?:^|\s)\+?\s*(add|new)\b/i.test(candidate) ? 'add'
                : /\bimport\b/i.test(candidate) ? 'import'
                : /\b(export|configure export)\b/i.test(candidate) ? 'export'
                : (/\bclear(?:\s+filters?)?\b/i.test(candidate) || /^\s*(x|✕|×)\s*$/i.test(visibleText)) ? 'clear'
                : '';
            if (!type) return;
            if (type === 'clear' && control.closest('.modal-overlay, dialog, [role="dialog"]')) return;

            var label = visibleText || accessibleText;
            label = label.replace(/^[+✕×]\s*/, '').trim();
            if (!label || /^x$/i.test(label)) label = type === 'clear' ? 'Clear filters' : type;

            control.dataset.pfimsActionLabel = 'ready';
            control.dataset.iconLabel = type;
            control.classList.add('pfims-expand-action');
            control.setAttribute('aria-label', label);
            control.setAttribute('title', label);

            var icon = document.createElement('span');
            icon.className = 'button-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = actionIcon(type);
            var text = document.createElement('span');
            text.className = 'button-label';
            text.textContent = label;
            control.replaceChildren(icon, text);
        });
    }

    function normalizePageSize(select) {
        if (select.dataset.pfimsPageSize === 'ready') return;
        select.dataset.pfimsPageSize = 'ready';
        var selected = '5';
        select.replaceChildren.apply(select, ['5', '25', '50', '100'].map(function (value) {
            return new Option(value, value, value === selected, value === selected);
        }));
        select.value = selected;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function normalizePaginationTotals() {
        document.querySelectorAll('.pagination-wrapper .rows-info > span').forEach(function (total) {
            var text = (total.textContent || '').trim();
            var match = text.match(/\bof\s+(\d[\d,]*)\b/i) || text.match(/\btotal:\s*(\d[\d,]*)\b/i);
            var normalized = match ? 'Total: ' + match[1] : '';
            if (normalized && text !== normalized) total.textContent = normalized;
        });
    }

    function paginationItems(page, totalPages) {
        if (totalPages <= 7) return Array.from({ length: totalPages }, function (_, index) { return index + 1; });
        var pages = new Set([1, totalPages, page - 1, page, page + 1]);
        var ordered = Array.from(pages).filter(function (value) { return value >= 1 && value <= totalPages; }).sort(function (a, b) { return a - b; });
        var items = [];
        ordered.forEach(function (value, index) {
            if (index && value - ordered[index - 1] > 1) items.push('ellipsis');
            items.push(value);
        });
        return items;
    }

    function installTablePagination(table) {
        if (table.dataset.pfimsPagination === 'ready' || table.closest('.modal-overlay, dialog')) return;
        var host = table.closest('.table-wrapper, .table-wrap, .report-table-wrapper, .items-table-wrapper, .budget-table-wrapper') || table;
        var existing = host.nextElementSibling;
        if (existing?.matches('.pagination-wrapper')) {
            table.dataset.pfimsPagination = 'ready';
            existing.querySelectorAll('select').forEach(normalizePageSize);
            return;
        }
        var body = table.tBodies?.[0];
        if (!body) return;
        table.dataset.pfimsPagination = 'ready';
        var page = 1;
        var perPage = 5;
        var wrapper = document.createElement('div');
        wrapper.className = 'pagination-wrapper pfims-generated-pagination';
        wrapper.innerHTML = '<div class="rows-info">Rows per page <select aria-label="Rows per page"><option value="5">5</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select> <span class="pagination-total">Total: 0</span></div><div class="pagination-links" aria-label="Table pagination"></div>';
        host.insertAdjacentElement('afterend', wrapper);
        var select = wrapper.querySelector('select');
        var total = wrapper.querySelector('.pagination-total');
        var links = wrapper.querySelector('.pagination-links');

        function dataRows() {
            return Array.from(body.rows).filter(function (row) {
                return !(row.cells.length === 1 && /loading|no\s+records|no\s+data|empty|fetching|please wait/i.test(row.textContent || ''));
            });
        }
        function render() {
            var rows = dataRows();
            var totalPages = Math.max(1, Math.ceil(rows.length / perPage));
            page = Math.min(page, totalPages);
            Array.from(body.rows).forEach(function (row) { row.hidden = true; });
            rows.forEach(function (row, index) { row.hidden = index < (page - 1) * perPage || index >= page * perPage; });
            total.textContent = 'Total: ' + rows.length;
            links.replaceChildren();
            function button(label, target, active, disabled) {
                var control = document.createElement('button');
                control.type = 'button';
                control.textContent = label;
                control.dataset.page = String(target);
                control.disabled = disabled;
                if (active) control.className = 'active';
                links.appendChild(control);
            }
            button('Previous', page - 1, false, page === 1);
            paginationItems(page, totalPages).forEach(function (item) {
                if (item === 'ellipsis') {
                    var dots = document.createElement('span');
                    dots.className = 'ellipsis';
                    dots.textContent = '…';
                    links.appendChild(dots);
                } else button(String(item), item, item === page, false);
            });
            button('Next', page + 1, false, page === totalPages);
        }
        select.addEventListener('change', function () { perPage = Number(select.value) || 5; page = 1; render(); });
        links.addEventListener('click', function (event) {
            var control = event.target.closest('[data-page]');
            if (!control || control.disabled) return;
            page = Number(control.dataset.page);
            render();
        });
        new MutationObserver(function () { page = 1; render(); }).observe(body, { childList: true });
        render();
    }

    function installSearchSuggestions(input) {
        if (input.dataset.pfimsSuggestions === 'ready') return;
        input.dataset.pfimsSuggestions = 'ready';
        input.setAttribute('autocomplete', 'off');

        var menu = document.createElement('div');
        menu.className = 'pfims-search-suggestions';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;
        var host = input.closest('.pfims-filter-field, label, .filter-control, .search-box, .search-container') || input.parentElement;
        if (!host) return;
        host.classList.add('pfims-search-host');
        host.appendChild(menu);

        function close() { menu.hidden = true; menu.replaceChildren(); }
        function candidates(query) {
            var scope = input.closest('.report-section, [data-analytics-content], .dashboard-tab-panel, .inventory-tab-content, main, .main-content') || document.body;
            var values = [];
            scope.querySelectorAll('tbody tr:not([hidden]) td').forEach(function (cell) {
                if (cell.offsetParent === null) return;
                var value = (cell.textContent || '').replace(/\s+/g, ' ').trim();
                if (value.length < 2 || value.length > 100 || !value.toLowerCase().includes(query)) return;
                values.push(value.length > 58 ? value.slice(0, 57) + '…' : value);
            });
            return Array.from(new Set(values)).slice(0, 8);
        }
        input.addEventListener('input', function () {
            var query = input.value.trim().toLowerCase();
            if (query.length < 3) return close();
            var matches = candidates(query);
            if (!matches.length) {
                menu.replaceChildren();
                var empty = document.createElement('div');
                empty.className = 'pfims-search-empty';
                empty.textContent = 'No results found';
                menu.appendChild(empty);
                menu.hidden = false;
                return;
            }
            menu.replaceChildren();
            matches.forEach(function (value) {
                var option = document.createElement('button');
                option.type = 'button';
                option.setAttribute('role', 'option');
                var match = value.replace(new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark>$1</mark>');
                option.innerHTML = match;
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
            menu.hidden = false;
        });
        input.addEventListener('blur', function () { window.setTimeout(close, 120); });
        input.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });
    }

    function installTableTooltips() {
        if (document.body.dataset.pfimsTableTooltips === 'ready') return;
        document.body.dataset.pfimsTableTooltips = 'ready';
        var tooltip = document.createElement('div');
        tooltip.id = 'pfimsTableTooltip';
        tooltip.className = 'pfims-table-tooltip';
        tooltip.setAttribute('role', 'tooltip');
        tooltip.hidden = true;
        document.body.appendChild(tooltip);
        var activeCell = null;

        function eligible(target) {
            return target.closest && target.closest('table th, table td');
        }
        function position(x, y) {
            var gap = 12;
            var rect = tooltip.getBoundingClientRect();
            var left = Math.min(Math.max(gap, x + gap), window.innerWidth - rect.width - gap);
            var top = y + gap;
            if (top + rect.height > window.innerHeight - gap) top = Math.max(gap, y - rect.height - gap);
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
        }
        function show(cell, x, y) {
            var value = (cell.textContent || '').replace(/\s+/g, ' ').trim();
            if (!value) return;
            activeCell = cell;
            tooltip.textContent = value;
            tooltip.hidden = false;
            cell.setAttribute('aria-describedby', tooltip.id);
            if (!cell.hasAttribute('tabindex') && !cell.querySelector('button, a, input, select, textarea')) cell.tabIndex = 0;
            position(x, y);
        }
        function hide(cell) {
            if (cell && activeCell !== cell) return;
            if (activeCell) activeCell.removeAttribute('aria-describedby');
            activeCell = null;
            tooltip.hidden = true;
        }
        document.addEventListener('pointerover', function (event) {
            var cell = eligible(event.target);
            if (cell) show(cell, event.clientX, event.clientY);
        });
        document.addEventListener('pointermove', function (event) {
            if (activeCell && !tooltip.hidden) position(event.clientX, event.clientY);
        });
        document.addEventListener('pointerout', function (event) {
            var cell = eligible(event.target);
            if (cell && !cell.contains(event.relatedTarget)) hide(cell);
        });
        document.addEventListener('focusin', function (event) {
            var cell = eligible(event.target);
            if (!cell) return;
            var rect = cell.getBoundingClientRect();
            show(cell, rect.left + rect.width / 2, rect.bottom);
        });
        document.addEventListener('focusout', function (event) {
            var cell = eligible(event.target);
            if (cell) hide(cell);
        });
    }

    function installPageLoader() {
        if (window.PFIMS_PAGE_LOADER || document.body.classList.contains('landing-page')) return;
        var host = document.querySelector('main.main-content, .main-content, main');
        if (!host) return;
        host.classList.add('pfims-loader-host');
        var loader = document.createElement('div');
        loader.id = 'pfimsPageLoader';
        loader.className = 'pfims-page-loader';
        loader.setAttribute('role', 'status');
        loader.setAttribute('aria-live', 'polite');
        loader.innerHTML = '<span class="pfims-page-spinner" aria-hidden="true"></span><span class="sr-only">Loading</span>';
        host.appendChild(loader);
        var pending = 0;
        var visible = false;
        loader.hidden = true;

        function show() {
            visible = true;
            loader.hidden = false;
            host.classList.add('pfims-is-loading');
        }
        var settleTimer;
        function hide() {
            if (pending > 0) return;
            window.clearTimeout(settleTimer);
            settleTimer = window.setTimeout(function () {
                if (pending > 0) return;
                window.requestAnimationFrame(function () { window.requestAnimationFrame(function () {
                    visible = false;
                    loader.hidden = true;
                    host.classList.remove('pfims-is-loading');
                }); });
            }, 80);
        }
        window.PFIMS_PAGE_LOADER = { show: show, hide: hide };

        if (!window.fetch.__pfimsTracked) {
            var originalFetch = window.fetch;
            var trackedFetch = function () {
                if (visible) pending += 1;
                return originalFetch.apply(this, arguments).finally(function () {
                    if (visible) {
                        pending = Math.max(0, pending - 1);
                        if (pending === 0 && document.readyState === 'complete') hide();
                    }
                });
            };
            trackedFetch.__pfimsTracked = true;
            window.fetch = trackedFetch;
        }
        show();
        if (document.readyState === 'complete') hide();
        else window.addEventListener('load', hide, { once: true });
        document.addEventListener('click', function (event) {
            var control = event.target.closest('a[href], [role="tab"], .tab, .nav-link, .nav-parent-toggle');
            if (!control || event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (control.matches('.nav-parent-toggle') || control.closest('.nav-parent-toggle')) return;
            if (control.matches('a[href]')) {
                var href = control.getAttribute('href') || '';
                if (!href || href.charAt(0) === '#' || control.target === '_blank') return;
                var destination = new URL(control.href, window.location.href);
                if (destination.href === window.location.href) return;
                show();
                return;
            }
            show();
            window.requestAnimationFrame(function () { window.requestAnimationFrame(hide); });
        }, true);
    }

    function installAccurateChartHover() {
        if (!window.Chart || window.Chart.__pfimsAccuratePointer) return;
        window.Chart.__pfimsAccuratePointer = true;
        window.Chart.register({
            id: 'pfimsAccuratePointer',
            beforeEvent: function (chart, args) {
                var event = args.event;
                var nativeEvent = event && event.native;
                var point = nativeEvent && nativeEvent.touches && nativeEvent.touches[0]
                    ? nativeEvent.touches[0] : nativeEvent;
                if (!point || typeof point.clientX !== 'number' || typeof point.clientY !== 'number') return;
                var rect = chart.canvas.getBoundingClientRect();
                if (!rect.width || !rect.height) return;
                event.x = (point.clientX - rect.left) * (chart.width / rect.width);
                event.y = (point.clientY - rect.top) * (chart.height / rect.height);
            }
        });
    }

    function openCapturedRowAction(row, handler, editMode) {
        // Expose the action intent for modules whose row handler opens a
        // detail modal and must enter its edit state directly.
        window.PFIMS_ROW_EDIT_MODE = !!editMode;
        handler.call(row, { currentTarget: row, target: row, preventDefault: function () {}, stopPropagation: function () {} });
        if (!editMode) return;
        window.setTimeout(function () {
            var visibleModals = Array.from(document.querySelectorAll('.modal, .modal-overlay, [role="dialog"]'))
                .filter(function (modal) { return !modal.hidden && getComputedStyle(modal).display !== 'none'; });
            var visibleModal = visibleModals
                .filter(function (modal) { return modal.matches('.modal.active, .modal-overlay.active'); })
                .pop() || visibleModals.pop();
            if (!visibleModal) return;
            var editButton = Array.from(visibleModal.querySelectorAll('button')).find(function (button) {
                return /^edit(?:\s|$)/i.test((button.textContent || '').trim()) && !button.hidden && getComputedStyle(button).display !== 'none';
            });
            if (editButton) editButton.click();
            window.PFIMS_ROW_EDIT_MODE = false;
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

    function visibleDetailsModal() {
        var candidates = Array.from(document.querySelectorAll('.modal, .modal-overlay, [role="dialog"]'))
            .filter(function (modal) {
                return !modal.hidden && getComputedStyle(modal).display !== 'none'
                    && !/delete|confirm/i.test(modal.id || '');
            });
        return candidates.filter(function (modal) {
            return modal.matches('.active, :modal, [open]') || modal.getAttribute('aria-hidden') === 'false';
        }).pop() || candidates.pop() || null;
    }

    function triggerDeleteThroughEdit(editAction) {
        editAction();
        window.setTimeout(function () {
            var modal = visibleDetailsModal();
            if (!modal) return;
            var deleteButton = Array.from(modal.querySelectorAll('button')).find(function (button) {
                return /^delete(?:\s|$)/i.test((button.textContent || '').trim())
                    && !button.hidden && getComputedStyle(button).display !== 'none';
            });
            if (deleteButton) deleteButton.click();
        }, 0);
    }

    function configureDetailsFooter(editAction, deleteAction) {
        var modal = visibleDetailsModal();
        if (!modal) return;
        var footer = modal.querySelector('.modal-footer, .dialog-actions');
        if (!footer) return;
        footer.classList.add('pfims-detail-footer');

        var buttons = Array.from(footer.querySelectorAll('button'));
        var cancel = buttons.find(function (button) { return /^(close|cancel)$/i.test((button.textContent || '').trim()); });
        var edit = buttons.find(function (button) { return /^edit(?:\s|$)/i.test((button.textContent || '').trim()); });
        var remove = buttons.find(function (button) { return /^delete(?:\s|$)/i.test((button.textContent || '').trim()); });

        if (cancel) {
            cancel.textContent = 'Cancel';
            cancel.classList.add('pfims-detail-cancel');
        }
        if (!edit && editAction) {
            edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn-edit-project';
            edit.textContent = 'Edit';
            edit.addEventListener('click', editAction);
            footer.appendChild(edit);
        }
        if (!remove && (deleteAction || editAction)) {
            remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-delete';
            remove.textContent = 'Delete';
            remove.addEventListener('click', deleteAction || function () { triggerDeleteThroughEdit(editAction); });
            footer.appendChild(remove);
        }
        if (edit) {
            edit.hidden = false;
            edit.style.display = 'inline-flex';
        }
        if (remove) {
            remove.hidden = false;
            remove.style.display = 'inline-flex';
        }
        if (cancel) footer.insertBefore(cancel, footer.firstChild);
        if (edit) footer.appendChild(edit);
        if (remove) footer.appendChild(remove);
    }

    function openGeneratedDetailsModal(table, row, actionIndex, editAction, deleteAction) {
        var modal = document.getElementById('pfimsGeneratedDetailsModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'pfimsGeneratedDetailsModal';
            modal.className = 'modal-overlay pfims-generated-details-modal';
            modal.innerHTML = '<div class="modal-container"><div class="modal-header"><h2>View Details</h2>'
                + '<button type="button" class="modal-close" aria-label="Close">×</button></div>'
                + '<div class="modal-body view-details-grid"></div><div class="modal-footer">'
                + '<button type="button" class="btn-cancel">Cancel</button></div></div>';
            document.body.appendChild(modal);
            modal.querySelector('.modal-close').addEventListener('click', function () { modal.classList.remove('active'); });
            modal.querySelector('.btn-cancel').addEventListener('click', function () { modal.classList.remove('active'); });
            modal.addEventListener('click', function (event) { if (event.target === modal) modal.classList.remove('active'); });
        }
        var sourceHeader = table.tHead && table.tHead.rows[0]
            ? table.tHead.rows[0] : table.querySelector('tr:has(th)');
        var headers = sourceHeader ? Array.from(sourceHeader.cells) : [];
        var body = modal.querySelector('.modal-body');
        body.replaceChildren();
        Array.from(row.cells).forEach(function (cell, index) {
            if (index === actionIndex) return;
            var item = document.createElement('div');
            item.className = 'view-item';
            var label = document.createElement('label');
            label.textContent = headers[index] ? (headers[index].textContent || '').trim() : 'Value';
            var value = document.createElement('span');
            value.className = 'view-value';
            value.textContent = (cell.textContent || '').replace(/\s+/g, ' ').trim() || '—';
            item.append(label, value);
            body.appendChild(item);
        });
        modal.classList.add('active');
        configureDetailsFooter(editAction, deleteAction);
    }

    function installStandardActions(table) {
        if (table.classList.contains('analytics-table') || table.closest('.predictive-analytics-root')) return;
        var headerRow = table.tHead && table.tHead.rows[0]
            ? table.tHead.rows[0] : table.querySelector('tr:has(th)');
        if (!headerRow) return;
        var actionIndex = Array.from(headerRow.cells).findIndex(function (cell) {
            return /^actions?$/i.test((cell.textContent || '').trim());
        });
        if (actionIndex < 0) return;
        headerRow.cells[actionIndex].classList.add('pfims-actions-heading');
        Array.from(table.tBodies || []).forEach(function (body) {
            Array.from(body.rows).forEach(function (row) {
                if (row.dataset.pfimsStandardAction === 'ready' || !row.cells[actionIndex]) return;
                var cell = row.cells[actionIndex];
                var controls = Array.from(cell.querySelectorAll('button, a'));
                if (!controls.length) return;
                var view = controls.find(function (control) { return /^view(?:\s|$)/i.test(control.getAttribute('aria-label') || control.title || control.textContent || ''); });
                var edit = controls.find(function (control) { return /^edit(?:\s|$)/i.test(control.getAttribute('aria-label') || control.title || control.textContent || ''); });
                var remove = controls.find(function (control) { return /^delete(?:\s|$)/i.test(control.getAttribute('aria-label') || control.title || control.textContent || ''); });
                var editAction = edit ? function () { edit.click(); } : null;
                var deleteAction = remove ? function () { remove.click(); } : null;
                if (!view) {
                    view = makeRowAction('view', 'View details', function () {
                        openGeneratedDetailsModal(table, row, actionIndex, editAction, deleteAction);
                    });
                } else {
                    view.addEventListener('click', function () {
                        window.setTimeout(function () { configureDetailsFooter(editAction, deleteAction); }, 0);
                    });
                }
                cell.className = 'action-cell';
                cell.replaceChildren(view);
                row.dataset.pfimsStandardAction = 'ready';
            });
        });
    }

    function installDetailsFooterBridge() {
        if (document.body.dataset.pfimsDetailsFooterBridge === 'ready') return;
        document.body.dataset.pfimsDetailsFooterBridge = 'ready';
        document.addEventListener('click', function (event) {
            var control = event.target.closest('button, a');
            if (!control || !/^view(?:\s|$)/i.test(control.getAttribute('aria-label') || control.title || control.textContent || '')) return;
            window.setTimeout(function () { configureDetailsFooter(null, null); }, 0);
            window.setTimeout(function () { configureDetailsFooter(null, null); }, 80);
        });
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

                var headerRow = table.tHead && table.tHead.rows[0]
                    ? table.tHead.rows[0] : table.querySelector('tr:has(th)');
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
                    makeRowAction('view', 'View details', function () {
                        openCapturedRowAction(row, handler, false);
                        window.setTimeout(function () { configureDetailsFooter(null, null); }, 0);
                    })
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
        installHeaderClock();
        installRoleNavigation();
        installAdminAuditNavigation();
        installSharedFilters();
        installDefaultFilters();
        installAuditDeepLink();
        removeProjectFilterControls();
        installModuleNavigation();
        installSingleClickNavigation();
        installExpandableActionButtons();
        document.querySelectorAll('.pagination-wrapper select').forEach(normalizePageSize);
        normalizePaginationTotals();
        document.querySelectorAll('input[type="search"]').forEach(installSearchSuggestions);
        installTableTooltips();
        installAccurateChartHover();
        installDetailsFooterBridge();
        installQuietScrollbars();
        installTableScrollFades();
        document.querySelectorAll('table').forEach(installActionsForClickableRows);
        document.querySelectorAll('table').forEach(installStandardActions);
        document.querySelectorAll('table').forEach(installTablePagination);
        document.querySelectorAll('table').forEach(installColumnChooser);
        new MutationObserver(function () {
            installSharedFilters();
            installAdminAuditNavigation();
            removeProjectFilterControls();
            installRoleNavigation();
            installModuleNavigation();
            installSingleClickNavigation();
            installExpandableActionButtons();
            document.querySelectorAll('.pagination-wrapper select').forEach(normalizePageSize);
            normalizePaginationTotals();
            document.querySelectorAll('input[type="search"]').forEach(installSearchSuggestions);
            installQuietScrollbars();
            installTableScrollFades();
            document.querySelectorAll('table').forEach(installActionsForClickableRows);
            document.querySelectorAll('table').forEach(installStandardActions);
            document.querySelectorAll('table').forEach(installTablePagination);
            document.querySelectorAll('table').forEach(installColumnChooser);
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        installPageLoader();
        document.addEventListener('DOMContentLoaded', initializeSystemUi, { once: true });
    } else {
        installPageLoader();
        initializeSystemUi();
    }
})();
