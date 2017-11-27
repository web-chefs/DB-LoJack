<?php

namespace WebChefs\DBLoJack;

// PHP
use InvalidArgumentException;

// Package
use WebChefs\DBLoJack\DBLoJackLogWriter;

// Framework
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

// Aliases
use Log;
use Config;

class DBLoJackHelpers
{

    /**
     * Is Database Logging Enabled.
     *
     * @param string $type
     *
     * @return boolean
     */
    public function isLogging($type = null)
    {
        $enabled = Config::get('database.query_log');

        if (is_null($type)) {
            return $enabled;
        }

        return $enabled && Config::get('database.query_log_type') == $type;
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
        return $this->formatSql($query->toSql(), $query->getBuindings());
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
            if (!is_bool($replace) && !is_numeric($replace) && Str::lower($replace) !== 'null') {
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
        if (app()->runningInConsole()) {
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
        Log::debug(t('Request Query Count #:count for :url', [
            ':count' => count($queries),
            ':url'   => $this->logLabel(),
        ]));

        if (empty($queries)) {
            return;
        }

        (new DBLoJackLogWriter($queries, $connection))->writeLogs();
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
        if (!($query instanceof Model || $query instanceof Builder)) {
            if (!$fail) {
                return false;
            }

            throw new InvalidArgumentException('Expects a instance of Query Builder or Eloquent Model.');
        }

        return true;
    }

}