<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // Empty deliberately. This application authorises entirely through two
        // route middlewares -- RequireAdmin and RequireTeacher -- plus inline
        // ownership checks in controllers. There are no policies and no gates.
        //
        // What was here was stock `laravel new` scaffolding mapping App\Model to
        // App\Policies\ModelPolicy; neither class has ever existed in this
        // project, which is why ide-helper could not generate auth templates.
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
