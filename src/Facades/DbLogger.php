<?php

namespace WebChefs\DBLoJack\Facades;

class DbLogger extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'db_lojack.helpers'; }

}