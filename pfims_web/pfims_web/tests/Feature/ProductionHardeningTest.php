<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    public function test_test_notification_get_endpoint_is_unavailable(): void
    {
        $this->get('/api/test-notify')->assertNotFound();
    }
}
