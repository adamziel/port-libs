<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class ProofStrand
{
    public const LEAF = 0;
    public const INVALID = 1;
    public const WITNESS_LEAF = 2;
    public const WITNESS_EMPTY = 3;
    public const WITNESS = 4;

    public function __construct(
        public readonly int $type,
        public readonly int $depth,
        public readonly string $keyHash,
        public readonly string $value = '',
        public readonly string $key = ''
    ) {
        if ($depth < 0 || $depth > 255) {
            throw new \InvalidArgumentException('proof strand depth must fit in one byte');
        }
    }
}
