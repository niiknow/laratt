<?php

namespace Niiknow\Laratt\Traits;

use Niiknow\Laratt\Models\TableModel;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Validator;

use League\Csv\Reader;
use Yajra\DataTables\DataTables;
use Niiknow\Laratt\RequestQueryBuilder;

use Niiknow\Laratt\TenancyResolver;
use Niiknow\Laratt\LarattException;
use Niiknow\Laratt\TableExporter;

trait ApiTableTrait
{
    /**
     * Get the model for the controller.  Method allow
     * for overriding with custome model.
     *
     * @param  array  $attrs initial model attributes
     * @return object        the model
     */
    public function getModel($attrs = [])
    {
        return new TableModel($attrs);
    }

    /**
     * Get the table name for the current controller.  It
     * allow for overriding of the table name.
     *
     * @return string   table name for the controller
     */
    public function getTable()
    {
        $table = request()->route('table');

        // length must be greater than 3 and less than 30
        // reserved tables: profile, user, recipe, tables
        $rules = [
            'table' => 'required|regex:/[a-z0-9_]{3,30}/|not_in:profile,user,recipe,tables'
        ];

        Validator::make(['table' => $table], $rules)->validate();

        return $table;
    }

    protected function getUid(Request $request)
    {
        return $request->route('uid');
    }

    protected function getUidField()
    {
        return 'uid';
    }

    /**
     * helper method to return response
     *
     * @param  integer $code the http response code
     * @param  object  $rsp  the response object
     * @return Response       the http response
     */
    public function rsp($code, $rsp = null)
    {
        if ($code == 404) {
            return response()->json([ "error" => "not found" ], 404);
        }

        if ($code == 422) {
            return response()->json([ "error" => $rsp ]);
        }

        return response()->json($rsp, $code);
    }

// <begin_controller_actions
    /**
     * create a record
     *
     * @param  Request $request http request
     * @return object        the created object
     */
    public function create(Request $request)
    {
        return $this->update($request, null);
    }

    /**
     * retrieve a record
     *
     * @param  string $uid     the object id
     * @return object     the found object or 404
     */
    public function retrieve(Request $request)
    {
        $table = $this->getTable();
        $uid   = $this->getUid($request);
        $item  = $this->getModel()->tableFind($uid, $table);
        return isset($item) ? $item : $this->rsp(404);
    }

    /**
     * delete a record
     *
     * @param  string  $uid     the object id
     * @return object     found and deleted, error, or 404
     */
    public function delete(Request $request)
    {
        $table = $this->getTable();
        $uid   = $this->getUid($request);
        $item  = $this->getModel()->tableFind($uid, $table);

        if ($item && !$item->delete()) {
            throw new LarattException(__('exceptions.tables.delete'));
        }

        return isset($item) ? $item : $this->rsp(404);
    }

    /**
     * list record by query parameter
     *
     * @param  Request $request http request
     * @return object     list result
     */
    public function list(Request $request)
    {
        $table = $this->getTable();
        $item  = $this->getModel();
        $item->createTableIfNotExists(TenancyResolver::resolve(), $table);

        $qb = new RequestQueryBuilder(\DB::table($item->getTable()));
        return $qb->applyRequest($request);
    }

    /**
     * jQuery datatables endpoint
     *
     * @param  Request $request http request
     * @return object     datatable result
     */
    public function data(Request $request)
    {
        $table = $this->getTable();
        $item  = $this->getModel();
        $item->createTableIfNotExists(TenancyResolver::resolve(), $table);
        $dt            = DataTables::of(\DB::table($item->getTable()));
        $action        = $request->query('action');
        $escapeColumns = $request->query('escapeColumns');

        if (!isset($encode)) {
            $dt = $dt->escapeColumns([]);
        }

        if (isset($action)) {
            // disable paging if length is not set
            if (!$request->query('length')) {
                $dt = $dt->skipPaging();
            }

            // validate action must be in xlsx, ods, csv
            $request->validate(['action' => 'required|in:xlsx,ods,csv']);
            $query = $dt->getFilteredQuery();
            $file  = $table . '-' . time() . '.' . $action;
            return \Maatwebsite\Excel\Facades\Excel::download(
                new TableExporter($query, $item),
                $file
            );
        }

        return $dt->make(true);
    }

    /**
     * update or insert a record
     *
     * @param  string  $uid     the object id
     * @param  Request $request http request
     * @return object     new record, updated record, or error
     */
    public function update(Request $request)
    {
        $table = $this->getTable();
        $uid   = $this->getUid($request);

        $rules     = array();
        $rules     = $this->vrules;
        $inputs    = $request->all();
        $validator = Validator::make($inputs, $rules);

        if ($validator->fails()) {
            return $this->rst(422, $validator->errors());
        }

        $data   = $request->all();
        $inputs = array();
        foreach ($data as $key => $value) {
            array_set($inputs, $key, $value);
        }

        $item = $this->getModel($inputs);
        if (isset($uid)) {
            $input[$this->getUidField()] = $uid;
            $item                        = $item->tableFill($uid, $inputs, $table);

            // if we cannot find item, do insert
            if (!isset($item)) {
                $item = $this->getModel($inputs)->tableCreate($table);
            }
        } else {
            $item = $item->tableCreate($table);
        }

        if (!$item->save()) {
            throw new LarattException(__('exceptions.tables.update'));
        }

        return $item;
    }

    /**
     * import a csv file
     *
     * @param  UploadedFile    $file    the file
     * @param  Request         $request http request
     * @return object           import result
     */
    public function import(Request $request)
    {
        $table = $this->getTable();

        // validate that the file import is required
        $inputs    = $request->all();
        $validator = Validator::make($inputs, ['file' => 'required']);

        if ($validator->fails()) {
            return $this->rst(422, $validator->errors());
        }

        // $start_memory = memory_get_usage();
        // \Log::info("import - processing: $start_memory");

        $file = $request->file('file')->openFile();
        $csv  = \League\Csv\Reader::createFromFileObject($file)
            ->setHeaderOffset(0);

        $data     = [];
        $importid = (string) Str::uuid();
        $model    = $this->getModel()->tableCreate($table);

        $rsp = $model->processCsv(
            $csv,
            $data,
            $importid,
            $this->vrules
        );

        // $used_memory = (memory_get_usage() - $start_memory) / 1024 / 1024;
        // \Log::info("import - before save: $used_memory");

        if ($rsp['code'] === 422) {
            return $this->rsp(422, $rsp);
        }

        $rsp = $model->saveImport(
            $data,
            $this->getUidField(),
            $table
        );

        // $used_memory = (memory_get_usage() - $start_memory) / 1024 / 1024;
        // \Log::info("import - after save: $used_memory");

        if ($rsp['code'] === 422) {
            return $this->rsp(422, $rsp);
        }

        // import success response
        return $this->rsp(200, [
            'inserted'  => $rsp['inserted'],
            'updated'   => $rsp['updated'],
            'skipped'   => $rsp['skipped'],
            'import_id' => $importid
        ]);
    }

    /**
     * truncate a table
     *
     * @return object           truncate success or error
     */
    public function truncate()
    {
        $table = $this->getTable();
        $item  = $this->getModel();
        $item->createTableIfNotExists(TenancyResolver::resolve(), $table);

        \DB::table($item->getTable())->truncate();
        return $this->rsp(200);
    }

    /**
     * drop a table
     *
     * @return object           drop success or error
     */
    public function drop()
    {
        $table = $this->getTable();
        $item  = $this->getModel();
        $item->dropTableIfExists(TenancyResolver::resolve(), $table);

        return $this->rsp(200);
    }
// </end
}
