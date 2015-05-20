<?php namespace Elkuent\Eloquent;

use Elkuent\Builder;
use Illuminate\Database\Eloquent\Model as EModel;

class Model extends EModel {

    /**
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {

        $connection = $this->getConnection();

        return new Builder($connection, $connection->getPostProcessor(), $this->index);
    }

}
