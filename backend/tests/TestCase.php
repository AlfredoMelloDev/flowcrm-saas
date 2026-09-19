<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Simulates requests coming from the SPA's origin, exactly like a real
        // browser/axios request would, so Sanctum treats them as "stateful"
        // (cookie/session based) instead of falling back to stateless token auth.
        $this->withHeader('Origin', 'http://localhost:5173');
    }
}
