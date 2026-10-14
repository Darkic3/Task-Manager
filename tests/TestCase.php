<?php

namespace Tests;

use App\Logging\AiTraceHandler;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Start every test with an empty AI trace buffer, so events from one
     * test can never leak into another test's assertions.
     */
    protected function setUp(): void
    {
        parent::setUp();
        AiTraceHandler::flush();
    }

    protected function tearDown(): void
    {
        AiTraceHandler::flush();
        parent::tearDown();
    }
}
