<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MlSectionInitializationTest extends TestCase
{
    public function test_ml_initialization_loads_only_data_allowed_by_the_selected_role_and_section(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/ml-dashboard-test.blade.php');

        $this->assertIsString($view);
        $this->assertStringContainsString("if (currentPortal === 'admin') loadDashboard();", $view);
        $this->assertStringContainsString("targetSection === 'costPredictionSection' && currentPortal === 'admin'", $view);
        $this->assertStringContainsString("targetSection === 'materialProjectionSection' && ['admin', 'operations'].includes(currentPortal)", $view);
        $this->assertStringContainsString("targetSection === 'budgetComparisonSection' && ['admin', 'accounting'].includes(currentPortal)", $view);
    }
}
