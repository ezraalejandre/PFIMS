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
        $this->assertStringContainsString('body:not(.landing-page) .sidebar', $css);
        $this->assertStringContainsString('position: fixed !important;', $css);
        $this->assertStringContainsString('inset: 72px auto auto 0 !important;', $css);
        $this->assertStringContainsString('height: calc(133.333333vh - 72px) !important;', $css);
        $this->assertStringContainsString('@media (max-width: 1024px)', $css);
        $this->assertStringNotContainsString(':where(.main-content, main) { font-size: .75rem; }', $css);

        foreach (glob(dirname(__DIR__, 2).'/resources/views/*.blade.php') as $viewPath) {
            $view = file_get_contents($viewPath);
            if (str_contains($view, "asset('css/ui-refresh.css')")) {
                $this->assertStringContainsString(
                    "asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}",
                    $view,
                    basename($viewPath).' must cache-bust the shared UI stylesheet.'
                );
            }
        }

        $finance = file_get_contents(dirname(__DIR__, 2).'/resources/views/finance.blade.php');
        $reports = file_get_contents(dirname(__DIR__, 2).'/resources/views/reports.blade.php');
        $reportsCss = file_get_contents(dirname(__DIR__, 2).'/public/css/centralized-reports.css');
        $settings = file_get_contents(dirname(__DIR__, 2).'/resources/views/settings.blade.php');
        $notifications = file_get_contents(dirname(__DIR__, 2).'/resources/views/notifications.blade.php');
        $systemUi = file_get_contents(dirname(__DIR__, 2).'/public/js/pfims-system-ui.js');

        $this->assertIsString($finance);
        $this->assertIsString($reports);
        $this->assertIsString($reportsCss);
        $this->assertIsString($settings);
        $this->assertIsString($notifications);
        $this->assertIsString($systemUi);
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
        $this->assertStringContainsString("asset('css/centralized-reports.css') }}?v={{ filemtime(public_path('css/centralized-reports.css')) }}", $reports);
        $this->assertStringContainsString("asset('css/finance.css') }}?v={{ filemtime(public_path('css/finance.css')) }}", $finance);
        $this->assertStringContainsString('width: auto !important;', $reportsCss);
        $this->assertStringContainsString('max-width: none !important;', $reportsCss);
        $this->assertStringContainsString('flex: 1 1 auto;', $reportsCss);
        $this->assertStringNotContainsString('width: calc(100vw - 248px) !important;', $reportsCss);
        $this->assertStringNotContainsString('id="configStatus"', $settings);
        $this->assertStringNotContainsString('id="addUserStatus"', $settings);
        $this->assertStringNotContainsString("document.getElementById('configStatus')", $settings);
        $this->assertStringNotContainsString("document.getElementById('addUserStatus')", $settings);
        $this->assertStringContainsString('.notifications-page .total-notif-item.card-red', $css);
        $this->assertStringContainsString('.notif-item.unread,', $notifications);
        $this->assertStringContainsString('.notif-item.read {', $notifications);
        $this->assertStringNotContainsString('opacity: 0.7;', $notifications);
        $this->assertStringContainsString('var rolePaths = ROLE_PATHS[portal] || ROLE_PATHS.admin;', $systemUi);
        $this->assertStringContainsString('childUrl.pathname = rolePath;', $systemUi);
        $this->assertStringNotContainsString('setInterval(function() {'.PHP_EOL.'                loadNotifications();', $notifications);
    }
}
