<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreeblock
{
    public function __construct(
        public readonly int $offset,
        public readonly int $size,
        public readonly ?int $nextOffset,
    ) {
    }

    public function endOffset(): int
    {
        return $this->offset + $this->size;
    }

    /**
     * @return array{offset:int,size:int,end_offset:int,next_offset:?int}
     */
    public function toArray(): array
    {
        return [
            'offset' => $this->offset,
            'size' => $this->size,
            'end_offset' => $this->endOffset(),
            'next_offset' => $this->nextOffset,
        ];
    }
}
