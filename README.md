# DB-LoJack

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

Laravel database query logger and debugger that support basic argument replacement.

## Features

1. Basic Query binding replacement
1. Middleware and Listener handlers
1. Security information hiding in production and staging environments
1. Request query count logging
1. Logs web and console queries
1. DBLog facade for easy developer query debugging
1. Configurable
1. Default text logger support log file rotating without any dependencies on other packages

## Versions

Confirmed to be working:

* Laravel 5.3 on PHP 5.6
* Laravel 5.4 on PHP 5.6, 7.0
* Laravel 5.5 on PHP 7.0, 7.1
* Laravel 5.6 on PHP 7.1

## Install

__Via Composer__

``` bash
$ composer require web-chefs/db-lojack
```

__Add Service Provider to `config/app.php`__

```php
'providers' => [
   // Other Service Providers
   WebChefs\DBLoJack\DBLoJackServiceProvider::class,
];
```

__Optionall add the DB LoJack Facade__

```php
'aliases' => [
   'DBLog' => WebChefs\DBLoJack\Facades\DbLogger::class,
];
```

## Handlers

There are two handlers available each different pros and cons.

### Middleware (Default)

Uses Laravel HTTP kernel middleware to enable database query logging and at the end of the request logs all queries as single block.

__Pros__

1. Logs all queries as single block allowing you to trace queries to a specific request
1. Logs query time in milliseconds for each query

__Cons__

1. Does not work for console commands (See Event Listener)
1. If large number of queries are executed in a request the tracking of the query data by Laravel can consume a large amount of memory, Middleware method should not be used for long running processes or requests
1. If not the first middleware some queries can be missed, should be setup to be above any middleware that make database calls

__Setup__

Configure handler to `middleware`.

Add Middleware to `App\Http\Kernel`

```php
    protected $middleware = [
        \WebChefs\DBLoJack\Middleware\QueryLoggingMiddleware::class,
    ];
```

### Event Listener

Uses a Laravel Event listener database events. Regardless of configurations this method is used in console / artisan applications.

__Pros__

1. Handles queries one at a time so is more memory efficient
1. Logs all queries regardless of setup

__Cons__

1. Does not log queries times consistently, often not provided by laravel
1. Multiple queries for one request are logged as separately making it more difficult to trace back to a specific request

__Setup__

Configure handler to `listener`.

## Developer Usage (Facade)

```php
# Get formated query builder query
$query = DB::table('users')->where('id', 1);
print DBLog::formatQuery($query);

# Get formated model query
$model = \App\User::where('id', 1);
print DBLog::formatQuery($model);

# Format raw sql and bindings
print DBLog::formatSql($query->toSql(), $query->getBindings());

# Logs single query or model
DBLog::logQuery($query);

# Debug query using dd();
DBLog::debugQuery($query);

# Check if enabled
DBLog::isEnabled();

# Check if will log for specific handler
DBLog::isLogging('middleware');

# Get configured handler
DBLog::getHandler();

# Check if an object is queryable and loggable throwing an exception
DBLog::isQueryable($query);

# Check if an object is queryable and loggable returning a boolean
DBLog::isQueryable($query, false);
```

## Logs

__Query Logs__

Query logs will log individual queries to a text file rotated daily.

EG: `storage/logs/db/db_query.console.2018-06-06.log`.

__Performance Watchdog Logs__

A `PerformanceWatchdog` class will collect statistics and queries and if a metric threshold is equaled or exceeded it will write a log.

**Summary Log:**

Records a single line per a request of the totals.

```
20180608 12:06:12: Time: 5ms, Version: 7.0.14, DB Queries: 8, Memory: 14 MiB, Request: "CLI: ./vendor/bin/phpunit"
20180608 12:07:35: Time: 10ms, Version: 7.0.14, DB Queries: 25, Memory: 35 MiB, Request: "http://localhost/login"
```

**Detailed Log:**

A log of each query run including with counters for each query and a mini stacktrace for each unique path to that query.

```
START===========================================================================
Date:           20180608 12:06:12
Time:           54ms
Version:        7.0.14
Request:        "CLI: ./vendor/bin/phpunit"
Memory Start:       10 MiB
Memory Current:     14 MiB
Memory Usage:       3.18 MiB
Memory Max Peak:    13.18 MiB
DB Queries:         8
--------------------------------------------------------------------------------
Usage Count:        2
Trace Count:        2
--------------------------------------------------------------------------------
select * from sqlite_master where type = 'table' and name = ?
--------------------------------------------------------------------------------

1) /var/www/site/vendor/WebChefs/DB-LoJack/src/PerformanceWatchdog.php(497): Illuminate\Support\Facades\Facade::__callStatic('simpleTrace', Array)
    2) /var/www/site/vendor/WebChefs/DB-LoJack/src/DBLoJackServiceProvider.php(70): WebChefs\DBLoJack\PerformanceWatchdog->logQuery('select * from s...')
    3) /var/www/site/vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php(348): WebChefs\DBLoJack\DBLoJackServiceProvider->WebChefs\DBLoJack\{closure}(Object(Illuminate\Database\Events\QueryExecuted))
    4) /var/www/site/vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php(199): Illuminate\Events\Dispatcher->Illuminate\Events\{closure}('Illuminate\\Data...', Array)
    5) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(808): Illuminate\Events\Dispatcher->dispatch('Illuminate\\Data...')
    6) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(665): Illuminate\Database\Connection->event(Object(Illuminate\Database\Events\QueryExecuted))
    7) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(618): Illuminate\Database\Connection->logQuery('select * from s...', Array, 0.52)
    8) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(326): Illuminate\Database\Connection->run('select * from s...', Array, Object(Closure))
    9) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Schema/Builder.php(72): Illuminate\Database\Connection->select('select * from s...', Array)
    10) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Migrations/DatabaseMigrationRepository.php(154): Illuminate\Database\Schema\Builder->hasTable('migrations')

1) /var/www/site/vendor/WebChefs/DB-LoJack/src/PerformanceWatchdog.php(497): Illuminate\Support\Facades\Facade::__callStatic('simpleTrace', Array)
    2) /var/www/site/vendor/WebChefs/DB-LoJack/src/DBLoJackServiceProvider.php(70): WebChefs\DBLoJack\PerformanceWatchdog->logQuery('select * from s...')
    3) /var/www/site/vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php(348): WebChefs\DBLoJack\DBLoJackServiceProvider->WebChefs\DBLoJack\{closure}(Object(Illuminate\Database\Events\QueryExecuted))
    4) /var/www/site/vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php(199): Illuminate\Events\Dispatcher->Illuminate\Events\{closure}('Illuminate\\Data...', Array)
    5) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(808): Illuminate\Events\Dispatcher->dispatch('Illuminate\\Data...')
    6) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(665): Illuminate\Database\Connection->event(Object(Illuminate\Database\Events\QueryExecuted))
    7) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(618): Illuminate\Database\Connection->logQuery('select * from s...', Array, 0.06)
    8) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(326): Illuminate\Database\Connection->run('select * from s...', Array, Object(Closure))
    9) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Schema/Builder.php(72): Illuminate\Database\Connection->select('select * from s...', Array)
    10) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Migrations/DatabaseMigrationRepository.php(154): Illuminate\Database\Schema\Builder->hasTable('migrations')

--------------------------------------------------------------------------------
Usage Count:        2
Trace Count:        2
--------------------------------------------------------------------------------
delete from "jobs" where "id" = ?
--------------------------------------------------------------------------------

1) /var/www/site/vendor/WebChefs/DB-LoJack/src/PerformanceWatchdog.php(497): Illuminate\Support\Facades\Facade::__callStatic('simpleTrace', Array)
    2) /var/www/site/vendor/WebChefs/DB-LoJack/src/DBLoJackServiceProvider.php(70): WebChefs\DBLoJack\PerformanceWatchdog->logQuery('delete from "jo...')
    3) /var/www/site/vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php(348): WebChefs\DBLoJack\DBLoJackServiceProvider->WebChefs\DBLoJack\{closure}(Object(Illuminate\Database\Events\QueryExecuted))
    4) /var/www/site/vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php(199): Illuminate\Events\Dispatcher->Illuminate\Events\{closure}('Illuminate\\Data...', Array)
    5) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(808): Illuminate\Events\Dispatcher->dispatch('Illuminate\\Data...')
    6) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(665): Illuminate\Database\Connection->event(Object(Illuminate\Database\Events\QueryExecuted))
    7) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(618): Illuminate\Database\Connection->logQuery('delete from "jo...', Array, 0.05)
    8) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(477): Illuminate\Database\Connection->run('delete from "jo...', Array, Object(Closure))
    9) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(428): Illuminate\Database\Connection->affectingStatement('delete from "jo...', Array)
    10) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(2225): Illuminate\Database\Connection->delete('delete from "jo...', Array)

1) /var/www/site/vendor/WebChefs/DB-LoJack/src/PerformanceWatchdog.php(497): Illuminate\Support\Facades\Facade::__callStatic('simpleTrace', Array)
    2) /var/www/site/vendor/WebChefs/DB-LoJack/src/DBLoJackServiceProvider.php(70): WebChefs\DBLoJack\PerformanceWatchdog->logQuery('delete from "jo...')
    3) /var/www/site/vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php(348): WebChefs\DBLoJack\DBLoJackServiceProvider->WebChefs\DBLoJack\{closure}(Object(Illuminate\Database\Events\QueryExecuted))
    4) /var/www/site/vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php(199): Illuminate\Events\Dispatcher->Illuminate\Events\{closure}('Illuminate\\Data...', Array)
    5) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(808): Illuminate\Events\Dispatcher->dispatch('Illuminate\\Data...')
    6) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(665): Illuminate\Database\Connection->event(Object(Illuminate\Database\Events\QueryExecuted))
    7) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(618): Illuminate\Database\Connection->logQuery('delete from "jo...', Array, 0.02)
    8) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(477): Illuminate\Database\Connection->run('delete from "jo...', Array, Object(Closure))
    9) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Connection.php(428): Illuminate\Database\Connection->affectingStatement('delete from "jo...', Array)
    10) /var/www/site/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(2225): Illuminate\Database\Connection->delete('delete from "jo...', Array)

END=============================================================================
```

## Configurations

To customize the configuration publish the `config/db-lojack.php`.

```
php artisan vendor:publish --provider="WebChefs\DBLoJack\DBLoJackServiceProvider" --tag="config"
```

__Default Config__

```php
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
            'time'    => 1000,   // request total time in ms
            'queries' => 100,    // total query count per request
            'memory'  => '35MB', // request max memory
         ],

    ],

];
```

## Security & Information Leaking

It is generally a very bad idea to log full database queries in production with actual parameters / bindings as this will end up logging sensitive information like usernames, passwords and sessions ids to a generally low security location in the form of application logs.

For this reason if the Laravel environment is set to `production` or `staging` queries will be logged but without bindings being replace and queries will be left with `?` placeholders.

## Contributing

All code submissions will only be evaluated and accepted as pull-requests. If you have any questions or find any bugs please feel free to open an issue.

## Credits

- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/web-chefs/db-lojack.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/web-chefs/db-lojack.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/web-chefs/db-lojack
[link-downloads]: https://packagist.org/packages/web-chefs/db-lojack
[link-contributors]: ../../contributors
