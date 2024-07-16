<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use MongoDB\Laravel\Auth\User;

use function array_key_exists;
use function class_uses_recursive;
use function is_object;
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

    private static $documentModelClasses = [
        User::class => true,
    ];

    /**
     * Indicates if the given model class is a MongoDB document model.
     * It must be a subclass of {@see BaseModel} and use the
     * {@see DocumentModel} trait.
     *
     * @param class-string|object $class
     */
    final public static function isDocumentModel(string|object $class): bool
    {
        if (is_object($class)) {
            $class = $class::class;
        }

        if (array_key_exists($class, self::$documentModelClasses)) {
            return self::$documentModelClasses[$class];
        }

        // We know all child classes of this class are document models.
        if (is_subclass_of($class, self::class)) {
            return self::$documentModelClasses[$class] = true;
        }

        // Document models must be subclasses of Laravel's base model class.
        if (! is_subclass_of($class, BaseModel::class)) {
            return self::$documentModelClasses[$class] = false;
        }

        // Document models must use the DocumentModel trait.
        return self::$documentModelClasses[$class] = array_key_exists(DocumentModel::class, class_uses_recursive($class));
    }
}
