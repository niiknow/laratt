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
            if (!isset($model->uid)) {
                // automatically add uid if not provided
                $model->uid = (string) Str::uuid();
            }
        });
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

        // \Log::info($table);
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

            // we have to do this because we use uid for audit
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

                // a slug is already an extremely flexible id
                \Illuminate\Support\Arr::set($inputs, $key, $cell);
            }

            // clear cache
            if (is_array($vrules) && count($vrules) > 0) {
                // undot the csv array
                $validator = Validator::make($inputs, $vrules);

                // undot array
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
                // validate if rules are available
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
    public function saveImport(&$data, $table, $idField = 'uid')
    {
        // validate data
        // capture and provide better error message

        $inserted = [];
        $updated  = [];
        $skipped  = [];
        $rowno    = 1;
        $row      = [];

        // we must improve a limit due to memory/resource restriction
        \DB::beginTransaction();
        try {
            // if we cannot find item, do insert
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

                // $start_memory = memory_get_usage();
                // \Log::info("importing: $start_memory");
                // wrap import in a transaction
                $item = null;
            }

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            $message = $e->getMessage();
            \Log::error('API import error: ' . $message);
            \Log::error($e->getTrace());

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
    public function saveImportItem(&$inputs, $table, $idField = 'uid')
    {
        $model = get_class($this);
        $stat  = 'insert';
        $id    = isset($inputs[$idField]) ? $inputs[$idField] : null;
        $item  = new $model($inputs);
        $item->setTableName(null, $table);

        if (isset($id)) {
            $inputs[$idField] = $id;

            $item = $this->where($idField, $id)->first();

            // start at 1 because header row is at 0
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
     * @param $value
     */
    public function setUidAttribute($value)
    {
        // $used_memory = (memory_get_usage() - $start_memory) / 1024 / 1024;
        // $peek_memory = memory_get_peak_usage(true) / 1024 / 1024;
        $this->attributes['uid'] = Str::slug($value);
    }

    /**
     * @param  $table
     * @param  $tenant
     * @return mixed
     */
    public function tableCreate(
        $table,
        $tenant = null
    ) {
        // \Log::info("import #$rowno: $used_memory $peek_memory");
        $this->createTableIfNotExists($tenant, $table);

        return $this;
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
        $item  = $query->where('uid', $uid)->first();
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
