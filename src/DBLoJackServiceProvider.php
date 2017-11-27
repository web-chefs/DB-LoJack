<?php

namespace WebChefs\DBLoJack;

// Package
use WebChefs\DBLoJack\DBLoJackHelpers;

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
        $this->app->singleton('db_lojack.helpers', function ($app) {
            return $app->make(DBLoJackHelpers::class);
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $helper = $this->app->make('db_lojack.helpers');

        if ($helper->isLogging('listener')) {

            $this->app['db']->listen(function($query, $bindings = null, $time = null, $name = null) use ($helper) {

                $connection = $name;

                // Convert into array
                $queries    = compact('query', 'bindings', 'time');

                if ($query instanceof QueryExecuted) {
                    $connection = $query->connection->getName();
                    Arr::set($queries, 'query', $query->sql);
                    Arr::set($queries, 'bindings', $query->bindings);
                }

                $helper->logQueries([$queries], $connection);
            });
        }
    }

}