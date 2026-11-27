<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MongoDB\Laravel\Eloquent\Model;

use function array_keys;
use function implode;
use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

/** @internal */
final class IgnoredPivotAttributes
{
    /** @param array<array-key, mixed> $attributes */
    public function __construct(
        private readonly array $attributes,
        private readonly BelongsToMany $relation,
    ) {
    }

    public function report(): void
    {
        if ($this->attributes === []) {
            return;
        }

        trigger_error(sprintf(
            'The pivot attributes [%s] passed to the [%s] relation are not stored: a MongoDB relation keeps only the related ids, and never has. This will throw in 6.0. %s',
            implode(', ', array_keys($this->attributes)),
            $this->relation->getRelationName(),
            $this->advice(),
        ), E_USER_DEPRECATED);
    }

    private function advice(): string
    {
        if (Model::isDocumentModel($this->relation->getParent())) {
            return 'The recommended way to model them is embedded in the documents of the side you read most, in an embedsMany relation that holds the related id and the attributes, or in a model of its own that references both sides. See https://www.mongodb.com/docs/manual/tutorial/model-embedded-many-to-many-relationships-between-documents/';
        }

        return 'The recommended way to model them is a model of its own that references both sides.';
    }
}
