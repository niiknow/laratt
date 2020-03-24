<?php
namespace Niiknow\Laratt\Traits;

use Illuminate\Support\Arr as Arr;
use Illuminate\Support\Facades\Cache as Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB as DB;
use Illuminate\Support\Facades\Log as Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Niiknow\Laratt\TenancyResolver;
use Validator;

/**
 * Add ability to audit to the cloud - such as s3
 * Enable revision support on s3
 */
trait TableModelTrait
{
    protected $no_audit = false;

    public static function bootTableModelTrait()
    {
        static::creating(function ($model) {
            $uidName = $model->getUidName();

            if (!isset($model->{$uidName})) {
                // automatically add uid if not provided
                $model->{$uidName} = (string) Str::uuid();
            }
        });
    }

    /**
     * @param  $tenant
     * @param  $tableName
     * @param  $tableCreateFunction
     * @return mixed
     */
    public function createTableIfNotExists(
        $tenant,
        $tableName,
        $schemaFunction = null
    ) {
        $tableNew = null;

        // invalid tenant, exit - throw exception?
        if (isset($tenant) && !empty($tenant) && strlen($tenant) < 3) {
            return $tableNew;
        }

        // invalid table name, exit - throw exception?
        if (isset($tableName) && !empty($tableName) && strlen($tableName) < 3) {
            return $tableNew;
        }

        $tableNew = $this->setTableName($tenant, $tableName);

// only need to improve performance in prod
        if (strpos(config('env'), 'pr') === 0 && \Cache::has('tnc_' . $tableNew)) {
            return $tableNew;
        }

        if (!Schema::hasTable($tableNew)) {
            if (isset($schemaFunction) && is_callable($schemaFunction)) {
                Schema::create($tableNew, $schemaFunction);
            } else if (method_exists($this, 'createTable')) {
                $this->createTable($tableNew);
            }
        }

        \Cache::add('tnc_' . $tableNew, 'true', 45 * 60);

        return $tableNew;
    }

    /**
     * @param $tenant
     * @param $tableName
     */
    public function dropTableIfExists(
        $tenant,
        $tableName
    ) {
        $tableNew = $this->setTableName($tenant, $tableName);

        Schema::dropIfExists($tableNew);

        \Cache::forget('tnc_' . $tableNew);
    }

    /**
     * get no audit
     *
     * @return boolean true if not audit
     */
    public function getNoAudit()
    {
        return $this->no_audit;
    }

    /**
     * get no_audit property
     *
     * @return boolean if enable audit
     */
    public function getNoAuditAttribute()
    {
        return $this->getNoAudit();
    }

    /**
     * Get the uid field name
     *
     * @return [type] [description]
     */
    public function getUidName()
    {
        return 'uid';
    }

    /**
     * process the csv records
     *
     * @param  array  $csv    the csv rows data
     * @param  array  &$data  the result array
     * @param  array  $vrules the validation rules
     * @return object null or response object if error
     */
    public function processCsv($csv, &$data, $vrules)
    {
        $rowno = 0;
        $limit = config('laratt.import_limit', 999);
        foreach ($csv as $row) {
            $inputs = [];

            foreach ($row as $key => $value) {
                $cell = $value;
                if (!is_string($cell)) {
                    $cell = (string) $cell;
                }
                $cv = trim(mb_strtolower($cell));

                if ($cv === 'null'
                    || $cv === 'nil'
                    || $cv === 'undefined') {
                    $cell = null;
                } elseif (!is_string($value) && is_numeric($cv)) {
                    $cell = $cell + 0;
                }

                \Illuminate\Support\Arr::set($inputs, $key, $cell);
            }

            if (is_array($vrules) && count($vrules) > 0) {
                $validator = Validator::make($inputs, $vrules);

                if ($validator->fails()) {
                    return [
                        'code'  => 422,
                        'error' => $validator->errors(),
                        'rowno' => $rowno,
                        'row'   => $inputs
                    ];
                }
            }

            $data[] = $inputs;
            $rowno += 1;

            if ($rowno > $limit) {
                return [
                    'code'  => 422,
                    'error' => "Import must be less than $limit records",
                    'count' => $rowno
                ];
            }
        }

        return ['code' => 200];
    }

    /**
     * @param $data
     * @param $table
     * @param $idField
     */
    public function saveImport(&$data, $table, $idField = null)
    {
        $inserted = [];
        $updated  = [];
        $skipped  = [];
        $rowno    = 1;
        $row      = [];

        if ($idField === null) {
            $idField = $this->getUidName();
        }

        \DB::beginTransaction();
        try {
            $rowno = 1;
            foreach ($data as $inputs) {
                $row               = $inputs;
                list($stat, $item) = $this->saveImportItem($inputs, $table, $idField);

                if (null === $item && $stat !== 'skip') {
                    // disable audit for bulk import
                    \DB::rollback();

                    return [
                        'code'  => 422,
                        'error' => 'Error while attempting to import row',
                        'rowno' => $rowno,
                        'row'   => $row
                    ];
                }

                if ($stat === 'insert') {
                    $inserted[] = $item->{$idField};
                } elseif ($stat === 'update') {
                    $updated[] = $item->{$idField};
                } else {
                    $skipped[] = $item->{$idField};
                }

                $rowno += 1;

                $item = null;
            }

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            $message = $e->getMessage();
            \Log::error('API import error: ' . $message);

            return [
                'code'  => 422,
                'error' => $message,
                'rowno' => $rowno,
                'row'   => $row
            ];
        }

        return [
            'code'     => 200,
            'count'    => $rowno,
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => $skipped
        ];
    }

    /**
     * @param $inputs
     * @param $table
     * @param $idField
     */
    public function saveImportItem(&$inputs, $table, $idField = null)
    {
        if ($idField === null) {
            $idField = $this->getUidName();
        }

        $model = get_class($this);
        $stat  = 'insert';
        $id    = isset($inputs[$idField]) ? $inputs[$idField] : null;
        $item  = new $model($inputs);
        $item->setTableName(null, $table);

        if (isset($id)) {
            $inputs[$idField] = $id;

            $item = $this->where($idField, $id)->first();

            if (isset($item)) {
                $item->fill($inputs);
                $stat = 'update';
            } else {
                $item = new $model($inputs);
                $item->setTableName(null, $table);
            }

            if ($item->shouldSkip()) {
                $stat = 'skip';

                return [$stat, $item];
            }
        }

        // rollback transaction
        $item->setNoAudit(true);

        return [$stat, $item->save() ? $item : null];
    }

    /**
     * Set no audit attribute
     *
     * @param  boolean $no_audit
     * @return $this
     */
    public function setNoAudit($no_audit)
    {
        $this->no_audit = $no_audit;

        return $this;
    }

    /**
     * set no_audit property
     *
     * @return boolean if enable audit
     */
    public function setNoAuditAttribute($value)
    {
        $this->setNoAudit($value);
    }

    /**
     * @param  $tenant
     * @param  $tableName
     * @return mixed
     */
    public function setTableName(
        $tenant,
        $tableName
    ) {
        if ($tenant === null) {
            $tenant = TenancyResolver::resolve();
        }

        $newName = TenancyResolver::slug($tenant) . '$' . TenancyResolver::slug($tableName);

        $this->table = $newName;

        return $newName;
    }

    /**
     * Set uid field and make sure it's a slug
     *
     * @param $value
     */
    public function setUidAttribute($value)
    {
        $this->attributes['uid'] = Str::slug($value);
    }

    /**
     * @param  $uid
     * @param  $table
     * @param  $tenant
     * @return mixed
     */
    public function tableFind(
        $uid,
        $table,
        $tenant = null
    ) {
        $this->createTableIfNotExists($tenant, $table);
        $tn    = $this->getTable();
        $query = $this->query();
        $query = $query->setModel($this);
        $item  = $query->where($this->getUidName(), $uid)->first();
        if (isset($item)) {
            $item->setTableName($tenant, $table);
        }

        return $item;
    }

    protected function shouldSkip()
    {
        return false;
    }
}
