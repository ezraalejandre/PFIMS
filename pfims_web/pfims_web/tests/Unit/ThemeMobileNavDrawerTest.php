<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ThemeMobileNavDrawerTest extends TestCase
{
    public function test_theme_script_installs_accessible_mobile_drawer_without_touching_desktop_collapse_state(): void
    {
        $script = file_get_contents(__DIR__ . '/../../public/js/theme.js');

        $this->assertStringContainsString("matchMedia('(max-width: 1024px)')", $script);
        $this->assertStringContainsString('data-mobile-nav-toggle', $script);
        $this->assertStringContainsString('data-mobile-nav-backdrop', $script);
        $this->assertStringContainsString("className = 'mobile-nav-toggle'", $script);
        $this->assertStringContainsString("className = 'mobile-nav-backdrop'", $script);
        $this->assertStringContainsString("classList.add('mobile-nav-open')", $script);
        $this->assertStringContainsString("aria-controls', 'pfimsMobileNavDrawer'", $script);
        $this->assertStringContainsString('sidebar.inert = !open;', $script);
        $this->assertStringContainsString("sidebar.setAttribute('aria-hidden', open ? 'false' : 'true')", $script);
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString('initializeSidebar();', $script);
        $this->assertStringContainsString('initializeMobileNavDrawer();', $script);
        $this->assertStringContainsString('sidebarStorageKey', $script);
    }

    public function test_theme_script_closes_drawer_on_navigation_and_cleans_up_on_resize(): void
    {
        $script = file_get_contents(__DIR__ . '/../../public/js/theme.js');

        $this->assertStringContainsString("!link.classList.contains('nav-parent-toggle')", $script);
        $this->assertStringContainsString("link.getAttribute('href') !== '#'", $script);
        $this->assertStringContainsString("setMobileStyles(isMobile());", $script);
        $this->assertStringContainsString("document.body.style.overflow = '';", $script);
        $this->assertStringContainsString("document.documentElement.classList.remove('mobile-nav-open')", $script);
        $this->assertStringNotContainsString("sidebar.style.transform = 'translateX(-100%)'", $script);
    }
}
