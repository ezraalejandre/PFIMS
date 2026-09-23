<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FinanceModalPresentationTest extends TestCase
{
    public function test_finance_add_actions_render_in_the_page_header_for_their_relevant_tabs(): void
    {
        $view = $this->financeView();

        $this->assertStringContainsString('id="financeHeaderActions"', $view);
        $this->assertStringContainsString("in_array(\$financeTab, ['expenses', 'budgets'], true)", $view);
        foreach ([
            ['profit', 'openAddContractModal()', '+ Add Contract'],
            ['receivables', 'openAddReceivableModal()', '+ Add Entry'],
            ['cash', 'openAddCashModal()', '+ Add Cash Position'],
            ['backhoe', 'openAddBackhoeExpenseModal()', '+ Add Expense'],
            ['backhoe', 'openAddBackhoeRentalModal()', '+ Add Rental Income'],
            ['bonds', 'openAddBondModal()', '+ Add Bond'],
        ] as [$tab, $handler, $label]) {
            $this->assertMatchesRegularExpression(
                '/<div class="finance-header-action-group" data-finance-tabs="' . $tab . '".*?<button onclick="' . preg_quote($handler, '/') . '".*?>' . preg_quote($label, '/') . '<\/button>/s',
                $view
            );
            $this->assertSame(1, substr_count($view, 'onclick="' . $handler . '"'));
        }
        $this->assertStringContainsString("headerActions.querySelectorAll('[data-finance-tabs]')", $view);
        $this->assertStringContainsString('headerActions.hidden = !hasActiveHeaderAction;', $view);

        $css = file_get_contents(__DIR__ . '/../../public/css/finance.css');
        $this->assertStringContainsString('.page-header .finance-header-action-group', $css);
    }

    private function financeView(): string
    {
        return file_get_contents(__DIR__ . '/../../resources/views/finance.blade.php');
    }

    public function test_detail_edit_buttons_start_hidden_when_edit_mode_is_enabled_and_return_in_view_mode(): void
    {
        $view = $this->financeView();

        foreach ([
            'isBudgetEditMode',
            'isEditMode',
            'isReceivableEditMode',
            'isCashEditMode',
            'isBondEditMode',
        ] as $mode) {
            $this->assertMatchesRegularExpression(
                '/if \(' . preg_quote($mode, '/') . '\) \{.*?editBtn\.style\.display = \'none\';.*?\} else \{.*?editBtn\.style\.display = \'inline-block\';/s',
                $view,
                "{$mode} must hide Edit while editing and show it in view mode."
            );
        }
    }

    public function test_all_finance_add_modals_use_the_shared_add_modal_class(): void
    {
        $view = $this->financeView();
        $modalIds = [
            'inventoryExpenseModal',
            'addExpenseModal',
            'addBudgetModal',
            'addContractModal',
            'addReceivableModal',
            'addCashModal',
            'addRepairModal',
            'addBackhoeExpenseModal',
            'addBackhoeRentalModal',
            'addBondModal',
        ];

        foreach ($modalIds as $id) {
            $this->assertMatchesRegularExpression(
                '/<div id="' . preg_quote($id, '/') . '" class="modal-overlay[^\"]*pfims-add-modal[^\"]*">/',
                $view,
                "{$id} must use the shared Finance add-modal class."
            );
        }
    }

    public function test_finance_add_modal_css_matches_inventory_add_transaction_container_contract(): void
    {
        $css = file_get_contents(__DIR__ . '/../../public/css/finance.css');

        $this->assertStringContainsString('.pfims-add-modal .modal-container', $css);
        $this->assertMatchesRegularExpression('/\.pfims-add-modal \.modal-container \{.*?width: 600px;.*?max-width: 95%;.*?max-height: 90vh;.*?border-radius: 20px;.*?padding: 30px 35px;/s', $css);
        $this->assertStringContainsString('.pfims-add-modal .modal-body', $css);
        $this->assertStringContainsString('.pfims-add-modal .form-group textarea', $css);
    }

    public function test_pending_stock_in_add_details_modal_contains_only_project_and_amount_inputs(): void
    {
        $view = $this->financeView();
        preg_match('/<div id="inventoryExpenseModal".*?<\/div>\s*<\/div>\s*<\/div>/s', $view, $matches);
        $modal = $matches[0] ?? '';

        $this->assertNotSame('', $modal, 'The pending stock-in Add Details modal must exist.');
        $this->assertStringContainsString('<h2>Edit Stock-In Expense</h2>', $modal);
        $this->assertStringContainsString('id="inventoryExpenseProject"', $modal);
        $this->assertStringContainsString('>Project Name <span class="required">*</span>', $modal);
        $this->assertStringContainsString('id="inventoryExpenseAmount"', $modal);
        $this->assertSame(2, substr_count($modal, 'class="form-group"'));
        $this->assertStringContainsString("'inventoryExpenseProject'", $view);
        $this->assertStringContainsString('JSON.stringify({ project_id: Number(projectId), amount: amount })', $view);
    }

    public function test_expense_status_filter_and_pending_actions_cover_incomplete_records(): void
    {
        $view = $this->financeView();
        $analytics = file_get_contents(__DIR__ . '/../../public/js/finance-analytics.js');

        foreach ([
            'id="expenseRecordStatusFilter"',
            'id="expenseSourceFilter"',
            '>All Records</option>',
            '>Missing Amount</option>',
            '>No Project</option>',
            '>Missing Amount &amp; Project</option>',
            "document.getElementById('expenseRecordStatusFilter').value = 'all';",
            "var recordStatusFilter = document.getElementById('expenseRecordStatusFilter').value;",
            "var sourceFilter = document.getElementById('expenseSourceFilter').value;",
            "sourceFilter === 'inventory' ? isInventoryExpense : !isInventoryExpense",
            'expense.is_pending_inventory === true',
            'class="pfims-row-action"',
            'src="/images/view.jpg"',
            "row.dataset.isPendingInventory === 'true'",
            "row.dataset.isInventoryExpense === 'true'",
            'id="detailViewInventoryBtn"',
            'class="expense-detail-footer-actions"',
            'onclick="openLinkedInventoryTransaction(event)"',
            'event.stopPropagation();',
            "window.sessionStorage.setItem('pfims_inventory_transaction_to_open', transactionId)",
            "openInventoryExpenseModal(transactionId)",
            "window.location.assign('/inventory?section=transactions&transaction_id='",
        ] as $contract) {
            $this->assertStringContainsString($contract, $view);
        }

        foreach ([
            "byId('expenseRecordStatusFilter').value = 'all'",
            "byId('expenseSourceFilter').value = 'all'",
            "var recordStatus = (byId('expenseRecordStatusFilter')",
            "var source = (byId('expenseSourceFilter')",
            "recordStatus === 'missing_amount' && isMissingAmount",
            "recordStatus === 'no_project' && !hasProject",
            "recordStatus === 'missing_amount_and_project' && isMissingAmount && !hasProject",
            "source === 'inventory' && isInventoryExpense",
            "source === 'manual' && !isInventoryExpense",
            '&& matchesRecordStatus',
            '&& matchesSource',
        ] as $contract) {
            $this->assertStringContainsString($contract, $analytics);
        }
    }

    public function test_construction_supply_expense_collects_inventory_stock_in_details(): void
    {
        $view = $this->financeView();

        foreach ([
            'id="expenseInventoryFields"',
            'id="expenseInventoryItem"',
            'id="expenseInventoryQuantity"',
            'id="expenseInventoryBarCode"',
            "categoryCode === 'CONST_SUPPLY'",
            "expenseFormData.append('inventory_item_id', inventoryItemId)",
            "expenseFormData.append('inventory_quantity', inventoryQuantity)",
            "apiFetch('/inventory-items-list')",
            "'Purchased ' + displayQuantity",
        ] as $contract) {
            $this->assertStringContainsString($contract, $view);
        }
    }

    public function test_finance_add_flow_uses_inventory_numbered_stepper_and_navigation_contract(): void
    {
        $script = file_get_contents(__DIR__ . '/../../public/js/finance-review-flow.js');
        $css = file_get_contents(__DIR__ . '/../../public/css/finance.css');

        $this->assertStringContainsString("stepper.className = 'step-indicator pfims-finance-stepper'", $script);
        $this->assertStringContainsString('<span class="step-number">1</span> Details', $script);
        $this->assertStringContainsString('<span class="step-number">2</span> Review', $script);
        $this->assertStringContainsString("back.className = 'btn-back pfims-review-back'", $script);
        $this->assertStringContainsString("save.textContent = review ? flow.originalSaveText : 'Continue'", $script);
        $this->assertStringContainsString("if (!isAddMode(modal)) return;", $script);
        $this->assertStringNotContainsString('pfims-review-tabs', $script);
        $this->assertStringContainsString('.pfims-add-modal .step-indicator', $css);
        $this->assertStringContainsString('.pfims-add-modal .step-indicator .step.completed', $css);
        $this->assertStringContainsString('.pfims-add-modal .pfims-finance-stepper[hidden]', $css);
    }

    public function test_shared_row_edit_action_detects_finance_modals_without_active_class(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/public/js/pfims-system-ui.js');

        $this->assertIsString($script);
        $this->assertStringContainsString(
            "document.querySelectorAll('.modal, .modal-overlay, [role=\"dialog\"]')",
            $script
        );
        $this->assertStringContainsString(
            "modal.matches('.modal.active, .modal-overlay.active')",
            $script
        );
        $this->assertStringNotContainsString(
            "document.querySelectorAll('.modal.active, .modal-overlay.active, [role=\"dialog\"]')",
            $script
        );
        $this->assertStringContainsString('window.PFIMS_ROW_EDIT_MODE = !!editMode;', $script);
        $this->assertStringContainsString('window.PFIMS_ROW_EDIT_MODE === true', $this->financeView());
        $this->assertStringContainsString('if (!isEditMode && currentDetailRow === row) toggleDetailEdit();', $this->financeView());
    }

    public function test_finance_editable_detail_modals_have_inventory_style_edit_footer_controls(): void
    {
        $view = $this->financeView();

        foreach ([
            ['expenseDetailModal', 'detailEditBtn', 'detailDeleteBtn', 'detailSaveBtn'],
            ['budgetDetailModal', 'budgetDetailEditBtn', 'budgetDetailDeleteBtn', 'budgetDetailSaveBtn'],
            ['receivableDetailModal', 'receivableDetailEditBtn', 'receivableDetailDeleteBtn', 'receivableDetailSaveBtn'],
            ['cashDetailModal', 'cashDetailEditBtn', 'cashDetailDeleteBtn', 'cashDetailSaveBtn'],
            ['bondDetailModal', 'bondDetailEditBtn', 'bondDetailDeleteBtn', 'bondDetailSaveBtn'],
        ] as [$modalId, $editId, $deleteId, $saveId]) {
            $this->assertMatchesRegularExpression(
                '/<div id="' . preg_quote($modalId, '/') . '".*?<div class="[^\"]*modal-footer[^\"]*".*?id="' . preg_quote($deleteId, '/') . '".*?id="' . preg_quote($editId, '/') . '".*?id="' . preg_quote($saveId, '/') . '"/s',
                $view,
                "{$modalId} must expose Edit, Delete, and Save footer controls."
            );
        }

        $this->assertStringContainsString('onclick="enableContractEdit()"', $view);
        $this->assertStringContainsString('onclick="saveContract()"', $view);
        foreach (['expenseDetailModal', 'budgetDetailModal', 'receivableDetailModal', 'cashDetailModal', 'bondDetailModal', 'addContractModal'] as $id) {
            $this->assertMatchesRegularExpression('/<div id="' . preg_quote($id, '/') . '" class="modal-overlay[^\"]*finance-edit-modal/', $view);
        }
        $this->assertStringContainsString("textContent = 'Edit Expense'", $view);
        $this->assertStringContainsString("textContent = 'Edit Budget'", $view);
        $this->assertStringContainsString("textContent = 'Edit AR/AP Entry'", $view);
        $this->assertStringContainsString("textContent = 'Edit Cash Position'", $view);
        $this->assertStringContainsString("textContent = 'Edit Construction Bond'", $view);
        $this->assertStringContainsString("textContent = 'Save Changes'", $view);
        $this->assertStringContainsString("classList.add('is-editing')", $view);
        $this->assertStringContainsString("classList.remove('is-editing')", $view);

        $css = file_get_contents(__DIR__ . '/../../public/css/finance.css');
        $this->assertStringContainsString('#expenseDetailModal .expense-detail-footer-actions', $css);
        $this->assertStringContainsString('justify-content: flex-end;', $css);
        $this->assertStringContainsString('flex-wrap: nowrap;', $css);
    }

    public function test_equipment_repair_report_is_not_presented_as_editable_record(): void
    {
        $view = $this->financeView();

        $this->assertStringContainsString("'/reports/repair-total?period='", $view);
        $this->assertStringNotContainsString('openRepairModal(this)', $view);
        $this->assertStringNotContainsString('function openRepairModal', $view);
    }

    public function test_contract_ar_ap_and_bond_searches_reuse_the_expense_filter_pattern(): void
    {
        $view = $this->financeView();

        foreach ([
            ['profitSearch', 'clearProfitSearch()', 'profitType'],
            ['receivableSearch', 'clearReceivableSearch()', 'receivableType'],
            ['bondSearch', 'clearBondSearch()', 'bondProjectFilter'],
        ] as [$searchId, $clearHandler, $filterId]) {
            $this->assertMatchesRegularExpression(
                '/<div class="filter-row">\s*<input type="search" id="' . $searchId . '" class="project-filter".*?id="' . $filterId . '".*?class="btn-clear-search" onclick="' . preg_quote($clearHandler, '/') . '"/s',
                $view
            );
        }

        $this->assertStringContainsString('function clearProfitSearch()', $view);
        $this->assertStringContainsString('function clearReceivableSearch()', $view);
        $this->assertStringContainsString('function clearBondSearch()', $view);
    }

    public function test_ar_ap_and_bond_project_identity_fields_are_editable_and_saved(): void
    {
        $view = $this->financeView();

        foreach ([
            "var editableFields = ['receivableDetailTypeEdit', 'receivableDetailCounterpartyEdit', 'receivableDetailProjectEdit'",
            "entry_type: document.getElementById('receivableDetailTypeEdit').value",
            "project_id: document.getElementById('receivableDetailProjectEdit').value || null",
            "counterparty_name: document.getElementById('receivableDetailCounterpartyEdit').value.trim()",
            "var editableFields = ['bondDetailProjectEdit', 'bondDetailDateEdit'",
            "project_id: parseInt(document.getElementById('bondDetailProjectEdit').value)",
        ] as $contract) {
            $this->assertStringContainsString($contract, $view);
        }

        $this->assertStringNotContainsString("document.getElementById('receivableDetailTypeEdit').style.display = 'none';", $view);
        $this->assertStringNotContainsString("document.getElementById('bondDetailProjectEdit').style.display = 'none';", $view);
    }
}
