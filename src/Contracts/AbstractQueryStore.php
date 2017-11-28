<?php

namespace WebChefs\DBLoJack\Contracts;

// Package
use WebChefs\DBLoJack\Contracts\QueryStoreInterface;

// Framework
use Illuminate\Support\Str;

abstract class AbstractQueryStore implements QueryStoreInterface
{

    /**
     * The application implementation.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * @var \WebChefs\DBLoJack\DBLoJackHelpers
     */
    protected $helper;

    /**
     * @var array
     */
    protected $queries;

    /**
     * @var string
     */
    protected $connectionName;

    /**
     * @var string
     */
    protected $context;

    /**
     * @var boolean
     */
    protected $isProduction = false;

    /**
     * Default constructor, building an instance.
     *
     * @param array  $queries
     * @param string $connectionName
     */
    public function __construct(array $queries, $connectionName = null)
    {
        $this->app = app();

        $this->setContext()
             ->setProductionFlag()
             ->setHelper();

        $this->queries        = collect($queries);
        $this->connectionName = $connectionName;
    }

    /**
     * Set  environmental logging context, default console or web.
     *
     * @param string $context
     *
     * @return $this
     */
    public function setContext($context = null)
    {
        $this->context = $context ?: $this->app->runningInConsole() ? 'console' : 'web';
        return $this;
    }

    /**
     * Set if queries are running in a production environment or not.
     *
     * @param boolean $flag
     *
     * @return $this
     */
    public function setProductionFlag($flag = null)
    {
        $this->isProduction = $flag ?: $this->app->environment('production', 'staging');
        return $this;
    }

    /**
     * Set the LoJack helper class. Defaults to the db_lojack manager facade
     * class application binding.
     *
     * @param Object $helper
     */
    public function setHelper($helper = 'db_lojack')
    {
        $this->helper = $this->app->make($helper);
    }

    /**
     * Allow for the isset function to work on dynamic properties.
     *
     * @param  string   $variableName
     * @return boolean
     */
    public function __isset($variableName)
    {
        $getterMethodName = 'get' . ucfirst($variableName) . 'Attribute';
        if (method_exists($this, $getterMethodName)) {
            return true;
        }

        $propertyName = Str::snake($variableName);
        if (property_exists($this, $propertyName)) {
            return true;
        }

        return false;
    }

    /**
     * Get magic method used for overloading.
     *
     * @param  string $variableName     Snake case variable name
     *
     * @return mixed
     */
    public function __get($variableName)
    {
        $getterMethodName = 'get' . ucfirst($variableName) . 'Attribute';
        if (method_exists($this, $getterMethodName)) {
            return call_user_func([$this, $getterMethodName]);
        }

        $propertyName = Str::snake($variableName);
        if (property_exists($this, $propertyName)) {
            return $this->$propertyName;
        }

        // debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        throw new InvalidArgumentException(sprintf('Property does not exist "%s" (Model Get)', $variableName));
    }

}