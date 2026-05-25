<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class BundlerOutput
{
    /**
     * @return array{entry: string, output: array{path: string, bytes: int, contents: string}, inputs: array<string, array{bytes: int, outputBytes: int}}, diagnostics: array{external: list<array{path: string, kind: string}>, missing: list<array{path: string, kind: string}>, unsupported: list<array{path: string, kind: string, resolved: string}>}}
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
            $relativePath = $this->relativePath($module->path, $root);
            $chunk = "// {$relativePath}\n" . rtrim($source) . "\n";
            $chunks[] = $chunk;
            $inputs[$relativePath] = [
                'bytes' => strlen($source),
                'outputBytes' => strlen($chunk),
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
