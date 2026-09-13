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
        $this->assertStringContainsString("viewModalTitle').textContent = 'Transaction Details'", $view);
        $this->assertStringContainsString("viewCancelBtn').textContent = 'Close'", $view);
    }

    public function test_transaction_modal_edit_footer_contains_delete_and_save_changes(): void
    {
        $view = $this->view();

        $this->assertStringContainsString('id="viewDeleteBtn"', $view);
        $this->assertStringContainsString('onclick="deleteTransaction()">Delete</button>', $view);
        $this->assertStringContainsString('id="viewSaveBtn"', $view);
        $this->assertStringContainsString('onclick="saveEdit()">Save Changes</button>', $view);
        $this->assertStringContainsString("viewDeleteBtn').style.display = 'inline-block'", $view);
        $this->assertStringContainsString("viewSaveBtn').style.display = 'inline-block'", $view);
        $this->assertStringContainsString("viewDeleteBtn').style.display = 'none'", $view);
        $this->assertStringContainsString("viewSaveBtn').style.display = 'none'", $view);
    }
}
