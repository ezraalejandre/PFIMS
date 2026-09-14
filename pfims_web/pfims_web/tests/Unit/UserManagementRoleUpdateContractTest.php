<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class UserManagementRoleUpdateContractTest extends TestCase
{
    public function test_role_update_preserves_existing_status_when_settings_payload_omits_it(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/web.php');

        $this->assertStringContainsString("'status' => \$request->input('status', \$user->status ?? 'Active')", $routes);
        $this->assertStringContainsString("role: document.getElementById('configRole').value", file_get_contents(__DIR__ . '/../../resources/views/settings.blade.php'));
    }

    public function test_user_creation_defaults_status_and_uses_lowercase_role_values(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/web.php');
        $settings = file_get_contents(__DIR__ . '/../../resources/views/settings.blade.php');

        $this->assertStringContainsString("'status' => \$request->input('status', 'Active')", $routes);
        $this->assertStringContainsString('<option value="admin">Admin</option>', $settings);
        $this->assertStringContainsString("addUserRole').value = 'admin'", $settings);
    }
}
