<?php

namespace WebChefs\DBLoJack;

use WebChefs\DBLoJack\PerformanceWatchdog;

class QueryLogItem
{
    public $key;

    public $sql;

    public $trace = [];

    public $count = 0;

    public function __construct($query)
    {
        $this->sql = $query;
        $this->key = PerformanceWatchdog::makeKey( $this->cleanQuery($query) );
    }

    public function increment()
    {
        $this->count++;
        return $this;
    }

    public function addTrace($trace)
    {
        if (is_null($trace)) {
            return;
        }

        $key = PerformanceWatchdog::makeKey($trace);
        $this->trace[ $key ] = $trace;

        return $this;
    }

    /**
     * Remove white space from a string, most commonly used for cleaning queries
     * to be on a single line safe for logging.
     *
     * @param  string $query
     *
     * @return string
     */
    public function cleanQuery($string)
    {
        return collect( explode("\n", $string) )
                ->transform(function($line) { return trim($line); })
                ->implode(' ');
    }

}