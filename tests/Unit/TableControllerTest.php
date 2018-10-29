<?php

namespace Niiknow\Laratt\Tests\Unit;

use Niiknow\Laratt\Tests\TestCase;
use Niiknow\Laratt\Tests\Controllers\TableController;

use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class TableControllerTest extends TestCase
{
    public static function tenant()
    {
        return 'tctest';
    }

    public function getRequest($table)
    {
        $mock = \Mockery::mock(\Illuminate\Http\Request::class)->makePartial();

        $mock->shouldReceive('route')
            ->with('table')
            ->andReturn($table);

        return $mock;
    }

    /** @test */
    public function test_crud_boom_table()
    {
        echo "\n\r{$this->yellow}    should create, update, and delete table records...";

        $c = new \Niiknow\Laratt\Tests\Controllers\TableController();

        $c->tableName = 'boom';

        $req = $this->getRequest('boom');
        $pd  = [
            'name' => 'Tom'
        ];

        $req->shouldReceive('uid')
            ->with('uid')
            ->andReturn(null);

        $req->shouldReceive('all')
            ->andReturn($pd);

        // test: create
        $rstc = $c->create($req);
        // var_dump($rstr);
        $this->assertInstanceOf(\Niiknow\Laratt\Models\TableModel::class, $rstc);

        $req = $this->getRequest('boom');

        $req->shouldReceive('route')
            ->with('uid')
            ->andReturn($rstc->uid);

        // test: retrieve
        $rstr = $c->retrieve($req);
        // var_dump($rstr);
        $this->assertInstanceOf(\Niiknow\Laratt\Models\TableModel::class, $rstr);


        $req = $this->getRequest('boom');

        $req->shouldReceive('route')
            ->with('uid')
            ->andReturn($rstc->uid);

        $pd['name'] = 'Noogen';
        $req->shouldReceive('all')
            ->andReturn($pd);

        // test: update
        $rstr = $c->upsert($req);
        // var_dump($rstr);
        $this->assertInstanceOf(\Niiknow\Laratt\Models\TableModel::class, $rstr);

        $item = \Niiknow\Laratt\Models\TableModel::query()->from('tctest_boom')->where('name', $pd['name'])->first();
        $this->assertTrue(isset($item));
        $this->assertSame('Noogen', $item->name);

        // test: delete
        $rstr = $c->delete($req);

        // var_dump($rstr);
        $this->assertInstanceOf(\Niiknow\Laratt\Models\TableModel::class, $rstr);

        $item = \Niiknow\Laratt\Models\TableModel::query()->from('tctest_boom')->where('name', $pd['name'])->first();
        $this->assertTrue(!isset($item));

        // truncate table
        $c->truncate($req);

        echo " {$this->green}[OK]{$this->white}\r\n";
    }
}
