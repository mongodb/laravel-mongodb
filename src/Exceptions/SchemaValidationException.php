<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Exceptions;

use Illuminate\Validation\ValidationException;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @template TModel of \MongoDB\Laravel\Eloquent\Model
 */
class SchemaValidationException extends ValidationException
{
    /**
     * The affected Eloquent model.
     *
     * @var TModel
     */
    protected $model;

    public function __construct($validator, $model = null, $response = null, $errorBag = 'default')
    {
        parent::__construct($validator, $response, $errorBag);
        $this->model = $model;
    }

    /**
     * Get the affected Eloquent model.
     *
     * @return TModel
     */
    public function getModel()
    {
        return $this->model;
    }
}
