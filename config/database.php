<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        /*
        |--------------------------------------------------------------------------
        | Base de datos del Framework (Laravel)
        |--------------------------------------------------------------------------
        */

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
           'database' => env('DB_NEGOCIO_DATABASE'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],

        /*
        |--------------------------------------------------------------------------
        | Base de datos del Negocio (PagoFacil_VendingMachinete)
        |--------------------------------------------------------------------------
        */

        'mysqlNegocio' => [
            'driver' => 'mysql',
            'host' => env('DB_NEGOCIO_HOST', '127.0.0.1'),
            'port' => env('DB_NEGOCIO_PORT', '3306'),
            'database' => env('DB_NEGOCIO_DATABASE', 'forge'),
            'username' => env('DB_NEGOCIO_USERNAME', 'forge'),
            'password' => env('DB_NEGOCIO_PASSWORD', ''),
            'unix_socket' => env('DB_NEGOCIO_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],

    ],

    'migrations' => 'migrations',

];