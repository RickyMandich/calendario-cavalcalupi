<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // I test non dipendono dalla build degli asset (public/build/manifest.json).
        $this->withoutVite();
    }
}
