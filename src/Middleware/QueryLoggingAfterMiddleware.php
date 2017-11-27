<?php

namespace WebChefs\DBLoJack\Middleware;

// PHP
use Closure;

// Package
use WebChefs\DBLoJack\DBQueryLogWriter;

// Aliases
use DB;
use Config;

class QueryLoggingAfterMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Make sure we run after request has been handled.
        $response = $next($request);

        // DbLoJack Helper
        $helper = app('db_lojack.helpers');

        // Log database queries for the request
        if ($helper->isLogging('middleware')) {
            $helper->logQueries( DB::getQueryLog() );
        }

        // return response
        return $response;
    }

}