<?php

return [
    'resolver'                => '',
    'export'                  => [
        'exts' => 'xlsx,ods,csv',
    ],
    'audit'                   => [
        'disk'    => env('AUDIT_DISK', 's3'),
        'bucket'  => env('AUDIT_BUCKET'),
        'include' => [
            'table'  => '.*',
            'tenant' => '.*',
        ],
        'exclude' => [
            'table'  => '(log.*|cache)',
            'tenant' => null,
        ],
    ],
    'import_limit'            => 9999,
    'api_return_soft_deletes' => false,
];
