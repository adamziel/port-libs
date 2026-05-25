<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class BundlerOutput
{
    /**
     * @return array{entry: string, output: array{path: string, bytes: int, contents: string}, inputs: array<string, array{bytes: int, outputBytes: int, importsRemoved: int}}, diagnostics: array{external: list<array{path: string, kind: string}>, missing: list<array{path: string, kind: string}>, unsupported: list<array{path: string, kind: string, resolved: string}>}}
     */
    public function build(BundlerGraph $graph, ?string $root = null, string $outputPath = 'out.js'): array
    {
        $root = $root === null ? dirname($graph->entry) : ((string) realpath($root) ?: $root);
        $chunks = [];
        $inputs = [];

        foreach ($this->orderedModules($graph) as $module) {
            if (!$this->isJavaScriptModule($module->path)) {
                continue;
            }

            $source = (string) file_get_contents($module->path);
            [$rewrittenSource, $importsRemoved] = $this->removeBundledStaticImports($source, $module, $graph);
            $relativePath = $this->relativePath($module->path, $root);
            $chunk = "// {$relativePath}\n" . rtrim($rewrittenSource) . "\n";
            $chunks[] = $chunk;
            $inputs[$relativePath] = [
                'bytes' => strlen($source),
                'outputBytes' => strlen($chunk),
                'importsRemoved' => $importsRemoved,
            ];
        }

        $contents = implode("\n", $chunks);

        return [
            'entry' => $this->relativePath($graph->entry, $root),
            'output' => [
                'path' => $outputPath,
                'bytes' => strlen($contents),
                'contents' => $contents,
            ],
            'inputs' => $inputs,
            'diagnostics' => (new BundlerMetafile())->summarize($graph, $root)['diagnostics'],
        ];
    }

    /**
     * @return list<BundlerModule>
     */
    private function orderedModules(BundlerGraph $graph): array
    {
        $modules = [];
        if (isset($graph->modules[$graph->entry])) {
            $modules[] = $graph->modules[$graph->entry];
        }

        foreach ($graph->modules as $path => $module) {
            if ($path !== $graph->entry) {
                $modules[] = $module;
            }
        }

        return $modules;
    }

    private function isJavaScriptModule(string $path): bool
    {
        return preg_match('/\.(?:mjs|cjs|js|jsx|ts|tsx)$/', $path) === 1;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private function removeBundledStaticImports(string $source, BundlerModule $module, BundlerGraph $graph): array
    {
        $ranges = [];

        foreach ($module->analysis->runtimeImports() as $index => $import) {
            $edge = $module->edges[$index] ?? null;
            if ($edge === null
                || !$this->isStaticImportKind($import->kind)
                || $edge->path === null
                || !isset($graph->modules[$edge->path])
                || !$this->isJavaScriptModule($edge->path)
            ) {
                continue;
            }

            $range = $this->statementRange($source, $import->offset);
            if ($range !== null) {
                $ranges[] = $range;
            }
        }

        if ($ranges === []) {
            return [$source, 0];
        }

        usort($ranges, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
        foreach ($ranges as [$start, $end]) {
            $source = substr_replace($source, '', $start, $end - $start);
        }

        return [$source, count($ranges)];
    }

    private function isStaticImportKind(string $kind): bool
    {
        return in_array($kind, [
            'side-effect',
            'named',
            'default',
            'namespace',
            'default-named',
            'default-namespace',
        ], true);
    }

    /**
     * @return array{0:int, 1:int}|null
     */
    private function statementRange(string $source, int $offset): ?array
    {
        if (substr($source, $offset, 6) !== 'import') {
            return null;
        }

        $length = strlen($source);
        $end = strpos($source, ';', $offset);
        if ($end === false) {
            $end = strcspn($source, "\r\n", $offset) + $offset;
        } else {
            $end++;
        }

        while ($end < $length && ($source[$end] === "\r" || $source[$end] === "\n")) {
            $end++;
        }

        $start = $offset;
        while ($start > 0 && ($source[$start - 1] === ' ' || $source[$start - 1] === "\t")) {
            $start--;
        }

        return [$start, $end];
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
