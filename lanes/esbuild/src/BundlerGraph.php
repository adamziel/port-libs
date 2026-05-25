<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class BundlerGraph
{
    /**
     * @param array<string, BundlerModule> $modules
     * @param list<BundlerEdge> $externalEdges
     * @param list<BundlerEdge> $missingEdges
     * @param list<BundlerEdge> $unsupportedEdges
     */
    public function __construct(
        public readonly string $entry,
        public readonly array $modules,
        public readonly array $externalEdges,
        public readonly array $missingEdges,
        public readonly array $unsupportedEdges = [],
    ) {
    }
}
