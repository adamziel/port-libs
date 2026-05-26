<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Block
{
    public function __construct(
        public readonly int $offset,
        public readonly int $size,
        public readonly string $hashHex,
    ) {
    }
}

