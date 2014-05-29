<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Collection;
use MongoId;

abstract class EmbedsOneOrMany extends Relation {

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
     * @param  string  $localKey
     * @param  string  $foreignKey
     * @param  string  $relation
     * @return void
     */
    public function __construct(Builder $query, Model $parent, Model $related, $localKey, $foreignKey, $relation)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $related;
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->relation = $relation;

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints)
        {
            $this->query->where($this->parent->getKeyName(), '=', $this->parent->getKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // There are no eager loading constraints.
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return void
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model)
        {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
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
        foreach ($models as $model)
        {
            $results = $model->$relation()->getResults();

            $model->setRelation($relation, $results);
        }

        return $models;
    }

    /**
    * Shorthand to get the results of the relationship.
    *
    * @return Illuminate\Database\Eloquent\Collection
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
        if ($this->fireModelEvent($model, 'saving') === false) return false;

        $this->updateTimestamps($model);

        // Attach a new model.
        if ( ! $this->contains($model))
        {
            if ($this->fireModelEvent($model, 'creating') === false) return false;

            $result = $this->performInsert($model);

            if ($result)
            {
                $this->fireModelEvent($model, 'created', false);

                // Mark model as existing
                $model->exists = true;
            }
        }

        // Update an existing model.
        else
        {
            if ($this->fireModelEvent($model, 'updating') === false) return false;

            $result = $this->performUpdate($model);

            if ($result) $this->fireModelEvent($model, 'updated', false);
        }

        if ($result)
        {
            $this->fireModelEvent($result, 'saved', false);

            return $result;
        }

        return false;
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
        $instance = $this->related->newInstance();

        $instance->setRawAttributes($attributes);

        return $this->save($instance);
    }

    /**
     * Attach an array of models to the parent instance.
     *
     * @param  array  $models
     * @return array
     */
    public function saveMany(array $models)
    {
        array_walk($models, array($this, 'save'));

        return $models;
    }

    /**
     * Create an array of new instances of the related model.
     *
     * @param  array  $records
     * @return array
     */
    public function createMany(array $records)
    {
        $instances = array_map(array($this, 'create'), $records);

        return $instances;
    }

    /**
     * Transform single ID, single Model or array of Models into an array of IDs
     *
     * @param  mixed  $ids
     * @return array
     */
    protected function getIdsArrayFrom($ids)
    {
        if (! is_array($ids)) $ids = array($ids);

        foreach ($ids as &$id)
        {
            if ($id instanceof Model) $id = $id->getKey();
        }

        return $ids;
    }

    /**
     * Create a related model instanced.
     *
     * @param  array  $attributes [description]
     * @return [type]             [description]
     */
    protected function toModel($attributes = array())
    {
        if (is_null($attributes)) return null;

        $model = $this->related->newFromBuilder((array) $attributes);

        // Attatch the parent relation to the embedded model.
        $model->setRelation($this->foreignKey, $this->parent);

        $model->setHidden(array_merge($model->getHidden(), array($this->foreignKey)));

        return $model;
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

        return isset($attributes[$this->localKey]) ? $attributes[$this->localKey] : null;
    }

    /**
     * Set the embedded records array.
     *
     * @param array $models
     * @return void
     */
    protected function setEmbedded($data)
    {
        $attributes = $this->parent->getAttributes();

        $attributes[$this->localKey] = $data;

        // Set raw attributes to skip mutators.
        $this->parent->setRawAttributes($attributes);

        // Set the relation on the parent.
        $this->parent->setRelation($this->relation, $this->getResults());
    }

    /**
     * Update the creation and update timestamps.
     *
     * @return void
     */
    protected function updateTimestamps(Model $model)
    {
        // Check if this model uses timestamps first.
        if ( ! $model->timestamps) return;

        $time = $model->freshTimestamp();

        if ( ! $model->isDirty(Model::UPDATED_AT))
        {
            $model->setUpdatedAt($time);
        }

        if ( ! $model->exists && ! $model->isDirty(Model::CREATED_AT))
        {
            $model->setCreatedAt($time);
        }
    }

    /**
     * Get the foreign key value for the relation.
     *
     * @param  mixed $id
     * @return mixed
     */
    protected function getForeignKeyValue($id)
    {
        if ($id instanceof Model)
        {
            $id = $id->getKey();
        }

        // Convert the id to MongoId if necessary.
        return $this->getBaseQuery()->convertKey($id);
    }

    /**
     * Convert an array of records to a Collection.
     *
     * @param  array  $records
     * @return Illuminate\Database\Eloquent\Collection
     */
    protected function toCollection(array $records = array())
    {
        $models = array();

        // Wrap records in model objects.
        foreach ($records as $attributes)
        {
            $models[] = $this->toModel($attributes);
        }

        if (count($models) > 0)
        {
            $models = $this->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Fire the given event for the given model.
     *
     * @param  string  $event
     * @param  bool    $halt
     * @return mixed
     */
    protected function fireModelEvent(Model $model, $event, $halt = true)
    {
        $dispatcher = $model->getEventDispatcher();

        if ( is_null($dispatcher)) return true;

        // We will append the names of the class to the event to distinguish it from
        // other model events that are fired, allowing us to listen on each model
        // event set individually instead of catching event for all the models.
        $event = "eloquent.{$event}: ".get_class($model);

        $method = $halt ? 'until' : 'fire';

        return $dispatcher->$method($event, $model);
    }

}
