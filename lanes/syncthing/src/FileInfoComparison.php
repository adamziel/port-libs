<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FileInfoComparison
{
    public function __construct(
        public readonly int $modTimeWindowNs = 0,
        public readonly bool $ignorePerms = false,
        public readonly bool $ignoreBlocks = false,
        public readonly int $ignoreFlags = 0,
        public readonly bool $ignoreOwnership = false,
        public readonly bool $ignoreXattrs = false,
    ) {
        if ($this->modTimeWindowNs < 0 || $this->ignoreFlags < 0) {
            throw new \InvalidArgumentException('Comparison options must not be negative');
        }
    }
}
