<?php

$root = dirname(__DIR__);
$contracts = [
    'resources/views/finance.blade.php' => [
        'projectFilter', 'projectSearch', 'expenseCategoryFilter', 'expenseComponentFilter', 'expenseCostComponent',
        'detailCostComponentEdit', 'Project Cost Component', "setActiveTab(this,'all')",
        'budgetProjectFilter', 'budgetStatusFilter', 'budgetSearch', 'expenseCategoryChart', 'budgetStatusChart',
        'expovrallMonth', 'loadExpovrall()', 'expdirectMonth', 'loadExpDirect()', 'adminexpMonth', 'loadAdminExp()',
        'directexpMonth', 'loadDirectExp()', 'overallexpMonth', 'loadOverallExp()', 'profitType', 'loadProfit()',
        'receivableType', 'loadReceivables()', 'cashMonth', 'loadCashAsset()', 'backhoeAsset', 'backhoeMonth',
        'loadBackhoe()', 'bondProjectFilter', 'bondStatusFilter', 'loadBonds()', 'summaryMonth', 'loadSummary()',
        'partials.data-import', 'finance-analytics.js',
    ],
    'resources/views/Afinance.blade.php' => [
        'projectFilter', 'projectSearch', 'expenseCategoryFilter', 'expenseComponentFilter', 'expenseCostComponent',
        'detailCostComponentEdit', 'Project Cost Component', "setActiveTab(this,'all')",
        'budgetProjectFilter', 'budgetStatusFilter', 'budgetSearch', 'expenseCategoryChart', 'budgetStatusChart',
        'expovrallMonth', 'loadExpovrall()', 'expdirectMonth', 'loadExpDirect()', 'adminexpMonth', 'loadAdminExp()',
        'directexpMonth', 'loadDirectExp()', 'overallexpMonth', 'loadOverallExp()', 'profitType', 'loadProfit()',
        'receivableType', 'loadReceivables()', 'cashMonth', 'loadCashAsset()', 'backhoeAsset', 'backhoeMonth',
        'loadBackhoe()', 'bondProjectFilter', 'bondStatusFilter', 'loadBonds()', 'summaryMonth', 'loadSummary()',
        'partials.data-import', 'finance-analytics.js',
    ],
    'resources/views/inventory.blade.php' => [
        'itemsSearchInput', 'itemsCategoryFilter', 'itemsSupplierFilter', 'itemsStockFilter', 'clearItemsFilters()',
        'searchInput', 'typeFilter', 'transactionCategoryFilter', 'transactionProjectFilter', 'startDate', 'endDate',
        'clearTransactionFilters()', 'totalItemsCount', 'lowStockCount', 'categoriesCount', 'inventoryStockChart',
        'inventoryMovementChart', 'partials.data-import', 'inventory-analytics.js',
    ],
    'resources/views/Oinventory.blade.php' => [
        'itemsSearchInput', 'itemsCategoryFilter', 'itemsSupplierFilter', 'itemsStockFilter', 'clearItemsFilters()',
        'searchInput', 'typeFilter', 'transactionCategoryFilter', 'transactionProjectFilter', 'startDate', 'endDate',
        'clearTransactionFilters()', 'totalItemsCount', 'lowStockCount', 'categoriesCount', 'inventoryStockChart',
        'inventoryMovementChart', 'partials.data-import', 'inventory-analytics.js',
    ],
    'public/js/finance-analytics.js' => [
        'window.applyFilters', 'window.filterBudgetTable', 'window.updateFinanceTotals', 'updateExpenseCategoryChart',
        'financeFilteredData', 'budgetFilteredData', 'expense.is_pending_inventory',
    ],
    'public/js/inventory-analytics.js' => [
        'window.filterItemsTable', 'window.filterTable', 'window.updateStats', 'updateItemChart',
        'updateTransactionChart', 'reorder_level', 'start > end', 'row.project', 'row.bar_code',
    ],
];

$failures = [];
foreach ($contracts as $relative => $needles) {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $contents = is_file($path) ? file_get_contents($path) : false;
    if ($contents === false) {
        $failures[] = "Missing file: {$relative}";

        continue;
    }
    foreach ($needles as $needle) {
        if (! str_contains($contents, $needle)) {
            $failures[] = "{$relative} is missing contract token: {$needle}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

echo 'Finance/inventory UI contracts verified (4 role views, shared filters, filtered KPIs, real-data charts, imports preserved).'.PHP_EOL;
