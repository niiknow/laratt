<?php

namespace Niiknow\Laratt\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @var string
     */
    protected $green = "\e[0;32m";

    /**
     * @var string
     */
    protected $white = "\e[0;37m";

    /**
     * @var string
     */
    protected $yellow = "\e[1;33m";

    /**
     * @param $app
     */
    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');

        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => $this->getTempDirectory().'/database.sqlite',
            'prefix'   => '',
        ]);

        $app['config']->set('laratt', [
            'resolver'     => $this->getResolver(),
            'audit'        => [
                'disk'    => null,
                'bucket'  => null,
                'include' => [
                    'table'  => '.*',
                    'tenant' => '.*',
                ],
                'exclude' => [
                    'table'  => '(log.*|cache)',
                    'tenant' => null,
                ],
            ],
            'import_limit' => 9999,
        ]);
    }

    public function getResolver()
    {
        return get_class($this).'::tenant';
    }

    public function getTempDirectory(): string
    {
        return __DIR__.'/temp';
    }

    protected function checkRequirements()
    {
        parent::checkRequirements();

        collect($this->getAnnotations())->filter(function ($location) {
            return in_array('!Travis', \Illuminate\Support\Arr::get($location, 'requires', []), true);
        })->each(function ($location) {
            getenv('TRAVIS') && $this->markTestSkipped('Travis will not run this test.');
        });
    }

    /**
     * @param $app
     */
    protected function getPackageProviders($app)
    {
        return [
        ];
    }

    protected function resetDatabase()
    {
        file_put_contents($this->getTempDirectory().'/database.sqlite', null);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function setUpDatabase()
    {
        $this->resetDatabase();
    }
}
