<?php
namespace Niiknow\Laratt\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
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
     * @param  $tableNew
     * @return void
     */
    public function createTable(
        $tableNew
    ) {
        Schema::create($tableNew, function (Blueprint $table) {
            $table->increments('id');

            // allow to uniquely identify this model
            $table->string('uid', 50)->unique();

            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable()->index();

            $table->longText('private')->nullable();
            $table->longText('public')->nullable();
            $table->timestamps();
        });
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
