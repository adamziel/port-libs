<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class SparseTreeIterator
{
    /**
     * @param list<SparseTreeEntry> $entries
     */
    public function __construct(
        private readonly array $entries,
        private int $position,
        private readonly bool $reverse
    ) {
    }

    public function atEnd(): bool
    {
        return $this->position < 0 || $this->position >= count($this->entries);
    }

    public function get(): ?SparseTreeEntry
    {
        if ($this->atEnd()) {
            return null;
        }

        return $this->entries[$this->position];
    }

    public function next(): void
    {
        $this->position += $this->reverse ? -1 : 1;
    }
}
