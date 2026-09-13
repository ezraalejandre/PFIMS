<?php

namespace Tests\Unit;

use Tests\TestCase;

class TestingCacheIsolationTest extends TestCase
{
    public function test_testing_bootstrap_does_not_use_production_cache_files(): void
    {
        $this->assertFalse(app()->configurationIsCached());
        $this->assertStringContainsString(
            'storage/framework/cache/testing-config.php',
            app()->getCachedConfigPath()
        );
        $this->assertStringContainsString(
            'storage/framework/cache/testing-routes-v7.php',
            app()->getCachedRoutesPath()
        );
    }
}
