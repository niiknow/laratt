<?php

namespace Niiknow\Laratt\Models;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;

use Niiknow\Laratt\Traits\CloudAuditable;
use Niiknow\Laratt\Traits\TableModelTrait;

use Carbon\Carbon;

class TableModel extends Model
{
    use CloudAuditable,
        TableModelTrait;

    /**
     * @var array
     */
    protected $fillable = [
        'uid', 'name', 'label', 'teaser', 'group', 'started_at', 'ended_at', 'priority',
        'title', 'summary', 'image_url', 'keywords', 'tags', 'hostnames', 'geos',
        'week_schedules', 'analytic_code', 'imp_pixel', 'msrp', 'price',
        'sale_price', 'sale_qty', 'skus', 'gtins', 'brands', 'cat1',
        'cat2', 'cat3', 'cat4', 'map_coords', 'clk_url', 'content',
        'data', 'meta', 'var', 'job_id'
    ];

    /**
     * @var array
     */
    protected $casts = [
        'priority'   => 'integer',
        'msrp'       => 'integer',
        'price'      => 'integer',
        'sale_price' => 'integer',
        'meta' => 'array',
        'data' => 'array',
        'var' => 'array',
    ];

    /**
     * The attributes that should be casted by Carbon
     *
     * @var array
     */
    protected $dates = [
        'started_at' => 'datetime:Y-m-d',
        'ended_at' => 'datetime',
        'created_at',
        'updated_at',
    ];

    protected $hidden = ['no_audit'];

    public function setStartedAtAttribute($value)
    {
        $this->attributes['started_at'] = Carbon::parse($value)->startOfDay();
    }

    public function setEndedAtAttribute($value)
    {
        $this->attributes['ended_at'] = Carbon::parse($value)->endOfDay();
    }
}
