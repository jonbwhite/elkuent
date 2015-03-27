<?php namespace Elkuent;

use DateTime, Closure;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;

class Builder extends BaseBuilder {

     public $index = null;

    /**
     * The maximum number of documents to be returned for a query.
     */
     public $maxQuerySize = 100;

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        // AFIK elasticsearch does not support bitwise operations
        // see: https://github.com/elastic/elasticsearch/issues/975
        // '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
    );

    /**
     * Operator conversion.
     *
     * @var array
     */
    protected $conversion;

    /**
     * Create a new query builder instance.
     *
     * @param Connection $connection
     * @return void
     */
    public function __construct(Connection $connection, Processor $processor, $index=null)
    {
        $this->connection = $connection;
        $this->processor = $processor;

        if ($index != null)
        {
            $this->index = $index;
        }
        else
        {
            $this->index = $connection->getDefaultIndex();
        }

        $this->conversion = array(
            '<'  => function($column, $value){
                return array(
                    'range' => array(
                        $column => array('lt' => $value)
                    )
                );},
            '<=' => function($column, $value){
                return array(
                    'range' => array(
                        $column => array('lte' => $value)
                    )
                );},
            '>'  => function($column, $value){
                return array(
                    'range' => array(
                        $column => array('gt' => $value)
                    )
                );},
            '>=' => function($column, $value){
                return array(
                    'range' => array(
                        $column => array('gte' => $value)
                    )
                );},
            'between' => function($column, $value){
                return array(
                    'range' => array(
                        $column => array(
                            'lte' => $value[0],
                            'gte' => $value[1]
                        )
                    )
                );}
        );
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return mixed
     */
    public function find($id, $columns = array())
    {
        return $this->where('_id', '=', $id)->first($columns);
    }

    /**
     * Execute the query as a fresh "select" statement.
     *
     * @param  array  $columns
     * @return array|static[]
     */
    public function getFresh($columns = array())
    {
        // If no columns have been specified for the select statement, we will set them
        // here to either the passed columns, or the standard default of retrieving
        // all of the columns on the table using the "wildcard" column character.
        if (is_null($this->columns)) $this->columns = $columns;

        // Drop all columns if * is present, Elasticsearch does not work this way.
        if (in_array('*', $this->columns)) $this->columns = array();

        // Compile wheres
        $wheres = $this->compileWheres();

        $params = array();
        $params['body']['query']['filtered']['filter'] = $wheres;
        $params['index'] = $this->index;
        $params['type']  = $this->from;

        if (!empty($this->columns)) {
            $params['body']['_source'] = $this->columns;
        }

        // Apply order, groups, and timeout
        /* TODO add these to reference ES stuffs...

        if ($this->timeout) $cursor->timeout($this->timeout);
        if ($this->orders)  $cursor->sort($this->orders);
        */



        $results = array();
        $numQueryResults = null;
        $limit = isset($this->limit) ? $this->limit : PHP_INT_MAX;
        $offset = isset($this->offset) ? $this->offset : 0;

        while (count($results) !== $numQueryResults){

            $params['from'] = $offset + count($results);
            $params['size'] = min($this->maxQuerySize, $limit - count($results));

            $hits = $this->connection->search($params);

            if (!isset($numQueryResults)){
                $numQueryResults = min($hits['hits']['total'], $limit);
            }

            foreach($hits['hits']['hits'] as $hit) {
                $result = $hit['_source'];

                if (empty($this->columns) || in_array('id', $this->columns) || in_array('_id', $this->columns)) {
                    $result['_id'] = $hit['_id'];
                }

                array_push($results, $result);
            }
        }

        return $results;
    }

    /**
     * Generate the unique cache key for the current query.
     *
     * @return string
     */
    public function generateCacheKey()
    {
        $key = array(
            'connection' => $this->connection->info()['name'],
            'wheres'     => $this->wheres,
            'columns'    => $this->columns,
            'groups'     => $this->groups,
            'orders'     => $this->orders,
            'offset'     => $this->offset,
            'limit'      => $this->limit,
            'aggregate'  => $this->aggregate,
        );

        return md5(serialize(array_values($key)));
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @TODO Update to use aggregation key in the returned results
     *       when aggregation support is added.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return mixed
     */
    public function aggregate($function, $columns = array())
    {
        // Compile wheres
        $wheres = $this->compileWheres();

        $params = array();
        $params['body']['query']['filtered']['filter'] = $wheres;
        $params['index'] = $this->index;
        $params['type']  = $this->from;

        $aggregates = array();

        $aggName = "HURRDURR";
        // Translate count into sum.
        if ($function == 'count')
        {
            $aggregates[$aggName] = array('filter' => array("and" => array()));
            foreach ($columns as $column)
            {
                if ($column == "*") {
                    $aggregates[$aggName]['filter']['and'][] = array('match_all' => array());
                } else {
                    $aggregates[$aggName]['filter']['and'][] = array('exists' => array('field' => $column));
                }

            }
            // Pass other functions directly.
        } else {
            $aggregates[$aggName] = array($function => array("field" => $column[0]));
        }
        $params['body']['aggs'] = $aggregates;

        print(json_encode($params));

        $results = $this->connection->search($params);

        // Once we have executed the query, we will reset the aggregate property so
        // that more select queries can be executed against the database without
        // the aggregate value getting in the way when the grammar builds it.
        $this->columns = null; $this->aggregate = null;

        if ($function == 'count') {
            return $results['aggregations'][$aggName]['doc_count'];
        } else {
            return $results['aggregations'][$aggName]['value'];
        }

    }

    /**
     * Force the query to only return distinct results.
     *
     * @return Builder
     */
    public function distinct($column = false)
    {
        $this->distinct = true;

        if ($column)
        {
            $this->columns = array($column);
        }

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return Builder
     */
    public function orderBy($column, $direction = 'asc')
    {
        $direction = (strtolower($direction) == 'asc' ? 1 : -1);

        if ($column == 'natural')
        {
            $this->orders['$natural'] = $direction;
        }
        else
        {
            $this->orders[$column] = $direction;
        }

        return $this;
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return Builder
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'values', 'not');

        return $this;
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will have to detect
        // if the user is inserting a single document or an array of documents.
        $batch = true;

        foreach ($values as $value)
        {
            // As soon as we find a value that is not an array we assume the user is
            // inserting a single document.
            if ( ! is_array($value))
            {
                $batch = false; break;
            }
        }

        if ( ! $batch) $values = array($values);

        $params = array();
        foreach ($values as $document) {
            $params['body'][] = array(
                'index' => array(
                    '_index' => $this->index,
                    '_type'  => $this->from,
                )
            );

            $params['body'][] = $document;
        }

        // Bulk insert
        $result = $this->connection->bulk($params);

        return !$result['errors'];
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $params = array();
        $params['body']  = $values;
        $params['index'] = $this->index;
        $params['type']  = $this->from;

        $result = $this->connection->index($params);

        if ($result['created'])
        {
            if (is_null($sequence) || $sequence == "_id" || $sequence = "id")
            {
                return $result["_id"];
            }

            // Return id
            return $values[$sequence];
        }
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @param  array  $options
     * @return int
     */
    public function update(array $values, array $options = array())
    {
        return $this->performUpdate($values, $options);
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = array(), array $options = array())
    {
        $query = array('$inc' => array($column => $amount));

        if ( ! empty($extra))
        {
            $query['$set'] = $extra;
        }

        // Protect
        $this->where(function($query) use ($column)
        {
            $query->where($column, 'exists', false);

            $query->orWhereNotNull($column);
        });

        return $this->performUpdate($query, $options);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = array(), array $options = array())
    {
        return $this->increment($column, -1 * $amount, $extra, $options);
    }

    /**
     * Pluck a single column from the database.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        $result = (array) $this->first(array($column));

        // MongoDB returns the _id field even if you did not ask for it, so we need to
        // remove this from the result.
        if (array_key_exists('_id', $result))
        {
            unset($result['_id']);
        }

        return count($result) > 0 ? reset($result) : null;
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        $wheres = $this->compileWheres();

        $result = $this->collection->remove($wheres);

        if (1 == (int) $result['ok'])
        {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Run a truncate statement on the table.
     *
     * @return void
     */
    public function truncate()
    {
        $result = $this->collection->remove();

        return (1 == (int) $result['ok']);
    }

    /**
     * Create a raw database expression.
     *
     * @param  closure  $expression
     * @return mixed
     */
    public function raw($expression = null)
    {
        // Execute the closure on the mongodb collection
        if ($expression instanceof Closure)
        {
            return call_user_func($expression, $this->collection);
        }

        // Create an expression for the given value
        else if ( ! is_null($expression))
        {
            return new Expression($expression);
        }

        // Quick access to the mongodb collection
        return $this->collection;
    }

    /**
     * Append one or more values to an array.
     *
     * @param  mixed   $column
     * @param  mixed   $value
     * @return int
     */
    public function push($column, $value = null, $unique = false)
    {
        // Use the addToSet operator in case we only want unique items.
        $operator = $unique ? '$addToSet' : '$push';

        // Check if we are pushing multiple values.
        $batch = (is_array($value) and array_keys($value) === range(0, count($value) - 1));

        if (is_array($column))
        {
            $query = array($operator => $column);
        }
        else if ($batch)
        {
            $query = array($operator => array($column => array('$each' => $value)));
        }
        else
        {
            $query = array($operator => array($column => $value));
        }

        return $this->performUpdate($query);
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  mixed   $column
     * @param  mixed   $value
     * @return int
     */
    public function pull($column, $value = null)
    {
        // Check if we passed an associative array.
        $batch = (is_array($value) and array_keys($value) === range(0, count($value) - 1));

        // If we are pulling multiple values, we need to use $pullAll.
        $operator = $batch ? '$pullAll' : '$pull';

        if (is_array($column))
        {
            $query = array($operator => $column);
        }
        else
        {
            $query = array($operator => array($column => $value));
        }

        return $this->performUpdate($query);
    }

    /**
     * Remove one or more fields.
     *
     * @param  mixed $columns
     * @return int
     */
    public function drop($columns)
    {
        if ( ! is_array($columns)) $columns = array($columns);

        $fields = array();

        foreach ($columns as $column)
        {
            $fields[$column] = 1;
        }

        $query = array('$unset' => $fields);

        return $this->performUpdate($query);
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return new Builder($this->connection, $this->processor);
    }

    /**
     * Perform an update query.
     *
     * @param  array  $query
     * @param  array  $options
     * @return int
     */
    protected function performUpdate($query, array $options = array())
    {
        $params = array();
        $documents = $this->getFresh();

        foreach ($documents as $document) {
            $params['body'][] = array(
                'index' => array(
                    '_index' => $this->index,
                    '_type'  => $this->from,
                    '_id' => $document['_id']
                )
            );

            $params['body'][] = array_merge($document, $query);
        }

        $results = $this->connection->bulk($params);

        if (!$results['errors'])  return count($results['items']);

        return 0;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     *
     * @throws \InvalidArgumentException
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $params = func_get_args();

        // Remove the leading $ from operators.
        if (func_num_args() == 3)
        {
            $operator = &$params[1];

            if (starts_with($operator, '$'))
            {
                $operator = substr($operator, 1);
            }
        }

        return call_user_func_array('parent::where', $params);
    }

    /**
     * Compile the where array.
     *
     * @return array
     */
    public function compileWheres()
    {

        // We will add all compiled wheres to this array.
        $filter = array(
            'bool' => array(
                'must' => array(),
                'should' => array(),
                'must_not' => array()
            )
        );

        // The wheres to compile.
        $wheres = $this->wheres ?: array();

        foreach ($wheres as $i => &$where)
        {
            // Make sure the operator is in lowercase.
            if (isset($where['operator']))
            {
                $where['operator'] = strtolower($where['operator']);

                // Operator conversions
                $convert = array(
                    null => 'term',
                    '=' => 'term',
                    '<>' => 'not term',
                    '!=' => 'not term',
                    'regex' => 'regexp',
                    'rlike' => 'regexp',
                    'ilike' => 'regexp',
                );

                if (array_key_exists($where['operator'], $convert))
                {
                    $where['operator'] = $convert[$where['operator']];
                }
            }

            // The next item in a "chain" of wheres devices the boolean of the
            // first item. So if we see that there are multiple wheres, we will
            // use the operator of the next where.
            if ($i == 0 and count($wheres) > 1 and $where['boolean'] == 'and')
            {
                $where['boolean'] = $wheres[$i+1]['boolean'];
            }

            // We use different methods to compile different wheres.
            $method = "compileWhere{$where['type']}";
            $result = $this->{$method}($where);

            // Wrap the where with an $or operator.
            if ($where['boolean'] == 'or')
            {
                $filter['bool']['should'][] = $result;
            }
            else
            {
                $filter['bool']['must'][] = $result;
            }

        }

        return $filter;
    }

    protected function compileWhereBasic($where)
    {
        $filter = array();
        extract($where);


        if (starts_with($operator, 'not'))
        {
            $where['operator'] = str_replace('not ', '', $operator);
            return array(
                'not' => $this->compileWhereBasic($where)
            );
        }

        // Turn like into regex
        if ($operator == 'like')
        {
            $value = str_replace(['_','%'], ['.','.*'], strtolower($value));
            $operator = 'regexp';
        }
        else if ($operator == 'regexp')
        {
            // Elasticsearch is all lowercase
            $value = strtolower($value);

            // Elasticsearch auto anchors regex queries, so this will convert
            // sql-like regexp patterns to a format that will match Lucene's patterns
            if (!starts_with($operator, '^')){
                $value = ".*".$value;
            } else {
                $value = substr($value, 1);
            }

            if (!ends_with($operator, '$')){
                $value = $value.".*";
            } else {
                $value = substr($value, 0, strlen($value)-1);
            }

        }

        if (array_key_exists($operator, $this->conversion))
        {
            $filter= $this->conversion[$operator]($column, $value);
        }
        else
        {
            $filter = array($operator => array($column => $value));
        }

        return $filter;
    }

    protected function compileWhereNested($where)
    {
        extract($where);

        return $query->compileWheres();
    }

    protected function compileWhereIn($where)
    {
        extract($where);

        $where['operator'] = 'terms';
        $where['value'] = array_values($values);

        return $this->compileWhereBasic($where);
    }

    protected function compileWhereNotIn($where)
    {
        extract($where);

        $where['operator'] = 'not terms';
        $where['value'] = array_values($values);

        return $this->compileWhereBasic($where);
    }

    protected function compileWhereNull($where)
    {
        $where['operator'] = 'missing';
        $where['value'] = $where['column'];
        $where['column'] = 'field';


        return $this->compileWhereBasic($where);
    }

    protected function compileWhereNotNull($where)
    {
        $where['operator'] = 'exists';
        $where['value'] = $where['column'];
        $where['column'] = 'field';

        return $this->compileWhereBasic($where);
    }

    protected function compileWhereBetween($where)
    {
        extract($where);

        $where['operator'] = 'between';
        $where['value'] = $values;

        return $this->compileWhereBasic($where);
    }

    protected function compileWhereRaw($where)
    {
        return $where['sql'];
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($method == 'unset')
        {
            return call_user_func_array(array($this, 'drop'), $parameters);
        }

        return parent::__call($method, $parameters);
    }

}
