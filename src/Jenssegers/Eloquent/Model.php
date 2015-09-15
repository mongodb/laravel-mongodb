<?php namespace Jenssegers\Eloquent;

use Jenssegers\Mongodb\Eloquent\HybridRelations;

abstract class Model extends \Illuminate\Database\Eloquent\Model {

    use HybridRelations;

    /**
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        // Check the connection type
        if ($connection instanceof \Jenssegers\Mongodb\Connection)
        {
            return new QueryBuilder($connection, $connection->getPostProcessor());
        }

        return parent::newBaseQueryBuilder();
    }

}
