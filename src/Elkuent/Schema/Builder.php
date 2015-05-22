<?php namespace Elkuent\Schema;

use Elkuent\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class Builder extends SchemaBuilder
{

    /**
     * Create a new database Schema manager.
     *
     * @param  Connection  $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create a new collection on the schema.
     *
     * @param  string   $collection
     * @param  Closure  $callback
     * @return bool
     */
    public function create($collection, Closure $callback = null)
    {
        $blueprint = $this->createBlueprint($collection);
        $blueprint->create();

        if ($callback)
        {
            $callback($blueprint);
        }
    }

    /**
     * Create a new Blueprint.
     *
     * @param  string   $collection
     * @return Schema\Blueprint
     */
    protected function createBlueprint($collection, Closure $callback = null)
    {
        return new Blueprint($this->connection, $collection);
    }

}
