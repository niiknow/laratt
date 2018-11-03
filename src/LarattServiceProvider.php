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
            $model . '/list',
            $modelName . 'Controller@list'
        )->name("$prefix.$model" . '.list');

        Route::match(
            ['get', 'post'],
            $model . '/data',
            $modelName . 'Controller@data'
        )->name("$prefix.$model" . '.data');

        Route::match(
            ['post'],
            $model . '/create',
            $modelName . 'Controller@create'
        )->name("$prefix.$model" . '.create');

        Route::match(
            ['get', 'post'],
            $model .'/{uid}/retrieve',
            $modelName . 'Controller@retrieve'
        )->name('laratt.' . $model . '.retrieve');

        Route::match(
            ['post'],
            $model . '/{uid}/update',
            $modelName . 'Controller@update'
        )->name("$prefix.$model" . '.update');

        Route::match(
            ['post', 'delete'],
            $model . '/{uid}/delete',
            $modelName . 'Controller@delete'
        )->name("$prefix.$model" . '.delete');

        Route::match(
            ['post'],
            $model . '/import',
            $modelName . 'Controller@import'
        )->name("$prefix.$model" . '.import');

        Route::match(
            ['post'],
            $model . '/truncate',
            $modelName . 'Controller@truncate'
        )->name("$prefix.$model" . '.truncate');

        Route::match(
            ['post'],
            $model . '/drop',
            $modelName . 'Controller@drop'
        )->name("$prefix.$model" . '.drop');
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
            ['get', 'post'],
            'tables/data',
            $controller . '@data'
        )->name($prefix . '.tables.data');

        Route::match(
            ['post'],
            'tables/{table}/create',
            $controller . '@create'
        )->name($prefix . '.tables.create');

        Route::match(
            ['get', 'post'],
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
