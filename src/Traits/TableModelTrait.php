<?php

namespace Niiknow\Laratt\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Config;

use Illuminate\Support\Str;

use Carbon\Carbon;

use Niiknow\Laratt\TenancyResolver;

/**
 * Add ability to audit to the cloud - such as s3
 * Enable revision support on s3
 */
trait TableModelTrait
{
    public static function bootTableModelTrait()
    {
        static::creating(function ($model) {
            if (!isset($model->uid)) {
                // automatically add uid if not provided
                $model->uid = (string) Str::uuid();
            }
        });
    }

    public function tableCreate($table, $tenant = null)
    {
        // \Log::info($table);
        $this->createTableIfNotExists($tenant, $table);
        return $this;
    }

    public function tableFill($uid, $data, $table, $tenant = null)
    {
        $item = $this->tableFind($uid, $table);
        if (isset($item)) {
            $item->fill($data);
        }
        return $item;
    }

    public function tableFind($uid, $table, $tenant = null)
    {
        $this->createTableIfNotExists($tenant, $table);
        $tn   = $this->getTable();
        $item = $this->query()->from($tn)->where('uid', $uid)->first();
        if (isset($item)) {
            $item->setTableName($tenant, $table);
        }
        return $item;
    }

    public function setTableName($tenant, $tableName)
    {
        if ($tenant == null) {
            $tenant = TenancyResolver::resolve();
        }

        $newName = TenancyResolver::slug($tenant) . '_' . TenancyResolver::slug($tableName);

        $this->table = $newName;

        return $newName;
    }

    public function setUid($value)
    {
        // we have to do this because we use uid for audit
        // a slug is already an extremely flexible id
        $this->attributes['uid'] = \Str::slug($value);
    }

    public function dropTableIfExists($tenant, $tableName)
    {
        $tableNew = $this->setTableName($tenant, $tableName);

        if (Schema::hasTable($tableNew)) {
            Schema::dropIfExists($tableNew);

            // clear cache
            Cache::forget('tnc_'.$tableNew);
        }
    }

    public function createTableIfNotExists($tenant, $tableName)
    {
        $tableNew = $this->setTableName($tenant, $tableName);

        // only need to improve performance in prod
        if (config('env') === 'production' && \Cache::has($tableNew)) {
            return $tableNew;
        }

        if (!Schema::hasTable($tableNew)) {
            Schema::create($tableNew, function (Blueprint $table) {
                $table->increments('id');

                // allow to uniquely identify this model
                $table->string('uid', 50)->unique();

                // example, name: home slider
                $table->string('name')->nullable();
                // label should be hidden from user, viewable by admin
                // example: location x, y, and z home slider
                $table->string('label')->nullable();
                $table->string('teaser')->nullable(); // ex: sale sale sale
                $table->string('group')->nullable()->index(); // ex: sales, daily
                $table->timestamp('started_at')->nullable()->index();
                $table->timestamp('ended_at')->nullable()->index();
                $table->unsignedSmallInteger('priority')->default(100);

                $table->string('title')->nullable(); // ex: box of chocolate
                $table->string('summary')->nullable(); // ex: summay of box
                $table->string('image_url')->nullable(); // ex: picture of box
                $table->string('keywords')->nullable(); // ex: valentine, birthday, ...

                // targeting data, for advertising
                $table->string('geos')->nullable();
                $table->string('tags')->nullable();
                $table->string('hostnames')->nullable(); // ex: example.com,go.com
                $table->string('week_schedules')->nullable(); // csv of 101 to 724

                // tracking/impression
                $table->string('analytic_code')->nullable(); // for google ua
                $table->string('imp_pixel')->nullable(); // track display

                // ecommerce stuff, value should be in cents - no decimal
                $table->unsignedInteger('msrp')->default(0);
                $table->unsignedInteger('price')->default(0);
                $table->unsignedInteger('sale_price')->default(0);
                $table->unsignedSmallInteger('sale_qty')->default(1);
                $table->string('skus')->nullable()->index();
                $table->string('gtins')->nullable()->index();
                $table->string('brands')->nullable();
                $table->string('cat1')->nullable()->index();
                $table->string('cat2')->nullable()
                $table->string('cat3')->nullable()
                $table->string('cat4')->nullable()
                $table->string('map_coords')->nullable(); // hot map coordinates

                $table->timestamps();

                // conversion/click tracking url
                $table->string('clk_url', 500)->nullable();

                $table->mediumText('content')->nullable(); // detail description of things
                // things that are hidden from the user
                // like tax_group, ship_weight/length/height
                $table->mediumText('meta')->nullable();
                // things that are shown like extra images
                $table->mediumText('data')->nullable();
                // variant color, size, price, etc...
                $table->mediumText('var')->nullable();
                $table->uuid('job_id')->nullable()->index();
            });

            // cache database check for 12 hours or half a day
            \Cache::add($tableNew, 'true', 60*12);
        }

        return $tableNew;
    }
}
