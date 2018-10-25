<?php

namespace niiknow\laratt;

use Illuminate\Support\ServiceProvider;

class LarattServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/laratt.php' => config_path('laratt.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laratt.php', 'laratt');
    }
}
