<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MultiPackIndexEntry
{
    public function __construct(
        public readonly string $oid,
        public readonly int $packIndex,
        public readonly int $packOffset,
        public readonly int $index,
    ) {
        if (preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Multi-pack-index object id must be a SHA-1 or SHA-256 hex string');
        }
        if ($packIndex < 0) {
            throw new \InvalidArgumentException('Multi-pack-index pack index cannot be negative');
        }
        if ($packOffset < 0) {
            throw new \InvalidArgumentException('Multi-pack-index pack offset cannot be negative');
        }
        if ($index < 0) {
            throw new \InvalidArgumentException('Multi-pack-index entry index cannot be negative');
        }
    }
}
