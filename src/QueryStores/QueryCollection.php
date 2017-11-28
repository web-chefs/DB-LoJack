<?php

namespace WebChefs\DBLoJack\QueryStores;

// Package
use WebChefs\DBLoJack\Contracts\AbstractQueryStore;

class QueryCollection extends AbstractQueryStore
{

    /**
     * @var Collection
     */
    protected $logs;

    /**
     * Prepare and format log entries.
     *
     * @return array|collection
     */
    public function formatLogs()
    {
        return $this->prepareQueryLogs();
    }

    /**
     * Log database queries and if needed rotate logs.
     *
     * @return null
     */
    protected function prepareQueryLogs()
    {
        // Only format the logs once
        if (!is_null($this->logs)) {
            return $this->logs;
        }

        // Retrieve all executed queries
        $queries = $this->queries;

        // If no queries exit early
        if ($this->queries->isEmpty()) {
            return;
        }

        // Setup Logs collection
        $this->logs = collect();

        // Start query log boundary
        $this->AddLogBoundary('Before');

        $this->queries->each(function(array $query) {

            $time     = null;
            $bindings = [];
            extract($query, EXTR_IF_EXISTS);

            if (!$this->isProduction) {
                $query = $this->helper->formatSql($query, $bindings);
            }

            // Create log entry
            $entry = collect();
            $entry->put('date', date('Y-m-d H:i:s', time()));
            $entry->put('time', $time);
            $entry->put('connection', $this->connectionName);
            $entry->put('query', $query);

            // Convert log entry to a string
            $logEntry = $entry->map(function($value, $key) { return sprintf("%s=%s", $key, $value); })
                              ->implode(', ');

            // Add log entry
            $this->logs->push($logEntry);

        });

        // End query log boundary
        $this->addLogBoundary('After');

        return $this->logs;
    }

    /**
     * Build a formated string for the boundary between log writing as a single
     * request / command can make multiple queries.
     */
    protected function addLogBoundary($type)
    {
        $env     = $this->app->make('env');
        $label   = $this->helper->logLabel();
        $handler = $this->helper->getHandler();

        $this->logs->push("---------BOUNDRY {$type}-{$handler} [{$env}] ({$label})---------");
    }

}