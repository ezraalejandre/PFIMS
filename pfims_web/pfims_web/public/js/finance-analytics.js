// Set up the finance analytics page actions.
(function () {
    'use strict';

    // Get the by id.
    function byId(id) {
        return document.getElementById(id);
    }

    // Get the numeric.
    function numeric(value) {
        var parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    // Get the add option.
    function addOption(select, value, label) {
        // Return early if the select is missing or false.
        if (!select) return;
        var option = document.createElement('option');
        option.value = String(value);
        option.textContent = label;
        select.appendChild(option);
    }

    // Show bars.
    function renderBars(containerId, entries, formatter) {
        var container = byId(containerId);
        // Return early if the container is missing or false.
        if (!container) return;
        container.replaceChildren();
        // Run this step if the entries length is missing or false.
        if (!entries.length) {
            var empty = document.createElement('div');
            empty.className = 'insight-empty';
            empty.textContent = 'No data matches the current filters.';
            container.appendChild(empty);
            return;
        }

        // Build a value for each entry.
        var max = Math.max.apply(null, entries.map(function (entry) { return numeric(entry.value); }));
        // Process each entry.
        entries.forEach(function (entry) {
            var row = document.createElement('div');
            row.className = 'insight-bar-row';
            var label = document.createElement('span');
            label.className = 'insight-bar-label';
            label.title = entry.label;
            label.textContent = entry.label;
            var track = document.createElement('span');
            track.className = 'insight-bar-track';
            var fill = document.createElement('span');
            fill.className = 'insight-bar-fill ' + (entry.className || '');
            fill.style.width = (max > 0 ? Math.max(2, (numeric(entry.value) / max) * 100) : 0) + '%';
            track.appendChild(fill);
            var value = document.createElement('span');
            value.className = 'insight-bar-value';
            value.textContent = formatter(entry.value);
            row.append(label, track, value);
            container.appendChild(row);
        });
    }

    // Show pie.
    function renderPie(containerId, entries, formatter) {
        var container = byId(containerId);
        // Return early if the container is missing or false.
        if (!container) return;
        container.replaceChildren();

        // Combine the items into one result.
        var total = entries.reduce(function (sum, entry) {
            return sum + Math.max(0, numeric(entry.value));
        }, 0);
        // Run this step if the entries length is missing or false or the total is at most 0.
        if (!entries.length || total <= 0) {
            var empty = document.createElement('div');
            empty.className = 'insight-empty';
            empty.textContent = 'No data matches the current filters.';
            container.appendChild(empty);
            return;
        }

        var colors = ['#c9a96e', '#547896', '#4f8b68', '#c95c5c', '#8d6cab', '#dd8b57', '#5d9b9b', '#a8a054'];
        var currentPercentage = 0;
        // Build a value for each entry.
        var segments = entries.map(function (entry, index) {
            var start = currentPercentage;
            currentPercentage += (Math.max(0, numeric(entry.value)) / total) * 100;
            return {
                color: colors[index % colors.length],
                end: currentPercentage,
                entry: entry,
                start: start
            };
        });

        var layout = document.createElement('div');
        layout.className = 'insight-pie-layout';
        var pie = document.createElement('div');
        pie.className = 'insight-pie';
        pie.setAttribute('role', 'img');
        // Build a value for each entry.
        pie.setAttribute('aria-label', entries.map(function (entry) {
            return entry.label + ': ' + formatter(entry.value);
        }).join(', '));

        var svgNamespace = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(svgNamespace, 'svg');
        svg.setAttribute('viewBox', '0 0 100 100');
        svg.setAttribute('aria-hidden', 'true');
        var tooltip = document.createElement('div');
        tooltip.className = 'insight-pie-tooltip';

        // Get the point at.
        function pointAt(percentage) {
            var angle = ((percentage / 100) * 360 - 90) * (Math.PI / 180);
            return { x: 50 + 49 * Math.cos(angle), y: 50 + 49 * Math.sin(angle) };
        }

        // Process each segment.
        segments.forEach(function (segment) {
            var percentage = (numeric(segment.entry.value) / total) * 100;
            var slice;
            // Run this step if the percentage is at least 99.999.
            if (percentage >= 99.999) {
                slice = document.createElementNS(svgNamespace, 'circle');
                slice.setAttribute('cx', '50');
                slice.setAttribute('cy', '50');
                slice.setAttribute('r', '49');
            } else {
                var startPoint = pointAt(segment.start);
                var endPoint = pointAt(segment.end);
                slice = document.createElementNS(svgNamespace, 'path');
                slice.setAttribute('d', [
                    'M 50 50',
                    'L ' + startPoint.x + ' ' + startPoint.y,
                    'A 49 49 0 ' + (percentage > 50 ? 1 : 0) + ' 1 ' + endPoint.x + ' ' + endPoint.y,
                    'Z'
                ].join(' '));
            }
            slice.classList.add('insight-pie-slice');
            slice.setAttribute('fill', segment.color);
            slice.setAttribute('tabindex', '0');
            var details = segment.entry.label + ': ' + formatter(segment.entry.value) + ' (' + percentage.toFixed(1) + '%)';
            slice.setAttribute('aria-label', details);
            // Show details.
            var showDetails = function () {
                tooltip.textContent = details;
                tooltip.classList.add('is-visible');
            };
            // Hide details.
            var hideDetails = function () { tooltip.classList.remove('is-visible'); };
            slice.addEventListener('mouseenter', showDetails);
            slice.addEventListener('mouseleave', hideDetails);
            slice.addEventListener('focus', showDetails);
            slice.addEventListener('blur', hideDetails);
            svg.appendChild(slice);
        });
        pie.append(svg, tooltip);

        var legend = document.createElement('div');
        legend.className = 'insight-pie-legend';
        // Process each segment.
        segments.forEach(function (segment) {
            var row = document.createElement('div');
            row.className = 'insight-pie-legend-row';
            var swatch = document.createElement('span');
            swatch.className = 'insight-pie-swatch';
            swatch.style.backgroundColor = segment.color;
            var label = document.createElement('span');
            label.className = 'insight-pie-label';
            label.title = segment.entry.label;
            label.textContent = segment.entry.label;
            var value = document.createElement('span');
            value.className = 'insight-pie-value';
            value.textContent = formatter(segment.entry.value) + ' (' + ((numeric(segment.entry.value) / total) * 100).toFixed(1) + '%)';
            row.append(swatch, label, value);
            legend.appendChild(row);
        });

        layout.append(pie, legend);
        container.appendChild(layout);
    }

    window.populateProjectFilter = function () {
        var filter = byId('projectFilter');
        // Return early if the filter is missing or false.
        if (!filter) return;
        var selected = filter.value || 'all';
        filter.replaceChildren();
        addOption(filter, 'all', 'All Projects');
        // Process each project.
        financeProjects.forEach(function (project) {
            addOption(filter, project.project_id, project.project_name || ('Project ' + project.project_id));
        });
        // Run the some action.
        filter.value = Array.from(filter.options).some(function (option) { return option.value === selected; }) ? selected : 'all';
    };

    window.populateBudgetProjectFilter = function () {
        var filter = byId('budgetProjectFilter');
        // Return early if the filter is missing or false.
        if (!filter) return;
        var selected = filter.value || 'all';
        filter.replaceChildren();
        addOption(filter, 'all', 'All Projects');
        // Process each project.
        financeProjects.forEach(function (project) {
            addOption(filter, project.project_id, project.project_name || ('Project ' + project.project_id));
        });
        // Run the some action.
        filter.value = Array.from(filter.options).some(function (option) { return option.value === selected; }) ? selected : 'all';
    };

    window.populateCategoryDropdown = function () {
        // Process each id.
        ['expenseCategory', 'detailCategoryEdit'].forEach(function (id) {
            var select = byId(id);
            // Return early if the select is missing or false.
            if (!select) return;
            filterExpenseCategorySelect(id, byId(id === 'expenseCategory' ? 'expenseType' : 'detailExpenseTypeEdit').value, select.value);
        });

        var filter = byId('expenseCategoryFilter');
        // Return early if the filter is missing or false.
        if (!filter) return;
        var selectedFilter = filter.value || 'all';
        filter.replaceChildren();
        addOption(filter, 'all', 'All Categories');
        // Process each category.
        financeCategories.forEach(function (category) {
            addOption(filter, category.fin_category_id || category.expense_category_id, category.category_name || category.category_code || 'Unnamed Category');
        });
        // Run the some action.
        filter.value = Array.from(filter.options).some(function (option) { return option.value === selectedFilter; }) ? selectedFilter : 'all';
    };

    window.filterByProject = function () {
        applyFilters();
    };

    window.clearSearch = function () {
        // Run this step if the by id check matches.
        if (byId('projectSearch')) byId('projectSearch').value = '';
        // Run this step if the by id check matches.
        if (byId('projectFilter')) byId('projectFilter').value = 'all';
        // Run this step if the by id check matches.
        if (byId('expenseScopeFilter')) byId('expenseScopeFilter').value = 'all';
        // Run this step if the by id check matches.
        if (byId('expenseRecordStatusFilter')) byId('expenseRecordStatusFilter').value = 'all';
        // Run this step if the by id check matches.
        if (byId('expenseSourceFilter')) byId('expenseSourceFilter').value = 'all';
        // Run this step if the by id check matches.
        if (byId('expenseCategoryFilter')) byId('expenseCategoryFilter').value = 'all';
        applyFilters();
    };

    window.applyFilters = function () {
        // Return early if the current report tab does not equal expenses.
        if (currentReportTab !== 'expenses') return;
        var search = ((byId('projectSearch') && byId('projectSearch').value) || '').toLocaleLowerCase().trim();
        var projectId = (byId('projectFilter') && byId('projectFilter').value) || 'all';
        var scope = (byId('expenseScopeFilter') && byId('expenseScopeFilter').value) || 'all';
        var recordStatus = (byId('expenseRecordStatusFilter') && byId('expenseRecordStatusFilter').value) || 'all';
        var source = (byId('expenseSourceFilter') && byId('expenseSourceFilter').value) || 'all';
        var categoryId = byId('expenseCategoryFilter') ? byId('expenseCategoryFilter').value : 'all';
        currentSearchTerm = search;
        currentProjectFilter = projectId;

        // Keep the matching expense items.
        financeFilteredData = filterByPeriod(financeExpenses.filter(function (expense) {
            var matchesProject = projectId === 'all' || String(expense.project_id || '') === projectId;
            var matchesCategory = categoryId === 'all' || String(expense.fin_category_id || expense.expense_category_id || '') === categoryId;
            // Run the find action.
            var category = financeCategories.find(function (item) {
                return String(item.fin_category_id || item.expense_category_id || '') === String(expense.fin_category_id || expense.expense_category_id || '');
            });
            var classification = String(category && category.classification || '').toLowerCase();
            var matchesScope = scope === 'all'
                || (scope === 'overall' && ['direct', 'admin', 'office'].includes(classification))
                || (scope === 'direct' && classification === 'direct')
                || (scope === 'admin' && classification === 'admin')
                || (scope === 'office' && classification === 'office');
            var hasProject = expense.project_id !== null
                && expense.project_id !== undefined
                && String(expense.project_id).trim() !== '';
            var amount = expense.amount === null || expense.amount === undefined || String(expense.amount).trim() === ''
                ? NaN
                : Number(expense.amount);
            var isMissingAmount = !Number.isFinite(amount) || amount <= 0;
            var matchesRecordStatus = recordStatus === 'all'
                || (recordStatus === 'missing_amount' && isMissingAmount)
                || (recordStatus === 'no_project' && !hasProject)
                || (recordStatus === 'missing_amount_and_project' && isMissingAmount && !hasProject);
            var isInventoryExpense = expense.is_inventory_expense === true
                || expense.entry_kind === 'inventory_purchase'
                || (expense.inventory_transaction_id !== null
                    && expense.inventory_transaction_id !== undefined
                    && String(expense.inventory_transaction_id).trim() !== '');
            var matchesSource = source === 'all'
                || (source === 'inventory' && isInventoryExpense)
                || (source === 'manual' && !isInventoryExpense);
            // Build a value for each value.
            var haystack = [expense.project_name, expense.expense_description, expense.category_name, expense.remarks]
                .map(function (value) { return String(value || '').toLocaleLowerCase(); }).join(' ');
            return matchesProject
                && matchesCategory
                && matchesScope
                && matchesRecordStatus
                && matchesSource
                && (!search || haystack.includes(search));
        }));

        renderFinancePage(1);
        updateFinanceTotals();
        updateExpenseCategoryChart();
    };

    window.updateFinanceTotals = function () {
        // Build a value for each expense.
        var projectIds = new Set(financeFilteredData.map(function (expense) {
            return expense.project_id == null ? '' : String(expense.project_id);
        }).filter(Boolean));
        // Combine the items into one result.
        var totalBudget = (budgetData || []).reduce(function (sum, budget) {
            return sum + (projectIds.has(String(budget.project_id)) ? numeric(budget.budget_amount) : 0);
        }, 0);
        // Combine the items into one result.
        var totalExpenses = financeFilteredData.reduce(function (sum, expense) {
            return sum + (expense.is_pending_inventory ? 0 : numeric(expense.amount));
        }, 0);
        var variance = totalBudget - totalExpenses;
        // Run this step if the by id check matches.
        if (byId('totalBudgetValue')) byId('totalBudgetValue').textContent = formatCurrency(totalBudget);
        // Run this step if the by id check matches.
        if (byId('totalExpensesValue')) byId('totalExpensesValue').textContent = formatCurrency(totalExpenses);
        var varianceElement = byId('netVarianceValue');
        // Run this step if the variance element is set.
        if (varianceElement) {
            varianceElement.textContent = formatCurrency(variance);
            varianceElement.className = 'stat-value ' + (variance < 0 ? 'red' : 'green');
        }
    };

    window.updateExpenseCategoryChart = function () {
        var totals = new Map();
        // Process each expense.
        financeFilteredData.forEach(function (expense) {
            // Return early if the is pending inventory is set or the amount equals null.
            if (expense.is_pending_inventory || expense.amount == null) return;
            var label = expense.category_name || 'Uncategorized';
            totals.set(label, (totals.get(label) || 0) + numeric(expense.amount));
        });
        // Run the from action.
        var entries = Array.from(totals, function (pair) { return { label: pair[0], value: pair[1] }; })
            .sort(function (a, b) { return b.value - a.value; });
        renderPie('expenseCategoryChart', entries, formatCurrency);
    };

    window.fetchExpenses = function () {
        // Use the result when this request finishes.
        return apiFetch('/finance-expenses')
            .then(function (data) {
                financeExpenses = Array.isArray(data) ? data : [];
                // Reload the saved spending after each change. Cached totals may be out of date.
                budgetDataCache = null;
                // Use the result when this request finishes.
                return fetchBudgetData().then(function () {
                    // Run this step if the current report tab equals expenses.
                    if (currentReportTab === 'expenses') applyFilters();
                    return financeExpenses;
                });
            })
            .catch(function (error) {
                financeExpenses = [];
                financeFilteredData = [];
                var body = byId('expenseTableBody');
                // Run this step if the body is set.
                if (body) body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:#d32f2f;">Expenses could not be loaded. Please try again.</td></tr>';
                var chart = byId('expenseCategoryChart');
                // Run this step if the chart is set.
                if (chart) chart.innerHTML = '<div class="insight-error">Expense analytics could not be loaded.</div>';
                updateFinanceTotals();
                showError(error.message || 'Expenses could not be loaded.');
                return [];
            });
    };

    window.filterBudgetTable = function () {
        var search = (byId('budgetSearch').value || '').toLocaleLowerCase().trim();
        var projectId = byId('budgetProjectFilter').value;
        var status = byId('budgetStatusFilter') ? byId('budgetStatusFilter').value : 'all';
        budgetSearchTerm = search;
        budgetProjectFilter = projectId;
        // Keep the matching item items.
        budgetFilteredData = budgetData.filter(function (item) {
            var matchesProject = projectId === 'all' || String(item.project_id || '') === projectId;
            var matchesStatus = status === 'all' || item.status === status;
            var matchesSearch = !search || String(item.project_name || '').toLocaleLowerCase().includes(search);
            return matchesProject && matchesStatus && matchesSearch;
        });
        renderBudgetPage(1);
        updateBudgetStats();
    };

    window.clearBudgetSearch = function () {
        byId('budgetSearch').value = '';
        byId('budgetProjectFilter').value = 'all';
        // Run this step if the by id check matches.
        if (byId('budgetStatusFilter')) byId('budgetStatusFilter').value = 'all';
        filterBudgetTable();
    };

    window.updateBudgetStats = function () {
        // Combine the items into one result.
        var allocated = budgetFilteredData.reduce(function (sum, row) { return sum + numeric(row.budget_amount); }, 0);
        // Combine the items into one result.
        var spent = budgetFilteredData.reduce(function (sum, row) { return sum + numeric(row.actual_amount); }, 0);
        // Run this step if the by id check matches.
        if (byId('budgetTotalValue')) byId('budgetTotalValue').textContent = formatCurrency(allocated);
        // Run this step if the by id check matches.
        if (byId('budgetSpentValue')) byId('budgetSpentValue').textContent = formatCurrency(spent);
        // Run this step if the by id check matches.
        if (byId('budgetRemainingValue')) {
            byId('budgetRemainingValue').textContent = formatCurrency(allocated - spent);
            byId('budgetRemainingValue').className = 'stat-value ' + (allocated - spent < 0 ? 'red' : 'green');
        }

        var counts = new Map([['On Track', 0], ['Near Limit', 0], ['Over Budget', 0], ['No Budget', 0]]);
        // Process each row.
        budgetFilteredData.forEach(function (row) { counts.set(row.status || 'No Budget', (counts.get(row.status || 'No Budget') || 0) + 1); });
        var classes = { 'On Track': 'is-success', 'Near Limit': 'is-warning', 'Over Budget': 'is-danger', 'No Budget': 'is-secondary' };
        // Run the from action.
        renderBars('budgetStatusChart', Array.from(counts, function (pair) {
            return { label: pair[0], value: pair[1], className: classes[pair[0]] || '' };
        }).filter(function (entry) { return entry.value > 0; }), function (value) { return String(value); });
    };

    // Handle the dom content loaded event.
    document.addEventListener('DOMContentLoaded', function () {
        // Run this step if the by id check matches.
        if (byId('expenseCategoryChart')) updateExpenseCategoryChart();
        // Run this step if the by id check matches.
        if (byId('budgetStatusChart')) updateBudgetStats();
    });
})();
