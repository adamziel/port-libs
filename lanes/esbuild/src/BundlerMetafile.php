<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class BundlerMetafile
{
    /**
     * @param array{output?: array{path: string, bytes: int}, inputs?: array<string, array{bytes: int, outputBytes: int, importsRemoved: int, importsRewritten?: int, importsExternal?: int, rewrites?: list<array{from: string, to: string, kind: string}>, externalImports?: list<array{path: string, kind: string}>}>}|null $output
     * @return array{entry: string, inputs: array<string, array{bytes: int, imports: list<array{path: string, kind: string, external: bool, loader?: string, missing?: bool, unsupported?: bool}>}>, outputs?: array<string, array{bytes: int, inputs: array<string, array{bytesInOutput: int, importsRemoved: int, importsExternal: int}>, importsRemoved: int, importsExternal: int, externalImports: list<array{path: string, kind: string, input: string}>}>, diagnostics: array{external: list<array{path: string, kind: string}>, missing: list<array{path: string, kind: string}>, unsupported: list<array{path: string, kind: string, resolved: string}>}}
     */
    public function summarize(BundlerGraph $graph, ?string $root = null, ?array $output = null): array
    {
        $root = $root === null ? dirname($graph->entry) : ((string) realpath($root) ?: $root);
        $inputs = [];

        foreach ($graph->modules as $module) {
            $imports = [];
            foreach ($module->edges as $edge) {
                $record = [
                    'path' => $this->edgePath($edge, $root),
                    'kind' => $edge->kind,
                    'external' => $edge->external,
                ];
                if ($edge->loader !== null) {
                    $record['loader'] = $edge->loader;
                }
                if ($edge->missing) {
                    $record['missing'] = true;
                }
                if (!$edge->missing && !$edge->external && $edge->path !== null && $edge->loader === null) {
                    $record['unsupported'] = true;
                }

                $imports[] = $record;
            }

            $inputs[$this->relativePath($module->path, $root)] = [
                'bytes' => filesize($module->path) ?: 0,
                'imports' => $imports,
            ];
        }
        ksort($inputs);

        $summary = [
            'entry' => $this->relativePath($graph->entry, $root),
            'inputs' => $inputs,
            'diagnostics' => [
                'external' => array_map(fn (BundlerEdge $edge): array => [
                    'path' => $edge->source,
                    'kind' => $edge->kind,
                ], $graph->externalEdges),
                'missing' => array_map(fn (BundlerEdge $edge): array => [
                    'path' => $edge->source,
                    'kind' => $edge->kind,
                ], $graph->missingEdges),
                'unsupported' => array_map(fn (BundlerEdge $edge): array => [
                    'path' => $edge->source,
                    'kind' => $edge->kind,
                    'resolved' => $this->edgePath($edge, $root),
                ], $graph->unsupportedEdges),
            ],
        ];

        if ($output !== null && isset($output['output']['path'], $output['output']['bytes'], $output['inputs'])) {
            $summary['outputs'] = $this->outputSummary($output);
        }

        return $summary;
    }

    /**
     * @param array{output: array{path: string, bytes: int}, inputs: array<string, array{bytes: int, outputBytes: int, importsRemoved: int, importsRewritten?: int, importsExternal?: int, rewrites?: list<array{from: string, to: string, kind: string}>, externalImports?: list<array{path: string, kind: string}>}>} $output
     * @return array<string, array{bytes: int, inputs: array<string, array{bytesInOutput: int, importsRemoved: int, importsExternal: int}>, importsRemoved: int, importsExternal: int, externalImports: list<array{path: string, kind: string, input: string}>}>
     */
    private function outputSummary(array $output): array
    {
        $inputs = [];
        $importsRemoved = 0;
        $importsExternal = 0;
        $externalImports = [];

        foreach ($output['inputs'] as $path => $input) {
            $inputs[$path] = [
                'bytesInOutput' => $input['outputBytes'],
                'importsRemoved' => $input['importsRemoved'],
                'importsExternal' => $input['importsExternal'] ?? 0,
            ];
            $importsRemoved += $input['importsRemoved'];
            $importsExternal += $input['importsExternal'] ?? 0;
            foreach ($input['externalImports'] ?? [] as $externalImport) {
                $externalImports[] = [
                    'path' => $externalImport['path'],
                    'kind' => $externalImport['kind'],
                    'input' => $path,
                ];
            }
        }

        ksort($inputs);

        return [
            $output['output']['path'] => [
                'bytes' => $output['output']['bytes'],
                'inputs' => $inputs,
                'importsRemoved' => $importsRemoved,
                'importsExternal' => $importsExternal,
                'externalImports' => $externalImports,
            ],
        ];
    }

    private function edgePath(BundlerEdge $edge, string $root): string
    {
        if ($edge->path === null || $edge->external || $edge->missing) {
            return $edge->source;
        }

        return $this->relativePath($edge->path, $root);
    }

    private function relativePath(string $path, string $root): string
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/');

        if ($root !== '' && str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }
}
