<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure Mongo DSN disables retryable writes in all tests
        $uri = env('MONGODB_URI', 'mongodb://127.0.0.1:27017');
        if (!str_contains($uri, 'retryWrites')) {
            $uri .= (str_contains($uri, '?') ? '&' : '?') . 'retryWrites=false';
        }
        if (!str_contains($uri, 'directConnection')) {
            $uri .= (str_contains($uri, '?') ? '&' : '?') . 'directConnection=true';
        }
        Config::set('database.connections.mongodb.dsn', $uri);
        Config::set('database.connections.mongodb.database', env('MONGODB_DATABASE', 'heyrube_testing'));
        // Some package versions expect 'uri' instead of 'dsn'; set both.
        Config::set('database.connections.mongodb.uri', $uri);
    }
}
