<?php

namespace niiknow\laratt;

class TenancyResolver
{
    /**
     * create a tenant slug
     *
     * @param  string $tenant tenant id
     * @return string         the slug
     */
    public static function slug($tenant)
    {
        return preg_replace('/[^a-z0-9]+/i', '', mb_strtolower($tenant));
    }

    /**
     * Method for resolving tenant
     * @return
     */
    public static function resolve()
    {
        // @codeCoverageIgnoreStart
        $resolver = config('laratt.resolver', '');

        if (!empty($resolver)
            && (strpos($resolver, "niiknow") === false)
            && is_callable($resolver)) {
            $tenant = call_user_func($resolver);
        } else {
            $tenant = request()->header('x-tenant');
        }

        if (!isset($tenant)) {
            $tenant = "";
        }
        // @codeCoverageIgnoreEnd

        return self::slug($tenant);
    }
}
