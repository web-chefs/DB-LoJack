<?php

namespace WebChefs\DBLoJack;

// PHP
use stdClass;
use InvalidArgumentException;

// Package
use WebChefs\DBLoJack\Facades\DbLogger;

// Framework
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

// Vendor
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

class PerformanceWatchdog
{

    /**
     * @var Filesystem
     */
    protected $fileHelper;

    /**
     * @var boolean
     */
    protected $logged = false;

    /**
     * @var Stopwatch
     */
    protected $stopwatch;

    /**
     * @var float
     */
    protected $memoryStart;

    /**
     * @var array
     */
    protected $queries;

    /**
     * Construct an instance
     */
    public function __construct(Stopwatch $stopwatch, Filesystem $fileHelper)
    {
        $this->queries     = collect();
        $this->stopwatch   = $stopwatch;
        $this->memoryStart = memory_get_usage(true);
        $this->fileHelper  = $fileHelper;

        if (!$this->isActive()) {
            return;
        }

        // Start measuring
        $this->stopwatch->start('performance');

        // Add shutdown function for when exit() is called or script ends
        // this way our logs are always written bypassing any need for
        // middleware and works in both HTTP and console applications.
        register_shutdown_function(function() {
            $this->writeLog();
        });
    }

    /**
     * Get the DB LoJack config or sub section.
     *
     * @param  string $context
     *
     * @return mixed
     */
    public function config($context, $default = null)
    {

        $context = empty($context) ? '' : '.' . $context;
        $context = 'db-lojack.performance_watchdog' . $context;
        return Config::get($context, $default);
    }

    /**
     * Is the logger active. Master Switch.
     *
     * @return boolean
     */
    public function isActive()
    {
        return $this->mode() !== false;
    }

    /**
     * Get the config that controls if we active and what gets logged.
     *
     * @return boolean|string
     */
    public function mode()
    {
        return $this->config('mode');
    }

    /*
     |--------------------------------------------------------------------------
     | Statics
     |--------------------------------------------------------------------------
     */

    /**
     * Create a unique key based on a provided string.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function makeKey($value)
    {
        // This has is not used for security but rather uniqueness so we can
        // rather go for speed.
        return hash('md5', $value, false);
    }

    /**
     * Format bytes to B, KiB, MiB, GiB, TiB, PiB
     *
     * @param  integer $size
     *
     * @return string
     */
    public static function formatBytes($bytes)
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Convert a string form of memory to bytes.
     *
     * EG: 10MB / 10mb
     *
     * @param  string $from
     *
     * @return integer
     */
    public static function strToBytes($from){
        $number=substr($from,0,-2);
        switch(strtoupper(substr($from,-2))){
            case "KB":
                return $number*1024;
            case "MB":
                return $number*pow(1024,2);
            case "GB":
                return $number*pow(1024,3);
            case "TB":
                return $number*pow(1024,4);
            case "PB":
                return $number*pow(1024,5);
            default:
                return $from;
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Logging
     |--------------------------------------------------------------------------
     */

    /**
     * Check if directory path exists, if not create it.
     *
     * @return null
     */
    protected function checkDirectory($dir)
    {
        if (!$this->fileHelper->exists($dir)) {
            $this->fileHelper->makeDirectory($dir);
        }
    }

    /**
     * Write th logs.
     *
     * @return void
     */
    public function writeLog()
    {
        // Add stopwatch data to info after mail has been dispatched
        $metrics = $this->stopwatch->stop('performance');
        $this->stopwatch->reset();

        // Check if we already written our log or should not run
        if (!$this->logged && !$this->shouldLog($metrics)) {
            return;
        }

        // Common data
        $date    = date('Ymd H:m:s');
        $memory  = $this->memoryUsage($metrics);
        $common = $this->logCommon($metrics);

        // Write summary
        if ($this->shouldLogForType('summary')) {
            $content = $date . ': ' . str_replace("\t", '', implode(', ', $common));
            $this->fileHelper->append($this->logFile('summary'), $content . PHP_EOL);
        }

        // Write detailed
        if ($this->shouldLogForType('detailed')) {
            $content = $this->logDetailed($date, $common, $memory);
            $this->fileHelper->append($this->logFile('detailed'), $content . PHP_EOL);
        }

        // We only write to the log once
        $this->logged = true;
    }

    /**
     * Check if we should write the log for a specific type.
     *
     * @param  string $type
     *
     * @return boolean
     */
    protected function shouldLogForType($type)
    {
        $mode = $this->mode();

        if ($mode === 'both') {
            return true;
        }

        return $mode === $type;
    }

    /**
     * Check if we have crossed a threshold.
     *
     * @param  integer $time
     * @param  integer $queries
     * @param  string  $memory
     *
     * @return boolean
     */
    protected function shouldLog(StopwatchEvent $metrics)
    {
        // Check if we active.
        if (!$this->isActive()) {
            return false;
        }

        // Only log if we have queries
        if ($this->queries->isEmpty()) {
            return false;
        }

        // Check query count
        if ($this->queries->count() >= $this->threshold('queries')) {
            return true;
        }

        // Check execution time
        if ($metrics->getDuration() >= $this->threshold('time')) {
            return true;
        }

        // Check memory usage
        if ($metrics->getMemory() >= $this->strToBytes( $this->threshold('memory') )) {
            return true;
        }

        return false;
    }

    /**
     * Get the threshold config value.
     *
     * @param  string $name
     *
     * @return mixed
     */
    public function threshold($name)
    {
        return $this->config('threshold.' . $name);
    }

    /**
     * Check if we should log stack traces. Only when are running detailed SQL
     * logging.
     *
     * @return boolean
     */
    protected function shouldTrace()
    {
        return $this->mode() == 'both' || $this->mode() == 'detailed';
    }

    /**
     * Build a log file path.
     *
     * @return string
     */
    protected function logFile($type)
    {
        $path = $this->config('log_path');
        $this->checkDirectory($path);
        return $path . DIRECTORY_SEPARATOR . 'bench.' . $type . '.' . date('Ymd') . '.log';
    }

    /**
     * Common log details used by both summary and detailed.
     *
     * @return array
     */
    protected function logCommon(StopwatchEvent $metrics)
    {
        return [
            'time' => 'Time: '       . "\t\t\t" . $metrics->getDuration() . 'ms',
            'ver'  => 'Version: '    . "\t\t"   . implode('.', [ PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION ]),
            'db'   => 'DB Queries: ' . "\t\t"   . $this->queries->count(),
            'mem'  => 'Memory: '     . "\t\t"   . $this->formatBytes( $metrics->getMemory() ),
            'uri'  => 'Request: '    . "\t\t"   . '"' . $this->formatRequestLog() . '"',
        ];
    }

    /**
     * Detailed SQL log.
     *
     * @param  string $date
     * @param  array $summary
     * @param  object $memory
     *
     * @return string
     */
    protected function logDetailed($date, $common, $memory)
    {
        // Build log header
        $content = implode(PHP_EOL, [
            'START===========================================================================',
            'Date:' . "\t\t\t" . $date,
            $common['time'],
            $common['ver'],
            $common['uri'],
            'Memory Start:'    . "\t\t" . $this->formatBytes($memory->start),
            'Memory Current:'  . "\t\t" . $this->formatBytes($memory->current),
            'Memory Usage:'    . "\t\t" . $this->formatBytes($memory->usage),
            'Memory Max Peak:' . "\t"   . $this->formatBytes($memory->peak),
            $common['db'],
        ]);

        // Get and sort queries by count highest to lowest
        $queries = $this->queries->sort(function($a, $b) {
            if ($a->count == $b->count) {
                return 0;
            }

            return ($a->count > $b->count) ? -1 : 1;
        });

        // Build our stack trace separator
        $traceSeperator = implode('', [
            PHP_EOL,
            // '++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++',
            PHP_EOL,
        ]);

        $minQueries = $this->config('min_queries', 1);

        // Build query log details
        $queries->each(function($query) use (&$content, $traceSeperator, $minQueries) {
            if ($query->count < $minQueries) {
                return;
            }

            $content .= PHP_EOL;
            $content .= '--------------------------------------------------------------------------------' . PHP_EOL;
            $content .= 'Usage Count:' . "\t\t" . $query->count . PHP_EOL;
            $content .= 'Trace Count:' . "\t\t" . count($query->trace) . PHP_EOL;
            $content .= '--------------------------------------------------------------------------------' . PHP_EOL;
            $content .= $query->sql . PHP_EOL;
            $content .= '--------------------------------------------------------------------------------' . PHP_EOL;
            $content .= PHP_EOL;
            $content .= implode($traceSeperator, $query->trace);
            $content .= PHP_EOL;
        });

        $content .= PHP_EOL . 'END=============================================================================' . PHP_EOL;
        return $content;
    }

    /**
     * Attempt to extract the calling url or CLI command.
     *
     * @return string
     */
    protected function formatRequestLog()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            return $_SERVER['REQUEST_URI'];
        }

        if (isset($_SERVER['argv'])) {
            return 'CLI: ' . implode(' ', $_SERVER['argv']);
        }

        return 'Unknown Environment';
    }

    /*
     |--------------------------------------------------------------------------
     | Memory Usage
     |--------------------------------------------------------------------------
     */

    /**
     * Gather current memory usage statistics.
     *
     * @return Object
     */
    public function memoryUsage(StopwatchEvent $metrics)
    {
        $mem = new stdClass;

        $mem->start   = $this->memoryStart;
        $mem->current = $metrics->getMemory();
        $mem->peak    = memory_get_peak_usage();
        $mem->usage   = $mem->peak - $mem->start;

        return $mem;
    }

    /*
     |--------------------------------------------------------------------------
     | Database Query Logger
     |--------------------------------------------------------------------------
     */

    /**
     * Log a database query.
     *
     * @param  string  $query
     * @param  integer $rowCount    Default to null not zero so we differentiate
     * @param  integer $traceSize
     *
     * @return string
     */
    public function logQuery($query)
    {
        $traceSize = $this->config('trace');

        // Log a string query
        if (!is_string($query)) {
            throw new InvalidArgumentException( $this->formatString('PerformanceWatchdog expects query to be a string ":type" given.', [
                ':type' => gettype($query),
            ]) );
        }

        if (!$this->isActive()) {
            return $this;
        }

        $log = $this->getQueryLog($query);
        $log->increment();

        // Add stack trace
        if ($this->shouldTrace()) {
            $trace = ($traceSize == false || $traceSize < 1) ? 'no trace' : DbLogger::simpleTrace($traceSize);
            $log->addTrace($trace);
        }

        return $this;
    }

    /**
     * Get a query log item.
     *
     * @param  string $query
     *
     * @return QueryLogItem
     */
    public function getQueryLog($query)
    {
        $log = new QueryLogItem($query);

        if (!$this->queries->has($log->key)) {
            $this->queries->put($log->key, $log);
            return $log;
        }

        return $this->queries->get($log->key);
    }

    /**
     * Get the total query count.
     *
     * @return integer
     */
    public function queryCount()
    {
        return $this->queries->count();
    }

}