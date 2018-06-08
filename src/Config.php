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

        // Enable query logging (true/false)
        'enabled'    => env('APP_DEBUG', false) && env('APP_ENV', 'local') == 'local',

        // If enabled, when running in console the listener handler will be forced
        'console_logging' => false,

        // Max files number of files to keep, logs are rotated daily
        'max_files'       => 10,

        // Type of handler to collect the query lots and action the log writer:
        // Options middleware or listener
        'handler'         => 'middleware',

        // Default logging location
        'log_path'        => storage_path('logs/db'),

        // Connections to log
        // Comma separated database connection names eg: mysql,pgsql,test
        // all            = all configured connections
        'connection'      => 'all',

        /*
         |----------------------------------------------------------------------
         | Log Formatters
         |----------------------------------------------------------------------
         |
         | Available tokens:
         |
         | All
         | - :env        = environment config variable at time
         | - :date       = date and time the log was writen
         | - :connection = database connection
         | - :label      = request label, URL for http requests, argv for console
         | - :handler    = DBLoJack handler, middleware or listener
         |
         | Query Only
         | - :time       = execution time of a query (not always available)
         | - :query      = formatted query
         |
         | Boundary entires
         | - :boundary    = boundary type, before or after
         |
         */

        // String format for single query (listener)
        'log_foramt_single' => '[:date] [:connection] [:env] :time ms ":query" ":label"',
        // String format for multiple queries being log at once (middleware)
        'log_foramt_multi'  => '[:connection] [:env] :time ms ":query"',

        // Log entries showing for grouping all the logs for single request
        // Leave empty or null to skip boundary
        'log_before_boundary' => '---------BOUNDARY :boundary-:handler [:env]---------' . "\n[:date] :label",
        'log_after_boundary'  => '---------BOUNDARY :boundary---------',

    ],

    /*
     |--------------------------------------------------------------------------
     | Lo-Jack Performance Watchdog
     |--------------------------------------------------------------------------
     |
     | Implements a monitoring system were logs are generated if a configured
     | threshold is exceeded.
     |
     */

    'performance_watchdog' => [

        // Default logging location
        'log_path'    => storage_path('logs/performance'),

        // Operating mode
        // - false      = turns off all monitoring
        // - 'summary'  = most light weight logs a single line per a request
        // - 'detailed' = logs debug details of every database query, good for development
        // - 'both'     = log summary and detailed
        'mode'        => false,

        // Size of mini back trace used in detailed log.
        // Can be integer or false to not log traces
        'trace'       => 10,

        // The minimum number of times a queries should be run before logging.
        'min_queries' => 2,

        // Threshold counters
        'threshold'   => [
            'time'    => 500,    // request total time in ms
            'queries' => 50,     // total query count per request
            'memory'  => '35MB', // request max memory
         ],

    ],

];