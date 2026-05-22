<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class ModuleAnalysis
{
    /**
     * @param list<ModuleImport> $imports
     * @param list<ModuleExport> $exports
     */
    public function __construct(
        public readonly array $imports,
        public readonly array $exports,
    ) {
    }

    /**
     * @return list<ModuleImport>
     */
    public function packageImports(): array
    {
        return array_values(array_filter($this->imports, static fn (ModuleImport $import): bool => $import->isPackage()));
    }

    /**
     * @return list<ModuleImport>
     */
    public function relativeImports(): array
    {
        return array_values(array_filter($this->imports, static fn (ModuleImport $import): bool => $import->isRelative()));
    }

    /**
     * @return list<ModuleImport>
     */
    public function wordpressPackageImports(): array
    {
        return array_values(array_filter($this->imports, static fn (ModuleImport $import): bool => $import->isWordPressPackage()));
    }
}
