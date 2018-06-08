<?php

namespace WebChefs\DBLoJack;

// Package
use WebChefs\DBLoJack\DBLoJack;
use WebChefs\DBLoJack\PerformanceWatchdog;

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
        $this->mergeConfigFrom(__DIR__ . '/Config.php', 'db-lojack');

        // Facade
        $this->app->singleton('db_lojack', function ($app) {
            return $app->make(DBLoJack::class);
        });

        $this->app->singleton('db_lojack.performance_watchdog', function ($app) {
            return $app->make(PerformanceWatchdog::class);
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish Package Config
        $this->publishes([ __DIR__ . '/Config.php' => config_path('db-lojack.php') ], 'config');

        // Add database query listener
        $this->addQueryLogListeners();
    }

    /**
     * Register listener with each database connection.
     *
     * @return void
     */
    protected function addQueryLogListeners()
    {
        // Services
        $service     = $this->app->make('db_lojack');
        $performance = $this->app->make('db_lojack.performance_watchdog');

        // Listener callback
        // Laravel >= 5.2 introduce QueryExecuted event.
        // So this will not work for 5.1 and below.
        $callback = function(QueryExecuted $query) use ($service, $performance) {
            if ($service->isLogging('listener')) {
                $this->logQuery($service, $query);
            }

            if ($performance->isActive()) {
                $performance->logQuery( $query->sql );
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