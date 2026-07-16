<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class SyncRequest
{
    private Key $path;

    public function __construct(
        Key $path,
        public readonly int $startDepth,
        public readonly int $depthLimit,
        public readonly bool $expandLeaves
    ) {
        if ($startDepth < 0 || $startDepth > 255) {
            throw new \InvalidArgumentException('sync request startDepth must be between 0 and 255');
        }
        if ($depthLimit < 0 || $depthLimit > 255) {
            throw new \InvalidArgumentException('sync request depthLimit must be between 0 and 255');
        }

        $this->path = new Key($path->bytes());
        $this->path->keepPrefixBits($startDepth);
    }

    public function path(): Key
    {
        return new Key($this->path->bytes());
    }

    public function pathHex(): string
    {
        return $this->path->hex();
    }
}
