<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class ImportRecord
{
    public function __construct(
        public readonly string $kind,
        public readonly string $source,
        public readonly string $path,
        public readonly bool $external,
        public readonly ?string $mainField = null,
        public readonly ?string $packageName = null,
        public readonly ?string $subpath = null,
    ) {
    }
}
