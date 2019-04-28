<?php
namespace Niiknow\Laratt\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage as Storage;

/**
 * Add ability to audit to the cloud - such as s3
 * Enable revision support on s3
 */
trait CloudAuditable
{
    public static function bootCloudAuditable()
    {
        static::created(function ($auditable) {
            $auditable->cloudAudit('create');
        });

        static::updated(function ($auditable) {
            $changes = $auditable->getDirty();
            $changed = [];
            foreach ($changes as $key => $value) {
                $record = [
                    'key' => $key,
                    'old' => $auditable->getOriginal($key),
                    'new' => $auditable->$key
                ];

                // do not log sensitive data
                if (array_search($key, $auditable->hidden, true)) {
                    $record['old'] = '***HIDDEN***';
                    $record['new'] = '***HIDDEN***';
                }

                $changed[] = $record;
            }

            $auditable->cloudAudit('update', $changed);
        });

        static::deleted(function ($auditable) {
            $auditable->cloudAudit('delete');
        });
    }

    /**
     * Determine if cloud audit is enabled.
     *
     * @return boolean false if not enabled
     */
    public function canCloudAudit()
    {
        if ($this->getNoAudit()) {
            return false;
        }

        $disk = config('laratt.audit.disk');
        if (!isset($disk) || strlen($disk) <= 0) {
            return false;
        }

        $iten = config('laratt.audit.include.tenant');
        $itab = config('laratt.audit.include.table');
        if (!isset($iten) || !isset($itab)) {
            return false;
        }

        $tn    = $this->getTable();
        $parts = explode('$', $tn);
        if (!preg_match("/$iten/", $parts[0])
            || !preg_match("/$itab/", $parts[1])) {
            return false;
        }

        $xten = config('laratt.audit.exclude.tenant');
        $xtab = config('laratt.audit.exclude.table');

        if (isset($xten) && preg_match("/$xten/", $parts[0])) {
            return false;
        }

        if (isset($xtab) && preg_match("/$xtab/", $parts[1])) {
            return false;
        }

        return true;
    }

    /**
     * use to audit the current object
     *
     * @param  string $action audit action
     * @param  array  $log    extra log info
     * @return object the current object
     */
    public function cloudAudit($action, $log = [])
    {
        $id  = $this->id;
        $uid = $this->uid;
        if (!isset($id) || !isset($uid)) {
            return;
        }

        if ($this->canCloudAudit()) {
            $table    = $this->getTable();
            $filename = "$table/$uid/index";

            return $this->cloudAuditWrite($action, $log, null, $filename);
        }
    }

    /**
     * Obtain cloud audit metadata.
     *
     * @param  string $action audit action
     * @param  array  $log    extra log info
     * @return object the audit meta data
     */
    public function cloudAuditBody($action, $log = [])
    {
        // $user    = null;
        $tn      = $this->getTable();
        $parts   = explode('$', $tn);
        $request = request();
        $route   = $request->route();
        $now     = Carbon::now('UTC');
        $memuse  = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        $body    = [
            // unique id allow for event idempotency/nonce key
            'app_name'   => config('app.name'),
            'class_name' => get_class($this),
            'table_name' => $tn,
            'tenant'     => $parts[0],
            'table'      => $parts[1],
            'action'     => $action,
            'log'        => $log,
            'created_at' => $now->timestamp,
            'mem_use'    => $memuse,
            'uname'      => php_uname()
        ];

        if ($route !== null) {
            // $user         = $request->user();
            $route_params = $route->parameters();

            $body = array_merge($body, [
                // 'user'          => $user,
                'id_address'     => $request->ip(),
                'user_agent'     => $request->userAgent(),
                'referer'        => $request->header('referer'),
                'locale'         => $request->header('accept-language'),
                'forwarded_for'  => $request->header('x-forwarded-for'),
                'content_type'   => $request->header('content-type'),
                'content_length' => $request->header('content-length'),
                'device_type'    => $request->device_type,
                'os'             => $request->os,
                'browser'        => $request->browser,
                'client'         => $request->client_information,
                'host'           => $request->getHttpHost(),
                'method'         => $request->method(),
                'path'           => $request->path(),
                'url'            => $request->url(),
                'full_url'       => $request->fullUrl(),
                'route_action'   => $request->route()->getActionName(),
                'route_query'    => $request->query(),
                'route_params'   => $route_params
            ]);
        }

        return $body;
    }

    /**
     * write to cloud - allow to override or special audit
     * per example use in bulk import
     *
     * @param  string $action   audit action
     * @param  array  $log      extra log info
     * @param  string $model    the object
     * @param  string $filename the file name without extension, null is $timestamp-log.json
     * @return object the current object
     */
    public function cloudAuditWrite($action, $log = [], $model = null, $filename = null)
    {
        $table = $this->getTable();

        if (!isset($filename)) {
            // timestamp in reverse chronological order
            // this allow for latest first
            $now      = Carbon::now('UTC');
            $filename = "$table/" . (9999 - $now->year) .
                (99 - $now->month) .
                (99 - $now->day) .
                '_revts';
        } elseif (strpos($filename, $table . '/') === false) {
            $path = "$table/$filename";
        }

        $path = $filename . '.json';
        $body = $this->cloudAuditBody($action, $log);

        if ($model) {
            $body['custom'] = $model;
        } else {
            $body['uid']      = $this->uid;
            $body['model_id'] = $this->id;
            $body['model']    = $this->toArray();
            $body['info']     = $this->getCloudAuditInfo() ?: [];
        }

        $bucket = config('laratt.audit.bucket');
        if (!isset($bucket) || strlen($bucket) <= 0) {
            return $this;
        }

        $disk = config('laratt.audit.disk');
        if (!isset($disk) || strlen($disk) <= 0) {
            return $this;
        }

        // right now, only support s3
        \Storage::disk($disk)
            ->getDriver()
            ->getAdapter()
            ->getClient()
            ->upload(
                $bucket,
                $path,
                gzencode(json_encode($body)),
                'private',
                ['params' => [
                    'ContentType'     => 'application/json',
                    'ContentEncoding' => 'gzip'
                ]
                ]
            );

        return $this;
    }

    /**
     * override this function to provide extra audit data
     * @return Array Audit info
     */
    public function getCloudAuditInfo()
    {
        return [];
    }
}
