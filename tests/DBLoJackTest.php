<?php

namespace WebChefs\DBLoJack\Tests;

// Package
use WebChefs\LaraAppSpawn\ApplicationResolver;
use WebChefs\DBLoJack\DBLoJackServiceProvider;
use WebChefs\DBLoJack\Middleware\QueryLoggingMiddleware;

// Framework
use Illuminate\Support\Arr;
use Illuminate\Foundation\Testing\TestCase;

// Aliases
use DB;

class DBLoJackTest extends TestCase
{

    /**
     * @var string
     */
    protected $connectionName = 'lojack_test_db';

    /**
     * Dummy Data
     *
     * @var array
     */
    protected $data = [
        [ 'test' => 'ABC' ],
        [ 'test' => 'XYZ' ],
        [ 'test' => 123   ],
    ];

    protected $resolver;

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        // Build Resolver config
        $config = ApplicationResolver::defaultConfig();
        Arr::set($config, 'database.connection', $this->connectionName);

        // Add our service provider to vendor builds
        $callback = function(array $config) {
            $config['providers'][] = DBLoJackServiceProvider::class;
            return $config;
        };
        Arr::set($config, 'callback.vendor_config', $callback);

        // Resolve Application
        $this->resolver = ApplicationResolver::makeApp(__DIR__, $config);
        $this->app      = $this->resolver->app();

        // Enable query logging
        $this->resolver->config()->set('database.query_log.enabled', true);

        // Run our database migrations
        $this->artisan('migrate:refresh', [ '--force' => 1 ]);

        return $this->app;
    }

    /**
     * Test our test queue was setup correctly and and is empty.
     *
     * @return void
     */
    public function testTableExists()
    {
        // Check our queues are setup is using our in memory database
        $this->assertEquals(DB::getDefaultConnection(), $this->connectionName);

        // Count that our migration was run
        $tables = DB::table('sqlite_master')
                    ->select('name')
                    ->where('type', 'table')
                    ->get();

        // Check our table exists
        $this->assertTrue($tables->keyBy('name')->has('test'));
    }

    /**
     * Test writing to a table.
     *
     * @return void
     */
    public function testWriteToTable()
    {
        $count = 0;

        // Run with listener
        $this->resolver->config()->set('database.query_log.handler', 'listener');
        DB::table('test')->insert($this->data);
        $test = DB::table('test')->get();
        $count += count($this->data);

        $this->assertEquals($count, $test->count());

        // Run with middleware
        $this->resolver->config()->set('database.query_log.handler', 'middleware');
        $middleware = $this->simulateMiddleware();
        DB::table('test')->insert($this->data);
        $test = DB::table('test')->get();
        $count += count($this->data);
        $middleware->logQueries();

        $this->assertEquals($count, $test->count());

    }

    /**
     * Simulate the setup of the middleware.
     *
     * @return void
     */
    protected function simulateMiddleware()
    {
        $middleware = new QueryLoggingMiddleware;

        $middleware->handle(null, function($dummyRequest) {
            return $dummyRequest;
        });

        return $middleware;
    }

}
