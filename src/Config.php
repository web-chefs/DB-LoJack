<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Lo-Jack Query Log
     |--------------------------------------------------------------------------
     |
     | To debug database queries by logging to a text file found in
     | storage/logs. We should avoid running this in production.
     |
     */

    'query_log' => [

        // Enable query logging
        'enabled' => env('APP_DEBUG', false) && env('APP_ENV', 'local') == 'local',

        // Max files number of files to keep, logs are rotated daily
        'max_files' => 10,

        // Type of handler to collect the query lots and action the log writer:
        // Options middleware or listener
        'handler' => 'middleware',

        // Default logging location
        'log_path' => storage_path('logs/db'),

    ],

];