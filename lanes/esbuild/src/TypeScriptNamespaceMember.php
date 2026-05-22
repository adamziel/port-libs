<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class TypeScriptNamespaceMember
{
    public function __construct(
        public readonly string $name,
        public readonly string $kind,
        public readonly bool $exported,
        public readonly bool $declared,
        public readonly bool $typeOnly,
        public readonly int $offset,
        public readonly ?string $source = null,
    ) {
    }
}
