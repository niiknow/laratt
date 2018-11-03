<?php

namespace Niiknow\Laratt\Models;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;

use Niiknow\Laratt\Traits\CloudAuditable;
use Niiknow\Laratt\Traits\TableModelTrait;
use Carbon\Carbon;

class ProfileModel extends Authenticatable
{
    use CloudAuditable,
        TableModelTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uid', 'email', 'email_verified_at', 'password', 'image_url',
        'phone_country_code', 'phone', 'group', 'tfa_type', 'authy_id', 'authy_status',
        'tfa_code', 'tfa_exp_at',

        'email_alt', 'first_name', 'last_name', 'address1', 'address2',
        'postal', 'city', 'state', 'country', 'email_list_optin_at',
        'is_retired_or_unemployed', 'occupation', 'employer',

        'card_customer_id', 'card_brand', 'card_last4',
        'data', 'meta', 'seen_at', 'access', 'import_id'
    ];

    /**
     * @var array
     */
    protected $casts = [
        'meta' => 'array',
        'data' => 'array',
        'is_retired_or_unemployed' => 'boolean'
    ];

    /**
     * The attributes that should be casted by Carbon
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'password_updated_at',
        'email_verified_at',
        'tfa_exp_at',
        'seen_at'
    ];

    /**
     * generated attributes
     *
     * @var array
     */
    protected $appends = ['name'];

    public function createTableIfNotExists($tenant, $tableName = 'profile')
    {
        $tableName = 'profile';
        $tableNew  = $this->setTableName($tenant, $tableName);

        // only need to improve performance in prod
        if (config('env') === 'production' && \Cache::has('tnc_'.$tableNew)) {
            return $tableNew;
        }

        if (!Schema::hasTable($tableNew)) {
            Schema::create($tableNew, function (Blueprint $table) {
                $table->increments('id');

                // client/consumer/external primary key
                // this allow client to prevent duplicate
                // for example, duplicate during bulk import
                $table->string('uid', 50)->unique();

                // the list of fields below has been carefully
                // choosen to support profile including
                // ecommerce and federal donation system
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('seen_at')->nullable();

                $table->string('password')->nullable();
                $table->timestamp('password_updated_at')->nullable();

                // profile image and two factor auth phone
                $table->string('image_url')->nullable();
                $table->string('phone_country_code', 5)->default('1');
                $table->string('phone', 20)->nullable();

                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();

                // field allowing for two factor auth
                $table->enum('tfa_type', [
                    'off', 'email', 'sms', 'call', 'google_soft_token', 'authy_soft_token', 'authy_onetouch'
                ])->default('off');
                $table->string('authy_id')->unique()->nullable();
                $table->string('authy_status')->nullable();
                $table->string('tfa_code')->nullable();
                $table->timestamp('tfa_exp_at')->nullable();

                // member, admin, etc...
                $table->string('group')->nullable()->index();
                $table->string('access')->default('member');

                // federally required donation contact info
                $table->string('email_alt')->nullable();
                $table->string('address1')->nullable();
                $table->string('address2')->nullable();
                $table->string('postal', 50)->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('country')->nullable();
                $table->double('lat', 11, 8)->nullable();
                $table->double('lng', 11, 8)->nullable();

                // email subscription and federal required donation contact
                $table->timestamp('email_list_optin_at')->nullable();
                $table->boolean('is_retired_or_unemployed')->default(0);
                $table->string('occupation')->nullable();
                $table->string('employer')->nullable();

                $table->string('card_customer_id')->nullable();
                $table->string('card_brand', 50)->nullable();
                $table->string('card_last4', 4)->nullable();

                $table->timestamps();

                // extra meta to store things like social provider
                $table->mediumText('meta')->nullable();
                // extra data/attribute about the user
                $table->mediumText('data')->nullable();
                $table->uuid('import_id')->nullable()->index();
            });

            // cache database check for 45 minutes
            \Cache::add('tnc_'.$tableNew, 'true', 45);
        }

        return $tableNew;
    }

    public function setEmailAttribute($value)
    {
        $existing = $this->email;
        $new      = mb_strtolower($value);

        // reset authy id
        if ($existing != $new) {
            $this->attributes['email'] = $new;
            $this->authy_id            = null;
        }
    }

    public function setPhoneAttribute($value)
    {
        $existing = $this->phone;
        $new      = preg_replace('/\D+/', '', $value);

        if ($existing != $new) {
            $this->attributes['phone'] = $new;
            $this->authy_id            = null;
        }
    }

    public function setPhoneCountryCodeAttribute($value)
    {
        $this->attributes['phone_country_code'] = preg_replace('/[^0-9\+]/', '', $value);
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password']            = $value;
        $this->attributes['password_updated_at'] = Carbon::now();
    }

// <tfa
    public function getTfaCode()
    {
        $value = $this->attributes['tfa_code'];
        return $value;
    }

    public function setTfaCode($value = null)
    {
        if (!isset($value)) {
            $value = $this->generateTfaCode();
        }

        $this->attributes['tfa_code'] = $value;

        // set expire in 10 minutes
        $this->attributes['tfa_exp_at'] = Carbon::now()->addMinutes(10);
    }
// </tfa

    public function getImageUrlAttribute($value)
    {
        $defaultUrl = 'https://www.gravatar.com/avatar/'.md5(mb_strtolower($this->email)).'.jpg?s=200&d=mm';

        return !isset($value)
            || strlen($value) <= 0
            || strpos($value, "http") === false ? $defaultUrl : url($value);
    }

    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = ucfirst($value);
    }

    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = ucfirst($value);
    }

    public function getNameAttribute($value)
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
