<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class OptionsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Options
    {
        $attributes = new Options();
        if (! empty($value['option1'])) {
            $attributes->setOption1($value['option1']);
        }

        if (! empty($value['option2'])) {
            $attributes->setOption2($value['option2']);
        }

        return $attributes;
    }

    /**
     * @param Model        $model
     * @param string       $key
     * @param Options|null $value
     * @param array        $attributes
     *
     * @return null[]|object[]
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [
            $key => $value?->serialize(),
        ];
    }
}
