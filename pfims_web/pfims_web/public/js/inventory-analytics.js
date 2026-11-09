// Set up the inventory analytics page actions.
(function () {
    'use strict';

    var activeInventoryTab = 'items';
    var movementChart = null;

    // Get the by id.
    function byId(id) { return document.getElementById(id); }
    // Get the numeric.
    function numeric(value) {
        var parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }
    // Get the stock state.
    function stockState(item) {
        var stock = numeric(item.current_stock);
        var threshold = numeric(item.reorder_level);
        // Return early if the stock is at most 0.
        if (stock <= 0) return 'out_of_stock';
        return stock <= threshold ? 'low_stock' : 'in_stock';
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
    // Set metric.
    function setMetric(labelId, label, valueId, value, subId, sub) {
        // Run this step if the by id check matches.
        if (byId(labelId)) byId(labelId).textContent = label;
        // Run this step if the by id check matches.
        if (byId(valueId)) byId(valueId).textContent = value;
        // Run this step if the by id check matches.
        if (byId(subId)) byId(subId).textContent = sub;
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
            fill.style.width = (max > 0 ? Math.max(2, numeric(entry.value) / max * 100) : 0) + '%';
            track.appendChild(fill);
            var value = document.createElement('span');
            value.className = 'insight-bar-value';
            value.textContent = formatter(entry.value);
            row.append(label, track, value);
            container.appendChild(row);
        });
    }

    window.switchInventoryTab = function (element, tab) {
        activeInventoryTab = tab;
        // Process each entry.
        document.querySelectorAll('.inventory-tabs .tab').forEach(function (entry) {
            entry.classList.toggle('active', entry === element);
        });
        byId('tabItems').classList.toggle('active', tab === 'items');
        byId('tabTransactions').classList.toggle('active', tab === 'transactions');
        // Run this step if the tab equals items.
        if (tab === 'items') {
            renderItemsPage(1);
        } else {
            renderTransactionPage(1);
        }
        updateStats(filteredData, itemsFilteredData, lookupData.categories || []);
    };

    window.populateFilterDropdowns = function () {
        var category = byId('itemsCategoryFilter');
        var transactionCategory = byId('transactionCategoryFilter');
        var supplier = byId('itemsSupplierFilter');
        // Process each select.
        [category, transactionCategory].forEach(function (select) {
            // Return early if the select is missing or false.
            if (!select) return;
            var selected = select.value || 'all';
            select.replaceChildren();
            addOption(select, 'all', 'All Categories');
            // Process each row.
            (lookupData.categories || []).forEach(function (row) {
                addOption(select, row.inventory_category_id, row.inventory_category_name);
            });
            // Run the some action.
            select.value = Array.from(select.options).some(function (option) { return option.value === selected; }) ? selected : 'all';
        });
        // Run this step if the supplier is set.
        if (supplier) {
            var selectedSupplier = supplier.value || 'all';
            supplier.replaceChildren();
            addOption(supplier, 'all', 'All Suppliers');
            // Process each row.
            (lookupData.suppliers || []).forEach(function (row) { addOption(supplier, row.supplier_id, row.supplier_name); });
            // Run the some action.
            supplier.value = Array.from(supplier.options).some(function (option) { return option.value === selectedSupplier; }) ? selectedSupplier : 'all';
        }
    };

    // Get the populate transaction filter options.
    function populateTransactionFilterOptions() {
        var project = byId('transactionProjectFilter');
        // Return early if the project is missing or false.
        if (!project) return;
        var selected = project.value || 'all';
        var projects = new Map();
        // Process each row.
        allTransactions.forEach(function (row) {
            // Run this step if the project id is set and the project is set.
            if (row.project_id && row.project) projects.set(String(row.project_id), row.project);
        });
        project.replaceChildren();
        addOption(project, 'all', 'All Projects');
        // Run the sort action.
        Array.from(projects).sort(function (a, b) { return a[1].localeCompare(b[1]); }).forEach(function (entry) {
            addOption(project, entry[0], entry[1]);
        });
        // Run the some action.
        project.value = Array.from(project.options).some(function (option) { return option.value === selected; }) ? selected : 'all';
    }

    window.updateStats = function () {
        // Run this step if the active inventory tab equals transactions.
        if (activeInventoryTab === 'transactions') {
            // Keep the matching row items.
            var inbound = filteredData.filter(function (row) { return row.transaction_type === 'IN'; })
                .reduce(function (sum, row) { return sum + numeric(row.quantity); }, 0);
            // Keep the matching row items.
            var outbound = filteredData.filter(function (row) { return row.transaction_type === 'OUT'; })
                .reduce(function (sum, row) { return sum + numeric(row.quantity); }, 0);
            setMetric('totalItemsLabel', 'Transactions', 'totalItemsCount', filteredData.length, 'totalItemsSub', 'Matching current transaction filters');
            setMetric('lowStockLabel', 'Stock In Quantity', 'lowStockCount', inbound.toLocaleString(), 'lowStockSub', 'Total inbound units in view');
            setMetric('categoriesLabel', 'Stock Out Quantity', 'categoriesCount', outbound.toLocaleString(), 'categoriesSub', 'Total outbound units in view');
            updateTransactionChart();
        } else {
            // Keep the matching item items.
            var low = itemsFilteredData.filter(function (item) { return stockState(item) === 'low_stock'; });
            // Keep the matching item items.
            var out = itemsFilteredData.filter(function (item) { return stockState(item) === 'out_of_stock'; });
            setMetric('totalItemsLabel', 'Total Items', 'totalItemsCount', itemsFilteredData.length, 'totalItemsSub', 'Matching current item filters');
            setMetric('lowStockLabel', 'Low Stock', 'lowStockCount', low.length, 'lowStockSub', 'At or below each item reorder level');
            setMetric('categoriesLabel', 'Out of Stock', 'categoriesCount', out.length, 'categoriesSub', 'No stock remaining');
        }
        var badge = byId('transactionBadge');
        // Run this step if the badge is set.
        if (badge) badge.textContent = allTransactions.length;
    };

    // Update item chart.
    function updateItemChart() {
        var counts = { in_stock: 0, low_stock: 0, out_of_stock: 0 };
        // Process each item.
        itemsFilteredData.forEach(function (item) { counts[stockState(item)] += 1; });
        // Run this step if the by id check matches.
        if (byId('inStockKpi')) byId('inStockKpi').textContent = counts.in_stock;
        // Run this step if the by id check matches.
        if (byId('lowStockKpi')) byId('lowStockKpi').textContent = counts.low_stock;
        // Run this step if the by id check matches.
        if (byId('outOfStockKpi')) byId('outOfStockKpi').textContent = counts.out_of_stock;
    }

    // Update transaction chart.
    function updateTransactionChart() {
        var dates = new Map();
        // Process each row.
        filteredData.forEach(function (row) {
            var date = row.transaction_date || 'Unknown date';
            // Run this step if the has is missing or false.
            if (!dates.has(date)) dates.set(date, { inbound: 0, outbound: 0 });
            var bucket = dates.get(date);
            // Run this step if the transaction type equals IN.
            if (row.transaction_type === 'IN') bucket.inbound += numeric(row.quantity);
            // Run this step if the transaction type equals OUT.
            if (row.transaction_type === 'OUT') bucket.outbound += numeric(row.quantity);
        });
        // Run the sort action.
        var pairs = Array.from(dates).sort(function (a, b) { return a[0].localeCompare(b[0]); }).slice(-14);
        // Build a value for each pair.
        var labels = pairs.map(function (pair) { return pair[0]; });
        // Build a value for each pair.
        var inbound = pairs.map(function (pair) { return pair[1].inbound; });
        // Build a value for each pair.
        var outbound = pairs.map(function (pair) { return pair[1].outbound; });
        var host = byId('inventoryMovementChart');
        // Return early if the host is missing or false.
        if (!host) return;
        var canvas = byId('inventoryMovementChartCanvas');
        // Run this step if the chart equals undefined or the canvas is missing or false.
        if (typeof Chart === 'undefined' || !canvas) {
            var entries = [];
            // Process each pair.
            pairs.forEach(function (pair) {
                entries.push({ label: pair[0] + ' IN', value: pair[1].inbound, className: 'is-success' });
                entries.push({ label: pair[0] + ' OUT', value: pair[1].outbound, className: 'is-danger' });
            });
            // Keep the matching entry items.
            renderBars(host.id, entries.filter(function (entry) { return entry.value > 0; }), function (value) { return numeric(value).toLocaleString(); });
            return;
        }
        // Run this step if the movement chart is set.
        if (movementChart) movementChart.destroy();
        movementChart = new Chart(canvas, {
            type: 'bar',
            data: { labels: labels, datasets: [
                { label: 'IN', data: inbound, backgroundColor: 'rgba(46, 125, 50, .78)', borderColor: '#2e7d32', borderWidth: 1, borderRadius: 4 },
                { label: 'OUT', data: outbound, backgroundColor: 'rgba(211, 47, 47, .78)', borderColor: '#d32f2f', borderWidth: 1, borderRadius: 4 }
            ] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (context) { return context.dataset.label + ': ' + numeric(context.raw).toLocaleString(); } } } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }

    window.loadInventoryItems = function () {
        // Get the json.
        function json(response, message) {
            // Run this step if the response ok is missing or false.
            if (!response.ok) throw new Error(message);
            return response.json();
        }
        // Use the result when this request finishes.
        return Promise.all([
            fetch('/api/inventory', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (response) { return json(response, 'Inventory items could not be loaded.'); }),
            fetch('/api/inventory/transactions', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (response) { return json(response, 'Inventory transactions could not be loaded.'); })
        ]).then(function (results) {
            inventoryItems = results[0].success ? results[0].data || [] : [];
            itemsData = inventoryItems;
            allTransactions = results[1].success ? results[1].data || [] : [];
            populateTransactionItemSelect();
            populateTransactionFilterOptions();
            filterItemsTable();
            filterTable();
        }).catch(function (error) {
            inventoryItems = [];
            itemsData = [];
            itemsFilteredData = [];
            allTransactions = [];
            filteredData = [];
            byId('itemsTableBody').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:#d32f2f;">Items could not be loaded. Please try again.</td></tr>';
            byId('inventoryTableBody').innerHTML = '<tr><td colspan="11" style="text-align:center;padding:20px;color:#d32f2f;">Transactions could not be loaded. Please try again.</td></tr>';
            // Run this step if the by id check matches.
            if (byId('inventoryStockChart')) byId('inventoryStockChart').innerHTML = '<div class="insight-error">Inventory analytics could not be loaded.</div>';
            // Run this step if the by id check matches.
            if (byId('inventoryMovementChart')) byId('inventoryMovementChart').innerHTML = '<div class="insight-error">Movement analytics could not be loaded.</div>';
            updateStats();
            // Run this step if the show error equals function.
            if (typeof showError === 'function') showError(error.message);
        });
    };

    window.filterItemsTable = function () {
        var search = (byId('itemsSearchInput').value || '').toLocaleLowerCase().trim();
        var category = byId('itemsCategoryFilter').value;
        var supplier = byId('itemsSupplierFilter').value;
        var state = byId('itemsStockFilter') ? byId('itemsStockFilter').value : 'all';
        // Keep the matching item items.
        itemsFilteredData = itemsData.filter(function (item) {
            // Build a value for each value.
            var haystack = [item.item_name, item.category, item.supplier, item.unit]
                .map(function (value) { return String(value || '').toLocaleLowerCase(); }).join(' ');
            return (!search || haystack.includes(search))
                && (category === 'all' || String(item.inventory_category_id) === category)
                && (supplier === 'all' || String(item.supplier_id) === supplier)
                && (state === 'all' || stockState(item) === state);
        });
        renderItemsPage(1);
        // Run this step if the active inventory tab equals items.
        if (activeInventoryTab === 'items') updateStats();
    };

    window.clearItemsFilters = function () {
        byId('itemsSearchInput').value = '';
        byId('itemsCategoryFilter').value = 'all';
        byId('itemsSupplierFilter').value = 'all';
        // Run this step if the by id check matches.
        if (byId('itemsStockFilter')) byId('itemsStockFilter').value = 'all';
        filterItemsTable();
    };

    window.filterTable = function () {
        var search = (byId('searchInput').value || '').toLocaleLowerCase().trim();
        var type = byId('typeFilter').value;
        var category = byId('transactionCategoryFilter') ? byId('transactionCategoryFilter').value : 'all';
        var project = byId('transactionProjectFilter') ? byId('transactionProjectFilter').value : 'all';
        var start = byId('startDate').value;
        var end = byId('endDate').value;
        var invalidRange = start && end && start > end;
        // Run this step if the invalid range is set.
        if (invalidRange) {
            filteredData = [];
        } else {
            // Keep the matching row items.
            filteredData = allTransactions.filter(function (row) {
                // Build a value for each value.
                var haystack = [row.project, row.item_name, row.category, row.supplier, row.unit, row.bar_code, row.description]
                    .map(function (value) { return String(value || '').toLocaleLowerCase(); }).join(' ');
                var date = row.transaction_date || '';
                return (!search || haystack.includes(search))
                    && (type === 'all' || row.transaction_type === type)
                    && (category === 'all' || String(row.inventory_category_id || '') === category)
                    && (project === 'all' || String(row.project_id || '') === project)
                    && (!start || date >= start)
                    && (!end || date <= end);
            });
        }
        renderTransactionPage(1);
        // Run this step if the active inventory tab equals transactions.
        if (activeInventoryTab === 'transactions') updateStats();
        return !invalidRange;
    };

    window.applyFilters = function () {
        // Run this step if the filter table is missing or false.
        if (!filterTable()) {
            // Run this step if the show error equals function.
            if (typeof showError === 'function') showError('The start date must be on or before the end date.');
            return;
        }
        // Run this step if the show success equals function.
        if (typeof showSuccess === 'function') showSuccess('Transaction filters applied.');
    };

    window.clearTransactionFilters = function () {
        byId('searchInput').value = '';
        byId('typeFilter').value = 'all';
        // Run this step if the by id check matches.
        if (byId('transactionCategoryFilter')) byId('transactionCategoryFilter').value = 'all';
        // Run this step if the by id check matches.
        if (byId('transactionProjectFilter')) byId('transactionProjectFilter').value = 'all';
        byId('startDate').value = '';
        byId('endDate').value = '';
        filterTable();
    };
})();
