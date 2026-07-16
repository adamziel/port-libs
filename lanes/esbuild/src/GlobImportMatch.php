<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class GlobImportMatch
{
    public function __construct(
        public readonly ModuleImport $import,
        public readonly string $key,
        public readonly string $path,
    ) {
    }
}
