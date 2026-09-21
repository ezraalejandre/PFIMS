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

    public function test_transaction_view_actions_hide_during_edit_and_keep_save_changes(): void
    {
        $view = $this->view();

        $this->assertStringContainsString('id="viewEditBtn" onclick="enableEditMode()">Edit</button>', $view);
        $this->assertStringContainsString('id="viewDeleteBtn" onclick="deleteTransaction()">Delete</button>', $view);
        $this->assertStringContainsString('id="viewSaveBtn"', $view);
        $this->assertStringContainsString('onclick="saveEdit()">Save Changes</button>', $view);
        $this->assertStringContainsString("viewSaveBtn').style.display = 'inline-block'", $view);
        $this->assertStringContainsString("viewSaveBtn').style.display = 'none'", $view);
        $this->assertStringContainsString("viewEditBtn').style.display = 'none'", $view);
        $this->assertStringContainsString("viewDeleteBtn').style.display = 'none'", $view);
    }
}
