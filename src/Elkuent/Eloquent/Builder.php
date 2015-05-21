<?php namespace Elkuent\Eloquent;

use Illuminate\Database\Eloquent\Builder as EBuilder;

class Builder extends EBuilder {

    public function __construct($query) 
    {
        parent::__construct($query);
        array_push($this->passthru, 'getRawAggregation');
    }

}
