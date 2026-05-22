<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class TrackedSparseTreeEntry
{
    public function __construct(
        private readonly Key $key,
        private readonly string $value,
        public readonly int $nodeId
    ) {
    }

    public function key(): Key
    {
        return $this->key;
    }

    public function keyHex(): string
    {
        return $this->key->hex();
    }

    public function value(): string
    {
        return $this->value;
    }
}
