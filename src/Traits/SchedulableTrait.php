<?php
namespace Niiknow\Laratt\Traits;

use Carbon\Carbon;

/**
 * Schedulable trait expect an object to have started_at and ended_at date.
 *
 * Both dates are output as YYYY-MM-DD, which represent begin of day.
 * Therefore, if you want it to end on a date, you should set ended_at to the next date.
 */
trait SchedulableTrait
{
    public static function bootSchedulableTrait()
    {
        static::creating(function ($model) {
            if (!isset($model->started_at)) {
                $model->started_at = Carbon::now();
            }
        });
    }

    /**
     * @param $value
     */
    public function getEndedAtAttribute($value)
    {
        if (isset($value) && !empty($value)) {
            return Carbon::parse($value)->format('Y-m-d');
        }
    }

    /**
     * @param $value
     */
    public function getStartedAtAttribute($value)
    {
        if (isset($value) && !empty($value)) {
            return Carbon::parse($value)->format('Y-m-d');
        }
    }

    /**
     * @param $value
     */
    public function setEndedAtAttribute($value)
    {
        $this->attributes['ended_at'] = Carbon::parse($value)->startOfDay();
    }

    /**
     * @param $value
     */
    public function setStartedAtAttribute($value)
    {
        $this->attributes['started_at'] = Carbon::parse($value)->startOfDay();
    }
}
