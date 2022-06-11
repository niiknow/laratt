<?php

namespace Niiknow\Laratt\Traits;

use Carbon\Carbon;
use Niiknow\Laratt\AuditableEvent;

/**
 * Add ability to audit to the cloud - such as s3
 * Enable revision support on s3.
 */
trait AuditableTrait
{
    public static function bootAuditable()
    {
        static::created(function ($auditable) {
            $auditable->doAudit('create');
        });

        static::updated(function ($auditable) {
            $changes = $auditable->getDirty();
            $changed = [];
            foreach ($changes as $key => $value) {
                $record = [
                    'key' => $key,
                    'old' => $auditable->getOriginal($key),
                    'new' => $auditable->$key,
                ];

                // do not log sensitive data
                if (array_search($key, $auditable->hidden, true)) {
                    $record['old'] = '***HIDDEN***';
                    $record['new'] = '***HIDDEN***';
                }

                $changed[] = $record;
            }

            $auditable->doAudit('update', $changed);
        });

        static::deleted(function ($auditable) {
            $auditable->doAudit('delete');
        });
    }

    /**
     * Determine if cloud audit is enabled.
     *
     * @return bool false if not enabled
     */
    public function canAudit()
    {
        if ($this->getNoAudit()) {
            return false;
        }

        $iten = config('laratt.audit.include.tenant');
        $itab = config('laratt.audit.include.table');
        if (empty($iten) || empty($itab)) {
            return false;
        }

        $tn = $this->getTable();
        $parts = explode('$', $tn);
        if (empty($tn)
            || ! preg_match("/$iten/", $parts[0])
            || ! preg_match("/$itab/", $parts[1])) {
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
     * use to audit the current object.
     *
     * @param  string $action audit action
     * @param  array  $log    extra log info
     * @return object the current object
     */
    public function doAudit($action, $log = [])
    {
        $id = $this->id;
        $uid = $this->{$this->getUidName()};
        if (empty($id) || empty($uid)) {
            return $this;
        }

        $table = $this->getTable();
        $filename = "$table/$uid/index";

        return $this->auditableTrigger($action, $log, null, $filename);
    }

    /**
     * Obtain cloud audit metadata.
     *
     * @param  string $action audit action
     * @param  array  $log    extra log info
     * @return object the audit meta data
     */
    public function auditableBody($action, $log = [])
    {
        // $user    = null;
        $tn = $this->getTable();
        $parts = explode('$', $tn);
        $now = Carbon::now('UTC');
        $memuse = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        $body = [
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
            'uname'      => php_uname(),
        ];

        $request = request();
        if (isset($request)) {
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
                'route_query'    => $request->query(),
            ]);
        }

        return $body;
    }

    /**
     * trigger audit event.
     *
     * @param  string $action   audit action
     * @param  array  $log      extra log info
     * @param  string $model    the object
     * @param  string $filename the file name without extension, null is $timestamp-log.json
     * @return object the current object
     */
    public function auditableTrigger($action, $log = [], $model = null, $filename = null)
    {
        if (! $this->canAudit()) {
            return $this;
        }

        $table = $this->getTable();

        if (empty($filename)) {
            // timestamp in reverse chronological order
            // this allow for latest first
            $now = Carbon::now('UTC');
            $filename = "$table/".(9999 - $now->year).
                (99 - $now->month).
                (99 - $now->day).
                '_revts';
        } elseif (strpos($filename, $table.'/') === false) {
            $path = "$table/$filename";
        }

        $path = $filename.'.json';
        $body = $this->auditableBody($action, $log);

        if ($model) {
            $body['custom'] = $model;
        } else {
            $body['uid'] = $this->{$this->getUidName()};
            $body['model_id'] = $this->id;
            $body['model'] = $this->toArray();
            $body['extra'] = $this->getAuditableExtra() ?: [];
        }

        $data['path'] = $path;
        $data['body'] = $body;
        AuditableEvent::dispatch($data);

        return $this;
    }

    /**
     * override this function to provide extra audit data.
     * @return array Audit extra
     */
    public function getAuditableExtra()
    {
        return [];
    }
}
