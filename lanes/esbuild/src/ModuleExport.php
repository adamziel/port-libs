<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class ModuleExport
{
    /**
     * @param list<array{exported:string, local:?string}> $specifiers
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?string $source,
        public readonly array $specifiers,
        public readonly int $offset,
        public readonly ?string $attributesKeyword = null,
        public readonly array $attributes = [],
    ) {
    }

    public function isReExport(): bool
    {
        return $this->source !== null;
    }
}
