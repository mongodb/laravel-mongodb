<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Relations;

/** @internal */
final class ThroughKeyState
{
    /** @param array<string, mixed> $farParentKeyByThroughKey */
    public function __construct(
        public readonly array $farParentKeyByThroughKey = [],
        public readonly int|string|null $whereIndex = null,
    ) {
    }

    /** @param array<string, mixed> $farParentKeyByThroughKey */
    public function resolvedTo(array $farParentKeyByThroughKey): self
    {
        return new self($farParentKeyByThroughKey, $this->whereIndex);
    }

    public function constrainedAt(int|string $whereIndex): self
    {
        return new self($this->farParentKeyByThroughKey, $whereIndex);
    }
}
