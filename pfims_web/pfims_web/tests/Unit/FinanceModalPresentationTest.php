<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FinanceModalPresentationTest extends TestCase
{
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

    public function test_shared_row_edit_action_detects_finance_modals_without_active_class(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/public/js/pfims-system-ui.js');

        $this->assertIsString($script);
        $this->assertStringContainsString(
            "document.querySelectorAll('.modal, .modal-overlay, [role=\"dialog\"]')",
            $script
        );
        $this->assertStringNotContainsString(
            "document.querySelectorAll('.modal.active, .modal-overlay.active, [role=\"dialog\"]')",
            $script
        );
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
                '/<div id="' . preg_quote($modalId, '/') . '".*?<div class="modal-footer".*?id="' . preg_quote($deleteId, '/') . '".*?id="' . preg_quote($editId, '/') . '".*?id="' . preg_quote($saveId, '/') . '"/s',
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
    }

    public function test_equipment_repair_report_is_not_presented_as_editable_record(): void
    {
        $view = $this->financeView();

        $this->assertStringContainsString("'/reports/repair-total?period='", $view);
        $this->assertStringNotContainsString('openRepairModal(this)', $view);
        $this->assertStringNotContainsString('function openRepairModal', $view);
    }
}
