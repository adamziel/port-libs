<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class TsConfigPathResolution
{
    /**
     * @param list<string> $tried
     */
    public function __construct(
        public readonly ModuleImport $import,
        public readonly string $path,
        public readonly string $tsconfigPath,
        public readonly string $baseUrl,
        public readonly string $matchedPattern,
        public readonly string $targetPattern,
        public readonly array $tried = [],
    ) {
    }
}
