<?php

namespace WebChefs\DBLoJack;

// Package
use WebChefs\DBLoJack\DBLoJackHelpers;

// Framework
use Illuminate\Support\Arr;

// Aliases
use File;
use Config;

class DBLoJackLogWriter
{
    protected $helper;
    protected $connection;
    protected $context;
    protected $queries      = [];
    protected $maxFiles     = 0;
    protected $logFile      = null;
    protected $mustRotate   = false;
    protected $isProduction = false;

    /**
     * Constructor that prepares and writes the logs.
     *
     * @param  array    $queries
     * @param  string   $connections
     *
     * @return void
     */
    public function __construct(array $queries, $connection = null)
    {
        $app                = app();
        $this->context      = $app->runningInConsole() ? 'console' : 'web';
        $this->helper       = $app->make('db_lojack.helpers');
        $this->isProduction = $app->environment('production', 'staging');

        $this->connection = $connection;
        $this->queries    = $queries;
        $this->maxFiles   = (int)Config::get('database.query_log_max_files');
        $this->mustRotate = false;
    }

    /**
     * Do the log writing.
     *
     * @return void
     */
    public function writeLogs()
    {
        $logs = $this->prepareQueryLogs();

        // Append to log
        if (!empty($logs)) {
            $this->setLogFile();
            $this->checkDirectory();
            $this->writeLog($logs);
        }
    }

    /**
     * Log database queries and if needed rotate logs.
     *
     * @return null
     */
    protected function prepareQueryLogs()
    {
        // Retrieve all executed queries
        $queries = $this->queries;

        // If no queries exit early
        if (empty($queries)) {
            return;
        }

        // Log::debug('DB logger queries: ' . print_r($queries, true));
        // dd($queries); // We cant use ddd()

        // Build log entry
        $logs = [];
        foreach ($queries as $query) {

            $log               = [];
            $log['date']       = date('Y-m-d H:i:s', time());
            $log['time']       = Arr::get($query, 'time');
            $log['connection'] = $this->connection;
            // Dont format SQL with bindings when running in production
            $log['query']      = $this->isProduction ? $query['query'] : $this->helper->formatSql($query['query'], $query['bindings']);

            $logs[] = $this->getLogBoundary('Before');
            $logs[] = implode(', ', array_map(
                function ($v, $k) {
                    return sprintf("%s=%s", $k, $v);
                }, $log, array_keys($log)
            ));
            $logs[] = $this->getLogBoundary('After');
        }

        return $logs;
    }

    /**
     * Build a formated string for the boundary between log writing as a single
     * request / command can make multiple queries.
     *
     * @return string
     */
    protected function getLogBoundary($type)
    {
        $env    = app('env');
        $label  = $this->helper->logLabel();
        $logger = Config::get('database.query_log_type');
        return "---------BOUNDRY {$type}-{$logger} [{$env}] ({$label})---------";
    }

    /**
     * Set logfile path and filename
     *
     * @return  null
     */
    protected function setLogFile()
    {
        $this->logFile = storage_path('logs/db/db_query.' . $this->context . '.' . date('Y-m-d') . '.log');

        if (!File::exists($this->logFile)) {
            $this->mustRotate = true;
        }
    }

    /**
     * Get Directory path from log filename
     *
     * @return string
     */
    protected function getDirectory()
    {
        $fileParts = pathinfo($this->logFile);
        return $fileParts['dirname'];
    }

    /**
     * Check if directory path exists, if not create it.
     *
     * @return null
     */
    protected function checkDirectory()
    {
        $dir = $this->getDirectory();
        if (!File::exists($dir)) {
            File::makeDirectory($dir);
        }
    }

    /**
     * Write Log file and check if we should rotate old logs
     *
     * @param  array    $logs
     *
     * @return null
     */
    protected function writeLog($logs)
    {
        if ($this->mustRotate) {
            $this->rotate();
        }

        File::append($this->logFile, implode(PHP_EOL, $logs) . PHP_EOL);
    }

    /**
     * Log GC process were old log files are removed based on the max files
     * value.
     *
     * @return null
     */
    protected function rotate()
    {
        $dir = $this->getDirectory();

        // skip GC of old logs if files are unlimited
        if (0 === $this->maxFiles) {
            return;
        }

        $logFiles = glob($dir . '/db_query.' . $this->context . '.*.log' );

        if ($this->maxFiles >= count($logFiles)) {
            // no files to remove
            return;
        }

        // Sorting the files by name to remove the older ones
        usort($logFiles, function ($a, $b) {
            return strcmp($b, $a);
        });

        // Remove max files - 1 as we are always adding a file after a rotate
        foreach (array_slice($logFiles, $this->maxFiles-1) as $file) {
            if (is_writable($file)) {
                // suppress errors here as unlink() might fail if two processes
                // are cleaning up/rotating at the same time
                set_error_handler(function ($errno, $errstr, $errfile, $errline) {});
                unlink($file);
                restore_error_handler();
            }
        }

        $this->mustRotate = false;
    }
}