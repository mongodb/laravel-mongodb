<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Relations;

/** @internal */
final class ThroughKeyState
{
    /**
     * @param array<string, mixed> $farParentKeyByThroughKey
     * @param list<mixed>|null     $resolvedFarParentKeys
     */
    public function __construct(
        public readonly array $farParentKeyByThroughKey = [],
        public readonly int|string|null $whereIndex = null,
        public readonly ?array $resolvedFarParentKeys = null,
        public readonly bool $keepTrashedParents = false,
    ) {
    }

    /**
     * @param list<mixed>          $resolvedFarParentKeys
     * @param array<string, mixed> $farParentKeyByThroughKey
     */
    public function resolvedTo(array $resolvedFarParentKeys, array $farParentKeyByThroughKey): self
    {
        return new self($farParentKeyByThroughKey, $this->whereIndex, $resolvedFarParentKeys, $this->keepTrashedParents);
    }

    public function constrainedAt(int|string $whereIndex): self
    {
        return new self($this->farParentKeyByThroughKey, $whereIndex, $this->resolvedFarParentKeys, $this->keepTrashedParents);
    }

    public function keepingTrashedParents(): self
    {
        return new self($this->farParentKeyByThroughKey, $this->whereIndex, $this->resolvedFarParentKeys, true);
    }
}
