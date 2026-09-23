<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InventoryTransactionModalPresentationTest extends TestCase
{
    private function view(): string
    {
        return file_get_contents(__DIR__ . '/../../resources/views/inventory.blade.php');
    }

    public function test_transaction_modal_restores_view_title_and_close_label(): void
    {
        $view = $this->view();

        $this->assertStringContainsString('id="viewModalTitle">Transaction Details</h2>', $view);
        $this->assertStringContainsString('id="viewCancelBtn" onclick="closeViewModal()">Close</button>', $view);
        $this->assertStringContainsString("viewModalTitle').textContent = 'Edit Transaction'", $view);
        $this->assertStringContainsString("viewCancelBtn').textContent = 'Cancel'", $view);
        $this->assertStringContainsString("viewCancelBtn').style.display = 'inline-block'", $view);
        $this->assertStringContainsString("viewModalTitle').textContent = 'Transaction Details'", $view);
        $this->assertStringContainsString("viewCancelBtn').textContent = 'Close'", $view);
    }

    public function test_transaction_modal_edit_footer_contains_only_cancel_and_save_changes(): void
    {
        $view = $this->view();

        $this->assertStringNotContainsString('<table data-pfims-standard-actions="off">', $view);
        $this->assertStringNotContainsString('id="viewDeleteBtn"', $view);
        $this->assertStringContainsString('id="viewSaveBtn"', $view);
        $this->assertStringContainsString('onclick="saveEdit()">Save Changes</button>', $view);
        $this->assertStringContainsString("viewSaveBtn').style.display = 'inline-block'", $view);
        $this->assertStringContainsString("if (/^(Edit|Delete)$/.test((button.textContent || '').trim())) button.style.display = 'none';", $view);
        $this->assertStringContainsString("viewSaveBtn').style.display = 'none'", $view);
    }

    public function test_stock_out_project_requirement_is_visible_and_actionable(): void
    {
        $view = $this->view();

        $stockOutPosition = strpos($view, 'name="transactionType" value="OUT"');
        $projectPosition = strpos($view, 'id="transactionProjectGroup"');
        $this->assertNotFalse($stockOutPosition);
        $this->assertNotFalse($projectPosition);
        $this->assertGreaterThan($stockOutPosition, $projectPosition);
        $this->assertStringContainsString('id="transactionProjectError"', $view);
        $this->assertStringContainsString("showTransactionProjectError('Please select a project for the Stock Out transaction.')", $view);
        $this->assertStringContainsString("select.scrollIntoView({ behavior: 'smooth', block: 'nearest' });", $view);
        $this->assertStringContainsString("if (!res.ok) throw new Error('Unable to load projects');", $view);
    }

    public function test_transaction_delete_confirmation_allows_deletion_and_warns_about_recalculation(): void
    {
        $view = $this->view();

        $this->assertStringContainsString('Stock will be recalculated. This action cannot be undone.', $view);
        $this->assertStringNotContainsString('Inventory transactions are part of the Finance audit trail and cannot be deleted.', $view);
    }

    public function test_inventory_transaction_deep_link_opens_the_requested_details_modal(): void
    {
        $view = $this->view();

        $this->assertStringContainsString("get('transaction_id')", $view);
        $this->assertStringContainsString("window.sessionStorage.getItem('pfims_inventory_transaction_to_open')", $view);
        $this->assertStringContainsString('String(transaction.inventory_transaction_id) === String(transactionId)', $view);
        $this->assertStringContainsString("switchInventoryTab(null, 'transactions');", $view);
        $this->assertStringContainsString('filteredData = allTransactions;', $view);
        $this->assertStringContainsString('Math.floor(matchIndex / inventoryPageSize) + 1', $view);
        $this->assertStringContainsString('openViewModal(row);', $view);
        $this->assertStringContainsString("document.getElementById('viewTransactionId').value !== String(transactionId)", $view);
        $this->assertStringContainsString("window.sessionStorage.removeItem('pfims_inventory_transaction_to_open')", $view);
    }
}
