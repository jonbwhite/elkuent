<?php namespace Elkuent\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Database\Schema\Blueprint as SchemaBlueprint;

class Blueprint extends SchemaBlueprint
{

    protected $index;

    /**
     * The columns that should be added to the table.
     *
     * @var array
     */
    protected $columns = array();

    /**
     * The ElasticsearchConnection object for this blueprint.
     *
     * @var MongoConnection
     */
    protected $connection;

    protected $type;

    /**
     * Create a new schema blueprint.
     *
     * @param  string   $table
     * @param  Closure  $callback
     * @return void
     */
    public function __construct(Connection $connection, $index, $type)
    {
        $this->connection = $connection;
        $this->index = $index;
        $this->table = $type;
    }

    public function geoShape($column, $attributes = array())
    {
        return $this->addColumn('geo_shape', $column, $attributes);
    }

    public function nested($column, $attributes = array())
    {
        return $this->addColumn('nested', $column, $attributes);
    }

    public function toSql(Connection $connection, Grammar $grammar)
    {
        throw new Exception('Call to undefined method.');
    }

    public function toEs(Connection $connection)
    {
        $statements = array();
        $strings = array('string', 'char', 'text', 'medium_text', 'long_text');
        $dates = array('date', 'date_time', 'datetime', 'time', 'timestamp');

        foreach($this->commands as $command) {
            $command = $command->toArray();
            if ($command['name'] == 'create') {
                $template = array();
                $template['name'] = $this->table;
                $template['method'] = 'put';
                $template['body']['template'] = $this->index;

                $properties = array();

                foreach($this->columns as $column) {
                    $column = $column->toArray();
                    $column['type'] = snake_case($column['type']);

                    $property = array();

                    if (in_array($column['type'], $strings)) {
                        $property = array('type' => 'string', 'index' => 'not_analyzed');
                    } else if (in_array($column['type'], ['integer', 'medium_integer'])) {
                        $property = array('type' => 'integer');
                    } else if (in_array($column['type'], ['tiny_integer'])) {
                        $property = array('type' => 'byte');
                    } else if (in_array($column['type'], ['small_integer'])) {
                        $property = array('type' => 'short');
                    } else if (in_array($column['type'], ['big_integer', 'unsigned_integer', 'unsigned_big_integer'])) {
                        $property = array('type' => 'long');
                    } else if (in_array($column['type'], ['float'])) {
                        $property = array('type' => 'float');
                    } else if (in_array($column['type'], ['double', 'decimal'])) {
                        $property = array('type' => 'double');
                    } else if (in_array($column['type'], ['boolean'])) {
                        $property = array('type' => 'boolean');
                    } else if (in_array($column['type'], $dates)) {
                        $property = array('type' => 'date', 'format' => 'date_optional_time');
                    } else if (in_array($column['type'], ['binary'])) {
                        $property = array('type' => 'binary');
                    } else if (in_array($column['type'], ['geo_shape'])) {
                        $property = array('type' => 'geo_shape');
                    } else if (in_array($column['type'], ['nested'])) {
                        $property = array('type' => 'nested');
                    }

                    if (!empty($property)) {
                        $properties[$column['name']] = $property;
                    } else {
                        trigger_error('No match found for type ' . $column['type'], E_USER_NOTICE);
                    }

                }

                $template['body']['mappings'] = array(
                    $this->table => array('properties' => $properties)
                );
                $statements[] = $template;
            } else if ($command['name'] == 'drop') {
                $template = array();
                $template['name'] = $this->table;
                $template['method'] = 'delete';
                $statements[] = $template;
            }
        }

        return $statements;
    }

    /**
     * Execute the blueprint against the database.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  \Illuminate\Database\Schema\Grammars\Grammar $grammar
     * @return void
     */
    public function build(Connection $connection, Grammar $grammar = null)
    {
        $idx = $this->connection->indices();

        foreach ($this->toES($connection) as $statement)
        {
            $method = $statement['method'] . 'Template';
            unset($statement['method']);
            $idx->{$method}($statement);
        }
    }

}
