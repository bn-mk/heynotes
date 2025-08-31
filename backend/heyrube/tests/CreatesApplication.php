<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        // Enforce Mongo DSN safety before the app boots, and choose host per environment
        $defaultHost = '127.0.0.1';
        // If running in Docker network where 'mongo' resolves, prefer it
        try {
            $resolved = gethostbyname('mongo');
            if ($resolved && $resolved !== 'mongo') {
                $defaultHost = 'mongo';
            }
        } catch (\Throwable $e) {}

        $incoming = getenv('MONGODB_URI');
        $uri = $incoming ?: "mongodb://{$defaultHost}:27017";
        if (!str_contains($uri, 'retryWrites')) {
            $uri .= (str_contains($uri, '?') ? '&' : '?') . 'retryWrites=false';
        }
        if (!str_contains($uri, 'directConnection')) {
            $uri .= (str_contains($uri, '?') ? '&' : '?') . 'directConnection=true';
        }
        putenv("MONGODB_URI=$uri");
        $_ENV['MONGODB_URI'] = $uri;
        $_SERVER['MONGODB_URI'] = $uri;

        if (!getenv('MONGODB_DATABASE')) {
            putenv('MONGODB_DATABASE=heyrube_testing');
            $_ENV['MONGODB_DATABASE'] = 'heyrube_testing';
            $_SERVER['MONGODB_DATABASE'] = 'heyrube_testing';
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Apply config overrides post-bootstrap as well
        config(['database.connections.mongodb.dsn' => $uri]);
        config(['database.connections.mongodb.uri' => $uri]);
        config(['database.connections.mongodb.database' => env('MONGODB_DATABASE', 'heyrube_testing')]);

        return $app;
    }
}

