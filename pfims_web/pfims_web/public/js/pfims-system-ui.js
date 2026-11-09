// Set up the pfims system ui page actions.
(function () {
    'use strict';

    // Return early if the pfims system ui loaded is set.
    if (window.PFIMS_SYSTEM_UI_LOADED) return;
    window.PFIMS_SYSTEM_UI_LOADED = true;

    var alertTimer = null;
    // Check account session.
    function checkAccountSession() {
        // Return early if the hidden is set.
        if (document.hidden) return;
        // Use the result when this request finishes.
        fetch('/api/session/status', {headers:{'Accept':'application/json'},cache:'no-store'}).then(function(response) {
            // Return early if the response status equals 401.
            if (response.status === 401) {
                // Use the result when this request finishes.
                return response.clone().json().then(function (payload) { window.pfimsRememberSessionExpiry?.(payload); })
                    .catch(function () {}).finally(function () { window.location.replace('/'); });
            }
        }).catch(function() {});
    }
    // Run this step if the pathname does not equal /.
    if (window.location.pathname !== '/') {
        window.setInterval(checkAccountSession, 15000);
        // Handle the visibilitychange event.
        document.addEventListener('visibilitychange', function() { if (!document.hidden) checkAccountSession(); });
    }


    // Get the install header clock.
    function installHeaderClock() {
        var headerActions = document.querySelector('.top-header .right');
        // Return early if the header actions is missing or false or the pfims clock equals ready.
        if (!headerActions || headerActions.dataset.pfimsClock === 'ready') return;
        headerActions.dataset.pfimsClock = 'ready';
        var clock = headerActions.querySelector('.header-clock');
        // Run this step if the clock is missing or false.
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
        // Get the update.
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
        // Run this step if the type equals success and the message equals string and the test check matches and the test is missing or false.
        if (type === 'success' && typeof message === 'string' && /\bdeleted\b|saved defaults removed/i.test(message) && !/30 days/.test(message)) {
            message = 'Moved to Trash Bin. Restore from Settings within 30 days before permanent deletion.';
        }
        var tone = ['success', 'error', 'info', 'warning'].includes(type) ? type : 'info';
        var alert = document.getElementById('pfimsGlobalAlert');
        // Run this step if the alert is missing or false.
        if (!alert) {
            alert = document.createElement('div');
            alert.id = 'pfimsGlobalAlert';
            alert.setAttribute('role', 'alert');
            alert.setAttribute('aria-live', 'polite');
            alert.innerHTML = '<span class="pfims-alert-icon" aria-hidden="true"></span><span class="pfims-alert-message"></span><button type="button" class="pfims-alert-close" data-pfims-no-expand aria-label="Close alert">&times;</button>';
            // Handle the click event.
            alert.querySelector('.pfims-alert-close').addEventListener('click', function () { alert.removeAttribute('data-visible'); });
            document.body.appendChild(alert);
        }
        alert.className = 'pfims-alert-popup ' + tone;
        alert.querySelector('.pfims-alert-icon').textContent = tone === 'success' ? '✓' : (tone === 'error' ? '!' : 'i');
        alert.querySelector('.pfims-alert-message').textContent = message || 'Action completed.';
        alert.setAttribute('data-visible', 'true');
        window.clearTimeout(alertTimer);
        // Run this action after the delay.
        alertTimer = window.setTimeout(function () { alert.removeAttribute('data-visible'); }, Number(duration) || 5000);
        return alert;
    };

    // Run this step if the show success equals function.
    if (typeof window.showSuccess === 'function') {
        window.showSuccess = function (message) {
            return window.showPfimsAlert(message || 'Action completed successfully.', 'success');
        };
    }

    // Handle the mousedown event.
    document.addEventListener('mousedown', function (event) {
        var alert = document.getElementById('pfimsGlobalAlert');
        // Run this step if the value equals true and the contains is missing or false.
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

    // Get the install role navigation.
    function installRoleNavigation() {
        var portal = document.body?.dataset.portal || 'admin';
        var paths = ROLE_PATHS[portal] || ROLE_PATHS.admin;
        // Process each link.
        document.querySelectorAll('a[href]').forEach(function (link) {
            var rawHref = link.getAttribute('href') || '';
            // Return early if the raw href is missing or false or the starts with check matches or the test check matches.
            if (!rawHref || rawHref.startsWith('#') || /^javascript:/i.test(rawHref)) return;
            var url;
            // Try this action and handle any errors below.
            try { url = new URL(link.href, window.location.origin); } catch (_) { return; }
            // Return early if the call is missing or false.
            if (!Object.prototype.hasOwnProperty.call(paths, url.pathname)) return;
            var replacement = paths[url.pathname];
            // Run this step if the replacement equals null.
            if (replacement === null) {
                var navigationItem = link.closest('.sidebar li');
                // Run this step if the navigation item is set.
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
            ['Expenses', ''], ['Budgets & Contracts', '?section=budgets'],
            ['AR / AP', '?section=ar-ap'], ['Cash Asset', '?section=cash-position'],
            ['Equipment', '?section=equipment'], ['Bonds', '?section=bonds'],
            ['Budget-Spending Comparison', '/ml-dashboard-test?section=budget-comparison']
        ],
        inventory: [
            ['Items', ''], ['Suppliers', '/suppliers'], ['Transactions', '?section=transactions'],
            ['Material Projection', '/ml-dashboard-test?section=material-projection']
        ]
    };

    // Filter label.
    function filterLabel(control) {
        var explicitLabels = {
            budgetProjectFilter: 'Project',
            budgetSearch: 'Search',
            budgetStatusFilter: 'Status',
            expenseCategoryFilter: 'Category',
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
        // Return early if the control id is set.
        if (explicitLabels[control.id]) return explicitLabels[control.id];
        // Return early if the control type equals date.
        if (control.type === 'date') return /end|to/i.test(control.id) ? 'To' : 'From';
        // Return early if the control type equals search.
        if (control.type === 'search') return 'Search';
        var firstOption = control.options && control.options[0];
        return firstOption ? firstOption.textContent.replace(/^All\s+|^Sort by\s+/i, '').trim() : 'Filter';
    }

    // Get the current default filter module.
    function currentDefaultFilterModule() {
        // Return early if the value check matches.
        if (document.body?.dataset.defaultFilterModule) return document.body.dataset.defaultFilterModule;
        var path = window.location.pathname.replace(/^\/(a|o)(?=dashboard|finance|projects|inventory|suppliers|reports)/, '/');
        var section = new URLSearchParams(window.location.search).get('section') || '';
        // Return early if the path equals /dashboard.
        if (path === '/dashboard') return 'dashboard';
        // Return early if the path equals /projects.
        if (path === '/projects') return 'projects';
        // Return early if the path equals /suppliers.
        if (path === '/suppliers') return 'suppliers';
        // Return early if the path equals /reports.
        if (path === '/reports') return 'reports';
        // Return early if the path equals /audit-logs.
        if (path === '/audit-logs') return 'audit-logs';
        // Run this step if the path equals /finance.
        if (path === '/finance') {
            // Return early if the test check matches.
            if (/budget/i.test(section)) return 'finance.budgets';
            // Return early if the test check matches.
            if (/bond/i.test(section)) return 'finance.bonds';
            return 'finance.expenses';
        }
        // Return early if the path equals /inventory.
        if (path === '/inventory') return /transaction/i.test(section) ? 'inventory.transactions' : 'inventory.items';
        // Return early if the path equals /ml-dashboard-test.
        if (path === '/ml-dashboard-test') return /budget/i.test(section) ? 'analytics.budget' : 'analytics.material';
        return null;
    }

    // Get the install default filters.
    function installDefaultFilters() {
        // Return early if the value equals loading or the value equals ready.
        if (document.body?.dataset.pfimsDefaultFilters === 'loading' || document.body?.dataset.pfimsDefaultFilters === 'ready') return;
        var module = currentDefaultFilterModule();
        // Return early if the module is missing or false or the contains check matches.
        if (!module || document.body.classList.contains('settings-page')) return;
        document.body.dataset.pfimsDefaultFilters = 'loading';
        // Use the result when this request finishes.
        fetch('/api/default-filters', { headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(8000) })
            .then(function (response) { return response.ok ? response.json() : {}; })
            .then(function (allDefaults) {
                var defaults = allDefaults[module] || {};
                // Process each key.
                [
                    'search', 'projectSearch', 'budgetSearch', 'itemsSearchInput', 'searchInput',
                    'supplierSearch', 'filterSearch', 'materialForecastSearch', 'budgetVarianceSearch'
                ].forEach(function (key) { delete defaults[key]; });
                // Run this step if the module equals dashboard and the stock status is set.
                if (module === 'dashboard' && defaults.stockStatus) {
                    defaults.stockStatus = {
                        in_stock: 'In stock',
                        low_stock: 'Low stock',
                        out_of_stock: 'Out of stock'
                    }[defaults.stockStatus] || defaults.stockStatus;
                }
                document.body.dataset.pfimsDefaultFilters = 'ready';
                // Return early if the keys length is missing or false.
                if (!Object.keys(defaults).length) return;
                var componentControls = new Map();
                var appliedOptions = new WeakMap();

                // Get the component for.
                function componentFor(control) {
                    var panel = control.closest('.pfims-filter-panel, .filters-bar, .filter-row, .filters-grid, .history-filters, section.filters');
                    return panel?.closest('.pfims-filter-component') || panel;
                }

                // Set control value.
                function setControlValue(control, expected) {
                    // Run this step if the multiple is set.
                    if (control.multiple) {
                        var selected = Array.isArray(expected) ? expected.map(String) : [String(expected)];
                        // Process each option.
                        Array.from(control.options).forEach(function (option) { option.selected = selected.includes(option.value); });
                    } else {
                        control.value = expected == null ? '' : String(expected);
                    }
                }

                // Get the notify filter controls.
                function notifyFilterControls(controls) {
                    // Process each control.
                    controls.forEach(function (control) {
                        control.dispatchEvent(new Event('input', { bubbles: true }));
                        control.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                }

                // Match component.
                function syncComponent(component) {
                    var state = componentControls.get(component);
                    // Return early if the state is missing or false.
                    if (!state) return;
                    // Run the some action.
                    var differs = state.controls.some(function (control) {
                        var expected = defaults[control.id || control.name];
                        // Run this step if the multiple is set.
                        if (control.multiple) expected = Array.isArray(expected) ? expected : [expected];
                        // Build a value for each option.
                        var actual = control.multiple
                            ? Array.from(control.selectedOptions).map(function (option) { return option.value; })
                            : control.value;
                        return JSON.stringify(actual) !== JSON.stringify(control.multiple ? expected.map(String) : String(expected ?? ''));
                    });
                    state.reset.hidden = !differs;
                    state.note.textContent = differs ? 'Filters differ from saved defaults' : 'Using saved defaults';
                    component.classList.toggle('pfims-defaults-modified', differs);
                }

                // Prepare component state.
                function ensureComponentState(component) {
                    // Return early if the has check matches.
                    if (componentControls.has(component)) return componentControls.get(component);
                    var heading = component.querySelector(':scope > .pfims-filter-heading, :scope > .panel-heading')
                        || component.querySelector('.pfims-filter-heading');
                    var note = document.createElement('span');
                    note.className = 'pfims-default-filter-note';
                    note.setAttribute('role', 'status');
                    note.textContent = 'Using saved defaults';
                    var headingText = heading?.querySelector(':scope > div');
                    // Run this step if the heading text is set.
                    if (headingText) headingText.appendChild(note);
                    else if (heading) heading.insertBefore(note, heading.querySelector('.pfims-clear-filters, button, a'));
                    else component.prepend(note);
                    var reset = document.createElement('button');
                    reset.type = 'button';
                    reset.className = 'pfims-reset-defaults';
                    reset.textContent = 'Reset Filters to Default';
                    reset.hidden = true;
                    var clear = component.querySelector('.pfims-clear-filters');
                    // Run this step if the clear is set.
                    if (clear) clear.insertAdjacentElement('afterend', reset);
                    else if (heading) heading.appendChild(reset);
                    else component.appendChild(reset);
                    var state = { controls: [], reset: reset, note: note };
                    componentControls.set(component, state);
                    // Handle the click event.
                    reset.addEventListener('click', function () {
                        // Process each control.
                        state.controls.forEach(function (control) {
                            delete control.dataset.pfimsDefaultUserChanged;
                            setControlValue(control, defaults[control.id || control.name]);
                            // Run this step if the control tag name equals SELECT.
                            if (control.tagName === 'SELECT') appliedOptions.set(control, Array.from(control.options));
                        });
                        notifyFilterControls(state.controls);
                        syncComponent(component);
                    });
                    var clearControl = component.querySelector('.pfims-clear-filters, .btn-clear-search, .btn-clear-filters, #clearFilters');
                    // Run this step if the clear control is set.
                    if (clearControl) clearControl.addEventListener('click', function () {
                        // Process each control.
                        state.controls.forEach(function (control) { control.dataset.pfimsDefaultUserChanged = 'true'; });
                    });
                    return state;
                }

                // Apply available controls.
                function applyAvailableControls() {
                    var appliedAny = false;
                    var changedControls = [];
                    // Process each key.
                    Object.keys(defaults).forEach(function (key) {
                        var control = document.getElementById(key) || document.querySelector('[name="' + CSS.escape(key) + '"]');
                        // Return early if the control is missing or false or the pfims default user changed equals true.
                        if (!control || control.dataset.pfimsDefaultUserChanged === 'true') return;
                        var value = defaults[key];
                        // Run this step if the control tag name equals SELECT and the value does not equal an empty value.
                        if (control.tagName === 'SELECT' && value !== '') {
                            var expectedOptions = Array.isArray(value) ? value.map(String) : [String(value)];
                            // Return early if the every is missing or false.
                            if (!expectedOptions.every(function (expected) {
                                // Run the some action.
                                return Array.from(control.options).some(function (option) { return option.value === expected; });
                            })) return;
                        }
                        var alreadyApplied = control.dataset.pfimsDefaultApplied === 'ready';
                        // Run this step if the already applied is set.
                        if (alreadyApplied) {
                            // Return early if the control tag name does not equal SELECT.
                            if (control.tagName !== 'SELECT') return;
                            var previousOptions = appliedOptions.get(control) || [];
                            var currentOptions = Array.from(control.options);
                            // Return early if the previous options length equals the current options length and the every check matches.
                            if (previousOptions.length === currentOptions.length
                                && previousOptions.every(function (option, index) { return option === currentOptions[index]; })) return;
                        }
                        control.dataset.pfimsDefaultApplied = 'ready';
                        appliedAny = true;
                        setControlValue(control, value);
                        // Run this step if the control tag name equals SELECT.
                        if (control.tagName === 'SELECT') appliedOptions.set(control, Array.from(control.options));
                        changedControls.push(control);
                        // Return early if the already applied is set.
                        if (alreadyApplied) return;
                        var component = componentFor(control);
                        // Return early if the component is missing or false.
                        if (!component) return;
                        component.classList.add('pfims-using-defaults');
                        var state = ensureComponentState(component);
                        state.controls.push(control);
                        // Run this step if the pfims default watch does not equal ready.
                        if (control.dataset.pfimsDefaultWatch !== 'ready') {
                            control.dataset.pfimsDefaultWatch = 'ready';
                            // Process each event name.
                            ['input', 'change'].forEach(function (eventName) {
                                // Run the add event listener action.
                                control.addEventListener(eventName, function (event) {
                                    // Run this step if the is trusted is set.
                                    if (event.isTrusted) control.dataset.pfimsDefaultUserChanged = 'true';
                                    // Run this action after the delay.
                                    window.setTimeout(function () { syncComponent(component); }, 0);
                                });
                            });
                        }
                    });
                    notifyFilterControls(changedControls);
                    // The observer also sees the note/reset elements created above. Only
                    // rewrite component state when a new filter was actually initialized;
                    // otherwise those writes continuously retrigger the observer.
                    if (appliedAny) componentControls.forEach(function (_, component) { syncComponent(component); });
                }
                applyAvailableControls();
                new MutationObserver(applyAvailableControls).observe(document.body, { childList: true, subtree: true });
            })
            .catch(function () { document.body.dataset.pfimsDefaultFilters = 'unavailable'; });
    }

    // Get the install admin audit navigation.
    function installAdminAuditNavigation() {
        // Return early if the value does not equal admin or the query selector check matches.
        if (document.body?.dataset.portal !== 'admin' || document.querySelector('.sidebar a[href$="/audit-logs"]')) return;
        var list = document.querySelector('.sidebar nav ul');
        // Return early if the list is missing or false.
        if (!list) return;
        var item = document.createElement('li');
        // Run this step if the pathname equals /audit-logs.
        if (window.location.pathname === '/audit-logs') item.className = 'active';
        item.innerHTML = '<a href="/audit-logs"><img src="/images/audit-log.svg" alt="" class="nav-link-icon" aria-hidden="true">AUDIT LOGS</a>';
        list.appendChild(item);
    }

    // Get the install audit deep link.
    function installAuditDeepLink() {
        var subject = new URLSearchParams(window.location.search).get('audit_subject');
        // Return early if the subject is missing or false or the pfims audit deep link equals ready.
        if (!subject || document.body.dataset.pfimsAuditDeepLink === 'ready') return;
        document.body.dataset.pfimsAuditDeepLink = 'ready';
        var attempts = 0;
        // Get the try open.
        function tryOpen() {
            attempts += 1;
            var candidates = Array.from(document.querySelectorAll('[data-id], [data-record-id], [data-project-id], [data-item-id], [data-supplier-id]'));
            // Run the find action.
            var target = candidates.find(function (element) {
                // Run the some action.
                return Array.from(element.attributes).some(function (attribute) { return /^data-.*id$/.test(attribute.name) && attribute.value === subject; });
            });
            var row = target?.closest('tr') || target;
            // Run the find action.
            var view = row && Array.from(row.querySelectorAll('button, a')).find(function (control) {
                return /^view/i.test(control.getAttribute('aria-label') || control.title || control.textContent || '');
            });
            // Run this step if the view is set.
            if (view) { view.click(); return; }
            // Run this step if the attempts is below 30.
            if (attempts < 30) window.setTimeout(tryOpen, 150);
        }
        tryOpen();
    }

    // Get the install shared filters.
    function installSharedFilters() {
        // Process each panel.
        document.querySelectorAll('.project-filter-panel, .filters-bar, .filter-row, .filters-grid, .history-filters, section.filters').forEach(function (panel) {
            // Return early if the pfims filters equals ready.
            if (panel.dataset.pfimsFilters === 'ready') return;
            panel.classList.add('pfims-filter-panel');
            var component = panel.closest('.filter-panel') || panel;
            component.classList.add('pfims-filter-component');
            // Run this step if the closest is missing or false.
            if (!panel.closest('.modal, .modal-overlay, dialog')) component.classList.add('pfims-sticky-filter');
            panel.dataset.pfimsFilters = 'ready';

            // Run this step if the closest is missing or false and the closest is missing or false.
            if (!panel.closest('.reports-page') && !panel.closest('.filter-panel')) {
                var subtitle = document.createElement('p');
                subtitle.className = 'pfims-filter-subtitle';
                subtitle.textContent = panel.dataset.filterDescription || 'Filters update the KPIs, live records, and graph together.';
                panel.prepend(subtitle);
            }

            // Process each child.
            Array.from(panel.children).forEach(function (child) {
                // Return early if the matches check matches.
                if (child.matches('input[type="hidden"]')) return;
                // Run this step if the matches check matches.
                if (child.matches('button, a.pfims-clear-filters')) {
                    // Run this step if the test check matches.
                    if (/clear|^x$|^✕$/i.test(((child.textContent || '') + ' ' + (child.className || '')).trim())) {
                        child.classList.add('pfims-clear-filters');
                        child.textContent = 'Clear filters';
                        child.setAttribute('aria-label', 'Clear filters');
                        // Handle the click event.
                        child.addEventListener('click', function () {
                            // Run this action after the delay.
                            window.setTimeout(function () {
                                // Process each select.
                                panel.querySelectorAll('select').forEach(function (select) {
                                    select.dispatchEvent(new Event('change', { bubbles: true }));
                                });
                            }, 0);
                        });
                    }
                    return;
                }
                // Run this step if the matches check matches.
                if (child.matches('label, .project-filter-field, .filter-control')) {
                    child.classList.add('pfims-filter-field');
                    // Run this step if the query selector check matches.
                    if (child.querySelector('input[type="search"]')) child.classList.add('pfims-filter-search');
                    return;
                }
                // Return early if the matches is missing or false.
                if (!child.matches('input, select')) return;
                var field = document.createElement('label');
                field.className = 'pfims-filter-field';
                // Run this step if the child type equals search.
                if (child.type === 'search') field.classList.add('pfims-filter-search');
                // Run this step if the child id is set.
                if (child.id) field.htmlFor = child.id;
                var text = document.createElement('span');
                text.textContent = filterLabel(child);
                panel.insertBefore(field, child);
                field.append(text, child);
            });

            var clear = panel.querySelector(':scope > .pfims-clear-filters');
            // Run this step if the clear is set.
            if (clear) {
                var reportHeading = panel.closest('.filter-panel')?.querySelector(':scope > .panel-heading');
                // Run this step if the report heading is set.
                if (reportHeading) {
                    reportHeading.classList.add('pfims-filter-heading');
                    reportHeading.appendChild(clear);
                } else {
                    var subtitle = panel.querySelector(':scope > .pfims-filter-subtitle');
                    // Run this step if the subtitle is set.
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

    // Presentation adapter: draft controls never run the live filter handlers.
    function installMobileFilterDialogs() {
        // Return early if the matches is missing or false.
        if (!document.body.matches('.finance-page, .projects-page, .inventory-page, .suppliers-page, .reports-page, .settings-page, .dashboard-page, .audit-logs-page')) return;
        var tabs = document.querySelector('.settings-page .config-tabs');
        // Run this step if the tabs is set and the mobile tab scroll does not equal ready.
        if (tabs && tabs.dataset.mobileTabScroll !== 'ready') {
            tabs.dataset.mobileTabScroll = 'ready';
            // Handle the click event.
            tabs.addEventListener('click', function (event) {
                var tab = event.target.closest('.config-tab');
                // Return early if the tab is missing or false or the matches is missing or false.
                if (!tab || !window.matchMedia('(max-width: 768px)').matches) return;
                var bounds = tabs.getBoundingClientRect();
                var item = tab.getBoundingClientRect();
                // Run this step if the left is below the value.
                if (item.left < bounds.left + 6) tabs.scrollLeft += item.left - bounds.left - 6;
                else if (item.right > bounds.right - 6) tabs.scrollLeft += item.right - bounds.right + 6;
            });
        }
        // Process each panel.
        document.querySelectorAll('main .pfims-filter-panel, main .pfims-mobile-search-panel').forEach(function (panel) {
            // Run this step if the mobile dialogs equals ready.
            if (panel.dataset.mobileDialogs === 'ready') {
                panel.pfimsRefreshMobileButtons?.();
                return;
            }
            // Return early if the closest check matches.
            if (panel.closest('.modal, .modal-overlay, dialog')) return;
            panel.dataset.mobileDialogs = 'ready';
            var toolbar = document.createElement('div');
            toolbar.className = 'pfims-mobile-filter-toolbar';
            panel.prepend(toolbar);
            var component = panel.closest('.pfims-filter-component') || panel;
            var clear = component.querySelector('.pfims-clear-filters, #clear');
            // Run this step if the clear is set.
            if (clear) {
                var clearAnchor = document.createComment('Desktop clear-filter position');
                clear.before(clearAnchor);
                var mobileLayout = window.matchMedia('(max-width: 768px)');
                // Get the place clear.
                function placeClear() {
                    // Run this step if the matches is set.
                    if (mobileLayout.matches) toolbar.appendChild(clear);
                    else clearAnchor.after(clear);
                }
                mobileLayout.addEventListener('change', placeClear);
                // Search/Filters are added below; append Clear after them.
                panel.pfimsPlaceMobileClear = placeClear;
            }

            // Get the controls for.
            function controlsFor(search) {
                // Keep the matching control items.
                return Array.from(panel.querySelectorAll('input, select')).filter(function (control) {
                    // Return early if the closest check matches.
                    if (control.closest('.pfims-select-options, .pfims-search-suggestions')) return false;
                    // Return early if the control type equals hidden and the closest is missing or false.
                    if (control.type === 'hidden' && !control.closest('.report-date-picker')) return false;
                    var field = control.closest('.pfims-filter-field, .filter-control, .project-filter-field');
                    // Return early if the hidden is set or the value check matches or the value equals none.
                    if (control.hidden || field?.hidden || field?.style.display === 'none') return false;
                    return (control.type === 'search' || /search/i.test(control.id)) === search;
                });
            }

            var buttons = [];
            panel.pfimsRefreshMobileButtons = function () {
                // Process each item.
                buttons.forEach(function (item) {
                    var hidden = !controlsFor(item.search).length;
                    // Run this step if the hidden does not equal the hidden.
                    if (item.button.hidden !== hidden) item.button.hidden = hidden;
                });
            };
            // Process each search.
            [true, false].forEach(function (search) {
                var opener = document.createElement('button');
                opener.type = 'button';
                opener.textContent = search ? 'Search' : 'Filters';
                opener.setAttribute('aria-haspopup', 'dialog');
                opener.hidden = !controlsFor(search).length;
                buttons.push({ button: opener, search: search });
                toolbar.appendChild(opener);
                // Handle the click event.
                opener.addEventListener('click', function () {
                    var dialog = document.createElement('dialog');
                    dialog.className = 'pfims-mobile-filter-dialog';
                    var heading = document.createElement('h2');
                    heading.id = 'pfims-mobile-dialog-title';
                    heading.textContent = search ? 'Search' : 'Filters';
                    dialog.setAttribute('aria-labelledby', heading.id);
                    var close = document.createElement('button');
                    close.type = 'button';
                    close.className = 'pfims-mobile-dialog-close';
                    close.textContent = '×';
                    close.setAttribute('aria-label', 'Close without applying');
                    dialog.append(heading, close);
                    // Build a value for each source.
                    var drafts = controlsFor(search).map(function (source) {
                        var field = document.createElement('label');
                        var label = document.createElement('span');
                        var originalField = source.closest('.pfims-filter-field, .filter-control, .project-filter-field');
                        // Get the plain label.
                        function plainLabel(element) {
                            // Return early if the element is missing or false.
                            if (!element) return '';
                            // Keep the matching node items.
                            var text = Array.from(element.childNodes).filter(function (node) {
                                return node.nodeType === Node.TEXT_NODE;
                            }).map(function (node) { return node.textContent.trim(); }).filter(Boolean).join(' ');
                            // Return early if the text is set.
                            if (text) return text;
                            var caption = element.querySelector(':scope > span:not(.pfims-select):not(.pfims-search-suggestions)');
                            return caption ? caption.textContent.trim() : '';
                        }
                        label.textContent = source.getAttribute('aria-label')
                            || plainLabel(originalField?.querySelector(':scope > label'))
                            || plainLabel(source.labels?.[0])
                            || plainLabel(originalField)
                            || filterLabel(source);
                        var draft;
                        // Run this step if the source type equals hidden.
                        if (source.type === 'hidden') {
                            draft = document.createElement('select');
                            var part = source.id.replace('filterReport', '').toLowerCase();
                            var start = part === 'year' ? 1900 : 1;
                            var end = part === 'year' ? 2100 : (part === 'month' ? 12 : 31);
                            // Repeat for each step in this range.
                            for (var value = start; value <= end; value++) {
                                draft.add(new Option(part === 'month' ? new Intl.DateTimeFormat('en', { month: 'long' }).format(new Date(2000, value - 1, 1)) : String(value), String(value)));
                            }
                            label.textContent = part.charAt(0).toUpperCase() + part.slice(1);
                        } else draft = source.cloneNode(true);
                        // Process each attribute.
                        ['id', 'name', 'oninput', 'onchange', 'onclick', 'onkeydown', 'style', 'class', 'hidden', 'aria-hidden', 'tabindex'].forEach(function (attribute) { draft.removeAttribute(attribute); });
                        // Keep the draft native and keyboard accessible; cloned enhancement
                        // flags prevent shared observers from adding live search suggestions.
                        draft.dataset.pfimsSelect = 'ready';
                        draft.dataset.pfimsSuggestions = 'ready';
                        // Process each element.
                        draft.querySelectorAll('[id]').forEach(function (element) { element.removeAttribute('id'); });
                        draft.value = source.value;
                        // Run this step if the multiple is set.
                        if (source.multiple) Array.from(draft.options).forEach(function (option, index) { option.selected = source.options[index].selected; });
                        field.append(label, draft);
                        dialog.appendChild(field);
                        return { source: source, draft: draft };
                    });
                    var footer = document.createElement('div');
                    footer.className = 'pfims-mobile-dialog-actions';
                    var cancel = document.createElement('button');
                    cancel.type = 'button';
                    cancel.className = 'btn-cancel';
                    cancel.textContent = 'Cancel';
                    var apply = document.createElement('button');
                    apply.type = 'button';
                    apply.className = 'btn-save';
                    apply.textContent = search ? 'Search' : 'Apply';
                    footer.append(cancel, apply);
                    dialog.appendChild(footer);
                    document.body.appendChild(dialog);
                    // Get the discard.
                    function discard() { dialog.close(); }
                    close.addEventListener('click', discard);
                    cancel.addEventListener('click', discard);
                    // Handle the close event.
                    dialog.addEventListener('close', function () { dialog.remove(); opener.focus(); });
                    // Handle the click event.
                    apply.addEventListener('click', function () {
                        // Return early if the every is missing or false.
                        if (!drafts.every(function (pair) { return pair.draft.reportValidity(); })) return;
                        // Set every value before notifying the existing module handlers.
                        var changed = drafts.filter(function (pair) {
                            // Run the from action.
                            return pair.source.multiple
                                ? JSON.stringify(Array.from(pair.source.selectedOptions, function (option) { return option.value; })) !== JSON.stringify(Array.from(pair.draft.selectedOptions, function (option) { return option.value; }))
                                : pair.source.value !== pair.draft.value;
                        });
                        // Process each pair.
                        changed.forEach(function (pair) {
                            // Run this step if the multiple is set.
                            if (pair.source.multiple) Array.from(pair.source.options).forEach(function (option, index) { option.selected = pair.draft.options[index].selected; });
                            else pair.source.value = pair.draft.value;
                            var dateTrigger = pair.source.closest('.report-date-picker')?.querySelector('.report-date-trigger');
                            // Run this step if the date trigger is set.
                            if (dateTrigger) dateTrigger.textContent = pair.draft.selectedOptions[0]?.textContent || pair.draft.value;
                        });
                        // Process each pair.
                        changed.forEach(function (pair) {
                            pair.source.dispatchEvent(new Event('input', { bubbles: true }));
                            pair.source.dispatchEvent(new Event('change', { bubbles: true }));
                            pair.source.pfimsSyncTrigger?.();
                        });
                        dialog.close();
                    });
                    dialog.showModal();
                    var mobile = window.matchMedia('(max-width: 768px)');
                    // Get the resize.
                    function resize() { if (!mobile.matches) discard(); }
                    mobile.addEventListener('change', resize);
                    // Handle the close event.
                    dialog.addEventListener('close', function () { mobile.removeEventListener('change', resize); }, { once: true });
                });
            });
            panel.pfimsPlaceMobileClear?.();
        });
    }

    // Get the install sticky filter tracking.
    function installStickyFilterTracking() {
        // Return early if the pfims sticky tracking equals ready.
        if (document.body.dataset.pfimsStickyTracking === 'ready') return;
        document.body.dataset.pfimsStickyTracking = 'ready';
        var queued = false;
        // Get the update.
        function update() {
            queued = false;
            var header = document.querySelector('.top-header');
            var headerBottom = header ? header.getBoundingClientRect().bottom : 0;
            // Process each component.
            document.querySelectorAll('.pfims-filter-component.pfims-sticky-filter').forEach(function (component) {
                var rect = component.getBoundingClientRect();
                var visible = rect.width > 0 && rect.height > 0;
                component.classList.toggle('is-stuck', visible && rect.top <= headerBottom + 2 && rect.bottom > headerBottom + 2);
            });
        }
        // Get the schedule.
        function schedule() {
            // Return early if the queued is set.
            if (queued) return;
            queued = true;
            window.requestAnimationFrame(update);
        }
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule, { passive: true });
        document.addEventListener('pfims:filters-updated', schedule);
        schedule();
    }

    // Remove project filter controls.
    function removeProjectFilterControls() {
        // Process each id.
        ['project', 'projectFilter', 'budgetProjectFilter', 'transactionProjectFilter', 'filterProject', 'budgetVarianceProject', 'bondProjectFilter']
            .forEach(function (id) {
                var control = document.getElementById(id);
                // Return early if the control is missing or false or the closest check matches.
                if (!control || control.closest('.modal-overlay, dialog')) return;
                var field = control.closest('label, .filter-control, .project-filter-field, .pfims-filter-field') || control;
                field.hidden = true;
                field.setAttribute('aria-hidden', 'true');
            });
    }

    // Get the install quiet scrollbars.
    function installQuietScrollbars() {
        // Process each element.
        document.querySelectorAll('.modal-container, dialog form, .table-wrapper, .table-wrap, .pfims-search-suggestions, .pfims-select-options, .pfims-column-chooser-menu').forEach(function (element) {
            // Return early if the pfims scrollbar equals ready.
            if (element.dataset.pfimsScrollbar === 'ready') return;
            element.dataset.pfimsScrollbar = 'ready';
            var timer;
            // Handle the scroll event.
            element.addEventListener('scroll', function () {
                element.classList.add('pfims-is-scrolling');
                window.clearTimeout(timer);
                // Run this action after the delay.
                timer = window.setTimeout(function () { element.classList.remove('pfims-is-scrolling'); }, 700);
            }, { passive: true });
        });
    }

    // Get the install table scroll fades.
    function installTableScrollFades() {
        var selector = '.table-wrapper, .table-wrap, .table-container, .table-responsive, .budget-table-wrapper, .items-table-wrapper, .forecast-table-wrapper, .report-table-wrapper, .analytics-table-wrapper';
        // Get the update.
        function update(wrapper) {
            var firstCell = wrapper.querySelector('table th:first-child');
            var dockedWidth = (firstCell && getComputedStyle(firstCell).position === 'sticky' ? firstCell.offsetWidth : 0) + 'px';
            // Run this step if the get property value does not equal the docked width.
            if (wrapper.style.getPropertyValue('--pfims-docked-column-width') !== dockedWidth) {
                wrapper.style.setProperty('--pfims-docked-column-width', dockedWidth);
            }
            var max = Math.max(0, wrapper.scrollWidth - wrapper.clientWidth);
            var scrollable = max > 1;
            wrapper.classList.toggle('is-at-start', !scrollable || wrapper.scrollLeft <= 1);
            wrapper.classList.toggle('is-at-end', !scrollable || wrapper.scrollLeft >= max - 1);
        }
        // Process each wrapper.
        document.querySelectorAll(selector).forEach(function (wrapper) {
            update(wrapper);
            // Return early if the pfims scroll fade equals ready.
            if (wrapper.dataset.pfimsScrollFade === 'ready') return;
            wrapper.dataset.pfimsScrollFade = 'ready';
            // Handle the scroll event.
            wrapper.addEventListener('scroll', function () { update(wrapper); }, { passive: true });
            // Run this step if the resize observer equals function.
            if (typeof ResizeObserver === 'function') new ResizeObserver(function () { update(wrapper); }).observe(wrapper);
        });
    }

    // Get the install custom select.
    function installCustomSelect(select) {
        // Return early if the pfims select equals ready or the multiple is set or the number is above 1.
        if (select.dataset.pfimsSelect === 'ready'
            || select.multiple || Number(select.size || 0) > 1) return;
        select.dataset.pfimsSelect = 'ready';
        select.classList.add('pfims-native-select');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

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
        menu.id = 'pfims-select-options-' + Math.random().toString(36).slice(2);
        trigger.setAttribute('aria-controls', menu.id);
        var fieldLabel = select.id ? document.querySelector('label[for="' + CSS.escape(select.id) + '"]') : null;
        var enclosingLabel = select.closest('label');
        // Get the direct label text.
        function directLabelText(element) {
            // Keep the matching node items.
            var text = Array.from(element?.childNodes || []).filter(function (node) { return node.nodeType === Node.TEXT_NODE; })
                .map(function (node) { return node.textContent.trim(); }).filter(Boolean).join(' ');
            return text || element?.querySelector(':scope > span:not(.pfims-select)')?.textContent?.trim() || '';
        }
        var siblingLabel = wrapper.parentElement?.querySelector(':scope > label');
        var label = select.getAttribute('aria-label') || directLabelText(fieldLabel)
            || directLabelText(enclosingLabel) || directLabelText(siblingLabel) || 'Choose an option';
        trigger.setAttribute('aria-label', label);
        wrapper.appendChild(trigger);
        (select.closest('dialog') || document.documentElement).appendChild(menu);
        var activeIndex = -1;
        var isStatusSelect = /status|state/i.test(select.id + ' ' + label);
        // Get the status tone.
        function statusTone(value) {
            // Return early if the is status select is missing or false.
            if (!isStatusSelect) return '';
            var normalized = String(value || '').trim().toLowerCase().replace(/[_-]+/g, ' ');
            // Return early if the normalized equals pending and the closest check matches.
            if (normalized === 'pending' && select.closest('.projects-page')) return 'project-pending';
            // Return early if the includes check matches.
            if (['ongoing', 'on track', 'in stock', 'sufficient', 'healthy', 'active', 'released', 'settled'].includes(normalized)) return 'success';
            // Return early if the includes check matches.
            if (['pending', 'at risk', 'near limit', 'low stock', 'reorder needed', 'missing amount', 'no project'].includes(normalized)) return 'warning';
            // Return early if the includes check matches.
            if (['delayed', 'over budget', 'out of stock', 'forfeited', 'missing amount and project'].includes(normalized)) return 'danger';
            // Return early if the normalized equals completed.
            if (normalized === 'completed') return 'complete';
            return '';
        }

        // Get the selected text.
        function selectedText() {
            return select.options[select.selectedIndex]?.textContent || select.getAttribute('aria-label') || 'Select';
        }
        // Get the close.
        function close() {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.removeAttribute('aria-activedescendant');
        }
        // Get the rebuild.
        function rebuild() {
            trigger.textContent = selectedText();
            trigger.disabled = select.disabled;
            trigger.dataset.statusTone = statusTone(select.options[select.selectedIndex]?.value || selectedText());
            menu.replaceChildren();
            // Process each native option.
            Array.from(select.options).forEach(function (nativeOption, index) {
                var option = document.createElement('button');
                option.type = 'button';
                option.className = 'pfims-select-option';
                option.textContent = nativeOption.textContent;
                option.title = nativeOption.textContent;
                option.disabled = nativeOption.disabled;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', String(nativeOption.selected));
                option.dataset.statusTone = statusTone(nativeOption.value || nativeOption.textContent);
                option.id = menu.id + '-option-' + index;
                // Handle the click event.
                option.addEventListener('click', function () {
                    select.value = nativeOption.value;
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    trigger.textContent = selectedText();
                    close();
                    trigger.focus();
                });
                menu.appendChild(option);
            });
        }
        select.pfimsSyncTrigger = function () {
            // Run this step if the text content does not equal the selected text or the trigger disabled does not equal the select disabled.
            if (trigger.textContent !== selectedText() || trigger.disabled !== select.disabled) rebuild();
        };

        // Get the position menu.
        function positionMenu() {
            var rect = trigger.getBoundingClientRect();
            var bodyStyle = window.getComputedStyle(document.body);
            var scale = parseFloat(bodyStyle.zoom) || 1;
            menu.style.setProperty('--pfims-menu-scale', scale);
            menu.style.fontFamily = bodyStyle.fontFamily;
            menu.style.fontSize = ((parseFloat(bodyStyle.fontSize) || 16) * scale) + 'px';
            var viewportHeight = window.visualViewport?.height || window.innerHeight;
            var below = viewportHeight - rect.bottom - 8;
            var above = rect.top - 8;
            var menuStyle = window.getComputedStyle(menu);
            var rowHeight = menu.firstElementChild?.getBoundingClientRect().height || 34 * scale;
            var menuSpacing = parseFloat(menuStyle.paddingTop) + parseFloat(menuStyle.paddingBottom)
                + parseFloat(menuStyle.borderTopWidth) + parseFloat(menuStyle.borderBottomWidth);
            var fiveRowsHeight = rowHeight * 5 + menuSpacing;
            var openAbove = below < fiveRowsHeight && above > below;
            var available = Math.max(34 * scale, openAbove ? above : below);
            var height = Math.min(fiveRowsHeight, available);
            menu.style.maxHeight = height + 'px';
            menu.style.width = Math.min(rect.width, window.innerWidth - 16) + 'px';
            menu.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - menu.offsetWidth - 8)) + 'px';
            menu.style.top = (openAbove ? Math.max(8, rect.top - Math.min(menu.scrollHeight, height) - 4) : rect.bottom + 4) + 'px';
            var dialogHost = select.closest('dialog');
            // Run this step if the dialog host is set.
            if (dialogHost) {
                var origin = dialogHost.getBoundingClientRect();
                menu.style.fontSize = (parseFloat(bodyStyle.fontSize) || 16) + 'px';
                menu.style.width = rect.width / scale + 'px';
                menu.style.maxHeight = height / scale + 'px';
                menu.style.left = (rect.left - origin.left) / scale + 'px';
                menu.style.top = ((openAbove ? rect.top - height - 4 : rect.bottom + 4) - origin.top) / scale + 'px';
            }
        }

        // Get the activate.
        function activate(index) {
            var options = Array.from(menu.children);
            // Return early if the options length is missing or false or the every check matches.
            if (!options.length || options.every(function (option) { return option.disabled; })) return;
            activeIndex = (index + options.length) % options.length;
            // Repeat while the value check matches.
            while (options[activeIndex]?.disabled) activeIndex = (activeIndex + 1) % options.length;
            // Process each option.
            options.forEach(function (option, optionIndex) { option.classList.toggle('is-active', optionIndex === activeIndex); });
            trigger.setAttribute('aria-activedescendant', options[activeIndex].id);
            var active = options[activeIndex];
            // Run this step if the offset top is below the scroll top.
            if (active.offsetTop < menu.scrollTop) menu.scrollTop = active.offsetTop;
            else if (active.offsetTop + active.offsetHeight > menu.scrollTop + menu.clientHeight)
                menu.scrollTop = active.offsetTop + active.offsetHeight - menu.clientHeight;
        }

        // Get the open.
        function open() {
            // Return early if the trigger disabled is set.
            if (trigger.disabled) return;
            // Process each other.
            document.querySelectorAll('.pfims-select-options:not([hidden])').forEach(function (other) { other.hidden = true; });
            // Process each other.
            document.querySelectorAll('.pfims-select-trigger[aria-expanded="true"]').forEach(function (other) {
                // Run this step if the other does not equal the trigger.
                if (other !== trigger) other.setAttribute('aria-expanded', 'false');
            });
            rebuild();
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            positionMenu();
            activate(Math.max(0, select.selectedIndex));
        }

        // Handle the click event.
        trigger.addEventListener('click', function () {
            // Run this step if the hidden is set.
            if (menu.hidden) open(); else close();
        });
        trigger.addEventListener('focus', select.pfimsSyncTrigger);
        // Handle the keydown event.
        trigger.addEventListener('keydown', function (event) {
            // Run this step if the key equals Escape.
            if (event.key === 'Escape') { close(); return; }
            // Run this step if the key equals ArrowDown or the key equals ArrowUp.
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                // Run this step if the hidden is set.
                if (menu.hidden) open();
                else activate(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
            } else if (event.key === 'Home' || event.key === 'End') {
                event.preventDefault();
                // Run this step if the hidden is set.
                if (menu.hidden) open();
                activate(event.key === 'Home' ? 0 : menu.children.length - 1);
            } else if ((event.key === 'Enter' || event.key === ' ') && !menu.hidden) {
                event.preventDefault();
                menu.children[activeIndex]?.click();
            }
        });
        select.addEventListener('change', rebuild);
        // Handle the focus event.
        select.addEventListener('focus', function () { trigger.focus(); });
        // Handle the pointerdown event.
        document.addEventListener('pointerdown', function (event) {
            // Run this step if the contains is missing or false and the contains is missing or false.
            if (!wrapper.contains(event.target) && !menu.contains(event.target)) close();
        });
        // Handle the scroll event.
        window.addEventListener('scroll', function (event) {
            // Run this step if the hidden is missing or false and the target does not equal the menu and the contains is missing or false.
            if (!menu.hidden && event.target !== menu && !menu.contains(event.target)) positionMenu();
        }, true);
        window.addEventListener('resize', close);
        new MutationObserver(rebuild).observe(select, { childList: true, subtree: true, attributes: true });
        rebuild();
    }

    // Get the install automatic refresh.
    function installAutomaticRefresh() {
        // Return early if the value equals ready.
        if (document.body?.dataset.pfimsAutoRefresh === 'ready') return;
        document.body.dataset.pfimsAutoRefresh = 'ready';
        // Data views refresh from their own successful create/update handlers or
        // an explicit user action. Never poll every page: that can invoke
        // role-incompatible endpoints and interrupt an active form.
    }

    // Get the install column chooser.
    function installColumnChooser(table) {
        // Return early if the value checks match.
        if (table.dataset.columnChooser === 'ready' || table.dataset.columnChooser === 'off'
            || table.closest('.report-preview') || !table.tHead || !table.tBodies.length) return;
        var headings = Array.from(table.tHead.rows[0]?.cells || []);
        // Return early if the headings length is below 2.
        if (headings.length < 2) return;

        var chooser = document.createElement('details');
        chooser.className = 'pfims-column-chooser';
        var summary = document.createElement('summary');
        var menu = document.createElement('div');
        menu.className = 'pfims-column-chooser-menu';
        chooser.append(summary, menu);

        var selections = [];
        // Process each heading.
        headings.forEach(function (heading, index) {
            var name = (heading.textContent || '').trim() || ('Column ' + (index + 1));
            // Return early if the test check matches.
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
        // Return early if the selections length is missing or false.
        if (!selections.length) return;

        var wrapper = table.closest('.table-wrapper, .table-wrap, .report-table-wrapper, .items-table-wrapper, .budget-table-wrapper, .table-responsive');
        // Run this step if the wrapper is set and the parent element is set.
        if (wrapper && wrapper.parentElement) {
            wrapper.parentElement.insertBefore(chooser, wrapper);
        } else {
            table.parentElement.insertBefore(chooser, table);
        }
        table.dataset.columnChooser = 'ready';
        updateSummary();

        // Update summary.
        function updateSummary() {
            // Keep the matching selection items.
            var visible = selections.filter(function (selection) { return selection.checkbox.checked; }).length;
            summary.textContent = 'Columns (' + visible + '/' + selections.length + ')';
        }

        // Apply column visibility.
        function applyColumnVisibility(event) {
            // Run this step if the some is missing or false.
            if (!selections.some(function (selection) { return selection.checkbox.checked; })) {
                event?.target && (event.target.checked = true);
            }
            // Process each selection.
            selections.forEach(function (selection) {
                var visible = selection.checkbox.checked;
                // Process each row.
                Array.from(table.rows).forEach(function (row) {
                    // Return early if the cells length equals 1 and the col span is above 1.
                    if (row.cells.length === 1 && row.cells[0].colSpan > 1) return;
                    // Run this step if the index is set.
                    if (row.cells[selection.index]) row.cells[selection.index].hidden = !visible;
                });
            });
            updateSummary();
        }

        new MutationObserver(function () { applyColumnVisibility(); })
            .observe(table.tBodies[0], { childList: true, subtree: true });

    }

    // Get the install single click navigation.
    function installSingleClickNavigation() {
        // Process each toggle.
        document.querySelectorAll('.nav-parent-toggle').forEach(function (toggle) {
            // Return early if the pfims dropdown equals ready.
            if (toggle.dataset.pfimsDropdown === 'ready') return;
            toggle.dataset.pfimsDropdown = 'ready';
            // Handle the click event.
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                var parent = toggle.closest('.nav-parent');
                var open = parent.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', String(open));
                var module = parent.dataset.pfimsModule;
                // Run this step if the module is set.
                if (module) {
                    // Try this action and handle any errors below.
                    try { window.localStorage.setItem('pfims-nav-' + module, open ? 'open' : 'closed'); } catch (error) {}
                }
            });
        });
        // Process each item.
        document.querySelectorAll('.sidebar li').forEach(function (item) {
            // Return early if the pfims navigation equals ready.
            if (item.dataset.pfimsNavigation === 'ready') return;
            var link = item.querySelector(':scope > a');
            // Return early if the link is missing or false.
            if (!link) return;
            item.dataset.pfimsNavigation = 'ready';
            item.setAttribute('role', 'presentation');
            // Handle the click event.
            item.addEventListener('click', function (event) {
                // Return early if the closest check matches.
                if (event.target.closest('a, button, input, select, textarea, form')) return;
                link.click();
            });
        });
    }

    // Get the install module navigation.
    function installModuleNavigation() {
        var loginEpoch = document.querySelector('script[data-login-epoch]')?.dataset.loginEpoch || '';
        var freshLogin = false;
        // Try this action and handle any errors below.
        try {
            freshLogin = !!loginEpoch && window.sessionStorage.getItem('pfims-navigation-epoch') !== loginEpoch;
            // Run this step if the fresh login is set.
            if (freshLogin) {
                // Process each module.
                ['projects','finance','inventory'].forEach(function(module) { window.localStorage.setItem('pfims-nav-'+module,'closed'); });
                window.sessionStorage.setItem('pfims-navigation-epoch',loginEpoch);
            }
        } catch(error) { freshLogin = true; }

        var portal = document.body?.dataset.portal || 'admin';
        // Process each link.
        document.querySelectorAll('.sidebar li > a').forEach(function (link) {
            var label = (link.textContent || '').trim().toLowerCase();
            // Run the find action.
            var module = Object.keys(MODULE_NAVIGATION).find(function (name) {
                return label === name || label.startsWith(name + ' ');
            });
            // Return early if the module is missing or false or the contains check matches.
            if (!module || link.classList.contains('nav-parent-toggle')) return;
            var item = link.closest('li');
            var rawHref = link.getAttribute('href') || '';
            // Return early if the item is missing or false or the raw href is missing or false or the raw href equals #.
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
            // Keep the matching entry items.
            MODULE_NAVIGATION[module]
                .filter(function (entry) {
                    return !(entry[0] === 'Project Cost Prediction' && portal !== 'admin');
                })
                .forEach(function (entry) {
                var child = document.createElement('a');
                var childHref = entry[1].charAt(0) === '/' ? entry[1] : base + entry[1];
                var childUrl = new URL(childHref, window.location.origin);
                var rolePaths = ROLE_PATHS[portal] || ROLE_PATHS.admin;
                // Run this step if the call check matches.
                if (Object.prototype.hasOwnProperty.call(rolePaths, childUrl.pathname)) {
                    var rolePath = rolePaths[childUrl.pathname];
                    // Return early if the role path equals null.
                    if (rolePath === null) return;
                    childUrl.pathname = rolePath;
                }
                child.href = childUrl.pathname + childUrl.search + childUrl.hash;
                child.textContent = entry[0];
                menu.appendChild(child);
            });
            item.appendChild(menu);
        });

        // Process each item.
        document.querySelectorAll('.sidebar .nav-parent').forEach(function (item) {
            // Return early if the pfims module state equals ready.
            if (item.dataset.pfimsModuleState === 'ready') return;
            var toggle = item.querySelector(':scope > .nav-parent-toggle');
            var module = item.dataset.pfimsModule || (toggle && (toggle.textContent || '').trim().split(/\s+/)[0].toLowerCase());
            // Return early if the toggle is missing or false or the module is missing or false.
            if (!toggle || !module) return;
            item.dataset.pfimsModule = module;

            var currentUrl = new URL(window.location.href);
            // Run the find action.
            var activeChild = Array.from(item.querySelectorAll(':scope > .nav-dropdown > a')).find(function (child) {
                var childUrl = new URL(child.href, window.location.origin);
                return childUrl.pathname.replace(/\/$/, '') === currentUrl.pathname.replace(/\/$/, '')
                    && childUrl.search === currentUrl.search;
            });
            item.classList.toggle('has-active-child', !!activeChild);
            // Process each child.
            item.querySelectorAll(':scope > .nav-dropdown > a').forEach(function (child) {
                child.classList.toggle('active', child === activeChild);
                // Handle the click event.
                child.addEventListener('click', function () {
                    // Process each parent.
                    document.querySelectorAll('.sidebar .nav-parent').forEach(function (parent) {
                        var parentModule = parent.dataset.pfimsModule;
                        // Return early if the parent module is missing or false.
                        if (!parentModule) return;
                        var selected = parent === item;
                        parent.classList.toggle('is-open', selected);
                        parent.querySelector(':scope > .nav-parent-toggle')?.setAttribute('aria-expanded', String(selected));
                        // Try this action and handle any errors below.
                        try { window.localStorage.setItem('pfims-nav-' + parentModule, selected ? 'open' : 'closed'); } catch (error) {}
                    });
                });
            });

            var savedState = null;
            // Try this action and handle any errors below.
            try { savedState = window.localStorage.getItem('pfims-nav-' + module); } catch (error) {}
            // Run this step if the active child is set and the fresh login is missing or false.
            if (activeChild && !freshLogin) {
                // Process each parent.
                document.querySelectorAll('.sidebar .nav-parent').forEach(function (parent) {
                    // Return early if the parent equals the item.
                    if (parent === item) return;
                    parent.classList.remove('is-open');
                    parent.querySelector(':scope > .nav-parent-toggle')?.setAttribute('aria-expanded', 'false');
                    var parentModule = parent.dataset.pfimsModule;
                    // Run this step if the parent module is set.
                    if (parentModule) {
                        // Try this action and handle any errors below.
                        try { window.localStorage.setItem('pfims-nav-' + parentModule, 'closed'); } catch (error) {}
                    }
                });
                // Try this action and handle any errors below.
                try { window.localStorage.setItem('pfims-nav-' + module, 'open'); } catch (error) {}
            }
            var shouldOpen = !freshLogin && (!!activeChild || savedState === 'open');
            item.classList.toggle('is-open', shouldOpen);
            toggle.setAttribute('aria-expanded', String(shouldOpen));
            item.dataset.pfimsModuleState = 'ready';
        });
    }

    // Get the action icon.
    function actionIcon(type) {
        // Return early if the type equals add.
        if (type === 'add') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
        }
        // Return early if the type equals import.
        if (type === 'import') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v11m0 0 4-4m-4 4-4-4M5 17v3h14v-3"/></svg>';
        }
        // Return early if the type equals export.
        if (type === 'export') {
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M12 17V11m0 0-3 3m3-3 3 3"/></svg>';
        }
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
    }

    // Get the install expandable action buttons.
    function installExpandableActionButtons() {
        // Process each control.
        document.querySelectorAll('button, a').forEach(function (control) {
            // Return early if the value checks match.
            if (control.dataset.pfimsActionLabel === 'ready' || control.hasAttribute('data-pfims-no-expand')
                || control.matches('.pfims-select-trigger, .pfims-alert-close')
                || /\b(close|dismiss)\b/i.test(control.getAttribute('aria-label') || '')
                || control.closest('.sidebar, .pagination-links, .pfims-select-options, .pfims-column-chooser-menu')) return;

            var visibleText = (control.textContent || '').replace(/\s+/g, ' ').trim();
            var accessibleText = (control.getAttribute('aria-label') || control.title || visibleText).replace(/\s+/g, ' ').trim();
            var candidate = (visibleText + ' ' + accessibleText).trim();
            var type = /(?:^|\s)\+?\s*(add|new)\b/i.test(candidate) ? 'add'
                : /\bimport\b/i.test(candidate) ? 'import'
                : /\b(export|configure export)\b/i.test(candidate) ? 'export'
                : (/\bclear(?:\s+filters?)?\b/i.test(candidate) || (/^\s*(x|✕|×)\s*$/i.test(visibleText)
                    && control.closest('.pfims-filter-panel, .filters-bar, .filter-row, .filters-grid, .history-filters'))) ? 'clear'
                : '';
            // Return early if the type is missing or false.
            if (!type) return;
            // Return early if the type equals clear and the closest check matches.
            if (type === 'clear' && control.closest('.modal-overlay, dialog, [role="dialog"]')) return;

            var label = visibleText || accessibleText;
            label = label.replace(/^[+✕×]\s*/, '').trim();
            // Run this step if the label is missing or false or the test check matches.
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

    // Clean up page size.
    function normalizePageSize(select) {
        // Return early if the pfims page size equals ready.
        if (select.dataset.pfimsPageSize === 'ready') return;
        select.dataset.pfimsPageSize = 'ready';
        var selected = '5';
        // Build a value for each value.
        select.replaceChildren.apply(select, ['5', '25', '50', '100'].map(function (value) {
            return new Option(value, value, value === selected, value === selected);
        }));
        select.value = selected;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Clean up pagination totals.
    function normalizePaginationTotals() {
        // Process each total.
        document.querySelectorAll('.pagination-wrapper .rows-info > span').forEach(function (total) {
            var text = (total.textContent || '').trim();
            var match = text.match(/\bof\s+(\d[\d,]*)\b/i) || text.match(/\btotal:\s*(\d[\d,]*)\b/i);
            var normalized = match ? 'Total: ' + match[1] : '';
            // Run this step if the normalized is set and the text does not equal the normalized.
            if (normalized && text !== normalized) total.textContent = normalized;
        });
    }

    // Get the pagination items.
    function paginationItems(page, totalPages) {
        // Return early if the total pages is at most 7.
        if (totalPages <= 7) return Array.from({ length: totalPages }, function (_, index) { return index + 1; });
        var pages = new Set([1, totalPages, page - 1, page, page + 1]);
        // Keep the matching value items.
        var ordered = Array.from(pages).filter(function (value) { return value >= 1 && value <= totalPages; }).sort(function (a, b) { return a - b; });
        var items = [];
        // Process each value.
        ordered.forEach(function (value, index) {
            // Run this step if the index is set and the value is above 1.
            if (index && value - ordered[index - 1] > 1) items.push('ellipsis');
            items.push(value);
        });
        return items;
    }

    // Get the install table pagination.
    function installTablePagination(table) {
        // Return early if the pfims pagination equals ready or the pfims pagination equals off or the closest check matches.
        if (table.dataset.pfimsPagination === 'ready' || table.dataset.pfimsPagination === 'off' || table.closest('.modal-overlay, dialog')) return;
        var host = table.closest('.table-wrapper, .table-wrap, .report-table-wrapper, .items-table-wrapper, .budget-table-wrapper') || table;
        var existing = host.nextElementSibling;
        // Run this step if the value check matches.
        if (existing?.matches('.pagination-wrapper')) {
            table.dataset.pfimsPagination = 'ready';
            existing.querySelectorAll('select').forEach(normalizePageSize);
            return;
        }
        var body = table.tBodies?.[0];
        // Return early if the body is missing or false.
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

        // Get the data rows.
        function dataRows() {
            // Keep the matching row items.
            return Array.from(body.rows).filter(function (row) {
                return row.style.display !== 'none' && row.dataset.pfimsEmpty !== 'true' && !(row.cells.length === 1
                    && /loading|no\s+records|no\s+data|no\s+.*\s+found|empty|fetching|please wait/i.test(row.textContent || ''));
            });
        }
        table.pfimsRefreshPagination = function () { page = 1; render(); };
        // Get the render.
        function render() {
            var rows = dataRows();
            // Run this step if the rows length is set.
            if (rows.length) {
                // Process each row.
                body.querySelectorAll('tr[data-pfims-empty="true"]').forEach(function (row) { row.remove(); });
            } else if (!body.rows.length) {
                var emptyRow = body.insertRow();
                emptyRow.dataset.pfimsEmpty = 'true';
                var emptyCell = emptyRow.insertCell();
                emptyCell.colSpan = Math.max(1, table.tHead?.rows[0]?.cells.length || 1);
                emptyCell.textContent = 'No Data Found';
                emptyCell.className = 'pfims-empty-table-cell';
            }
            var totalPages = Math.max(1, Math.ceil(rows.length / perPage));
            page = Math.min(page, totalPages);
            // Keep the matching row items.
            var placeholderRows = rows.length ? [] : Array.from(body.rows).filter(function (row) { return !rows.includes(row); });
            // Process each row.
            Array.from(body.rows).forEach(function (row) { row.hidden = true; });
            // Process each row.
            rows.forEach(function (row, index) { row.hidden = index < (page - 1) * perPage || index >= page * perPage; });
            // Process each row.
            placeholderRows.forEach(function (row) { row.hidden = false; });
            total.textContent = 'Total: ' + rows.length;
            links.replaceChildren();
            // Get the button.
            function button(label, target, active, disabled) {
                var control = document.createElement('button');
                control.type = 'button';
                control.textContent = label;
                control.dataset.page = String(target);
                control.disabled = disabled;
                // Run this step if the active is set.
                if (active) control.className = 'active';
                links.appendChild(control);
            }
            button('Previous', page - 1, false, page === 1);
            // Process each item.
            paginationItems(page, totalPages).forEach(function (item) {
                // Run this step if the item equals ellipsis.
                if (item === 'ellipsis') {
                    var dots = document.createElement('span');
                    dots.className = 'ellipsis';
                    dots.textContent = '…';
                    links.appendChild(dots);
                } else button(String(item), item, item === page, false);
            });
            button('Next', page + 1, false, page === totalPages);
            table.dispatchEvent(new Event('pfims:page-rendered'));
        }
        // Handle the change event.
        select.addEventListener('change', function () { perPage = Number(select.value) || 5; page = 1; render(); });
        // Handle the click event.
        links.addEventListener('click', function (event) {
            var control = event.target.closest('[data-page]');
            // Return early if the control is missing or false or the control disabled is set.
            if (!control || control.disabled) return;
            page = Number(control.dataset.page);
            render();
        });
        new MutationObserver(function () { page = 1; render(); }).observe(body, { childList: true });
        render();
    }

    // Get the install search suggestions.
    function installSearchSuggestions(input) {
        // Return early if the pfims suggestions equals ready.
        if (input.dataset.pfimsSuggestions === 'ready') return;
        input.dataset.pfimsSuggestions = 'ready';
        input.setAttribute('autocomplete', 'off');

        var menu = document.createElement('div');
        menu.className = 'pfims-search-suggestions';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;
        var host = input.closest('.pfims-filter-field, label, .filter-control, .search-box, .search-container') || input.parentElement;
        // Return early if the host is missing or false.
        if (!host) return;
        host.classList.add('pfims-search-host');
        host.appendChild(menu);

        // Get the close.
        function close() { menu.hidden = true; menu.replaceChildren(); }
        // Get the candidates.
        function candidates(query) {
            var scope = input.closest('.report-section, [data-analytics-content], .dashboard-tab-panel, .inventory-tab-content, main, .main-content') || document.body;
            var values = [];
            // Process each cell.
            scope.querySelectorAll('tbody tr:not([hidden]) td').forEach(function (cell) {
                // Return early if the offset parent equals null.
                if (cell.offsetParent === null) return;
                var value = (cell.textContent || '').replace(/\s+/g, ' ').trim();
                // Return early if the value length is below 2 or the value length is above 100 or the includes is missing or false.
                if (value.length < 2 || value.length > 100 || !value.toLowerCase().includes(query)) return;
                values.push(value.length > 58 ? value.slice(0, 57) + '…' : value);
            });
            return Array.from(new Set(values)).slice(0, 8);
        }
        // Handle the input event.
        input.addEventListener('input', function () {
            var query = input.value.trim().toLowerCase();
            // Return early if the query length is below 3.
            if (query.length < 3) return close();
            var matches = candidates(query);
            // Run this step if the matches length is missing or false.
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
            // Process each value.
            matches.forEach(function (value) {
                var option = document.createElement('button');
                option.type = 'button';
                option.setAttribute('role', 'option');
                var match = value.replace(new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark>$1</mark>');
                option.innerHTML = match;
                option.title = value;
                // Handle the mousedown event.
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
        // Handle the blur event.
        input.addEventListener('blur', function () { window.setTimeout(close, 120); });
        // Handle the keydown event.
        input.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });
    }

    // Get the install table tooltips.
    function installTableTooltips() {
        // Return early if the pfims table tooltips equals ready.
        if (document.body.dataset.pfimsTableTooltips === 'ready') return;
        document.body.dataset.pfimsTableTooltips = 'ready';
        var tooltip = document.createElement('div');
        tooltip.id = 'pfimsTableTooltip';
        tooltip.className = 'pfims-table-tooltip';
        tooltip.setAttribute('role', 'tooltip');
        tooltip.hidden = true;
        document.body.appendChild(tooltip);
        var activeCell = null;

        // Get the eligible.
        function eligible(target) {
            return target.closest && target.closest('table th, table td');
        }
        // Get the position.
        function position(x, y) {
            var gap = 12;
            var rect = tooltip.getBoundingClientRect();
            var left = Math.min(Math.max(gap, x + gap), window.innerWidth - rect.width - gap);
            var top = y + gap;
            // Run this step if the value is above the value.
            if (top + rect.height > window.innerHeight - gap) top = Math.max(gap, y - rect.height - gap);
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
        }
        // Get the show.
        function show(cell, x, y) {
            var value = (cell.textContent || '').replace(/\s+/g, ' ').trim();
            // Return early if the value is missing or false.
            if (!value) return;
            activeCell = cell;
            tooltip.textContent = value;
            tooltip.hidden = false;
            cell.setAttribute('aria-describedby', tooltip.id);
            // Run this step if the has attribute is missing or false and the query selector is missing or false.
            if (!cell.hasAttribute('tabindex') && !cell.querySelector('button, a, input, select, textarea')) cell.tabIndex = 0;
            position(x, y);
        }
        // Get the hide.
        function hide(cell) {
            // Return early if the cell is set and the active cell does not equal the cell.
            if (cell && activeCell !== cell) return;
            // Run this step if the active cell is set.
            if (activeCell) activeCell.removeAttribute('aria-describedby');
            activeCell = null;
            tooltip.hidden = true;
        }
        // Handle the pointerover event.
        document.addEventListener('pointerover', function (event) {
            var cell = eligible(event.target);
            // Run this step if the cell is set.
            if (cell) show(cell, event.clientX, event.clientY);
        });
        // Handle the pointermove event.
        document.addEventListener('pointermove', function (event) {
            // Run this step if the active cell is set and the hidden is missing or false.
            if (activeCell && !tooltip.hidden) position(event.clientX, event.clientY);
        });
        // Handle the pointerout event.
        document.addEventListener('pointerout', function (event) {
            var cell = eligible(event.target);
            // Run this step if the cell is set and the contains is missing or false.
            if (cell && !cell.contains(event.relatedTarget)) hide(cell);
        });
        // Handle the focusin event.
        document.addEventListener('focusin', function (event) {
            var cell = eligible(event.target);
            // Return early if the cell is missing or false.
            if (!cell) return;
            var rect = cell.getBoundingClientRect();
            show(cell, rect.left + rect.width / 2, rect.bottom);
        });
        // Handle the focusout event.
        document.addEventListener('focusout', function (event) {
            var cell = eligible(event.target);
            // Run this step if the cell is set.
            if (cell) hide(cell);
        });
    }

    // Get the install page loader.
    function installPageLoader() {
        // Return early if the pfims page loader is set or the contains check matches.
        if (window.PFIMS_PAGE_LOADER || document.body.classList.contains('landing-page')) return;
        var host = document.querySelector('main.main-content, .main-content, main');
        // Return early if the host is missing or false.
        if (!host) return;
        host.classList.add('pfims-loader-host');
        var loader = document.createElement('div');
        loader.id = 'pfimsPageLoader';
        loader.className = 'pfims-page-loader';
        loader.setAttribute('role', 'status');
        loader.setAttribute('aria-live', 'polite');
        loader.innerHTML = '<span class="pfims-page-spinner" aria-hidden="true"></span><span class="sr-only">Loading</span>';
        host.appendChild(loader);
        var pageGate = window.PFIMS_PAGE_PRELOADER = window.PFIMS_PAGE_PRELOADER || { active: true, pending: 0 };
        var pending = Number(pageGate.pending || 0);
        var visible = false;
        loader.hidden = true;

        // Get the show.
        function show() {
            visible = true;
            pageGate.active = true;
            document.documentElement.classList.add('pfims-preload');
            loader.hidden = false;
            host.classList.add('pfims-is-loading');
        }
        var settleTimer;
        // Get the hide.
        function hide() {
            pending = Number(pageGate.pending || 0);
            // Return early if the pending is above 0.
            if (pending > 0) return;
            // Return early if the pfims wait for ready equals true and the pfims page ready does not equal true.
            if (document.body.dataset.pfimsWaitForReady === 'true'
                && document.documentElement.dataset.pfimsPageReady !== 'true') return;
            window.clearTimeout(settleTimer);
            // Run this action after the delay.
            settleTimer = window.setTimeout(function () {
                pending = Number(pageGate.pending || 0);
                // Return early if the pending is above 0.
                if (pending > 0) return;
                // Return early if the pfims wait for ready equals true and the pfims page ready does not equal true.
                if (document.body.dataset.pfimsWaitForReady === 'true'
                    && document.documentElement.dataset.pfimsPageReady !== 'true') return;
                // Run the request animation frame action.
                window.requestAnimationFrame(function () { window.requestAnimationFrame(function () {
                    pending = Number(pageGate.pending || 0);
                    // Return early if the pending is above 0.
                    if (pending > 0) return;
                    // Return early if the pfims wait for ready equals true and the pfims page ready does not equal true.
                    if (document.body.dataset.pfimsWaitForReady === 'true'
                        && document.documentElement.dataset.pfimsPageReady !== 'true') return;
                    visible = false;
                    pageGate.active = false;
                    loader.hidden = true;
                    host.classList.remove('pfims-is-loading');
                    document.documentElement.classList.remove('pfims-preload');
                }); });
            }, 80);
        }
        window.PFIMS_PAGE_LOADER = { show: show, hide: hide };

        // Run this step if the pfims tracked is missing or false.
        if (!window.fetch.__pfimsTracked) {
            var originalFetch = window.fetch;
            // Get the tracked fetch.
            var trackedFetch = function () {
                // Run this step if the active is set.
                if (pageGate.active) pageGate.pending += 1;
                // Use the result when this request finishes.
                return originalFetch.apply(this, arguments).then(function(response) {
                    // Run this step if the response status equals 422.
                    if (response.status === 422) response.clone().json().then(function(data) {
                        var first = Object.values(data.errors || {})[0];
                        var message = Array.isArray(first) ? first[0] : first;
                        window.showPfimsAlert(message || data.message || 'Invalid input. Please check the entered values.', 'error');
                    }).catch(function() {});
                    return response;
                }).finally(function () {
                    // Return early if the active is missing or false.
                    if (!pageGate.active) return;
                    pageGate.pending = Math.max(0, Number(pageGate.pending || 0) - 1);
                    pending = pageGate.pending;
                    window.dispatchEvent(new CustomEvent('pfims:page-fetch-settled'));
                });
            };
            trackedFetch.__pfimsTracked = true;
            window.fetch = trackedFetch;
        }
        // Handle the pfims page fetch settled event.
        window.addEventListener('pfims:page-fetch-settled', function () {
            pending = Number(pageGate.pending || 0);
            // Run this step if the ready state equals complete.
            if (document.readyState === 'complete') hide();
        });
        // Handle the pfims page ready event.
        document.addEventListener('pfims:page-ready', function () {
            document.documentElement.dataset.pfimsPageReady = 'true';
            // Run this step if the ready state equals complete.
            if (document.readyState === 'complete') hide();
        });
        show();
        // Run this step if the ready state equals complete.
        if (document.readyState === 'complete') hide();
        else window.addEventListener('load', hide, { once: true });
        // Handle the click event.
        document.addEventListener('click', function (event) {
            var control = event.target.closest('a[href], [role="tab"], .tab, .nav-link, .nav-parent-toggle');
            // Return early if the value checks match.
            if (!control || event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            // Return early if the matches check matches or the closest check matches.
            if (control.matches('.nav-parent-toggle') || control.closest('.nav-parent-toggle')) return;
            // Run this step if the matches check matches.
            if (control.matches('a[href]')) {
                var href = control.getAttribute('href') || '';
                // Return early if the href is missing or false or the char at equals # or the target equals _blank or the has attribute check matches.
                if (!href || href.charAt(0) === '#' || control.target === '_blank' || control.hasAttribute('download')) return;
                var destination = new URL(control.href, window.location.href);
                // Return early if the href equals the href.
                if (destination.href === window.location.href) return;
                show();
                return;
            }
            show();
            // Run the request animation frame action.
            window.requestAnimationFrame(function () { window.requestAnimationFrame(hide); });
        }, true);
    }

    // Get the install accurate chart hover.
    function installAccurateChartHover() {
        // Return early if the chart is missing or false or the pfims accurate pointer is set.
        if (!window.Chart || window.Chart.__pfimsAccuratePointer) return;
        window.Chart.__pfimsAccuratePointer = true;
        window.Chart.register({
            id: 'pfimsAccuratePointer',
            // Get the before event.
            beforeEvent: function (chart, args) {
                var event = args.event;
                var nativeEvent = event && event.native;
                var point = nativeEvent && nativeEvent.touches && nativeEvent.touches[0]
                    ? nativeEvent.touches[0] : nativeEvent;
                // Return early if the point is missing or false or the client x does not equal number or the client y does not equal number.
                if (!point || typeof point.clientX !== 'number' || typeof point.clientY !== 'number') return;
                var rect = chart.canvas.getBoundingClientRect();
                // Return early if the width is missing or false or the height is missing or false.
                if (!rect.width || !rect.height) return;
                event.x = (point.clientX - rect.left) * (chart.width / rect.width);
                event.y = (point.clientY - rect.top) * (chart.height / rect.height);
            }
        });
    }

    // Open captured row action.
    function openCapturedRowAction(row, handler, editMode) {
        // Expose the action intent for modules whose row handler opens a
        // detail modal and must enter its edit state directly.
        window.PFIMS_ROW_EDIT_MODE = !!editMode;
        handler.call(row, { currentTarget: row, target: row, preventDefault: function () {}, stopPropagation: function () {} });
        // Return early if the edit mode is missing or false.
        if (!editMode) return;
        // Run this action after the delay.
        window.setTimeout(function () {
            // Keep the matching modal items.
            var visibleModals = Array.from(document.querySelectorAll('.modal, .modal-overlay, [role="dialog"]'))
                .filter(function (modal) { return !modal.hidden && getComputedStyle(modal).display !== 'none'; });
            // Keep the matching modal items.
            var visibleModal = visibleModals
                .filter(function (modal) { return modal.matches('.modal.active, .modal-overlay.active'); })
                .pop() || visibleModals.pop();
            // Return early if the visible modal is missing or false.
            if (!visibleModal) return;
            // Run the find action.
            var editButton = Array.from(visibleModal.querySelectorAll('button')).find(function (button) {
                return /^edit(?:\s|$)/i.test((button.textContent || '').trim()) && !button.hidden && getComputedStyle(button).display !== 'none';
            });
            // Run this step if the edit button is set.
            if (editButton) editButton.click();
            window.PFIMS_ROW_EDIT_MODE = false;
        }, 0);
    }

    // Get the make row action.
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
        // Handle the click event.
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            callback();
        });
        return button;
    }

    // Get the visible details modal.
    function visibleDetailsModal() {
        // Keep the matching modal items.
        var candidates = Array.from(document.querySelectorAll('.modal, .modal-overlay, [role="dialog"]'))
            .filter(function (modal) {
                return !modal.hidden && getComputedStyle(modal).display !== 'none'
                    && !/delete|confirm/i.test(modal.id || '');
            });
        // Keep the matching modal items.
        return candidates.filter(function (modal) {
            return modal.matches('.active, :modal, [open]') || modal.getAttribute('aria-hidden') === 'false';
        }).pop() || candidates.pop() || null;
    }

    // Get the trigger delete through edit.
    function triggerDeleteThroughEdit(editAction) {
        editAction();
        // Run this action after the delay.
        window.setTimeout(function () {
            var modal = visibleDetailsModal();
            // Return early if the modal is missing or false.
            if (!modal) return;
            // Run the find action.
            var deleteButton = Array.from(modal.querySelectorAll('button')).find(function (button) {
                return /^delete(?:\s|$)/i.test((button.textContent || '').trim())
                    && !button.hidden && getComputedStyle(button).display !== 'none';
            });
            // Run this step if the delete button is set.
            if (deleteButton) deleteButton.click();
        }, 0);
    }

    // Get the configure details footer.
    function configureDetailsFooter(editAction, deleteAction) {
        var modal = visibleDetailsModal();
        // Return early if the modal is missing or false.
        if (!modal) return;
        var footer = modal.querySelector('.modal-footer, .dialog-actions');
        // Return early if the footer is missing or false.
        if (!footer) return;
        footer.classList.add('pfims-detail-footer');

        var buttons = Array.from(footer.querySelectorAll('button'));
        // Run the find action.
        var cancel = buttons.find(function (button) { return /^(close|cancel)$/i.test((button.textContent || '').trim()); });
        // Run the find action.
        var edit = buttons.find(function (button) { return /^edit(?:\s|$)/i.test((button.textContent || '').trim()); });
        // Run the find action.
        var remove = buttons.find(function (button) { return /^delete(?:\s|$)/i.test((button.textContent || '').trim()); });

        // Run this step if the cancel is set.
        if (cancel) {
            cancel.textContent = 'Cancel';
            cancel.classList.add('pfims-detail-cancel');
        }
        // Run this step if the edit is missing or false and the edit action is set.
        if (!edit && editAction) {
            edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn-edit-project';
            edit.textContent = 'Edit';
            edit.addEventListener('click', editAction);
            footer.appendChild(edit);
        }
        // Run this step if the remove is missing or false and the delete action is set or the edit action is set.
        if (!remove && (deleteAction || editAction)) {
            remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-delete';
            remove.textContent = 'Delete';
            remove.addEventListener('click', deleteAction || function () { triggerDeleteThroughEdit(editAction); });
            footer.appendChild(remove);
        }
        // Run this step if the edit is set.
        if (edit) {
            edit.hidden = false;
            edit.style.display = 'inline-flex';
        }
        // Run this step if the remove is set.
        if (remove) {
            remove.hidden = false;
            remove.style.display = 'inline-flex';
        }
        // Run this step if the cancel is set.
        if (cancel) footer.insertBefore(cancel, footer.firstChild);
        // Run this step if the edit is set.
        if (edit) footer.appendChild(edit);
        // Run this step if the remove is set.
        if (remove) footer.appendChild(remove);
    }

    // Open generated details modal.
    function openGeneratedDetailsModal(table, row, actionIndex, editAction, deleteAction) {
        var modal = document.getElementById('pfimsGeneratedDetailsModal');
        // Run this step if the modal is missing or false.
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'pfimsGeneratedDetailsModal';
            modal.className = 'modal-overlay pfims-generated-details-modal';
            modal.innerHTML = '<div class="modal-container"><div class="modal-header"><h2>View Details</h2>'
                + '<button type="button" class="modal-close" aria-label="Close">×</button></div>'
                + '<div class="modal-body view-details-grid"></div><div class="modal-footer">'
                + '<button type="button" class="btn-cancel">Cancel</button></div></div>';
            document.body.appendChild(modal);
            // Handle the click event.
            modal.querySelector('.modal-close').addEventListener('click', function () { modal.classList.remove('active'); });
            // Handle the click event.
            modal.querySelector('.btn-cancel').addEventListener('click', function () { modal.classList.remove('active'); });
            // Handle the click event.
            modal.addEventListener('click', function (event) { if (event.target === modal) modal.classList.remove('active'); });
        }
        var sourceHeader = table.tHead && table.tHead.rows[0]
            ? table.tHead.rows[0] : table.querySelector('tr:has(th)');
        var headers = sourceHeader ? Array.from(sourceHeader.cells) : [];
        var body = modal.querySelector('.modal-body');
        body.replaceChildren();
        // Process each cell.
        Array.from(row.cells).forEach(function (cell, index) {
            // Return early if the index equals the action index.
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

    // Get the install standard actions.
    function installStandardActions(table) {
        // Return early if the pfims standard actions equals off or the contains check matches or the closest check matches.
        if (table.dataset.pfimsStandardActions === 'off' || table.classList.contains('analytics-table') || table.closest('.predictive-analytics-root')) return;
        var headerRow = table.tHead && table.tHead.rows[0]
            ? table.tHead.rows[0] : table.querySelector('tr:has(th)');
        // Return early if the header row is missing or false.
        if (!headerRow) return;
        // Run the find index action.
        var actionIndex = Array.from(headerRow.cells).findIndex(function (cell) {
            return /^actions?$/i.test((cell.textContent || '').trim());
        });
        // Return early if the action index is below 0.
        if (actionIndex < 0) return;
        headerRow.cells[actionIndex].classList.add('pfims-actions-heading');
        // Process each body.
        Array.from(table.tBodies || []).forEach(function (body) {
            // Process each row.
            Array.from(body.rows).forEach(function (row) {
                // Return early if the pfims standard action equals ready or the action index is missing or false.
                if (row.dataset.pfimsStandardAction === 'ready' || !row.cells[actionIndex]) return;
                var cell = row.cells[actionIndex];
                var controls = Array.from(cell.querySelectorAll('button, a'));
                // Return early if the controls length is missing or false.
                if (!controls.length) return;
                // Run the find action.
                var view = controls.find(function (control) { return /^view(?:\s|$)/i.test(control.getAttribute('aria-label') || control.title || control.textContent || ''); });
                // Run the find action.
                var edit = controls.find(function (control) { return /^edit(?:\s|$)/i.test(control.getAttribute('aria-label') || control.title || control.textContent || ''); });
                // Run the find action.
                var remove = controls.find(function (control) { return /^delete(?:\s|$)/i.test(control.getAttribute('aria-label') || control.title || control.textContent || ''); });
                // Get the invoke row control.
                var invokeRowControl = function (control) {
                    // Keep the original control attached to its row while its handler runs.
                    // Several module handlers resolve their record with this.closest('tr').
                    control.hidden = true;
                    cell.appendChild(control);
                    // Try this action and handle any errors below.
                    try {
                        control.click();
                    } finally {
                        control.remove();
                        control.hidden = false;
                    }
                };
                var editAction = edit ? function () { invokeRowControl(edit); } : null;
                var deleteAction = remove ? function () { invokeRowControl(remove); } : null;
                // Run this step if the view is missing or false.
                if (!view) {
                    // Run the make row action action.
                    view = makeRowAction('view', 'View details', function () {
                        openGeneratedDetailsModal(table, row, actionIndex, editAction, deleteAction);
                    });
                } else {
                    // Handle the click event.
                    view.addEventListener('click', function () {
                        // Run this action after the delay.
                        window.setTimeout(function () { configureDetailsFooter(editAction, deleteAction); }, 0);
                    });
                }
                cell.className = 'action-cell';
                cell.replaceChildren(view);
                row.dataset.pfimsStandardAction = 'ready';
            });
        });
    }

    // Get the install details footer bridge.
    function installDetailsFooterBridge() {
        // Return early if the pfims details footer bridge equals ready.
        if (document.body.dataset.pfimsDetailsFooterBridge === 'ready') return;
        document.body.dataset.pfimsDetailsFooterBridge = 'ready';
        // Handle the click event.
        document.addEventListener('click', function (event) {
            var control = event.target.closest('button, a');
            // Return early if the control is missing or false or the test is missing or false.
            if (!control || !/^view(?:\s|$)/i.test(control.getAttribute('aria-label') || control.title || control.textContent || '')) return;
            // Run this action after the delay.
            window.setTimeout(function () { configureDetailsFooter(null, null); }, 0);
            // Run this action after the delay.
            window.setTimeout(function () { configureDetailsFooter(null, null); }, 80);
        });
    }

    // Get the install actions for clickable rows.
    function installActionsForClickableRows(table) {
        // Return early if the contains check matches or the closest check matches or the closest check matches.
        if (table.classList.contains('analytics-table') || table.closest('.predictive-analytics-root') || table.closest('.dashboard-page')) return;
        // Process each body.
        Array.from(table.tBodies || []).forEach(function (body) {
            // Process each row.
            Array.from(body.rows).forEach(function (row) {
                // Return early if the pfims actions equals ready or the contains check matches.
                if (row.dataset.pfimsActions === 'ready' || row.classList.contains('total-row')) return;
                // Return early if the cells length equals 1 and the col span is above 1.
                if (row.cells.length === 1 && row.cells[0].colSpan > 1) return;
                // Return early if the onclick does not equal function.
                if (typeof row.onclick !== 'function') return;

                var handler = row.onclick;
                row.onclick = null;
                row.style.cursor = 'default';
                row.removeAttribute('tabindex');
                row.removeAttribute('role');
                row.removeAttribute('aria-label');

                var headerRow = table.tHead && table.tHead.rows[0]
                    ? table.tHead.rows[0] : table.querySelector('tr:has(th)');
                // Run the find index action.
                var actionIndex = headerRow ? Array.from(headerRow.cells).findIndex(function (cell) {
                    return /^actions?$/i.test((cell.textContent || '').trim());
                }) : -1;
                // Run this step if the header row is set and the action index is below 0.
                if (headerRow && actionIndex < 0) {
                    var actionHeading = document.createElement('th');
                    actionHeading.textContent = 'Actions';
                    headerRow.appendChild(actionHeading);
                    actionIndex = headerRow.cells.length - 1;
                }

                var actionCell = actionIndex >= 0 && row.cells[actionIndex] ? row.cells[actionIndex] : row.insertCell(-1);
                actionCell.className = 'action-cell';
                // Run the make row action action.
                actionCell.replaceChildren(
                    makeRowAction('view', 'View details', function () {
                        openCapturedRowAction(row, handler, false);
                        // Run this action after the delay.
                        window.setTimeout(function () { configureDetailsFooter(null, null); }, 0);
                    })
                );
                row.dataset.pfimsActions = 'ready';
            });
        });
    }

    // Get the install notification count.
    function installNotificationCount() {
        // Return early if the pfims notification count is set.
        if (document.body.dataset.pfimsNotificationCount) return;
        document.body.dataset.pfimsNotificationCount = 'ready';
        // Get the refresh.
        function refresh() {
            // Use the result when this request finishes.
            fetch('/api/notifications/unread-count', {headers: {'Accept':'application/json'}, credentials:'same-origin'})
                .then(function(response) { if (!response.ok) throw new Error(); return response.json(); })
                .then(function(data) {
                    // Process each link.
                    document.querySelectorAll('.top-header a[href*="notification"], header a[href*="notification"]').forEach(function(link) {
                        var badge = link.querySelector('.pfims-notification-count');
                        // Run this step if the badge is missing or false.
                        if (!badge) { badge=document.createElement('span'); badge.className='pfims-notification-count'; link.appendChild(badge); }
                        var count=Number(data.unread_count || 0); badge.textContent=String(count); badge.hidden=!count;
                        link.setAttribute('aria-label', count + ' unopened notifications');
                    });
                }).catch(function() {});
        }
        refresh(); window.setInterval(refresh, 60000); window.addEventListener('focus', refresh);
        // Handle the click event.
        document.addEventListener('click', function(event) { if (event.target.closest('[onclick*="mark"], [id*="mark"], [class*="mark-read"]')) window.setTimeout(refresh, 1000); });
    }

    // Set up system ui.
    function initializeSystemUi() {
        // Run this step if the contains check matches.
        if (document.body.classList.contains('landing-page')) {
            var seconds = Number(document.body.dataset.inactivityTimeoutSeconds || 0);
            // Try this action and handle any errors below.
            try {
                var notice = JSON.parse(window.sessionStorage.getItem('pfims-inactivity-notice') || 'null');
                window.sessionStorage.removeItem('pfims-inactivity-notice');
                // Run this step if the seconds is missing or false and the notice is set and the value is below 300000.
                if (!seconds && notice && Date.now() - notice.at < 300000) seconds = Number(notice.seconds || 0);
            } catch (error) {}
            // Run this step if the seconds is above 0.
            if (seconds > 0) {
                var duration = seconds % 60 === 0
                    ? (seconds / 60) + (seconds === 60 ? ' minute' : ' minutes')
                    : seconds + (seconds === 1 ? ' second' : ' seconds');
                window.showPfimsAlert('You were logged out after ' + duration + ' of inactivity. Please sign in again.', 'warning', 12000);
            }
            return;
        }

        // Process each label.
        document.querySelectorAll('header a[href*="notification"] span:not(.pfims-notification-count), .top-header a[href*="notification"] span:not(.pfims-notification-count)').forEach(function (label) {
            label.classList.add('sr-only');
            label.textContent = 'Open alerts';
        });
        installNotificationCount();
        installAutomaticRefresh();
        installHeaderClock();
        installRoleNavigation();
        installAdminAuditNavigation();
        installSharedFilters();
        installMobileFilterDialogs();
        installStickyFilterTracking();
        installDefaultFilters();
        installAuditDeepLink();
        removeProjectFilterControls();
        installModuleNavigation();
        installSingleClickNavigation();
        installExpandableActionButtons();
        document.querySelectorAll('.pagination-wrapper select').forEach(normalizePageSize);
        normalizePaginationTotals();
        document.querySelectorAll('input[type="search"]').forEach(installSearchSuggestions);
        document.querySelectorAll('main select, .modal-overlay select, dialog select').forEach(installCustomSelect);
        // Run this action at each time interval.
        window.setInterval(function () {
            // Return early if the hidden is set.
            if (document.hidden) return;
            // Process each select.
            document.querySelectorAll('select.pfims-native-select').forEach(function (select) { select.pfimsSyncTrigger?.(); });
        }, 250);
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
            installMobileFilterDialogs();
            document.dispatchEvent(new CustomEvent('pfims:filters-updated'));
            installAdminAuditNavigation();
            removeProjectFilterControls();
            installRoleNavigation();
            installModuleNavigation();
            installSingleClickNavigation();
            installExpandableActionButtons();
            document.querySelectorAll('.pagination-wrapper select').forEach(normalizePageSize);
            normalizePaginationTotals();
            document.querySelectorAll('input[type="search"]').forEach(installSearchSuggestions);
            document.querySelectorAll('main select, .modal-overlay select, dialog select').forEach(installCustomSelect);
            installQuietScrollbars();
            installTableScrollFades();
            document.querySelectorAll('table').forEach(installActionsForClickableRows);
            document.querySelectorAll('table').forEach(installStandardActions);
            document.querySelectorAll('table').forEach(installTablePagination);
            document.querySelectorAll('table').forEach(installColumnChooser);
        }).observe(document.body, { childList: true, subtree: true });
    }

    // Run this step if the ready state equals loading.
    if (document.readyState === 'loading') {
        installPageLoader();
        document.addEventListener('DOMContentLoaded', initializeSystemUi, { once: true });
    } else {
        installPageLoader();
        initializeSystemUi();
    }
})();
