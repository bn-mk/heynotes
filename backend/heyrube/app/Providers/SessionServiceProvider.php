<?php

namespace App\Providers;

use App\Session\MongoSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\ConnectionInterface;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(ConnectionInterface $connection): void
    {
        Session::Extend('database', function ($app) use ($connection) {
            $table = config('session.table');
            $minutes = config('session.lifetime');
            return new MongoSessionHandler($connection, $table, $minutes);
        });
    }
}
