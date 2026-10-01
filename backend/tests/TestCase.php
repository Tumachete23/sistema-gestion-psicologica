<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clave efímera exclusiva de las pruebas; no requiere secretos locales.
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }
}
