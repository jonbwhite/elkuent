<?php namespace Elkuent;

use Elasticsearch;

class Connection extends \Illuminate\Database\Connection {

    /**
     * Elastic search db handler.
     *
     * @Elasticsearch
     */
     protected $db;

    /**
     * Elastic search connection handler.
     *
     * @Elasticsearch
     */
     protected $connection;

     protected $configFilters = array('driver', 'defaultIndex');

     protected $config;

     protected $defaultIndex = null;

    /**
     * Create a new database connection instance.
     *
     * @param array $config
     * @return @void
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        $filteredConfig = array();

        foreach($config as $key => $value) {
            if (!in_array($key, $this->configFilters)) {
                $filteredConfig[$key] = $value;
            }
        }
        $this->defaultIndex = $config['defaultIndex'];
        $this->connection = new \Elasticsearch\Client($filteredConfig);
        $this->useDefaultPostProcessor();
    }

    public function getDefaultIndex()
    {
        return $this->defaultIndex;
    }

    public function getDriverName()
    {
        return 'elasticsearch';
    }

    /**
     * Begin a fluent query against a database collection.
     *
     * @param  string  $table
     * @return QueryBuilder
     */
    public function table($table)
    {
        $processor = $this->getPostProcessor();
        $query = new Builder($this, $processor);
        return $query->from($table);
    }

    /**
     * Get the default post processor instance.
     *
     * @return Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Processor;
    }

    public function getElasticsearchClient()
    {
        return $this->connection;
    }

	public function getSchemaBuilder()
    {
        return new Schema\Builder($this);
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array(array($this->connection, $method), $parameters);
    }

    public function disconnect()
    {

    }

}
