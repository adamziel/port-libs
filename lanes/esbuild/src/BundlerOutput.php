<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class BundlerOutput
{
    /**
     * @return array{entry: string, output: array{path: string, bytes: int, contents: string}, inputs: array<string, array{bytes: int, outputBytes: int, importsRemoved: int, importsRewritten: int, exportsRewritten: int, importsExternal: int, rewrites: list<array{from: string, to: string, kind: string}>, externalImports: list<array{path: string, kind: string}>}}, diagnostics: array{external: list<array{path: string, kind: string}>, missing: list<array{path: string, kind: string}>, unsupported: list<array{path: string, kind: string, resolved: string}>}}
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
            [$rewrittenSource, $exportsRewritten] = $this->rewriteBundledReExports($rewrittenSource, $module, $graph);
            [$rewrittenSource, $rewrites] = $this->rewriteRetainedStaticImports($rewrittenSource, $module, $root, $outputPath);
            $externalImports = $this->externalImports($module);
            $relativePath = $this->relativePath($module->path, $root);
            $chunk = "// {$relativePath}\n" . rtrim($rewrittenSource) . "\n";
            $chunks[] = $chunk;
            $inputs[$relativePath] = [
                'bytes' => strlen($source),
                'outputBytes' => strlen($chunk),
                'importsRemoved' => $importsRemoved,
                'importsRewritten' => count($rewrites),
                'exportsRewritten' => $exportsRewritten,
                'importsExternal' => count($externalImports),
                'rewrites' => $rewrites,
                'externalImports' => $externalImports,
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

    /**
     * @return array{0:string, 1:int}
     */
    private function rewriteBundledReExports(string $source, BundlerModule $module, BundlerGraph $graph): array
    {
        $replacements = [];
        $edgeOffset = count($module->analysis->runtimeImports());

        $sourceExportIndex = 0;
        foreach ($module->analysis->exports as $export) {
            if ($export->typeOnly || $export->source === null) {
                continue;
            }

            $edge = $module->edges[$edgeOffset + $sourceExportIndex] ?? null;
            $sourceExportIndex++;
            if ($edge === null
                || $export->kind !== 're-export-named'
                || $edge->path === null
                || !isset($graph->modules[$edge->path])
                || !$this->isJavaScriptModule($edge->path)
            ) {
                continue;
            }

            $range = $this->reExportSourceRange($source, $export->offset, $export->source);
            if ($range !== null) {
                $replacements[] = $range;
            }
        }

        if ($replacements === []) {
            return [$source, 0];
        }

        usort($replacements, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
        foreach ($replacements as [$start, $end]) {
            $source = substr_replace($source, '', $start, $end - $start);
        }

        return [$source, count($replacements)];
    }

    /**
     * @return array{0:string, 1:list<array{from: string, to: string, kind: string}>}
     */
    private function rewriteRetainedStaticImports(string $source, BundlerModule $module, string $root, string $outputPath): array
    {
        $replacements = [];
        $rewrites = [];

        foreach ($module->analysis->runtimeImports() as $index => $import) {
            $edge = $module->edges[$index] ?? null;
            if ($edge === null || !$this->isStaticImportKind($import->kind)) {
                continue;
            }

            $replacement = $this->retainedImportPath($edge, $root, $outputPath);
            if ($replacement === null || $replacement === $import->source) {
                continue;
            }

            $range = $this->importSourceRange($source, $import->offset, $import->source);
            if ($range === null) {
                continue;
            }

            $replacements[] = [$range[0], $range[1], $replacement];
            $rewrites[] = [
                'from' => $import->source,
                'to' => $replacement,
                'kind' => $import->kind,
            ];
        }

        if ($replacements === []) {
            return [$source, []];
        }

        usort($replacements, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
        foreach ($replacements as [$start, $end, $replacement]) {
            $source = substr_replace($source, $replacement, $start, $end - $start);
        }

        return [$source, $rewrites];
    }

    private function retainedImportPath(BundlerEdge $edge, string $root, string $outputPath): ?string
    {
        if ($edge->external) {
            return $edge->source;
        }

        if ($edge->path === null || $edge->missing || $this->isJavaScriptModule($edge->path)) {
            return null;
        }

        $outputDir = dirname($outputPath);
        $fromDir = $outputDir === '.' ? $root : $root . '/' . trim(str_replace('\\', '/', $outputDir), '/');
        $relative = $this->relativeBetween($fromDir, $edge->path);

        return str_starts_with($relative, '.') ? $relative : './' . $relative;
    }

    /**
     * @return list<array{path: string, kind: string}>
     */
    private function externalImports(BundlerModule $module): array
    {
        $external = [];

        foreach ($module->edges as $edge) {
            if (!$edge->external) {
                continue;
            }

            $external[] = [
                'path' => $edge->source,
                'kind' => $edge->kind,
            ];
        }

        return $external;
    }

    /**
     * @return array{0:int, 1:int}|null
     */
    private function importSourceRange(string $source, int $offset, string $importSource): ?array
    {
        $statement = $this->statementRange($source, $offset);
        $quoted = preg_quote($importSource, '/');
        if ($statement !== null) {
            [$start, $end] = $statement;
            if (preg_match('/(["\'])' . $quoted . '\1/', substr($source, $start, $end - $start), $match, PREG_OFFSET_CAPTURE) === 1) {
                $matchText = $match[0][0];
                $matchStart = $start + $match[0][1] + 1;

                return [$matchStart, $matchStart + strlen($matchText) - 2];
            }
        }

        if (preg_match('/(["\'])' . $quoted . '\1/', $source, $match, PREG_OFFSET_CAPTURE, max(0, min($offset, strlen($source)))) === 1) {
            $matchText = $match[0][0];
            $matchStart = $match[0][1] + 1;

            return [$matchStart, $matchStart + strlen($matchText) - 2];
        }

        if (preg_match('/(["\'])' . $quoted . '\1/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $matchText = $match[0][0];
        $matchStart = $match[0][1] + 1;

        return [$matchStart, $matchStart + strlen($matchText) - 2];
    }

    /**
     * @return array{0:int, 1:int}|null
     */
    private function reExportSourceRange(string $source, int $offset, string $exportSource): ?array
    {
        $statement = $this->statementRange($source, $offset);
        if ($statement === null) {
            return null;
        }

        [$start, $end] = $statement;
        $fragment = substr($source, $start, $end - $start);
        $quoted = preg_quote($exportSource, '/');
        if (preg_match('/\s+from\s+(["\'])' . $quoted . '\1/', $fragment, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return [$start + $match[0][1], $start + $match[0][1] + strlen($match[0][0])];
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
        if (substr($source, $offset, 6) !== 'import' && substr($source, $offset, 6) !== 'export') {
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

    private function relativeBetween(string $fromDir, string $path): string
    {
        $fromParts = array_values(array_filter(explode('/', trim(str_replace('\\', '/', $fromDir), '/')), 'strlen'));
        $toParts = array_values(array_filter(explode('/', trim(str_replace('\\', '/', $path), '/')), 'strlen'));

        while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        return implode('/', array_merge(array_fill(0, count($fromParts), '..'), $toParts));
    }
}
