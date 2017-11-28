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

## Versions

Developed and tested on Laravel 5.4 using PHP 5.6. Should work on other version, however currently un-tested.

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

__Optionall add the DB LoJack Facade__

```php
'aliases' => [
   'DBLog' => WebChefs\DBLoJack\Facades\DbLogger::class,
];
```

## Usage

### Handlers

There are two handlers available each different pros and cons.

#### Middleware (Default)

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

#### Event Listener

Uses a Laravel Event listener database events. Regardless of configurations this method is used in console / artisan applications.

__Pros__

1. Handles queries one at a time so is more memory efficient
1. Logs all queries regardless of setup

__Cons__

1. Does not log queries times consistently, often not provided by laravel
1. Multiple queries for one request are logged as separately making it more difficult to trace back to a specific request

__Setup__

Configure handler to `listener`.

### Facade / Developer

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

# Check if isEnabled
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

## Standards

* psr-1
* psr-2
* psr-4

## Contributing

All code submissions will only be evaluated and accepted as pull-requests. If you have any questions or find any bugs please feel free to open an issue.

## Credits

- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/web-chefs/db-lojack.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/web-chefs/db-lojack.svg?style=flat-square