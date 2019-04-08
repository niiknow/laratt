<?php

namespace Niiknow\Laratt\Traits;

use Niiknow\Laratt\Models\TableModel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Validator;

use Carbon\Carbon;
use League\Csv\Reader;
use Yajra\DataTables\DataTables;
use Niiknow\Laratt\RequestQueryBuilder;
use Niiknow\Laratt\TableExporter;

use Niiknow\Laratt\LarattException;
use Niiknow\Laratt\TenancyResolver;

trait ApiTableTrait
{
    /**
     * Tenant field identify if it's a table or a field
     *
     * @return string  null for table, value for tenant field name
     */
    protected function getTenantField()
    {
        return null;
    }

    /**
     * Override to provide the model for this controller
     *
     * @param  array  $attrs create and initialize new model
     * @return object        the model
     */
    protected function getModel($attrs = [])
    {
        $table = $this->getTableName();
        $item  = new TableModel($attrs);
        $item->createTableIfNotExists(
            TenancyResolver::resolve(),
            $table
        );

        return $item;
    }

    /**
     * Get the table name or tenant field value
     *
     * @return string   null for global, table, or value use to
     *                  search with getTenantField
     */
    protected function getTableName()
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

    /**
     * Overridable function get the query
     *
     * @return Builder the eloquent query builder object
     */
    protected function getQuery()
    {
        $model = $this->getModel();
        $query = $model->query();
        $query->setModel($model);

        $tf = $this->getTenantField();

        if (null !== $tf) {
            $tn    = $this->getTableName();
            $query = $query->where($tf, $tn);
        }

        return $query;
    }

    /**
     * Default id as uid
     *
     * @return string   the model id field
     */
    protected function getIdField()
    {
        return 'uid';
    }

    /**
     * Overridable function to exclude object properties
     *
     * @return Array list/array of properties to exclude from object
     */
    protected function getExcludes()
    {
        return [];
    }

    /**
     * Get relational includes.
     *
     * @return array  a list of object to includes
     */
    protected function getIncludes()
    {
        return [];
    }

    /**
     * Get all request inputs.
     *
     * @return array  all request inputs except getExcludes()
     */
    protected function requestAll(Request $request)
    {
        $inputs = $request->except($this->getExcludes());
        $tf     = $this->getTenantField();

        if ($tf !== null) {
            $tn = $this->getTableName();

            if (!isset($inputs[$tf])) {
                $inputs[$tf] = $tn;
            } elseif ($inputs[$tf] !== $tn) {
                throw new LarattException(__("exceptions.table.does_not_match"));
            }
        }

        // \Log::info($inputs);
        return $inputs;
    }

    /**
     * Find a model by id
     * @param  string  $id           the id
     * @param  boolean $loadIncludes true to load includes
     * @return object      the model
     */
    protected function findById($id, $loadIncludes = false)
    {
        $item = $this->getQuery()
            ->where($this->getIdField(), $id)->first();

        if ($loadIncludes && isset($item)) {
            $includes = $this->getIncludes();
            if (count($includes) > 0) {
                $item->load($includes);
            }
        }

        return $item;
    }

    /**
     * Get the id.
     *
     * @param  Request $request the request object
     * @return string           the id
     */
    protected function getId(Request $request)
    {
        $idf = $this->getIdField();
        $id  = $request->route($idf);

        if (!isset($id)) {
            $id = $request->input($idf);
        }

        return $id;
    }

    /**
     * helper method to return response
     *
     * @param  integer $code the http response code
     * @param  object  $rsp  the response object
     * @return Response       the http response
     */
    protected function rsp($code, $rsp = null)
    {
        if ($code == 404) {
            return response()->json([ "error" => "not found" ], 404);
        }

        if ($code == 422) {
            return response()->json([ "error" => $rsp ], 422);
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
        return $this->update($request);
    }

    /**
     * restore a soft-deleted record
     *
     * @param  Request $request http request
     * @return object        the restored object?
     */
    public function restore(Request $request)
    {
        $id    = $this->getId($request);
        $model = $this->getModel();
        $item  = call_user_func($model, 'withTrashed')
            ->findOrFail($id);

        $item->restore();
        return $item;
    }

    /**
     * retrieve a record
     *
     * @return object     the found object or 404
     */
    public function retrieve(Request $request)
    {
        $id   = $this->getId($request);
        $item = $this->findById($id, true);
        return isset($item) ? $item : $this->rsp(404);
    }

    /**
     * delete a record
     *
     * @param  string  $id     the object id
     * @return object     found and deleted, error, or 404
     */
    public function delete(Request $request)
    {
        $id   = $this->getId($request);
        $item = $this->findById($id);

        if ($item && !$item->delete()) {
            throw new LarattException(__('exceptions.record.delete'));
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
        $tf    = $this->getTenantField();
        $item  = $this->getModel();
        $query = \DB::table($item->getTable());
        if (null !== $tf) {
            $tn    = $this->getTenantName();
            $query = $query->where($tf, $tn);
        }

        $qb = new RequestQueryBuilder($query);
        return $qb->applyRequest($request);
    }

    /**
     * jQuery datatables endpoint
     *
     * @return object     datatable result
     */
    public function data(Request $request)
    {
        $tf    = $this->getTenantField();
        $item  = $this->getModel();
        $query = \DB::table($item->getTable());
        $table = explode('$', $item->getTable())[1];
        if (null !== $tf) {
            $tn    = $this->getTableName();
            $query = $query->where($tf, $tn);
        }

        // get the table name from model
        $dt            = DataTables::of($query);
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
     * @param  Request $request http request
     * @return object     new record, updated record, or error
     */
    public function update(Request $request)
    {
        $tf        = $this->getTenantField();
        $id        = $this->getId($request);
        $rules     = $this->vrules;
        $data      = $this->requestAll($request);
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return $this->rsp(422, $validator->errors());
        }

        $inputs = array();
        foreach ($data as $key => $value) {
            array_set($inputs, $key, $value);
        }

        if (null !== $tf) {
            $inputs[$tf] = $this->getTableName();
        }

        // \Log::info($inputs);
        $item = $this->getModel($inputs);

        if (isset($id)) {
            $input[$this->getIdField()] = $id;
            $item                       = $this->findById($id, true);

            // if we cannot find item, do insert
            if (isset($item)) {
                $item->fill($inputs);
            } else {
                $item = $this->getModel($inputs);
            }
        }

        if (!$item->save()) {
            throw new LarattException(__('exceptions.record.update'));
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
        // validate that the file import is required
        $inputs    = $this->requestAll($request);
        $validator = Validator::make($inputs, ['file' => 'required']);

        if ($validator->fails()) {
            return $this->rsp(422, $validator->errors());
        }

        // $start_memory = memory_get_usage();
        // \Log::info("import - processing: $start_memory");

        $file = $request->file('file')->openFile();
        $csv  = \League\Csv\Reader::createFromFileObject($file)
            ->setHeaderOffset(0);

        $data     = [];
        $importid = (string) Str::uuid();
        $model    = $this->getModel();

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
            $this->getTableName(),
            $this->getIdField()
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
        $item = $this->getModel();
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
        $item = $this->getModel();

        if (method_exists($item, 'dropTableIfExists')) {
            $item->dropTableIfExists(TenancyResolver::resolve(), $this->getTableName());
        }

        return $this->rsp(200);
    }
// </end
}
