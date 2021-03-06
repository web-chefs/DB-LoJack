<?php

namespace WebChefs\DBLoJack\Facades;

// Framework
use Illuminate\Support\Facades\Facade;

class DbLogger extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'db_lojack'; }

}