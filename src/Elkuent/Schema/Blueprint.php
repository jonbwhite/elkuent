<?php namespace Elkuent\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint as SchemaBlueprint;

class Blueprint extends SchemaBlueprint
{

    /**
     * The ElasticsearchConnection object for this blueprint.
     *
     * @var MongoConnection
     */
    protected $connection;

    /**
     * Create a new schema blueprint.
     *
     * @param  string   $table
     * @param  Closure  $callback
     * @return void
     */
    public function __construct(Connection $connection, $collection)
    {
        $this->connection = $connection;
        $this->collection = $connection->getCollection($collection);
    }

}
