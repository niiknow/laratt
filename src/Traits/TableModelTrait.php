<?php

namespace Niiknow\Laratt\Traits;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Config;

use Illuminate\Support\Str;
use Validator;

use League\Csv\Reader;

use Niiknow\Laratt\TenancyResolver;

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
     * get no audit
     *
     * @return boolean true if not audit
     */
    public function getNoAudit()
    {
        return $this->no_audit;
    }

    /**
     * Set no audit attribute
     *
     * @param  boolean  $no_audit
     * @return $this
     */
    public function setNoAudit($no_audit)
    {
        $this->no_audit = $no_audit;

        return $this;
    }

    /**
     * get no_audit property
     *
     * @return boolean  if enable audit
     */
    public function getNoAuditAttribute()
    {
        return $this->getNoAudit();
    }

    /**
     * set no_audit property
     *
     * @return boolean  if enable audit
     */
    public function setNoAuditAttribute($value)
    {
        $this->setNoAudit($value);
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

        $newName = TenancyResolver::slug($tenant) . '$' . TenancyResolver::slug($tableName);

        $this->table = $newName;

        return $newName;
    }

    public function setUidAttribute($value)
    {
        // we have to do this because we use uid for audit
        // a slug is already an extremely flexible id
        $this->attributes['uid'] = Str::slug($value);
    }

    public function dropTableIfExists($tenant, $tableName)
    {
        $tableNew = $this->setTableName($tenant, $tableName);

        Schema::dropIfExists($tableNew);

        // clear cache
        \Cache::forget('tnc_'.$tableNew);
    }


    /**
     * process the csv records
     *
     * @param  array  $csv      the csv rows data
     * @param  array  &$data    the result array
     * @param  string $importid the importid id
     * @param  array  $vrules   the validation rules
     * @return object        null or response object if error
     */
    public function processCsv($csv, &$data, $importid, $vrules)
    {
        $rowno = 0;
        $limit = config('laratt.import_limit', 999);
        foreach ($csv as $row) {
            $inputs = [];

            // undot the csv array
            foreach ($row as $key => $value) {
                $cell = $value;
                if (!is_string($cell)) {
                    $cell = (string)$cell;
                }
                $cv = trim(mb_strtolower($cell));

                if ($cv === 'null'
                    || $cv === 'nil'
                    || $cv === 'undefined') {
                    $cell = null;
                } elseif (!is_string($value) && is_numeric($cv)) {
                    $cell = $cell + 0;
                }

                // undot array
                array_set($inputs, $key, $cell);
            }

            // validate data
            $validator = Validator::make($inputs, $vrules);

            // capture and provide better error message
            if ($validator->fails()) {
                return [
                    'code'  => 422,
                    'error' => $validator->errors(),
                    'rowno' => $rowno,
                    'row'   => $inputs
                ];
            }

            $data[] = $inputs;
            if ($rowno > $limit) {
                // we must improve a limit due to memory/resource restriction
                return [
                    'code'  => 422,
                    'error' => "Each import must be less than $limit records"
                ];
            }
            $rowno += 1;
        }

        return ['code' => 200];
    }

    public function saveImportItem(&$inputs, $idField = 'uid', $table)
    {
        $model = get_class($this);
        $stat  = 'insert';
        $id    = isset($inputs[$idField]) ? $inputs[$idField] : null;
        $item  = new $model($inputs);
        $item->tableCreate($table);

        if (isset($id)) {
            $inputs[$idField] = $id;

            $item = $this->where($idField, $id)->first();

            // if we cannot find item, do insert
            if (isset($item)) {
                $item->fill($inputs);
                $stat = 'update';
            } else {
                $item = new $model($inputs);
            }

            if (!isset($item[$idField])) {
                $stat = 'skip';
                return [$stat, null];
            }
        }

        // disable audit for bulk import
        $item->setNoAudit(true);

        return [$stat, $item->save() ? $item : null];
    }

    public function saveImport(&$data, $idField = 'uid', $table)
    {
        // $start_memory = memory_get_usage();
        // \Log::info("importing: $start_memory");

        $inserted = [];
        $updated  = [];
        $skipped  = [];

        // wrap import in a transaction
        \DB::beginTransaction();
        try {
            // start at 1 because header row is at 0
            $rowno = 1;
            foreach ($data as $inputs) {
                list($stat, $item) = $this->saveImportItem($inputs, $idField, $table);

                if (null === $item && $stat !== 'skip') {
                    // rollback transaction
                    \DB::rollback();

                    return [
                        'code'      => 422,
                        'error'     => 'Error while attempting to import row',
                        'rowno'     => $rowno,
                        'row'       => $inputs,
                        'import_id' => $importid
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
                $item   = null;

                // $used_memory = (memory_get_usage() - $start_memory) / 1024 / 1024;
                // $peek_memory = memory_get_peak_usage(true) / 1024 / 1024;
                // \Log::info("import #$rowno: $used_memory $peek_memory");
            }

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            $message = $e->getMessage();
            \Log::error('API import error: ' . $message);
            return [
                'code'  => 422,
                'error' => $message
            ];
        }

        return [
            'code' => 200,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped
        ];
    }
}
