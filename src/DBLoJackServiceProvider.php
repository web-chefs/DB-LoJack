<?php

namespace WebChefs\DBLoJack;

// Package
use WebChefs\DBLoJack\DBLoJack;

// Framework
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\QueryExecuted;

class DBLoJackServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register our default config
        $this->mergeConfigFrom(__DIR__ . '/Config.php', 'database');

        // Facade
        $this->app->singleton('db_lojack', function ($app) {
            return $app->make(DBLoJack::class);
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->addQueryLogListeners();
    }

    /**
     * Register listener with each database connection.
     *
     * @return void
     */
    protected function addQueryLogListeners()
    {
        // DB LoJack Service
        $service = $this->app->make('db_lojack');

        // Listener callback
        $callback = function($query, $bindings = null, $time = null, $connection = null) use ($service) {
            if ($service->isLogging('listener')) {
                $this->logQuery($service, $query, $bindings, $time, $connection);
            }
        };

        // Listen to DB query events
        $this->app['db']->listen($callback);
    }

    /**
     * Log query from listener
     *
     * @param  DBLoJack $service
     * @param  string $query
     * @param  array  $bindings
     * @param  float  $time
     * @param  string $name
     *
     * @return void
     */
    protected function logQuery($service, $query, $bindings = null, $time = null, $connection = null)
    {
        // Convert into array
        $queries    = compact('query', 'bindings', 'time');

        if ($query instanceof QueryExecuted) {
            $connection = $query->connection->getName();
            Arr::set($queries, 'query', $query->sql);
            Arr::set($queries, 'bindings', $query->bindings);
        }

        $service->logQueries([$queries], $connection);
    }

}