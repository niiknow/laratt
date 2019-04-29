<?php
namespace Niiknow\Laratt\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache as Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Niiknow\Laratt\Traits\CloudAuditable;
use Niiknow\Laratt\Traits\SchedulableTrait;
use Niiknow\Laratt\Traits\TableModelTrait;

class TableModel extends Model
{
    use CloudAuditable,
    SchedulableTrait,
        TableModelTrait;

    /**
     * @var array
     */
    protected $casts = [
        'private' => 'array',
        'public'  => 'array'
    ];

    /**
     * The attributes that should be casted by Carbon
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at'
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'uid', 'private', 'public', 'started_at', 'ended_at'
    ];

    /**
     * @param  $tenant
     * @param  $tableName
     * @return mixed
     */
    public function createTableIfNotExists(
        $tenant,
        $tableName
    ) {
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

                $table->timestamp('started_at')->index();
                $table->timestamp('ended_at')->nullable()->index();

                $table->longText('private')->nullable();
                $table->longText('public')->nullable();
                $table->timestamps();
            });

            // cache database check for 45 minutes
            \Cache::add($tableNew, 'true', 45);
        }

        return $tableNew;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if ($model->uid === null || empty($model->uid)) {
                $model->uid = (string) Str::uuid();
            }
        });
    }
}
