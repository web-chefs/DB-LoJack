<?php

namespace WebChefs\DBLoJack\Contracts;

interface QueryStoreInterface
{

    /**
     * Prepare and format log entries.
     *
     * @return array|collection
     */
    public function formatLogs();

}