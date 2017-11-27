<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Enable Query Log
     |--------------------------------------------------------------------------
     |
     | To debug database queries by logging to a text file found in
     | storage/logs. We should avoid running this in production.
     |
     */

    // Enable query logging
    'query_log' => env('APP_DEBUG', false) && env('APP_ENV', 'local') == 'local',

    // Max files number of files to keep, logs are rotated daily
    'query_log_max_files' => 10,

     // Type of logger to use middleware or listener
    'query_log_type' => 'listener',

];