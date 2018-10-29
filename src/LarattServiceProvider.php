<?php

namespace Niiknow\Laratt;

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

    public static function routeModel($modelName = 'profile')
    {
        $model = mb_strtolower($modelName);

        Route::match(
            ['get','delete'],
            $model . 's/list',
            $modelName . 'Controller@list'
        )->name('laratt.'. $model .'s.list');

        Route::match(
            ['get'],
            $model . 's/data',
            ucFirst($modelName) . 'Controller@data'
        )->name('laratt.'. $model .'s.data');

        Route::match(
            ['post'],
            $model .'s/create',
            $modelName . 'Controller@create'
        )->name('laratt.'. $model .'s.create');

        Route::match(
            ['get'],
            $model .'s/{uid}/retrieve',
            $modelName . 'Controller@retrieve'
        )->name('laratt.'. $model .'s.retrieve');

        Route::match(
            ['post'],
            $model .'s/{uid}/upsert',
            $modelName . 'Controller@upsert'
        )->name('laratt.'. $model .'s.upsert');

        Route::match(
            ['post', 'delete'],
            $model .'s/{uid}/delete',
            $modelName . 'Controller@delete'
        )->name('laratt.'. $model .'s.delete');

        Route::match(
            ['post'],
            $model .'s/import',
            $modelName . 'Controller@import'
        )->name('laratt.'. $model .'s.import');

        Route::match(
            ['post'],
            $model .'s/truncate',
            $modelName . 'Controller@truncate'
        )->name('laratt.'. $model .'s.truncate');

        Route::match(
            ['post'],
            'profiles/drop',
            $modelName . 'Controller@drop'
        )->name('laratt.'. $model .'s.drop');
    }

    public static function routeTables($controller)
    {
        // table stuff
        Route::match(
            ['get','delete'],
            'tables/list',
            $controller . '@list'
        )->name('laratt.tables.list');

        Route::match(
            ['get'],
            'tables/data',
            $controller . '@data'
        )->name('laratt.tables.data');

        Route::match(
            ['post'],
            'tables/{table}/create',
            $controller . '@create'
        )->name('laratt.tables.create');

        Route::match(
            ['get'],
            'tables/{table}/{uid}/retrieve',
            $controller . '@retrieve'
        )->name('laratt.tables.retrieve');

        Route::match(
            ['post'],
            'tables/{table}/{uid}/upsert',
            $controller . '@upsert'
        )->name('laratt.tables.upsert');

        Route::match(
            ['post', 'delete'],
            'tables/{table}/{uid}/delete',
            $controller . '@delete'
        )->name('laratt.tables.delete');

        Route::match(
            ['post'],
            'tables/{table}/import',
            $controller . '@import'
        )->name('laratt.tables.import');

        Route::match(
            ['post'],
            'tables/{table}/truncate',
            $controller . '@truncate'
        )->name('laratt.tables.truncate');

        Route::match(
            ['post'],
            'tables/{table}/drop',
            $controller . '@drop'
        )->name('laratt.tables.drop');
    }
}
