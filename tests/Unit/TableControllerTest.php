<?php

namespace Niiknow\Laratt\Tests\Unit;

use Niiknow\Laratt\Tests\TestCase;

class TableControllerTest extends TestCase
{
    /**
     * @param  $table
     * @return mixed
     */
    public function getRequest($table)
    {
        $mock = \Mockery::mock(\Illuminate\Http\Request::class)->makePartial();

        $mock->shouldReceive('route')
             ->with('table')
             ->andReturn($table);

        return $mock;
    }

    public static function tenant()
    {
        return 'tctest';
    }

    /**
     * @test
     */
    public function testCrudBoomTable()
    {
        echo "\n\r{$this->yellow}    should create, update, and delete table records...";

        $c = new \Niiknow\Laratt\Tests\Controllers\TableController();

        $c->tableName = 'boom';

        $req = $this->getRequest('boom');
        $pd = [
            'private.name' => 'Tom',
        ];

        $req->shouldReceive('uid')
            ->with('uid')
            ->andReturn(null);

        $req->shouldReceive('input')
            ->with('uid')
            ->andReturn(null);

        $req->shouldReceive('except')
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

        $pd['private.name'] = 'Noogen';
        $req->shouldReceive('except')
            ->andReturn($pd);

        // test: update
        $rstr = $c->update($req);
        // var_dump($rstr);
        $this->assertInstanceOf(\Niiknow\Laratt\Models\TableModel::class, $rstr);

        $item = \Niiknow\Laratt\Models\TableModel::query()->from('tctest$boom')->where('uid', $rstc->uid)->first();
        $this->assertTrue(isset($item));
        $this->assertSame('Noogen', $item->private['name']);

        // test: delete
        $rstr = $c->delete($req);

        // var_dump($rstr);
        $this->assertInstanceOf(\Niiknow\Laratt\Models\TableModel::class, $rstr);

        $item = \Niiknow\Laratt\Models\TableModel::query()->from('tctest$boom')->where('uid', $rstc->uid)->first();
        $this->assertTrue(! isset($item));

        // truncate table
        $c->truncate($req);

        echo " {$this->green}[OK]{$this->white}\r\n";
    }
}
