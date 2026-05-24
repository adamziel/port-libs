<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class PackageResolution
{
    /**
     * @param list<string> $tried
     */
    public function __construct(
        public readonly ModuleImport $import,
        public readonly string $packageName,
        public readonly string $subpath,
        public readonly string $path,
        public readonly string $packageDir,
        public readonly ?string $packageJsonPath = null,
        public readonly ?string $mainField = null,
        public readonly array $tried = [],
        public readonly bool $external = false,
    ) {
    }
}
