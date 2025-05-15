<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Exceptions;

use Illuminate\Validation\ValidationException;
use MongoDB\Laravel\Eloquent\Model;

class SchemaValidationException extends ValidationException
{
    protected $model;

    public function __construct($validator, $model = null, $response = null, $errorBag = 'default')
    {
        parent::__construct($validator, $response, $errorBag);
        $this->model = $model;
    }

    /**
     * @return Model|null
     */
    public function getModel()
    {
        return $this->model;
    }
}
