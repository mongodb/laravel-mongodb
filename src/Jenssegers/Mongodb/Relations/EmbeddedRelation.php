<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Model;

abstract class EmbeddedRelation {

    /**
     * The parent model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $parent;

    /**
     * The related model class name.
     *
     * @var string
     */
    protected $related;

    /**
     * Create a new has many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $related
     * @param  string  $collection
     * @return void
     */
    public function __construct(Model $parent, $related)
    {
        $this->parent = $parent;
        $this->related = $related;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    abstract public function getResults();

}
