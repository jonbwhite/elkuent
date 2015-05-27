<?php namespace Elkuent\Schema;

use Closure;
use Elkuent\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class Builder extends SchemaBuilder
{

    /**
     * The schema grammar instance.
     *
     * @var \Illuminate\Database\Schema\Grammars\Grammar
     */
    protected $grammar;

    public $index;

    /**
     * Create a new database Schema manager.
     *
     * @param  Connection  $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->index = $connection->getDefaultIndex();
    }

    /**
     * Drop a table from the schema.
     *
     * @param  string  $table
     * @return bool
     */
    public function drop($table)
    {
        $blueprint = $this->createBlueprint($table);

        return $blueprint->drop();
    }

    /**
     * Create a new Blueprint.
     *
     * @param  string   $table
     * @return Schema\Blueprint
     */
    protected function createBlueprint($table, Closure $callback = null)
    {
        return new Blueprint($this->connection, $this->index, $table);
    }
}
