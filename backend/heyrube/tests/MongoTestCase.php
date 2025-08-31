<?php

namespace Tests;

use Illuminate\Support\Facades\Config;
use MongoDB\Client as MongoClient;
use MongoDB\Driver\Exception\Exception as MongoException;

abstract class MongoTestCase extends TestCase
{
    protected static ?MongoClient $mongoClient = null;
    protected static string $mongoDbName = 'heyrube_testing';

    protected function setUp(): void
    {
        parent::setUp();

        $uri = env('MONGODB_URI', 'mongodb://127.0.0.1:27017');
        if (!str_contains($uri, 'retryWrites')) {
            $uri .= (str_contains($uri, '?') ? '&' : '?') . 'retryWrites=false';
        }
        $db = env('MONGODB_DATABASE', 'heyrube_testing');
        self::$mongoDbName = $db;

        // Force Laravel to use the mongodb connection for this test case
        Config::set('database.default', 'mongodb');
        Config::set('database.connections.mongodb.dsn', $uri);
        Config::set('database.connections.mongodb.database', $db);

        // Lazily initialize client
        if (!self::$mongoClient) {
            try {
                self::$mongoClient = new MongoClient($uri);
            } catch (\Throwable $e) {
                // Allow tests to proceed; connection errors will surface in assertions
            }
        }

        // Clean the test database before each test to ensure isolation
        try {
            if (self::$mongoClient) {
                self::$mongoClient->selectDatabase($db)->drop();
            }
        } catch (MongoException $e) {
            // Ignore drop failures (e.g., server unavailable) to not mask test errors
        }
    }
}

