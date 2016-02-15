<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class EmbedsOneOrMany extends Relation
{
    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The "name" of the relationship.
     *
     * @var string
     */
    protected $relation;

    /**
     * Create a new embeds many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model    $parent
     * @param  \Illuminate\Database\Eloquent\Model    $related
     * @param  string  $localKey
     * @param  string  $foreignKey
     * @param  string  $relation
     */
    public function __construct(Builder $query, Model $parent, Model $related, $localKey, $foreignKey, $relation)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $related;
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->relation = $relation;

         // If this is a nested relation, we need to get the parent query instead.
        if ($parentRelation = $this->getParentRelation()) {
            $this->query = $parentRelation->getQuery();
        }

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->getQualifiedParentKeyName(), '=', $this->getParentKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     */
    public function addEagerConstraints(array $models)
    {
        // There are no eager loading constraints.
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        foreach ($models as $model) {
            $results = $model->$relation()->getResults();

            $model->setParentRelation($this);

            $model->setRelation($relation, $results);
        }

        return $models;
    }

    /**
     * Shorthand to get the results of the relationship.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get()
    {
        return $this->getResults();
    }

    /**
     * Get the number of embedded models.
     *
     * @return int
     */
    public function count()
    {
        return count($this->getEmbedded());
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function save(Model $model)
    {
        $model->setParentRelation($this);

        return $model->save() ? $model : false;
    }

    /**
     * Attach a collection of models to the parent instance.
     *
     * @param  \Illuminate\Database\Eloquent\Collection|array  $models
     * @return \Illuminate\Database\Eloquent\Collection|array
     */
    public function saveMany($models)
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $attributes)
    {
        // Here we will set the raw attributes to avoid hitting the "fill" method so
        // that we do not have to worry about a mass accessor rules blocking sets
        // on the models. Otherwise, some of these attributes will not get set.
        $instance = $this->related->newInstance($attributes);

        $instance->setParentRelation($this);

        $instance->save();

        return $instance;
    }

    /**
     * Create an array of new instances of the related model.
     *
     * @param  array  $records
     * @return array
     */
    public function createMany(array $records)
    {
        $instances = [];

        foreach ($records as $record) {
            $instances[] = $this->create($record);
        }

        return $instances;
    }

    /**
     * Transform single ID, single Model or array of Models into an array of IDs.
     *
     * @param  mixed  $ids
     * @return array
     */
    protected function getIdsArrayFrom($ids)
    {
        if ($ids instanceof \Illuminate\Support\Collection) {
            $ids = $ids->all();
        }

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as &$id) {
            if ($id instanceof Model) {
                $id = $id->getKey();
            }
        }

        return $ids;
    }

    /**
     * Get the embedded records array.
     *
     * @return array
     */
    protected function getEmbedded()
    {
        // Get raw attributes to skip relations and accessors.
        $attributes = $this->parent->getAttributes();

        // Get embedded models form parent attributes.
        $embedded = isset($attributes[$this->localKey]) ? (array) $attributes[$this->localKey] : null;

        return $embedded;
    }

    /**
     * Set the embedded records array.
     *
     * @param  array  $records
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function setEmbedded($records)
    {
        // Assign models to parent attributes array.
        $attributes = $this->parent->getAttributes();
        $attributes[$this->localKey] = $records;

        // Set raw attributes to skip mutators.
        $this->parent->setRawAttributes($attributes);

        // Set the relation on the parent.
        return $this->parent->setRelation($this->relation, $records === null ? null : $this->getResults());
    }

    /**
     * Get the foreign key value for the relation.
     *
     * @param  mixed  $id
     * @return mixed
     */
    protected function getForeignKeyValue($id)
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        // Convert the id to MongoId if necessary.
        return $this->getBaseQuery()->convertKey($id);
    }

    /**
     * Convert an array of records to a Collection.
     *
     * @param  array  $records
     * @return \Jenssegers\Mongodb\Eloquent\Collection
     */
    protected function toCollection(array $records = [])
    {
        $models = [];

        foreach ($records as $attributes) {
            $models[] = $this->toModel($attributes);
        }

        if (count($models) > 0) {
            $models = $this->eagerLoadRelations($models);
        }

        return new Collection($models);
    }

    /**
     * Create a related model instanced.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function toModel($attributes = [])
    {
        if (is_null($attributes)) {
            return;
        }

        $model = $this->related->newFromBuilder((array) $attributes);

        $model->setParentRelation($this);

        $model->setRelation($this->foreignKey, $this->parent);

        // If you remove this, you will get segmentation faults!
        $model->setHidden(array_merge($model->getHidden(), [$this->foreignKey]));

        return $model;
    }

    /**
     * Get the relation instance of the parent.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    protected function getParentRelation()
    {
        return $this->parent->getParentRelation();
    }

    /**
     * Get the underlying query for the relation.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getQuery()
    {
        // Because we are sharing this relation instance to models, we need
        // to make sure we use separate query instances.
        return clone $this->query;
    }

    /**
     * Get the base query builder driving the Eloquent builder.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function getBaseQuery()
    {
        // Because we are sharing this relation instance to models, we need
        // to make sure we use separate query instances.
        return clone $this->query->getQuery();
    }

    /**
     * Check if this relation is nested in another relation.
     *
     * @return bool
     */
    protected function isNested()
    {
        return $this->getParentRelation() != null;
    }

    /**
     * Get the fully qualified local key name.
     *
     * @param  string  $glue
     * @return string
     */
    protected function getPathHierarchy($glue = '.')
    {
        if ($parentRelation = $this->getParentRelation()) {
            return $parentRelation->getPathHierarchy($glue) . $glue . $this->localKey;
        }

        return $this->localKey;
    }

    /**
     * Get the parent's fully qualified key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        if ($parentRelation = $this->getParentRelation()) {
            return $parentRelation->getPathHierarchy() . '.' . $this->parent->getKeyName();
        }

        return $this->parent->getKeyName();
    }

    /**
     * Get the primary key value of the parent.
     *
     * @return string
     */
    protected function getParentKey()
    {
        return $this->parent->getKey();
    }
}
