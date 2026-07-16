<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class AssetReference
{
    public function __construct(
        public readonly string $source,
        public readonly int $offset,
        public readonly string $base,
        public readonly string $context,
    ) {
    }

    public function isRelative(): bool
    {
        return str_starts_with($this->source, './')
            || str_starts_with($this->source, '../')
            || str_starts_with($this->source, '/');
    }
}
