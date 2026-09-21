<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SharedFiltersContractTest extends TestCase
{
    public function test_filter_cards_share_sticky_and_per_component_default_reset_behavior(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root.'/public/js/pfims-system-ui.js');
        $css = file_get_contents($root.'/public/css/ui-refresh.css');

        $this->assertIsString($script);
        $this->assertIsString($css);
        $this->assertStringContainsString("var component = panel.closest('.filter-panel') || panel;", $script);
        $this->assertStringContainsString("component.classList.add('pfims-filter-component');", $script);
        $this->assertStringContainsString("component.classList.add('pfims-sticky-filter');", $script);
        $this->assertStringContainsString('var componentControls = new Map();', $script);
        $this->assertStringContainsString("reset.textContent = 'Reset Filters to Default';", $script);
        $this->assertStringContainsString("clear.insertAdjacentElement('afterend', reset)", $script);
        $this->assertStringContainsString("state.note.textContent = differs ? 'Filters differ from saved defaults' : 'Using saved defaults';", $script);
        $this->assertStringContainsString("control.dispatchEvent(new Event('input'", $script);
        $this->assertStringContainsString("control.dispatchEvent(new Event('change'", $script);
        $this->assertStringContainsString('.pfims-sticky-filter.is-stuck', $css);
        $this->assertStringContainsString('.filter-panel.pfims-filter-component > .pfims-filter-panel', $css);
        $this->assertStringContainsString('.pfims-reset-defaults[hidden]', $css);
    }

    public function test_settings_definitions_use_live_filter_ids_and_supported_select_values(): void
    {
        $settings = file_get_contents(dirname(__DIR__, 2).'/resources/views/settings.blade.php');

        $this->assertIsString($settings);
        $this->assertStringContainsString("['stockStatus','Stock status','select'", $settings);
        $this->assertStringContainsString("['expenseScopeFilter','Expense type','select'", $settings);
        $this->assertStringContainsString("['itemsStockFilter','Stock status','select'", $settings);
        $this->assertStringContainsString("['typeFilter','Transaction type','select'", $settings);
        $this->assertStringContainsString("definition[2] === 'select' ? document.createElement('select')", $settings);
        $this->assertStringNotContainsString("['stock_status','Stock status'", $settings);
    }

    public function test_default_filter_observer_does_not_rewrite_its_own_ui_forever(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/public/js/pfims-system-ui.js');

        $this->assertStringContainsString('var appliedAny = false;', $script);
        $this->assertStringContainsString('appliedAny = true;', $script);
        $this->assertStringContainsString('if (appliedAny) componentControls.forEach', $script);
    }
}
