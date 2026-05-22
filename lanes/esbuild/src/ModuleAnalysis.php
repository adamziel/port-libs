<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class ModuleAnalysis
{
    /**
     * @param list<ModuleImport> $imports
     * @param list<ModuleExport> $exports
     * @param list<int> $importMetaOffsets
     * @param list<array{property:string, offset:int}> $importMetaProperties
     * @param list<AssetReference> $assetReferences
     */
    public function __construct(
        public readonly array $imports,
        public readonly array $exports,
        public readonly array $importMetaOffsets = [],
        public readonly array $importMetaProperties = [],
        public readonly array $assetReferences = [],
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

    public function hasImportMeta(): bool
    {
        return $this->importMetaOffsets !== [];
    }

    public function isConsideredESModule(): bool
    {
        if ($this->exports !== [] || $this->hasImportMeta()) {
            return true;
        }

        foreach ($this->imports as $import) {
            if ($import->kind !== 'dynamic') {
                return true;
            }
        }

        return false;
    }
}
