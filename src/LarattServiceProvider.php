<?php
// phpcs:ignoreFile
namespace Niiknow\Laratt;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LarattServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/laratt.php' => config_path('laratt.php')], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laratt.php', 'laratt');
    }

    /**
     * @param $modelName
     * @param $prefix
     * @param $idField
     */
    public static function routeModel(
        $modelName = 'Profile',
        $prefix = 'laratt',
        $idField = 'uid'
    ) {
        $model = mb_strtolower($modelName);

        Route::match(
            ['get', 'delete'],
            $model . '/query',
            $modelName . 'Controller@query'
        )->name("$prefix.$model" . '.query');

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
            $model . '/{' . $idField . '}/retrieve',
            $modelName . 'Controller@retrieve'
        )->name('laratt.' . $model . '.retrieve');

        Route::match(
            ['post'],
            $model . '/{' . $idField . '}/update',
            $modelName . 'Controller@update'
        )->name("$prefix.$model" . '.update');

        Route::match(
            ['post', 'delete'],
            $model . '/{' . $idField . '}/delete',
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

    /**
     * @param $controller
     * @param $prefix
     * @param $idField
     */
    public static function routeTables(
        $controller,
        $prefix = 'laratt',
        $idField = 'uid'
    ) {
        // table stuff
        Route::match(
            ['get', 'delete'],
            'tables/query',
            $controller . '@query'
        )->name($prefix . '.tables.query');

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
            'tables/{table}/{' . $idField . '}/retrieve',
            $controller . '@retrieve'
        )->name($prefix . '.tables.retrieve');

        Route::match(
            ['post'],
            'tables/{table}/{' . $idField . '}/update',
            $controller . '@update'
        )->name($prefix . '.tables.update');

        Route::match(
            ['post', 'delete'],
            'tables/{table}/{' . $idField . '}/delete',
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
