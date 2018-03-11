<?php

namespace WebChefs\DBLoJack;

// PHP
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
use Log;
use Config;

class DBLoJack
{

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
        return Config::get('database.query_log.enabled');
    }

    /**
     * Get configured handler.
     *
     * @return string
     */
    public function getHandler()
    {
        return $this->app->runningInConsole() ? 'listener' : Config::get('database.query_log.handler');
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
        return $this->formatSql($query->toSql(), $query->getBindings());
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
    public function logQueries($queries, $connection = null)
    {
        Log::debug(t('Query Count #:count for :url', [
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
        $queryData      = [
            'query'    => $query->toSql(),
            'bindings' => $query->getBindings(),
        ];
        $queryStore     = $this->makeQueryStore([ $queryData ], $connectionName);

        $this->makeLogger($queryStore)->writeLogs();
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
     * @param  [type] $connectionName
     *
     * @return QueryStoreInterface
     */
    public function makeQueryStore(array $queries, $connectionName = null)
    {
        return new QueryCollection($queries, $connectionName);
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

}
