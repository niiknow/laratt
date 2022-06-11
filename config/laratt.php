<?php

return [
    'resolver'                => '',
    'export'                  => [
        'exts' => 'xlsx,ods,csv',
    ],
    'audit'                   => [
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
