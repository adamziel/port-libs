<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class BundlerEdge
{
    public function __construct(
        public readonly string $kind,
        public readonly string $source,
        public readonly ?string $path,
        public readonly bool $external,
        public readonly bool $missing,
        public readonly ?string $mainField = null,
        public readonly ?string $loader = null,
    ) {
    }
}
