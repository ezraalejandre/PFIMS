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
                '/<div id="' . preg_quote($id, '/') . '" class="modal-overlay pfims-add-modal">/',
                $view,
                "{$id} must use the shared Finance add-modal class."
            );
        }
    }

    public function test_finance_add_modal_css_matches_inventory_add_transaction_container_contract(): void
    {
        $css = file_get_contents(__DIR__ . '/../../public/css/finance.css');

        $this->assertStringContainsString('.pfims-add-modal .modal-container', $css);
        $this->assertMatchesRegularExpression('/\.pfims-add-modal \.modal-container \{.*?width: 600px;.*?max-width: 95%;.*?max-height: 90vh;.*?border-radius: 16px;.*?padding: 30px 35px;/s', $css);
        $this->assertStringContainsString('.pfims-add-modal .modal-body', $css);
        $this->assertStringContainsString('.pfims-add-modal .form-group textarea', $css);
    }
}
