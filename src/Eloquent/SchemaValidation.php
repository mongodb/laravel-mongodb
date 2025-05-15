<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use Illuminate\Support\Facades\Validator;
use MongoDB\Laravel\Exception\SchemaValidationException;

trait SchemaValidation
{
    protected bool $validateBeforeSave = false;

    /**
     * @param bool $validateBeforeSave
     * @return $this
     */
    public function markForValidation(bool $validateBeforeSave = true): static
    {
        $this->validateBeforeSave = $validateBeforeSave;

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
            throw new SchemaValidationException($validator);
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
            $model->validateBeforeSave = false;
        });
    }
}
