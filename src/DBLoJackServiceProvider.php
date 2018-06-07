<?php

namespace WebChefs\DBLoJack;

// Package
use WebChefs\DBLoJack\DBLoJack;

// Framework
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
        // Laravel >= 5.2 introduce QueryExecuted event.
        // So this will not work for 5.1 and below.
        $callback = function(QueryExecuted $query) use ($service) {
            if ($service->isLogging('listener')) {
                $this->logQuery($service, $query);
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
    protected function logQuery($service, QueryExecuted $query)
    {
        // Convert info into array
        $connectionName = $query->connection->getName();
        $queryInfo      = [
            'query'    => $query->sql,
            'bindings' => $query->bindings,
            'time'     => $query->time,
        ];
        $service->logQueries([$queryInfo], $connectionName);
    }

}