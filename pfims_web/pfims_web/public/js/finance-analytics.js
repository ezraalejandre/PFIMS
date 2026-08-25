(function () {
    'use strict';

    function byId(id) {
        return document.getElementById(id);
    }

    function numeric(value) {
        var parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function addOption(select, value, label) {
        if (!select) return;
        var option = document.createElement('option');
        option.value = String(value);
        option.textContent = label;
        select.appendChild(option);
    }

    function renderBars(containerId, entries, formatter) {
        var container = byId(containerId);
        if (!container) return;
        container.replaceChildren();
        if (!entries.length) {
            var empty = document.createElement('div');
            empty.className = 'insight-empty';
            empty.textContent = 'No data matches the current filters.';
            container.appendChild(empty);
            return;
        }

        var max = Math.max.apply(null, entries.map(function (entry) { return numeric(entry.value); }));
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

    window.populateProjectFilter = function () {
        var filter = byId('projectFilter');
        if (!filter) return;
        var selected = filter.value || 'all';
        filter.replaceChildren();
        addOption(filter, 'all', 'All Projects');
        financeProjects.forEach(function (project) {
            addOption(filter, project.project_id, project.project_name || ('Project ' + project.project_id));
        });
        filter.value = Array.from(filter.options).some(function (option) { return option.value === selected; }) ? selected : 'all';
    };

    window.populateBudgetProjectFilter = function () {
        var filter = byId('budgetProjectFilter');
        if (!filter) return;
        var selected = filter.value || 'all';
        filter.replaceChildren();
        addOption(filter, 'all', 'All Projects');
        financeProjects.forEach(function (project) {
            addOption(filter, project.project_id, project.project_name || ('Project ' + project.project_id));
        });
        filter.value = Array.from(filter.options).some(function (option) { return option.value === selected; }) ? selected : 'all';
    };

    window.populateCategoryDropdown = function () {
        ['expenseCategory', 'detailCategoryEdit'].forEach(function (id) {
            var select = byId(id);
            if (!select) return;
            var selected = select.value;
            select.replaceChildren();
            addOption(select, '', 'Select Category...');
            financeCategories.forEach(function (category) {
                var option = document.createElement('option');
                option.value = category.fin_category_id || category.expense_category_id;
                option.dataset.code = category.category_code || '';
                option.textContent = category.category_name || category.category_code || 'Unnamed Category';
                select.appendChild(option);
            });
            select.value = selected;
        });

        var filter = byId('expenseCategoryFilter');
        if (!filter) return;
        var selectedFilter = filter.value || 'all';
        filter.replaceChildren();
        addOption(filter, 'all', 'All Categories');
        financeCategories.forEach(function (category) {
            addOption(filter, category.fin_category_id || category.expense_category_id, category.category_name || category.category_code || 'Unnamed Category');
        });
        filter.value = Array.from(filter.options).some(function (option) { return option.value === selectedFilter; }) ? selectedFilter : 'all';
    };

    window.filterByProject = function () {
        applyFilters();
    };

    window.clearSearch = function () {
        byId('projectSearch').value = '';
        byId('projectFilter').value = 'all';
        if (byId('expenseCategoryFilter')) byId('expenseCategoryFilter').value = 'all';
        applyFilters();
    };

    window.applyFilters = function () {
        if (currentReportTab !== 'expenses') return;
        var search = (byId('projectSearch').value || '').toLocaleLowerCase().trim();
        var projectId = byId('projectFilter').value;
        var categoryId = byId('expenseCategoryFilter') ? byId('expenseCategoryFilter').value : 'all';
        currentSearchTerm = search;
        currentProjectFilter = projectId;

        financeFilteredData = filterByPeriod(financeExpenses.filter(function (expense) {
            var matchesProject = projectId === 'all' || String(expense.project_id || '') === projectId;
            var matchesCategory = categoryId === 'all' || String(expense.fin_category_id || expense.expense_category_id || '') === categoryId;
            var haystack = [expense.project_name, expense.expense_description, expense.category_name, expense.remarks]
                .map(function (value) { return String(value || '').toLocaleLowerCase(); }).join(' ');
            return matchesProject && matchesCategory && (!search || haystack.includes(search));
        }));

        renderFinancePage(1);
        updateFinanceTotals();
        updateExpenseCategoryChart();
    };

    window.updateFinanceTotals = function () {
        var projectIds = new Set(financeFilteredData.map(function (expense) {
            return expense.project_id == null ? '' : String(expense.project_id);
        }).filter(Boolean));
        var totalBudget = financeProjects.reduce(function (sum, project) {
            return sum + (projectIds.has(String(project.project_id)) ? numeric(project.budget) : 0);
        }, 0);
        var totalExpenses = financeFilteredData.reduce(function (sum, expense) {
            return sum + (expense.is_pending_inventory ? 0 : numeric(expense.amount));
        }, 0);
        var variance = totalBudget - totalExpenses;
        byId('totalBudgetValue').textContent = formatCurrency(totalBudget);
        byId('totalExpensesValue').textContent = formatCurrency(totalExpenses);
        var varianceElement = byId('netVarianceValue');
        varianceElement.textContent = formatCurrency(variance);
        varianceElement.className = 'stat-value ' + (variance < 0 ? 'red' : 'green');
    };

    window.updateExpenseCategoryChart = function () {
        var totals = new Map();
        financeFilteredData.forEach(function (expense) {
            if (expense.is_pending_inventory || expense.amount == null) return;
            var label = expense.category_name || 'Uncategorized';
            totals.set(label, (totals.get(label) || 0) + numeric(expense.amount));
        });
        var entries = Array.from(totals, function (pair) { return { label: pair[0], value: pair[1] }; })
            .sort(function (a, b) { return b.value - a.value; });
        renderBars('expenseCategoryChart', entries, formatCurrency);
    };

    window.fetchExpenses = function () {
        return apiFetch('/finance-expenses')
            .then(function (data) {
                financeExpenses = Array.isArray(data) ? data : [];
                if (currentReportTab === 'expenses') applyFilters();
                return financeExpenses;
            })
            .catch(function (error) {
                financeExpenses = [];
                financeFilteredData = [];
                var body = byId('expenseTableBody');
                if (body) body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:#d32f2f;">Expenses could not be loaded. Please try again.</td></tr>';
                var chart = byId('expenseCategoryChart');
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
        if (byId('budgetStatusFilter')) byId('budgetStatusFilter').value = 'all';
        filterBudgetTable();
    };

    window.updateBudgetStats = function () {
        var allocated = budgetFilteredData.reduce(function (sum, row) { return sum + numeric(row.budget_amount); }, 0);
        var spent = budgetFilteredData.reduce(function (sum, row) { return sum + numeric(row.actual_amount); }, 0);
        byId('budgetTotalValue').textContent = formatCurrency(allocated);
        if (byId('budgetSpentValue')) byId('budgetSpentValue').textContent = formatCurrency(spent);
        if (byId('budgetRemainingValue')) {
            byId('budgetRemainingValue').textContent = formatCurrency(allocated - spent);
            byId('budgetRemainingValue').className = 'stat-value ' + (allocated - spent < 0 ? 'red' : 'green');
        }

        var counts = new Map([['On Track', 0], ['Near Limit', 0], ['Over Budget', 0], ['No Budget', 0]]);
        budgetFilteredData.forEach(function (row) { counts.set(row.status || 'No Budget', (counts.get(row.status || 'No Budget') || 0) + 1); });
        var classes = { 'On Track': 'is-success', 'Near Limit': 'is-warning', 'Over Budget': 'is-danger', 'No Budget': 'is-secondary' };
        renderBars('budgetStatusChart', Array.from(counts, function (pair) {
            return { label: pair[0], value: pair[1], className: classes[pair[0]] || '' };
        }).filter(function (entry) { return entry.value > 0; }), function (value) { return String(value); });
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (byId('expenseCategoryChart')) updateExpenseCategoryChart();
        if (byId('budgetStatusChart')) updateBudgetStats();
    });
})();
