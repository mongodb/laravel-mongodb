<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;

use function array_key_exists;
use function class_uses_recursive;
use function is_subclass_of;

abstract class Model extends BaseModel
{
    use DocumentModel;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = '_id';

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /** @param class-string|object $related */
    public static function isDocumentModel(string|object $related): bool
    {
        return is_subclass_of($related, BaseModel::class)
            && array_key_exists(DocumentModel::class, class_uses_recursive($related));
    }
}
