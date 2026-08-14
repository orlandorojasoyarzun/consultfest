<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent any real HTTP request from leaking out of tests. If a test
        // forgets to Http::fake(), the request throws an exception instead
        // of burning credits on FestivalAPI or other paid services.
        Http::preventStrayRequests();
    }
}