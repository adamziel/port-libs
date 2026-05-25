<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class BundlerMetafile
{
    /**
     * @return array{entry: string, inputs: array<string, array{bytes: int, imports: list<array{path: string, kind: string, external: bool, loader?: string, missing?: bool, unsupported?: bool}>}>, diagnostics: array{external: list<array{path: string, kind: string}>, missing: list<array{path: string, kind: string}>, unsupported: list<array{path: string, kind: string, resolved: string}>}}
     */
    public function summarize(BundlerGraph $graph, ?string $root = null): array
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

        return [
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
