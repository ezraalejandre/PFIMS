(function () {
    'use strict';

    var activeInventoryTab = 'items';

    function byId(id) { return document.getElementById(id); }
    function numeric(value) {
        var parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }
    function stockState(item) {
        var stock = numeric(item.current_stock);
        var threshold = numeric(item.reorder_level);
        if (stock <= 0) return 'out_of_stock';
        return stock <= threshold ? 'low_stock' : 'in_stock';
    }
    function addOption(select, value, label) {
        if (!select) return;
        var option = document.createElement('option');
        option.value = String(value);
        option.textContent = label;
        select.appendChild(option);
    }
    function setMetric(labelId, label, valueId, value, subId, sub) {
        if (byId(labelId)) byId(labelId).textContent = label;
        if (byId(valueId)) byId(valueId).textContent = value;
        if (byId(subId)) byId(subId).textContent = sub;
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
        document.querySelectorAll('.inventory-tabs .tab').forEach(function (entry) {
            entry.classList.toggle('active', entry === element);
        });
        byId('tabItems').classList.toggle('active', tab === 'items');
        byId('tabTransactions').classList.toggle('active', tab === 'transactions');
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
        [category, transactionCategory].forEach(function (select) {
            if (!select) return;
            var selected = select.value || 'all';
            select.replaceChildren();
            addOption(select, 'all', 'All Categories');
            (lookupData.categories || []).forEach(function (row) {
                addOption(select, row.inventory_category_id, row.inventory_category_name);
            });
            select.value = Array.from(select.options).some(function (option) { return option.value === selected; }) ? selected : 'all';
        });
        if (supplier) {
            var selectedSupplier = supplier.value || 'all';
            supplier.replaceChildren();
            addOption(supplier, 'all', 'All Suppliers');
            (lookupData.suppliers || []).forEach(function (row) { addOption(supplier, row.supplier_id, row.supplier_name); });
            supplier.value = Array.from(supplier.options).some(function (option) { return option.value === selectedSupplier; }) ? selectedSupplier : 'all';
        }
    };

    function populateTransactionFilterOptions() {
        var project = byId('transactionProjectFilter');
        if (!project) return;
        var selected = project.value || 'all';
        var projects = new Map();
        allTransactions.forEach(function (row) {
            if (row.project_id && row.project) projects.set(String(row.project_id), row.project);
        });
        project.replaceChildren();
        addOption(project, 'all', 'All Projects');
        Array.from(projects).sort(function (a, b) { return a[1].localeCompare(b[1]); }).forEach(function (entry) {
            addOption(project, entry[0], entry[1]);
        });
        project.value = Array.from(project.options).some(function (option) { return option.value === selected; }) ? selected : 'all';
    }

    window.updateStats = function () {
        if (activeInventoryTab === 'transactions') {
            var inbound = filteredData.filter(function (row) { return row.transaction_type === 'IN'; })
                .reduce(function (sum, row) { return sum + numeric(row.quantity); }, 0);
            var outbound = filteredData.filter(function (row) { return row.transaction_type === 'OUT'; })
                .reduce(function (sum, row) { return sum + numeric(row.quantity); }, 0);
            setMetric('totalItemsLabel', 'Transactions', 'totalItemsCount', filteredData.length, 'totalItemsSub', 'Matching current transaction filters');
            setMetric('lowStockLabel', 'Stock In Quantity', 'lowStockCount', inbound.toLocaleString(), 'lowStockSub', 'Total inbound units in view');
            setMetric('categoriesLabel', 'Stock Out Quantity', 'categoriesCount', outbound.toLocaleString(), 'categoriesSub', 'Total outbound units in view');
            updateTransactionChart();
        } else {
            var low = itemsFilteredData.filter(function (item) { return stockState(item) !== 'in_stock'; });
            var categories = new Set(itemsFilteredData.map(function (item) { return item.category || 'Uncategorized'; }));
            setMetric('totalItemsLabel', 'Total Items', 'totalItemsCount', itemsFilteredData.length, 'totalItemsSub', 'Matching current item filters');
            setMetric('lowStockLabel', 'Needs Restocking', 'lowStockCount', low.length, 'lowStockSub', low.length ? 'At or below each item reorder level' : 'All matching items well stocked');
            setMetric('categoriesLabel', 'Categories', 'categoriesCount', categories.size, 'categoriesSub', 'Categories represented in view');
            updateItemChart();
        }
        var badge = byId('transactionBadge');
        if (badge) badge.textContent = allTransactions.length;
    };

    function updateItemChart() {
        var counts = { in_stock: 0, low_stock: 0, out_of_stock: 0 };
        itemsFilteredData.forEach(function (item) { counts[stockState(item)] += 1; });
        renderBars('inventoryStockChart', [
            { label: 'In Stock', value: counts.in_stock, className: 'is-success' },
            { label: 'Low Stock', value: counts.low_stock, className: 'is-warning' },
            { label: 'Out of Stock', value: counts.out_of_stock, className: 'is-danger' }
        ].filter(function (entry) { return entry.value > 0; }), function (value) { return String(value); });
    }

    function updateTransactionChart() {
        var dates = new Map();
        filteredData.forEach(function (row) {
            var date = row.transaction_date || 'Unknown date';
            if (!dates.has(date)) dates.set(date, { inbound: 0, outbound: 0 });
            var bucket = dates.get(date);
            if (row.transaction_type === 'IN') bucket.inbound += numeric(row.quantity);
            if (row.transaction_type === 'OUT') bucket.outbound += numeric(row.quantity);
        });
        var entries = [];
        Array.from(dates).sort(function (a, b) { return b[0].localeCompare(a[0]); }).slice(0, 10).reverse().forEach(function (pair) {
            entries.push({ label: pair[0] + ' IN', value: pair[1].inbound, className: 'is-success' });
            entries.push({ label: pair[0] + ' OUT', value: pair[1].outbound, className: 'is-danger' });
        });
        renderBars('inventoryMovementChart', entries.filter(function (entry) { return entry.value > 0; }), function (value) {
            return numeric(value).toLocaleString();
        });
    }

    window.loadInventoryItems = function () {
        function json(response, message) {
            if (!response.ok) throw new Error(message);
            return response.json();
        }
        Promise.all([
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
            if (byId('inventoryStockChart')) byId('inventoryStockChart').innerHTML = '<div class="insight-error">Inventory analytics could not be loaded.</div>';
            if (byId('inventoryMovementChart')) byId('inventoryMovementChart').innerHTML = '<div class="insight-error">Movement analytics could not be loaded.</div>';
            updateStats();
            if (typeof showError === 'function') showError(error.message);
        });
    };

    window.filterItemsTable = function () {
        var search = (byId('itemsSearchInput').value || '').toLocaleLowerCase().trim();
        var category = byId('itemsCategoryFilter').value;
        var supplier = byId('itemsSupplierFilter').value;
        var state = byId('itemsStockFilter') ? byId('itemsStockFilter').value : 'all';
        itemsFilteredData = itemsData.filter(function (item) {
            var haystack = [item.item_name, item.category, item.supplier, item.unit]
                .map(function (value) { return String(value || '').toLocaleLowerCase(); }).join(' ');
            return (!search || haystack.includes(search))
                && (category === 'all' || String(item.inventory_category_id) === category)
                && (supplier === 'all' || String(item.supplier_id) === supplier)
                && (state === 'all' || stockState(item) === state);
        });
        renderItemsPage(1);
        if (activeInventoryTab === 'items') updateStats();
    };

    window.clearItemsFilters = function () {
        byId('itemsSearchInput').value = '';
        byId('itemsCategoryFilter').value = 'all';
        byId('itemsSupplierFilter').value = 'all';
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
        if (invalidRange) {
            filteredData = [];
        } else {
            filteredData = allTransactions.filter(function (row) {
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
        if (activeInventoryTab === 'transactions') updateStats();
        return !invalidRange;
    };

    window.applyFilters = function () {
        if (!filterTable()) {
            if (typeof showError === 'function') showError('The start date must be on or before the end date.');
            return;
        }
        if (typeof showSuccess === 'function') showSuccess('Transaction filters applied.');
    };

    window.clearTransactionFilters = function () {
        byId('searchInput').value = '';
        byId('typeFilter').value = 'all';
        if (byId('transactionCategoryFilter')) byId('transactionCategoryFilter').value = 'all';
        if (byId('transactionProjectFilter')) byId('transactionProjectFilter').value = 'all';
        byId('startDate').value = '';
        byId('endDate').value = '';
        filterTable();
    };
})();
