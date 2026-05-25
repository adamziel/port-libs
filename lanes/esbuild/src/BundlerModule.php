<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class BundlerModule
{
    /**
     * @param list<BundlerEdge> $edges
     */
    public function __construct(
        public readonly string $path,
        public readonly ModuleAnalysis $analysis,
        public readonly array $edges,
    ) {
    }
}
