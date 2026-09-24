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
        $this->assertStringContainsString('width: calc(100% - 248px) !important;', $css);
        $this->assertStringContainsString('html.sidebar-collapsed body:not(.landing-page) .main-content', $css);
        $this->assertStringContainsString('width: calc(100% - 76px) !important;', $css);
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
        $theme = file_get_contents(dirname(__DIR__, 2).'/public/js/theme.js');
        $themeViews = glob(dirname(__DIR__, 2).'/resources/views/*.blade.php');

        $this->assertIsString($finance);
        $this->assertIsString($reports);
        $this->assertIsString($reportsCss);
        $this->assertIsString($settings);
        $this->assertIsString($notifications);
        $this->assertIsString($systemUi);
        $this->assertIsString($theme);
        $this->assertIsArray($themeViews);
        $themeInclusions = 0;
        foreach ($themeViews as $themeViewPath) {
            $themeView = file_get_contents($themeViewPath);
            if (substr_count($themeView, "asset('js/theme.js')")) {
                $themeInclusions += substr_count($themeView, "asset('js/theme.js')");
                $this->assertStringNotContainsString(
                    'asset(\'js/theme.js\') }}"></script>',
                    $themeView,
                    basename($themeViewPath).' must version theme.js.'
                );
                $this->assertStringContainsString(
                    "asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}",
                    $themeView,
                    basename($themeViewPath).' must use the theme.js filemtime version.'
                );
            }
        }
        $this->assertSame(12, $themeInclusions, 'Every theme.js view inclusion must be accounted for.');
        $this->assertStringContainsString('loadLegacySystemUiIfNeeded', $theme);
        $this->assertStringContainsString("document.addEventListener('DOMContentLoaded', loadLegacySystemUiIfNeeded", $theme);
        $this->assertStringContainsString('const themeScript = document.currentScript;', $theme);
        $this->assertStringNotContainsString('if (!window.PFIMS_SYSTEM_UI_LOADED && !document.querySelector', $theme);
        $this->assertStringNotContainsString('new URL(\'pfims-system-ui.js\', document.currentScript.src)', $theme);
        $this->assertStringNotContainsString("document.head.appendChild(systemUi);\n    }\n    // Authenticated pages", $theme);
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
        $this->assertStringContainsString('@media (max-width: 1024px)', $css);
        $this->assertStringContainsString('transform: translateX(-100%) !important;', $css);
        $this->assertStringContainsString('top: 72px !important;', $css);
        $this->assertStringContainsString('height: calc(100dvh - 72px) !important;', $css);
        $this->assertStringContainsString('width: 50vw !important;', $css);
        $this->assertStringContainsString('html.mobile-nav-open .sidebar', $css);
        $this->assertStringContainsString('.mobile-nav-backdrop', $css);
        $this->assertStringContainsString('.mobile-nav-toggle', $css);
        $this->assertStringContainsString('inset: 72px 0 0;', $css);
        $this->assertStringContainsString('flex-direction: column;', $css);
        $this->assertStringNotContainsString('.mobile-nav-toggle::before', $css);
        $this->assertStringContainsString('display: none !important;', $css);
        $this->assertStringContainsString('@media (max-width: 700px)', $css);
        $this->assertStringContainsString('height: calc(100dvh - 64px) !important;', $css);
        $this->assertStringContainsString('width: 75vw !important;', $css);
        $legacyResponsiveSidebar = strpos($css, '@media (max-width: 900px)');
        $finalDrawerContract = strpos($css, '/* Final responsive drawer contract.');
        $this->assertNotFalse($legacyResponsiveSidebar);
        $this->assertNotFalse($finalDrawerContract);
        $this->assertGreaterThan($legacyResponsiveSidebar, $finalDrawerContract, 'The final drawer contract must follow legacy responsive sidebar rules.');
        $finalDrawerCss = substr($css, $finalDrawerContract);
        $this->assertStringContainsString('body:not(.landing-page) .sidebar {', $finalDrawerCss);
        $this->assertStringContainsString('html.mobile-nav-open body:not(.landing-page) .sidebar', $finalDrawerCss);
        $this->assertStringContainsString('inset: 72px 0 0;', $finalDrawerCss);
        $this->assertStringContainsString('position: relative !important;', $finalDrawerCss);
        $this->assertStringContainsString('flex-direction: column;', $finalDrawerCss);
        $this->assertStringContainsString('margin-top: 0 !important;', $finalDrawerCss);
        $this->assertStringNotContainsString('.mobile-nav-toggle::before', $finalDrawerCss);
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
        $this->assertStringContainsString('data-is-admin="{{ $isAdmin ? \'1\' : \'0\' }}"', $settings);
        $this->assertStringContainsString("var isAdmin = document.body.dataset.isAdmin === '1';", $settings);
        $this->assertStringContainsString('if (!isAdmin) return;', $settings);
        $this->assertStringContainsString('if (isAdmin) fetchConfigItems(currentConfigType);', $settings);
        $this->assertStringContainsString('.notifications-page .total-notif-item.card-red', $css);
        $this->assertStringContainsString('.notif-item.unread,', $notifications);
        $this->assertStringContainsString('.notif-item.read {', $notifications);
        $this->assertStringNotContainsString('opacity: 0.7;', $notifications);
        $this->assertStringContainsString('var rolePaths = ROLE_PATHS[portal] || ROLE_PATHS.admin;', $systemUi);
        $this->assertStringContainsString('childUrl.pathname = rolePath;', $systemUi);
        $this->assertStringContainsString("target.closest('table th, table td')", $systemUi);
        $this->assertStringContainsString("document.body.classList.contains('landing-page')", $systemUi);
        $this->assertStringContainsString("host.classList.add('pfims-loader-host')", $systemUi);
        $this->assertStringContainsString("control.matches('.nav-parent-toggle')", $systemUi);
        $this->assertStringContainsString("control.hasAttribute('download')", $systemUi);
        $this->assertStringNotContainsString("window.addEventListener('beforeunload', show)", $systemUi);
        $this->assertStringContainsString('window.PFIMS_PAGE_PRELOADER', $theme);
        $this->assertStringContainsString("document.documentElement.classList.add('pfims-preload')", $theme);
        $this->assertStringContainsString('pageGate.pending', $theme);
        $this->assertStringContainsString("document.documentElement.classList.remove('pfims-preload')", $systemUi);
        $this->assertStringContainsString('position: fixed;', $css);
        $this->assertStringContainsString('inset: 72px 0 0 248px;', $css);
        $this->assertStringContainsString('html.sidebar-collapsed .pfims-page-loader', $css);
        $this->assertStringContainsString('.pfims-loader-host', $css);
        $this->assertStringContainsString('html.pfims-preload body:not(.landing-page) main.main-content > *', $css);
        $this->assertStringContainsString('main.main-content::before', $css);
        $this->assertStringContainsString('main.main-content::after', $css);
        $this->assertStringNotContainsString('setInterval(function() {'.PHP_EOL.'                loadNotifications();', $notifications);
    }
}
