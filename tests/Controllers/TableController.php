<?php

namespace Niiknow\Laratt\Tests\Controllers;

use Niiknow\Laratt\Traits\ApiTableTrait;

class TableController
{
    use ApiTableTrait;

    /**
     * @var mixed
     */
    public $tableName;

    /**
     * @var array
     */
    protected $vrules = [
        'uid'        => 'nullable|string|max:50',
        'started_at' => 'nullable|date|date_format:Y-m-d',
        'ended_at'   => 'nullable|date|date_format:Y-m-d',
        'publicdata.*'  => 'nullable',
        'privatedata.*'   => 'nullable',
    ];

    /**
     * @return mixed
     */
    public function getTableName()
    {
        return $this->tableName;
    }
}
