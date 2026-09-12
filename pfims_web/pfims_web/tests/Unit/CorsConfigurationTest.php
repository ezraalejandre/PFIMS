<?php

namespace Tests\Unit;

use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    public function test_cors_defaults_to_a_specific_origin(): void
    {
        $origins = config('cors.allowed_origins');

        $this->assertIsArray($origins);
        $this->assertNotEmpty($origins);
        $this->assertNotContains('*', $origins);
        $this->assertContains(config('app.url'), $origins);
        $this->assertFalse((bool) config('cors.supports_credentials'));
    }
}
