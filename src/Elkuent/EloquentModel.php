<?php namespace Elkuent;

use Elkuent\Builder;

class EloquentModel extends \Illuminate\Database\Eloquent\Model {

    /**
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
        var_dump('NEW QUERY');

        $connection = $this->getConnection();

        return new Builder($connection, $connection->getPostProcessor(), $this->index);
    }

}
