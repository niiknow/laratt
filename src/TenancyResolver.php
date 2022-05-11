<?php
namespace Niiknow\Laratt;

class TenancyResolver
{
    /**
     * Method for resolving tenant
     *
     * @param  boolean $throwError throw error if tenant not found
     * @return string
     */
    public static function resolve($throwError = false)
    {
        // @codeCoverageIgnoreStart

        // attempt resolve by request
        $req    = request();
        $tenant = '';
        if (isset($req)) {
            $tenant = $req->header('x-tenant') ?? $req->query('x-tenant');
        }

        // attempt resolve by resolver
        if (!isset($tenant) || empty($tenant)) {
            $resolver = config('laratt.resolver', '');

            if (!empty($resolver)
                && (strpos($resolver, 'niiknow') === false)
                && is_callable($resolver)) {
                $tenant = call_user_func($resolver);
            }
        }

        // throw error if not found
        if (!isset($tenant) || empty($tenant)) {
            if ($throwError) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'x-tenant is required.');
            }
        }

        // @codeCoverageIgnoreEnd

        return self::slug($tenant);

        // @codeCoverageIgnoreEnd

        return self::slug($tenant);
    }

    /**
     * create a tenant slug
     *
     * @param  string $tenant tenant id
     * @return string the slug
     */
    public static function slug($tenant)
    {
        return preg_replace('/[^a-z0-9_]+/i', '', mb_strtolower($tenant));
    }
}
