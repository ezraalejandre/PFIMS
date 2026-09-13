<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DashboardLifecycleTest extends TestCase
{
    public function test_dashboard_table_render_waits_for_initial_data(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertIsString($view);
        $this->assertStringContainsString(
            "if (!state.data) return;\n                const size = Number(document.getElementById('pageSize').value);",
            $view
        );
    }
}
