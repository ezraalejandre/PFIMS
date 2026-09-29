import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const controls = {
    projectSearch: { value: '' }, projectFilter: { value: 'all' },
    expenseScopeFilter: { value: 'all' }, expenseRecordStatusFilter: { value: 'all' },
    expenseSourceFilter: { value: 'all' }, expenseCategoryFilter: { value: 'all' },
    expenseComponentFilter: { value: 'all' },
};
const context = {
    document: { getElementById: (id) => controls[id] || null, addEventListener: () => {} },
    currentReportTab: 'expenses', currentSearchTerm: '', currentProjectFilter: 'all',
    financeCategories: [], budgetData: [],
    financeExpenses: [
        { fin_expense_id: 1, inventory_transaction_id: 10, amount: 100 },
        { fin_expense_id: 2, entry_kind: 'inventory_purchase', amount: 200 },
        { fin_expense_id: 3, inventory_transaction_id: null, amount: 300 },
        { fin_expense_id: 4, inventory_transaction_id: 11, is_pending_inventory: true, amount: null },
    ],
    financeFilteredData: [], filterByPeriod: (expenses) => expenses,
    renderFinancePage: () => {}, formatCurrency: String,
};
context.window = context;
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/finance-analytics.js'), 'utf8'), context);

for (const [source, expectedIds] of [
    ['inventory', [1, 2, 4]], ['manual', [3]], ['all', [1, 2, 3, 4]],
]) {
    controls.expenseSourceFilter.value = source;
    context.applyFilters();
    assert.deepEqual(Array.from(context.financeFilteredData, (row) => row.fin_expense_id), expectedIds);
}
