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

    public function toSql(Connection $connection, Grammar $grammar)
    {
        throw new Exception('Call to undefined method.');
    }

    public function toEs(Connection $connection)
    {
        $statements = array();
        $strings = array('string', 'char', 'text', 'mediumText', 'longText');
        $dates = array('date', 'dateTime', 'time', 'timestamp');

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

                    $property = array();

                    if (in_array($column['type'], $strings)) {
                        $property = array('type' => 'string', 'index' => 'not_analyzed');
                    } else if (in_array($column['type'], ['interger', 'mediumInteger'])) {
                        $property = array('type' => 'integer');
                    } else if (in_array($column['type'], ['tinyInteger'])) {
                        $property = array('type' => 'byte');
                    } else if (in_array($column['type'], ['smallInteger'])) {
                        $property = array('type' => 'short');
                    } else if (in_array($column['type'], ['bigInteger', 'unsignedInteger', 'unsignedBigInteger'])) {
                        $property = array('type' => 'long');
                    } else if (in_array($column['type'], ['float'])) {
                        $property = array('type' => 'float');
                    } else if (in_array($column['type'], ['double', 'decimal'])) {
                        $property = array('type' => 'double');
                    } else if (in_array($column['type'], ['boolean'])) {
                        $property = array('type' => 'boolean');
                    } else if (in_array($column['type'], $dates)) {
                        $property = array('type' => 'dateOptionalTime');
                    } else if (in_array($column['type'], ['binary'])) {
                        $property = array('type' => 'binary');
                    } else if (in_array($column['type'], ['geoShape'])) {
                        $property = array('type' => 'geo_shape');
                    } else if (in_array($column['type'], ['nested'])) {
                        $property = array('type' => 'nested');
                    }

                    $properties[$column['name']] = $property;
                }

                $template['body']['mappings'] = array(
                    $this->table => array('properties' => $properties)
                );
                $statements[] = $template;
            } else if ($command['name'] == 'drop') {
                $template = array();
                $tempalte['name'] = $this->table;
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
