<?php namespace Elkuent;

// use Elkuent\Builder;
use Elkuent\Eloquent\Builder;
use Elkuent\Eloquent\Model as EModel;

use Carbon\Carbon;
use DateTime;

class Model extends EModel {

    protected $index = null;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = '_id';

    /**
     * The parent relation instance.
     *
     * @var Relation
     */
    protected $parentRelation;

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected static $resolver;

    /**
     * Put Mapping
     *
     * @param    array $mapping
     * @param    bool $ignoreConflicts
     * @return   array
     */
    public function putMapping($mapping, $ignoreConflicts = false)
    {
        $params = array();

        $params['index'] = $this->index;
        $params['type'] = $this->table;
        $params['ignore_conflicts'] = $ignoreConflicts;
        $params['body'][$this->table] = array(
            'properties' => $mapping
        );

        return $this->getConnection()->indices()->putMapping($params);
    }

    /**
     * Get Index Name
     *
     * @return string
     */
    public function getIndexName()
    {
        return $this->index;
    }

    /**
     * Set Index Name
     *
     * @return string
     */
    public function setIndexName($name)
    {
        $this->index = $name;

        return $this->index;
    }

    /**
     * Custom accessor for the model's id.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function getIdAttribute($value)
    {
        // If we don't have a value for 'id', we will use the Mongo '_id' value.
        // This allows us to work with models in a more sql-like way.
        if ( ! $value and array_key_exists('_id', $this->attributes))
        {
            $value = $this->attributes['_id'];
        }

        return $value;
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName()
    {
        return $this->getKeyName();
    }


    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    protected function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        // Check if the key is an array dot notation.
        if (str_contains($key, '.'))
        {
            $attributes = array_dot($this->attributes);

            if (array_key_exists($key, $attributes))
            {
                return $this->getAttributeValue($key);
            }
        }

        $camelKey = camel_case($key);

        return parent::getAttribute($key);
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        // Support keys in dot notation.
        if (str_contains($key, '.'))
        {
            $attributes = array_dot($this->attributes);

            if (array_key_exists($key, $attributes))
            {
                return $attributes[$key];
            }
        }

        return parent::getAttributeFromArray($key);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        // Support keys in dot notation.
        if (str_contains($key, '.'))
        {
            if (in_array($key, $this->getDates()) && $value)
            {
                $value = $this->fromDateTime($value);
            }

            array_set($this->attributes, $key, $value); return;
        }

        parent::setAttribute($key, $value);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
     /*
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new Builder($connection, $connection->getPostProcessor(), $this->index);
    }
    */

    /**
     * Create a new Eloquent query builder for the model.
     *
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
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
        // Unset method
        if ($method == 'unset')
        {
            return call_user_func_array(array($this, 'drop'), $parameters);
        }

        return parent::__call($method, $parameters);
    }

}
