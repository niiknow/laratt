<?php

namespace Niiknow\Laratt\Tests\Unit;

use Niiknow\Laratt\Tests\TestCase;
use Niiknow\Laratt\Tests\Controllers\ProfileController;

use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class ProfileControllerTest extends TestCase
{
    public static function tenant()
    {
        return 'pctest';
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
    public function test_crud_profile()
    {
        echo "\n\r{$this->yellow}    should create, update, and delete profile...";

        $c   = new \Niiknow\Laratt\Tests\Controllers\ProfileController();
        $req = $this->getRequest('profile');
        $pd  = [
            'email'         => 'tom@noogen.com',
            'first_name'    => 'Tom',
            'last_name'     => 'Noogen'
        ];

        $req->shouldReceive('route')
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
        $this->assertInstanceOf(\Niiknow\Laratt\Models\ProfileModel::class, $rstc);

        $req = $this->getRequest('profile');

        $req->shouldReceive('route')
            ->with('uid')
            ->andReturn($rstc->uid);

        // test: retrieve
        $rstr = $c->retrieve($req);
        // var_dump($rstr);
        $this->assertInstanceOf(\Niiknow\Laratt\Models\ProfileModel::class, $rstr);


        $req = $this->getRequest('profile');

        $req->shouldReceive('route')
            ->with('uid')
            ->andReturn($rstc->uid);

        $pd['last_name'] = 'Niiknow';
        $req->shouldReceive('except')
            ->andReturn($pd);

        // test: update
        $rstr = $c->update($req);
        // var_dump($rstr);
        $this->assertInstanceOf(\Niiknow\Laratt\Models\ProfileModel::class, $rstr);

        $item = \Niiknow\Laratt\Models\ProfileModel::query()->from('pctest$profile')->where('email', $pd['email'])->first();
        $this->assertTrue(isset($item));
        $this->assertSame('Niiknow', $item->last_name);

        // test: delete
        $rstr = $c->delete($req);

        // var_dump($rstr);
        $this->assertInstanceOf(\Niiknow\Laratt\Models\ProfileModel::class, $rstr);

        $item = \Niiknow\Laratt\Models\ProfileModel::query()->from('pctest$profile')->where('email', $pd['email'])->first();
        $this->assertTrue(!isset($item));

        // truncate table
        $c->truncate($req);

        echo " {$this->green}[OK]{$this->white}\r\n";
    }

    /** @test */
    public function test_query_profile()
    {
        echo "\n\r{$this->yellow}    query profile...";
        $expected = 20;
        $c        = new \Niiknow\Laratt\Tests\Controllers\ProfileController();

        $faker = \Faker\Factory::create();
        for ($i = 0; $i < $expected; $i++) {
            $pd = [
                'email' => $faker->unique()->safeEmail,
                'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
            ];

            $req = $this->getRequest('profile');

            $req->shouldReceive('route')
                ->with('uid')
                ->andReturn(null);

            $req->shouldReceive('input')
                ->with('uid')
                ->andReturn(null);

            $req->shouldReceive('except')
                ->andReturn($pd);

            // create
            $rst = $c->create($req);
            // $this->assertSame(2, json_encode($rst));
        }

        // test: list all
        $items = \Niiknow\Laratt\Models\ProfileModel::query()->from('pctest$profile')->get();
        $this->assertSame($expected, count($items));

        $req = $this->getRequest('profile');
        $req->shouldReceive('query')
            ->with('select')
            ->andReturn(null);
        $req->shouldReceive('query')
            ->with('filter')
            ->andReturn(null);
        $req->shouldReceive('query')
            ->with('limit')
            ->andReturn(null);
        $req->shouldReceive('query')
            ->with('page')
            ->andReturn(null);
        $req->shouldReceive('query')
            ->with('sort')
            ->andReturn(null);
        $req->shouldReceive('isMethod')
            ->with('delete')
            ->andReturn(false);

        // test datatable query
        $rst = $c->list($req);
        // var_dump($rst->toArray());
        $this->assertTrue(isset($rst), "Query response with data.");
        $this->assertSame(15, $rst->toArray()['per_page'], "Correctly return datatable.");

        // test: list paging
        $req = $this->getRequest('profile');
        $req->shouldReceive('query')
            ->with('select')
            ->andReturn(null);
        $req->shouldReceive('query')
            ->with('filter')
            ->andReturn(null);
        $req->shouldReceive('query')
            ->with('limit')
            ->andReturn(5);
        $req->shouldReceive('query')
            ->with('page')
            ->andReturn(2);
        $req->shouldReceive('query')
            ->with('sort')
            ->andReturn(null);
        $req->shouldReceive('isMethod')
            ->with('delete')
            ->andReturn(false);

        $rst = $c->list($req);
        $this->assertTrue(isset($rst), "Query response with data.");
        $body = $rst->toArray();
        $this->assertSame(2, $body['current_page'], "Correctly parse page parameter.");
        $this->assertSame(5, count($body['data']), "Has right count.");
        $expected = \Niiknow\Laratt\Models\ProfileModel::query()->from('pctest$profile')->count() - 8;

        // test: list filter with delete
        $req = $this->getRequest('profile');
        $req->shouldReceive('query')
            ->with('select')
            ->andReturn(null);
        $req->shouldReceive('query')
            ->with('filter')
            ->andReturn("id:lte:8");
        $req->shouldReceive('query')
            ->with('limit')
            ->andReturn(null);
        $req->shouldReceive('query')
            ->with('page')
            ->andReturn(null);
        $req->shouldReceive('query')
            ->with('sort')
            ->andReturn(null);
        $req->shouldReceive('isMethod')
            ->with('delete')
            ->andReturn(true);

        $rst = $c->list($req);

        $count = \Niiknow\Laratt\Models\ProfileModel::query()->from('pctest$profile')->count();
        $this->assertSame($expected, $count, "Has right count.");

        // truncate table
        $c->truncate($req);

        echo " {$this->green}[OK]{$this->white}\r\n";
    }

    /** @test */
    public function test_import_profile()
    {
        echo "\n\r{$this->yellow}    import profile...";

        // Fake any disk here
        \Storage::fake('local');

        $filePath = '/tmp/randomstring.csv';
        $expected = 10;

        // Create file
        $data  = "email,password,data.x,data.y,meta.domain\n";
        $faker = \Faker\Factory::create();
        for ($i = 0; $i < $expected; $i++) {
            $fakedata = [
                'email' => $faker->unique()->safeEmail,
                'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
                'data.x' => $faker->catchPhrase,
                'data.y' => $faker->domainName,
                'meta.domain' => $faker->domainWord
            ];

            $data .= '"' . join($fakedata, '","') . "\"\n";
        }

        // Create file
        file_put_contents($filePath, $data);
        $file = new \Illuminate\Http\UploadedFile($filePath, 'test.csv', null, null, null, true);

        $c   = new \Niiknow\Laratt\Tests\Controllers\ProfileController();
        $req = $this->getRequest('profile');

        $req->shouldReceive('except')
            ->andReturn(['file' => $file]);

        $req->shouldReceive('file')
            ->with('file')
            ->andReturn($file);

        $rst   = $c->import($req);
        $count = \Niiknow\Laratt\Models\ProfileModel::query()->from('pctest$profile')->count();
        $this->assertSame($expected, $count, "Has right count.");

        // drop table
        $c->drop($req);

        echo " {$this->green}[OK]{$this->white}\r\n";
    }
}
