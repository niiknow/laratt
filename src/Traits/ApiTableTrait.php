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
            if (!isset($request->query('length'))) {
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
     * process the csv records
     *
     * @param  array  $csv      the csv rows data
     * @param  array  &$data    the result array
     * @param  string $importid the importid id
     * @return object        null or response object if error
     */
    public function processCsv($csv, &$data, $importid)
    {
        $rowno = 0;
        $limit = config('laratt.import_limit', 999);
        foreach ($csv as $row) {
            $inputs = ['import_id' => $importid];

            // undot the csv array
            foreach ($row as $key => $value) {
                $cell = $value;
                if (!is_string($cell)) {
                    $cell = (string)$cell;
                }

                if ($cell === '' || $cell === 'null') {
                    $cell = null;
                } elseif (is_numeric($cell)) {
                    $cell = $cell + 0;
                }

                // undot array
                array_set($inputs, $key, $cell);
            }

            // validate data
            $validator = Validator::make($inputs, $this->vrules);

            // capture and provide better error message
            if ($validator->fails()) {
                return $this->rsp(
                    422,
                    [
                        "error" => $validator->errors(),
                        "rowno" => $rowno,
                        "row" => $inputs
                    ]
                );
            }

            $data[] = $inputs;
            if ($rowno > $limit) {
                // we must improve a limit due to memory/resource restriction
                return $this->rsp(
                    422,
                    ['error' => "Each import must be less than $limit records"]
                );
            }
            $rowno += 1;
        }
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

        $file = $request->file('file')->openFile();
        $csv  = \League\Csv\Reader::createFromFileObject($file)
            ->setHeaderOffset(0);

        $data     = [];
        $importid = (string) Str::uuid();
        $rst      = $this->processCsv($csv, $data, $importid);
        if ($rst) {
            return $rst;
        }

        $rst  = array();
        $item = $this->getModel();
        $item->createTableIfNotExists(TenancyResolver::resolve());

        // wrap import in a transaction
        \DB::transaction(function () use ($data, &$rst, $importid, $table) {
            $rowno = 0;
            foreach ($data as $inputs) {
                // get uid
                $uid  = isset($inputs[$this->getUidField()]) ? $inputs[$this->getUidField()] : null;
                $item = $this->getModel($inputs);
                if (isset($uid)) {
                    $inputs[$this->getUidField()] = $uid;
                    $item                         = $item->tableFill($uid, $inputs, $table);

                    // if we cannot find item, insert
                    if (!isset($item)) {
                        $item = $this->getModel($inputs);
                        $item = $item->tableCreate($table);
                    }
                } else {
                    $item = $item->tableCreate($table);
                }

                // disable audit for bulk import
                $item->setNoAudit(true);

                // something went wrong, error out
                if (!$item->save()) {
                    $this->rsp(
                        422,
                        [
                            "error" => "Error while attempting to import row",
                            "rowno" => $rowno,
                            "row" => $item,
                            "import_id" => $importid
                        ]
                    );

                    // throw exception to rollback transaction
                    throw new LarattException(__('exceptions.import'));
                }

                $rst[]  = $item->toArray();
                $rowno += 1;
            }
        });

        // import success response
        $out = array_pluck($rst, $this->getUidField());
        return $this->rsp(200, ["data" => $out, "import_id" => $importid]);
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
