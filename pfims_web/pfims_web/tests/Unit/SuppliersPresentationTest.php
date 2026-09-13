<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SuppliersPresentationTest extends TestCase
{
    public function test_supplier_table_uses_an_em_dash_for_blank_contact_numbers(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/suppliers.blade.php');

        $this->assertIsString($view);
        $this->assertStringContainsString(
            "String(supplier.contact_number || '').trim() || '—'",
            $view
        );
    }
}
