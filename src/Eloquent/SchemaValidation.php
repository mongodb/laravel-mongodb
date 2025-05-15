<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use Illuminate\Support\Facades\Validator;
use MongoDB\Laravel\Exceptions\SchemaValidationException;

trait SchemaValidation
{
    protected bool $validateBeforeSave = false;

    /**
     * @return $this
     */
    public function withValidation(): static
    {
        $this->validateBeforeSave = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutValidation(): static
    {
        $this->validateBeforeSave = false;

        return $this;
    }

    /**
     * @return array
     */
    public function schemaRules(): array
    {
        return [];
    }

    /**
     * @return void
     * @throws SchemaValidationException
     */
    protected function validate(): void
    {
        $validator = Validator::make($this->attributesToArray(), $this->schemaRules());

        if ($validator->fails()) {
            throw new SchemaValidationException($validator, $this);
        }
    }

    public static function bootSchemaValidation(): void
    {
        static::saving(function ($model) {
            if ($model->validateBeforeSave) {
                $model->validate();
            }
        });

        static::saved(function ($model) {
            $model->withoutValidation();
        });
    }
}
