<?php

namespace WebChefs\DBLoJack\Middleware;

// PHP
use Closure;

// Aliases
use DB;
use Config;

class QueryLoggingBeforeMiddleware
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
        $helper = app('db_lojack.helpers');

        if ($helper->isLogging('middleware')) {
            DB::enableQueryLog();
        }
        return $next($request);
    }
}