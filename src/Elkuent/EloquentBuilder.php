<?php namespace Elkuent\Eloquent;

use Illuminate\Database\Eloquent\Builder as EBuilder;

class Builder extends EBuilder {

    // /**
    //  * The methods that should be returned from query builder.
    //  *
    //  * @var array
    //  */
    // protected $passthru = array(
    //     'toSql', 'lists', 'insert', 'insertGetId', 'pluck',
    //     'count', 'min', 'max', 'avg', 'sum', 'exists', 'push', 'pull', 'getAggregation'
    // );

    public function __construct($query) 
    {
        parent::__construct($query);
        array_push($this->passthru, 'getRawAggregation');
    }

    public function foo()
    {
        return 'we';
    }

}
