<?php

namespace WebChefs\DBLoJack;

// PHP
use Exception;
use InvalidArgumentException;

// Package
use WebChefs\DBLoJack\QueryStores\QueryCollection;
use WebChefs\DBLoJack\Contracts\QueryStoreInterface;
use WebChefs\DBLoJack\Loggers\RotatingTextFileLogger;

// Framework
use Illuminate\Support\Str;
use Illuminate\Database\Query\Builder as QueryBulder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Foundation\Application;

// Aliases
use DB;
use Log;
use Config;

class DBLoJack
{

    const ALL_CONNECTIONS = 'all';

    /**
     * The application implementation.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Default Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Is Database Logging Enabled.
     *
     * @param string $handler
     *
     * @return boolean
     */
    public function isLogging($handler = null)
    {
        $enabled = $this->isEnabled();

        if (is_null($handler)) {
            return $enabled;
        }

        return $enabled && $this->getHandler() == $handler;
    }

    /**
     * Is the logging enabled.
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->config('query_log.enabled');
    }

    /**
     * Check if a connection should be logged or ignored.
     *
     * @param  string $connection
     * @return boolean
     */
    public function shouldLog($connection)
    {
        return in_array($connection, $this->connections());
    }

    /**
     * List of all allowed connections.
     *
     * @return array
     */
    public function connections()
    {
        $allowedConnections = $this->config('query_log.connection');

        if ($allowedConnections === static::ALL_CONNECTIONS) {
            return array_keys(Config::get('database.connections', []));
        }

        return explode(',', $allowedConnections);
    }

    /**
     * Get configured handler.
     *
     * @return string
     */
    public function getHandler()
    {
        // Only use listener when running in console
        if ($this->app->runningInConsole() && $this->config('query_log.console_logging')) {
            return 'listener';
        }

        return $this->config('query_log.handler');
    }

    /**
     * Get the DB LoJack config or sub section.
     *
     * @param  string $context
     *
     * @return mixed
     */
    public function config($context = 'query_log', $default = null)
    {
        $context = empty($context) ? '' : '.' . $context;
        return Config::get('db-lojack' . $context, $default);
    }

    /**
     * Extract sql and bindings and format to RAW sql.
     *
     * @param  Model|Builder $query
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function formatQuery($query)
    {
        $this->isQueryable($query);

        $bindings = [];
        $query    = $this->getQueryData($query);

        extract($query, EXTR_IF_EXISTS);
        return $this->formatSql($query, $bindings);
    }

    /**
     * Build a Raw SQL with bindings substitution.
     *
     * @param  string  $query
     * @param  array   $bindings
     *
     * @return string
     */
    public function formatSql($sql, $bindings)
    {
        $needle = '?';
        foreach ($bindings as $replace) {
            // Handle quoted data types
            if (!is_bool($replace) && !is_int($replace) && !is_float($replace) && Str::lower($replace) !== 'null') {
                $replace = "'$replace'";
            }

            // Handle boolean data types
            if (is_bool($replace)) {
                $replace = $replace ? 'true' : 'false';
            }

            $sql = Str::replaceFirst($needle, $replace, $sql);
        }
        return $sql;
    }

    /**
     * Format the label used in the log boundary.
     *
     * @return string
     */
    public function logLabel()
    {
        // Build current command
        if ($this->app->runningInConsole()) {
            $command = \Request::server('argv', null);
            if (is_array($command)) {
                $command = implode(' ', $command);
            }
            return $command;
        }

        return request()->fullUrl();
    }

    /**
     * Manually log a database query.
     *
     * @param  array    $queries
     *
     * @return void
     */
    public function logQueries(array $queries, $connection = null)
    {
        Log::debug($this->formatString('Query Count #:count for :url', [
            ':count' => count($queries),
            ':url'   => $this->logLabel(),
        ]));

        if (empty($queries)) {
            return;
        }

        $this->makeLogger($this->makeQueryStore($queries, $connection))->writeLogs();
    }

    /**
     * Log single Query
     *
     * @param  Model|Builder $query
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function logQuery($query)
    {
        $this->isQueryable($query);

        $connectionName = $query->getConnection()->getName();
        $queryData      = $this->getQueryData($query);
        $queryStore     = $this->makeQueryStore([ $queryData ], $connectionName);

        $this->makeLogger($queryStore)->writeLogs();
    }

    /**
     * Common way of preparing the sql and bindings into an array.
     *
     * @param  Model|Builder $query
     *
     * @return array
     */
    public function getQueryData($query)
    {
        $bindings = $query->getBindings();

        return [
            'query'    => $query->toSql(),
            'bindings' => $this->prepareBindings($query->getConnection(), $bindings),
        ];
    }

    /**
     * Prepare the bindings using the connection specific prepare method.
     *
     * @param  ConnectionInterface  $connection
     * @param  array                $bindings
     *
     * @return array
     */
    public function prepareBindings($connection, array $bindings)
    {
        if (is_string($connection)) {
            $connection = DB::connection($connection);
        }
        return $connection->prepareBindings($bindings);
    }

    /**
     * Die Dump of a formatted query.
     *
     * @param  Model|Builder $query
     *
     * @return void
     */
    public function debugQuery($query)
    {
        dd($this->formatQuery($query));
    }

    /**
     * Validate that a object is a queryable object.
     *
     * @param  Model|Builder  $query
     *
     * @return boolean
     * @throws InvalidArgumentException
     */
    public function isQueryable($query, $fail = true)
    {
        if (!($query instanceof Model || $query instanceof Builder || $query instanceof QueryBulder)) {
            if (!$fail) {
                return false;
            }

            throw new InvalidArgumentException(sprintf('Expects a instance of Query Builder or Eloquent Model "%s" given.', get_class($query)));
        }

        return true;
    }

    /**
     * QueryStore Factory building the default query store.
     *
     * @param  array  $queries
     * @param  string $connectionName
     *
     * @return QueryStoreInterface
     */
    public function makeQueryStore(array $queries, $connectionName = null)
    {
        $queries = collect($queries)->transform(function ($query) use ($connectionName) {
            if (is_array($query)) {
                $bindings = array_get($query, 'bindings', []);
                $bindings = $this->prepareBindings($connectionName, $bindings);
                array_set($query, 'bindings', $bindings);
            }
            // Handle query objects
            if (is_object($query) && $this->isQueryable($query)) {
                $query = $this->getQueryData($query);
            }
            return $query;
        });
        return new QueryCollection($queries->all(), $connectionName);
    }

    /**
     * LogWriter Factory building the default LogWriter
     *
     * @param  QueryStoreInterface $queries
     *
     * @return LogWriterInterface
     */
    public function makeLogger(QueryStoreInterface $queries)
    {
        return new RotatingTextFileLogger($queries);
    }

    /**
     * Build a string based on named tokens. String token modifier are supported.
     *
     * @ = escape
     * : = raw
     *
     * @param   string  $string
     * @param   array   $args
     *
     * @return  string
     */
    public function formatString($string, array $args = array())
    {
        // Transform arguments before inserting them.
        foreach ($args as $key => $value) {
            switch ($key[0]) {
                case '@':
                default:
                    // Escaped and placeholder.
                    $args[$key] = e($value);
                    break;

                case ':':
                    // Pass-through.
            }
        }
        return strtr($string, $args);
    }

    /**
     * Provide a mini Call Stack Trace, used by ddd().
     *
     * @param  integer $last
     *
     * @return string
     */
    public function simpleTrace($limit = 0)
    {
        $e = new Exception();
        $trace = explode("\n", $e->getTraceAsString());

        array_shift($trace); // remove call to this method
        array_pop($trace);   // remove {main}

        $length = count($trace);
        $result = array();

        for ($i = 0; $i < $limit; $i++)
        {
            if ($length == $i) {
                break;
            }

            // replace '#someNum' with '$i)', set the right ordering
            $result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' '));
        }
        return implode("\n\t", $result);
    }

}