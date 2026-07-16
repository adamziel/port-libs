<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class ProofCommand
{
    public const HASH_PROVIDED = 0;
    public const HASH_EMPTY = 1;
    public const MERGE = 2;

    public function __construct(
        public readonly int $operation,
        public readonly int $nodeOffset,
        public readonly string $hash = ''
    ) {
        if ($nodeOffset < 0) {
            throw new \InvalidArgumentException('proof command node offset must be non-negative');
        }
    }
}
