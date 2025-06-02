<?php

namespace EloquentRelation\Guard;

use Illuminate\Support\ServiceProvider;

class EloquentRelationGuardServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eloquent-relation-guard.php', 'eloquent-relation-guard');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/eloquent-relation-guard.php' => config_path('eloquent-relation-guard.php'),
        ], 'config');
    }
}
