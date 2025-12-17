<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure errors are visible during tests
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    }

    protected function assertPreConditions(): void
    {
        // This runs before each test method
        // Useful for catching issues early
        parent::assertPreConditions();
    }
}
