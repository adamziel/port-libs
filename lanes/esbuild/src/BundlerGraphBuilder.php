<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class BundlerGraphBuilder
{
    /**
     * @param list<string> $extensions
     */
    public function __construct(
        private readonly PackageResolver $packageResolver = new PackageResolver('browser'),
        private readonly array $extensions = ['.tsx', '.ts', '.jsx', '.js', '.css', '.json'],
    ) {
    }

    public function build(string $entryPath): BundlerGraph
    {
        $entry = realpath($entryPath);
        if ($entry === false || !is_file($entry)) {
            throw new \InvalidArgumentException("Entry file does not exist: {$entryPath}");
        }

        $modules = [];
        $externalEdges = [];
        $missingEdges = [];
        $unsupportedEdges = [];
        $queue = [$entry];

        while ($queue !== []) {
            $path = array_shift($queue);
            if (isset($modules[$path])) {
                continue;
            }

            $source = (string) file_get_contents($path);
            $analysis = (new JsModuleAnalyzer())->analyze($source);
            $edges = [];

            foreach ($analysis->imports as $import) {
                if ($import->typeOnly) {
                    continue;
                }

                $edge = $this->edgeForSource($import->kind, $import->source, dirname($path));
                $edges[] = $edge;
                if ($edge->external) {
                    $externalEdges[] = $edge;
                    continue;
                }
                if ($edge->missing || $edge->path === null) {
                    $missingEdges[] = $edge;
                    continue;
                }
                if ($edge->loader === null) {
                    $unsupportedEdges[] = $edge;
                    continue;
                }
                if (!isset($modules[$edge->path]) && $this->canAnalyze($edge->path)) {
                    $queue[] = $edge->path;
                }
            }

            foreach ($analysis->exports as $export) {
                if ($export->typeOnly || $export->source === null) {
                    continue;
                }

                $edge = $this->edgeForSource($export->kind, $export->source, dirname($path));
                $edges[] = $edge;
                if ($edge->external) {
                    $externalEdges[] = $edge;
                    continue;
                }
                if ($edge->missing || $edge->path === null) {
                    $missingEdges[] = $edge;
                    continue;
                }
                if ($edge->loader === null) {
                    $unsupportedEdges[] = $edge;
                    continue;
                }
                if (!isset($modules[$edge->path]) && $this->canAnalyze($edge->path)) {
                    $queue[] = $edge->path;
                }
            }

            $modules[$path] = new BundlerModule($path, $analysis, $edges);
        }

        return new BundlerGraph($entry, $modules, $externalEdges, $missingEdges, $unsupportedEdges);
    }

    private function edgeForSource(string $kind, string $source, string $sourceDir): BundlerEdge
    {
        if ($this->isRelativeSource($source)) {
            $resolved = $this->resolveRelative($source, $sourceDir);

            return new BundlerEdge($kind, $source, $resolved, false, $resolved === null, null, $this->loaderForPath($resolved));
        }

        $resolution = $this->packageResolver->resolveImport(new ModuleImport($kind, $source, [], 0), $sourceDir);
        if ($resolution === null) {
            return new BundlerEdge($kind, $source, null, false, true);
        }

        return new BundlerEdge(
            $kind,
            $source,
            $resolution->path === '' ? null : $resolution->path,
            $resolution->external,
            false,
            $resolution->mainField,
            $this->loaderForPath($resolution->path),
        );
    }

    private function isRelativeSource(string $source): bool
    {
        return str_starts_with($source, './')
            || str_starts_with($source, '../')
            || str_starts_with($source, '/');
    }

    private function resolveRelative(string $source, string $sourceDir): ?string
    {
        if (str_starts_with($source, '/')) {
            return null;
        }

        $base = $sourceDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
        $tried = [];

        return $this->resolveFileOrDirectory($base, $tried);
    }

    /**
     * @param list<string> $tried
     */
    private function resolveFileOrDirectory(string $path, array &$tried): ?string
    {
        $file = $this->resolveFile($path, $tried);
        if ($file !== null) {
            return $file;
        }

        if (is_dir($path)) {
            foreach ($this->extensions as $extension) {
                $candidate = $path . DIRECTORY_SEPARATOR . 'index' . $extension;
                $tried[] = $candidate;
                if (is_file($candidate)) {
                    $real = realpath($candidate);

                    return $real === false ? $candidate : $real;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $tried
     */
    private function resolveFile(string $path, array &$tried): ?string
    {
        $tried[] = $path;
        if (is_file($path)) {
            $real = realpath($path);

            return $real === false ? $path : $real;
        }

        if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return null;
        }

        foreach ($this->extensions as $extension) {
            $candidate = $path . $extension;
            $tried[] = $candidate;
            if (is_file($candidate)) {
                $real = realpath($candidate);

                return $real === false ? $candidate : $real;
            }
        }

        return null;
    }

    private function canAnalyze(string $path): bool
    {
        return preg_match('/\.(?:mjs|cjs|js|jsx|ts|tsx)$/', $path) === 1;
    }

    private function loaderForPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'css',
            'json' => 'json',
            'mjs', 'cjs', 'js' => 'js',
            'jsx' => 'jsx',
            'ts' => 'ts',
            'tsx' => 'tsx',
            default => null,
        };
    }
}
