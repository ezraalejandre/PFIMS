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
        $this->assertStringContainsString('data-pfims-wait-for-ready="true"', $view);
        $this->assertStringContainsString("document.dispatchEvent(new CustomEvent('pfims:page-ready'))", $view);
    }

    public function test_view_project_reuses_footer_styling_and_project_table_details_action(): void
    {
        $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');
        $projects = file_get_contents(dirname(__DIR__, 2).'/resources/views/projtracking.blade.php');
        $css = file_get_contents(dirname(__DIR__, 2).'/public/css/centralized-dashboard.css');

        $this->assertStringContainsString('class="btn-refresh dashboard-view-project"', $dashboard);
        $this->assertStringContainsString('.dashboard-view-project', $css);
        $this->assertStringContainsString('background: linear-gradient(135deg, var(--dashboard-accent), var(--dashboard-accent-dark));', $css);
        $this->assertStringNotContainsString('var tempRow = createProjectRow(targetProject);', $projects);
        $this->assertStringContainsString('renderProjectPage(Math.floor(targetIndex / projectPageSize) + 1);', $projects);
        $this->assertStringContainsString('window.setTimeout(function() {', $projects);
        $this->assertStringContainsString("document.querySelectorAll('#projectTableBody tr[data-project-id]')", $projects);
        $this->assertStringContainsString("targetRow && targetRow.querySelector('.pfims-row-action')", $projects);
        $this->assertStringContainsString('viewButton.click();', $projects);
    }
}
