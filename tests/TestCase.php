<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // HTTP/backend tests exercise responses without depending on a local Vite build.
        // The browser suite independently builds and loads the actual assets.
        $this->withoutVite();
    }
}
