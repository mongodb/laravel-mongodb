<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use Illuminate\Database\Eloquent\MassPrunable as EloquentMassPrunable;
use Illuminate\Database\Events\ModelsPruned;

use function class_uses_recursive;
use function event;
use function in_array;

trait MassPrunable
{
    use EloquentMassPrunable;

    /**
     * Prune all prunable models in the database.
     *
     * @see \Illuminate\Database\Eloquent\MassPrunable::pruneAll()
     */
    public function pruneAll(): int
    {
        $query = $this->prunable();
        $total = in_array(SoftDeletes::class, class_uses_recursive(static::class))
                    ? $query->forceDelete()
                    : $query->delete();

        event(new ModelsPruned(static::class, $total));

        return $total;
    }
}
