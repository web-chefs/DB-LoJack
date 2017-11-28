<?php

namespace WebChefs\DBLoJack\Middleware;

// PHP
use Closure;

// Aliases
use DB;
use Config;

class QueryLoggingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // DbLoJack Helper
        $helper = app('db_lojack');

        // Move on if we not the handler
        if (!$helper->isLogging('middleware')) {
            return $next($request);
        }

        // Enabled DB logging
        DB::enableQueryLog();

        // Make sure we run after request has been handled.
        $response = $next($request);

        // Log database queries for the request
        $helper->logQueries( DB::getQueryLog() );

        // return response
        return $response;
    }
}