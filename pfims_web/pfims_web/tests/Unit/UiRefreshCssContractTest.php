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
    }
}
