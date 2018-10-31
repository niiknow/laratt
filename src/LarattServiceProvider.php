<?php

namespace Niiknow\Laratt;

use Illuminate\Support\Facades\Route;
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

    public static function routeModel($modelName = 'Profile', $prefix = 'laratt')
    {
        $model = mb_strtolower($modelName);

        Route::match(
            ['get','delete'],
            $model . 's/list',
            $modelName . 'Controller@list'
        )->name("$prefix.$model" . 's.list');

        Route::match(
            ['get'],
            $model . 's/data',
            $modelName . 'Controller@data'
        )->name("$prefix.$model" . 's.data');

        Route::match(
            ['post'],
            $model . 's/create',
            $modelName . 'Controller@create'
        )->name("$prefix.$model" . 's.create');

        Route::match(
            ['get'],
            $model .'s/{uid}/retrieve',
            $modelName . 'Controller@retrieve'
        )->name('laratt.' . $model . 's.retrieve');

        Route::match(
            ['post'],
            $model . 's/{uid}/update',
            $modelName . 'Controller@update'
        )->name("$prefix.$model" . 's.update');

        Route::match(
            ['post', 'delete'],
            $model . 's/{uid}/delete',
            $modelName . 'Controller@delete'
        )->name("$prefix.$model" . 's.delete');

        Route::match(
            ['post'],
            $model . 's/import',
            $modelName . 'Controller@import'
        )->name("$prefix.$model" . 's.import');

        Route::match(
            ['post'],
            $model . 's/truncate',
            $modelName . 'Controller@truncate'
        )->name("$prefix.$model" . 's.truncate');

        Route::match(
            ['post'],
            'profiles/drop',
            $modelName . 'Controller@drop'
        )->name("$prefix.$model" . 's.drop');
    }

    public static function routeTables($controller, $prefix = 'laratt')
    {
        // table stuff
        Route::match(
            ['get','delete'],
            'tables/list',
            $controller . '@list'
        )->name($prefix . '.tables.list');

        Route::match(
            ['get'],
            'tables/data',
            $controller . '@data'
        )->name($prefix . '.tables.data');

        Route::match(
            ['post'],
            'tables/{table}/create',
            $controller . '@create'
        )->name($prefix . '.tables.create');

        Route::match(
            ['get'],
            'tables/{table}/{uid}/retrieve',
            $controller . '@retrieve'
        )->name($prefix . '.tables.retrieve');

        Route::match(
            ['post'],
            'tables/{table}/{uid}/update',
            $controller . '@update'
        )->name($prefix . '.tables.update');

        Route::match(
            ['post', 'delete'],
            'tables/{table}/{uid}/delete',
            $controller . '@delete'
        )->name($prefix . '.tables.delete');

        Route::match(
            ['post'],
            'tables/{table}/import',
            $controller . '@import'
        )->name($prefix . '.tables.import');

        Route::match(
            ['post'],
            'tables/{table}/truncate',
            $controller . '@truncate'
        )->name($prefix . '.tables.truncate');

        Route::match(
            ['post'],
            'tables/{table}/drop',
            $controller . '@drop'
        )->name($prefix . '.tables.drop');
    }
}
