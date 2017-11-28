<?php

namespace WebChefs\DBLoJack\Contracts;

interface LogWriterInterface
{

    /**
     * Do the log writing.
     *
     * @return void
     */
    public function writeLogs();

}