<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

use MongoId;

class EmbedsMany extends EmbeddedRelation {

    /**
     * The parent collection attribute where the related are stored.
     *
     * @var string
     */
    protected $collection;

    /**
     * Create a new has many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $related
     * @param  string  $collection
     * @return void
     */
    public function __construct(Model $parent, $related, $collection)
    {
        $this->collection = $collection;

        parent::__construct($parent, $related);
    }

    /**
     * Get the results of the relationship.
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function getResults()
    {
        $models = new Collection();

        $modelsAttributes = $this->parent->getAttribute($this->collection);

        if (is_array($modelsAttributes))
        {
            foreach ($modelsAttributes as $attributes)
            {
                $models->push(new $this->related($attributes));
            }
        }

        return $models;
    }

    /**
     * Create a new instance and attach it to the parent model.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function build(array $attributes)
    {
        if ( ! isset($attributes['_id'])) $attributes['_id'] = new MongoId;

        $collection = $this->parent->getAttribute($this->collection);
        $collection[''.$attributes['_id']] = $attributes;
        $this->parent->setAttribute($this->collection, $collection);

        return new $this->related($attributes);
    }

    /**
     * Attach an instance to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function add($model)
    {
        return $this->build($model->toArray());
    }

    /**
     * Create a new instance, attach it to the parent model and save this model.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $attributes)
    {
        $instance = $this->build($attributes);

        $this->parent->save();

        return $instance;
    }

    /**
     * Attach a model instance to the parent model and save this model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function push($model)
    {
        return $this->create($model->toArray());
    }

    /**
     * Create an array of new instances and attach them to the parent model.
     *
     * @param  array|Traversable  $modelsAttributes
     * @return array
     */
    public function buildMany($modelsAttributes)
    {
        $instances = array();

        foreach ($modelsAttributes as $attributes)
        {
            $instances[] = $this->build($attributes);
        }

        return $instances;
    }

    /**
     * Attach an array of new instances to the parent model.
     *
     * @param  array|Traversable  $models
     * @return array
     */
    public function addMany($models)
    {
        $modelsAttributes = $this->getAttributesOf($models);

        return $this->buildMany($modelsAttributes);
    }

    /**
     * Create an array of new instances, attach them to the parent model and save this model.
     *
     * @param  array|Traversable  $modelsAttributes
     * @return array
     */
    public function createMany($modelsAttributes)
    {
        $instances = $this->buildMany($modelsAttributes);

        $this->parent->save();

        return $instances;
    }

    /**
     * Attach an array of new instances to the parent model and save this model.
     *
     * @param  array|Traversable  $models
     * @return array
     */
    public function pushMany($models)
    {
        $modelsAttributes = $this->getAttributesOf($models);

        return $this->createMany($modelsAttributes);
    }

    /**
     * Transform a list of models to a list of models' attributes
     *
     * @param  array|Traversable  $models
     * @return array
     */
    protected function getAttributesOf($models)
    {
        $modelsAttributes = array();

        foreach ($models as $key => $model)
        {
            $modelsAttributes[$key] = $model->getAttributes();
        }

        return $modelsAttributes;
    }

}
