<?php

namespace Niiknow\Laratt\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected $yellow = "\e[1;33m";
    protected $green  = "\e[0;32m";
    protected $white  = "\e[0;37m";

    protected function setUp()
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function checkRequirements()
    {
        parent::checkRequirements();

        collect($this->getAnnotations())->filter(function ($location) {
            return in_array('!Travis', array_get($location, 'requires', []));
        })->each(function ($location) {
            getenv('TRAVIS') && $this->markTestSkipped('Travis will not run this test.');
        });
    }

    protected function getPackageProviders($app)
    {
        return [
        ];
    }

    public function getResolver()
    {
        return get_class($this) . '::tenant';
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');

        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $this->getTempDirectory() . '/database.sqlite',
            'prefix' => '',
        ]);

        $app['config']->set('laratt', [
            'resolver' => $this->getResolver(),
            'audit'    => [
                'disk'    => null,
                'bucket'   => null,
                'include' => [
                    'table'  => '.*',
                    'tenant' => '.*'
                ],
                'exclude' => [
                    'table'  => '(log.*|cache)',
                    'tenant' => null
                ],
            ],
            'import_limit' => 9999
        ]);
    }

    protected function setUpDatabase()
    {
        $this->resetDatabase();
    }

    protected function resetDatabase()
    {
        file_put_contents($this->getTempDirectory() . '/database.sqlite', null);
    }

    public function getTempDirectory(): string
    {
        return __DIR__ . '/temp';
    }
}
