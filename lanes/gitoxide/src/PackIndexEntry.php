<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackIndexEntry
{
    public function __construct(
        public readonly string $oid,
        public readonly int $packOffset,
        public readonly ?int $crc32,
        public readonly int $index,
    ) {
        if (preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Pack index object id must be a SHA-1 or SHA-256 hex string');
        }
        if ($packOffset < 0) {
            throw new \InvalidArgumentException('Pack index offset cannot be negative');
        }
    }
}
