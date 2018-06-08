<?php

namespace WebChefs\DBLoJack\Loggers;

// Package
use WebChefs\DBLoJack\DBLoJackHelpers;
use WebChefs\DBLoJack\Contracts\QueryStoreInterface;

// Framework
use Illuminate\Support\Arr;

// Aliases
use File;
use Config;

class RotatingTextFileLogger
{
    protected $queries;

    protected $maxFiles     = 0;
    protected $logFile      = null;
    protected $mustRotate   = false;

    /**
     * Constructor that prepares and writes the logs.
     *
     * @param  array    $queries
     * @param  string   $connections
     *
     * @return void
     */
    public function __construct(QueryStoreInterface $queries)
    {
        $this->queries    = $queries;
        $this->maxFiles   = (int)Config::get('db-lojack.query_log.max_files');
        $this->mustRotate = false;
    }

    /**
     * Do the log writing.
     *
     * @return void
     */
    public function writeLogs()
    {
        $logs = $this->queries->formatLogs();

        // Append to log
        if (!empty($logs)) {
            $this->setLogFile();
            $this->checkDirectory();
            $this->writeLog($logs);
        }
    }

    /**
     * Set logfile path and filename
     *
     * @return  null
     */
    protected function setLogFile()
    {
        $logPath       = Config::get('db-lojack.query_log.log_path');
        $this->logFile = $logPath . '/db_query.' . $this->queries->context . '.' . date('Y-m-d') . '.log';

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

        File::append($this->logFile, $logs->implode(PHP_EOL) . PHP_EOL);
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

        $logFiles = glob($dir . '/db_query.' . $this->queries->context . '.*.log' );

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