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

        $logFormat = $this->helper->config('query_log.log_foramt_single');

        // Start query log boundary
        if ($queries->count() > 1) {
          $this->AddLogBoundary('before');
          $logFormat = $this->helper->config('query_log.log_foramt_multi');
        }


        $this->queries->each(function(array $query) use ($logFormat) {

            $time     = null;
            $bindings = [];
            extract($query, EXTR_IF_EXISTS);

            if (!$this->isProduction) {
                $query = $this->helper->formatSql($query, $bindings);
            }

            // Create log entry
            $logEntry = $this->formatLog($logFormat, [
              ':time'  => $time,
              ':query' => $query,
            ]);

            // Add log entry
            $this->logs->push($logEntry);

        });

        // End query log boundary
        if ($queries->count() > 1) {
          $this->addLogBoundary('after');
        }

        return $this->logs;
    }

    /**
     * Build a formated string for the boundary between log writing as a single
     * request / command can make multiple queries.
     *
     * @param string $type
     */
    protected function addLogBoundary($type)
    {
        $configKey = "log_{$type}_boundary";
        $logFormat = $this->helper->config('query_log.' . $configKey);

        if (empty($logFormat)) {
          return;
        }

        $logEntry  = $this->formatLog($logFormat, [ ':boundary'  => $type ]);
        $this->logs->push($logEntry);
    }

    /**
     * Format a log entry merge common tokens into supplied tokens.
     *
     * @param  string $format
     * @param  array  $tokens
     *
     * @return string
     */
    protected function formatLog($format, array $tokens)
    {
      return $this->helper->formatString($format, $this->getLogTokens($tokens));
    }

    /**
     * Collect all tokens for formatting a log entry.
     *
     * @param  array  $addition
     *
     * @return array
     */
    protected function getLogTokens(array $addition = [])
    {
      return array_merge([
        ':date'       => date('Y-m-d H:i:s', time()),
        ':env'        => $this->app->make('env'),
        ':label'      => $this->helper->logLabel(),
        ':handler'    => $this->helper->getHandler(),
        ':connection' => $this->connectionName,
      ], $addition);
    }

}