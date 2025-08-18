<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Journal;
use App\Policies\JournalPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Journal::class => JournalPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}