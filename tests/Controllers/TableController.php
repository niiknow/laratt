<?php

namespace Niiknow\Laratt\Tests\Controllers;

use Niiknow\Laratt\Traits\ApiTableTrait;
use Niiknow\Laratt\Models\TableModel;

class TableController
{
    use ApiTableTrait;

    public $tableName;

    public function getTable()
    {
        return $this->tableName;
    }

    /**
     * @var array
     */
    protected $vrules = [
        'uid' => 'nullable|string|max:190',
        'name' => 'nullable|string|max:190',
        'label' => 'nullable|string|max:190',
        'teaser' => 'nullable|string|max:190',
        'group' => 'nullable|string|max:190',
        'started_at' => 'nullable|date|date_format:Y-m-d',
        'ended_at' => 'nullable|date|date_format:Y-m-d',
        'priority' => 'nullable|integer|max:32000',
        'title' => 'nullable|string|max:190',
        'summary' => 'nullable|string|max:190',
        'image_url' => 'nullable|url|max:190',
        'geos' => 'nullable|string|max:190',
        'keywords' => 'nullable|string|max:190',
        'tags' => 'nullable|string|max:190',
        'hostnames' => 'nullable|string|max:190',
        'week_schedules' => 'nullable|url|max:190',
        'analytic_code' => 'nullable|url|max:190',
        'imp_pixel' => 'nullable|url|max:190',
        'msrp' => 'nullable|integer',
        'price' => 'nullable|integer',
        'sale_price' => 'nullable|integer',
        'sale_qty' => 'nullable|integer|max:32000',
        'skus' => 'nullable|string|max:190',
        'gtins' => 'nullable|string|max:190',
        'brands' => 'nullable|string|max:190',
        'cat1' => 'nullable|string|max:190',
        'cat2' => 'nullable|string|max:190',
        'cat3' => 'nullable|string|max:190',
        'cat4' => 'nullable|string|max:190',
        'map_coords' => 'nullable|string|max:190',

        'clk_url' => 'nullable|url|max:500',
        'content' => 'nullable|string',
        'data.*' => 'nullable',
        'meta.*' => 'nullable',
        'var.*' => 'nullable',
    ];
}
