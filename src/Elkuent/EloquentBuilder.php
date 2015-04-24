<?php namespace Elkuent\Eloquent;

use Illuminate\Database\Eloquent\Builder as EBuilder;

class Builder extends EBuilder {

    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = array(
        'toSql', 'lists', 'insert', 'insertGetId', 'pluck',
        'count', 'min', 'max', 'avg', 'sum', 'exists', 'push', 'pull', 'test'
    );

    public function foo()
    {
        return 'we';
    }

}
