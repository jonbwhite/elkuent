<?php namespace elkuent\elkuent;

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

    /**
     * Create a new database connection instance.
     *
     * @param array $config
     * @return @void
     */
    public function __construct(array $config)
    {

    }
}
