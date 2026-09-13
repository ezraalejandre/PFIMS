<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class UiRefreshCssContractTest extends TestCase
{
    public function test_desktop_density_is_applied_once_and_breakpoint_is_preserved(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/public/css/ui-refresh.css');

        $this->assertIsString($css);
        $this->assertStringContainsString('--pfims-desktop-scale: 75%;', $css);
        $this->assertStringContainsString('@media (min-width: 1025px)', $css);
        $this->assertStringContainsString('zoom: var(--pfims-desktop-scale);', $css);
        $this->assertStringContainsString('@media (max-width: 1024px)', $css);
        $this->assertStringNotContainsString(':where(.main-content, main) { font-size: .75rem; }', $css);

        $finance = file_get_contents(dirname(__DIR__, 2).'/resources/views/finance.blade.php');
        $reports = file_get_contents(dirname(__DIR__, 2).'/resources/views/reports.blade.php');

        $this->assertIsString($finance);
        $this->assertIsString($reports);
        $this->assertStringNotContainsString('id="filePreviewImage" src=""', $finance);
        $this->assertStringNotContainsString('id="budgetFilePreviewImage" src=""', $finance);
        $this->assertStringContainsString('.reports-page .report-tabs .tab', $css);
        $this->assertStringContainsString('flex: 0 0 auto;', $css);
        $this->assertStringContainsString('body:not(.landing-page) .sidebar li.nav-parent.is-open > .nav-dropdown a', $css);
        $this->assertStringContainsString('font-size: .76rem !important;', $css);
        $this->assertStringContainsString('body:not(.landing-page) .sidebar li.nav-parent > .nav-dropdown', $css);
        $this->assertStringContainsString('html.sidebar-collapsed .sidebar .nav-dropdown {', $css);
        $this->assertStringContainsString('body.analytics-module-page', $css);
        $this->assertStringContainsString('padding-inline: 0 !important;', $css);
        $this->assertStringContainsString('id="reportTabs"', $reports);
    }
}
