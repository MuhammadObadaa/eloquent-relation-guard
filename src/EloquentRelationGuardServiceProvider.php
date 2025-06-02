<?php

namespace EloquentRelation\Guard;

use EloquentRelation\Guard\Console\Commands\RecordTree;
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

        if (app()->runningInConsole()) {
            $this->commands([
                RecordTree::class,
            ]);
        }
    }
}
