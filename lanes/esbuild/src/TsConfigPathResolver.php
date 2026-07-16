<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class TsConfigPathResolver
{
    /**
     * @param list<string> $extensions
     */
    public function __construct(
        private readonly array $extensions = ['.tsx', '.ts', '.jsx', '.js', '.css', '.json'],
    ) {
    }

    /**
     * @return list<TsConfigPathResolution>
     */
    public function resolve(ModuleAnalysis $analysis, string $sourceDir): array
    {
        $resolutions = [];
        foreach ($analysis->imports as $import) {
            $resolution = $this->resolveImport($import, $sourceDir);
            if ($resolution !== null) {
                $resolutions[] = $resolution;
            }
        }

        return $resolutions;
    }

    public function resolveImport(ModuleImport $import, string $sourceDir): ?TsConfigPathResolution
    {
        if (str_starts_with($import->source, './') || str_starts_with($import->source, '../') || str_starts_with($import->source, '#') || str_starts_with($import->source, 'node:')) {
            return null;
        }

        $sourceDir = realpath($sourceDir);
        if ($sourceDir === false || !is_dir($sourceDir)) {
            return null;
        }

        $config = $this->findTsConfig($sourceDir);
        if ($config === null) {
            return null;
        }

        $baseUrl = $this->baseUrl($config['json'], $config['baseUrlDir'], $config['pathsDir']);
        $paths = $config['json']['compilerOptions']['paths'] ?? null;
        if (is_array($paths) && !array_is_list($paths)) {
            foreach ($this->sortedPathKeys($paths) as $pattern) {
                $matched = $this->matchPattern($pattern, $import->source);
                if ($matched === null) {
                    continue;
                }

                $targets = $paths[$pattern];
                if (!is_array($targets)) {
                    continue;
                }

                foreach ($targets as $target) {
                    if (!is_string($target) || $target === '' || !$this->isAllowedTarget($target)) {
                        continue;
                    }

                    $candidate = $this->targetPath($target, $matched, $baseUrl);
                    $tried = [];
                    $resolved = $this->resolvePath($candidate, $tried);
                    if ($resolved === null || !$this->isPathInsideDir($resolved, $config['dir'])) {
                        continue;
                    }

                    return new TsConfigPathResolution($import, $resolved, $config['path'], $baseUrl, $pattern, $target, $tried);
                }
            }
        }

        if (array_key_exists('baseUrl', $config['json']['compilerOptions'] ?? [])) {
            $tried = [];
            $resolved = $this->resolvePath($this->targetPath($import->source, [], $baseUrl), $tried);
            if ($resolved !== null && $this->isPathInsideDir($resolved, $config['dir'])) {
                return new TsConfigPathResolution($import, $resolved, $config['path'], $baseUrl, '<baseUrl>', $import->source, $tried);
            }
        }

        return null;
    }

    /**
     * @return array{dir:string, path:string, json:array<string, mixed>, baseUrlDir:string, pathsDir:string}|null
     */
    private function findTsConfig(string $sourceDir): ?array
    {
        for ($dir = $sourceDir; true; $dir = dirname($dir)) {
            $path = $dir . DIRECTORY_SEPARATOR . 'tsconfig.json';
            if (is_file($path)) {
                return $this->loadTsConfig($path);
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return null;
            }
        }
    }

    /**
     * @param array<string, bool> $seen
     * @return array{dir:string, path:string, json:array<string, mixed>, baseUrlDir:string, pathsDir:string}|null
     */
    private function loadTsConfig(string $path, array $seen = []): ?array
    {
        $realPath = realpath($path) ?: $path;
        if (isset($seen[$realPath])) {
            return null;
        }
        $seen[$realPath] = true;

        $dir = dirname($realPath);
        $contents = file_get_contents($realPath);
        $json = $contents === false ? null : json_decode($contents, true);
        $json = is_array($json) ? $json : [];

        $config = [
            'dir' => $dir,
            'path' => $realPath,
            'json' => [],
            'baseUrlDir' => $dir,
            'pathsDir' => $dir,
        ];

        foreach ($this->extendedConfigPaths($json['extends'] ?? null, $dir) as $extendedPath) {
            $base = $this->loadTsConfig($extendedPath, $seen);
            if ($base === null) {
                continue;
            }

            $config['json'] = $this->mergeTsConfigJson($config['json'], $base['json']);
            $config['baseUrlDir'] = $base['baseUrlDir'];
            $config['pathsDir'] = $base['pathsDir'];
        }

        $compilerOptions = $json['compilerOptions'] ?? null;
        if (is_array($compilerOptions) && !array_is_list($compilerOptions)) {
            $existing = $config['json']['compilerOptions'] ?? [];
            $config['json']['compilerOptions'] = is_array($existing) ? $this->mergeCompilerOptions($existing, $compilerOptions) : $compilerOptions;

            if (array_key_exists('baseUrl', $compilerOptions)) {
                $config['baseUrlDir'] = $dir;
            }
            if (array_key_exists('paths', $compilerOptions)) {
                $config['pathsDir'] = $dir;
            }
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    private function extendedConfigPaths(mixed $extends, string $configDir): array
    {
        $values = is_array($extends) && array_is_list($extends) ? $extends : [$extends];
        $paths = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
                continue;
            }

            $candidate = str_replace('/', DIRECTORY_SEPARATOR, $value);
            if (!$this->isAbsolutePath($candidate)) {
                if (!str_starts_with($candidate, '.') && !str_starts_with($candidate, DIRECTORY_SEPARATOR)) {
                    $packagePath = $this->resolvePackageExtendsPath($value, $configDir);
                    if ($packagePath !== null) {
                        $paths[] = $packagePath;
                    }

                    continue;
                }
                $candidate = $configDir . DIRECTORY_SEPARATOR . $candidate;
            }

            $paths[] = is_file($candidate) ? $candidate : $candidate . '.json';
        }

        return $paths;
    }

    private function resolvePackageExtendsPath(string $specifier, string $configDir): ?string
    {
        if (str_starts_with($specifier, '#')) {
            return null;
        }

        $resolution = (new PackageResolver('node', mainFields: ['tsconfig'], extensions: ['.json']))
            ->resolveImport(new ModuleImport('tsconfig-extends', $specifier, [], 0), $configDir);
        if ($resolution === null || !str_ends_with($resolution->path, '.json')) {
            return $this->resolvePackageExtendsFallbackPath($specifier, $configDir);
        }

        return $resolution->path;
    }

    private function resolvePackageExtendsFallbackPath(string $specifier, string $configDir): ?string
    {
        $packageName = $this->packageNameFromSpecifier($specifier);
        if ($packageName === null) {
            return null;
        }

        for ($dir = $configDir; true; $dir = dirname($dir)) {
            $packageDir = $dir . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageName);
            if (is_dir($packageDir)) {
                $candidate = $specifier === $packageName
                    ? $packageDir . DIRECTORY_SEPARATOR . 'tsconfig.json'
                    : $packageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, substr($specifier, strlen($packageName) + 1));

                $path = $this->jsonConfigPath($candidate);
                if ($path !== null && $this->isPathInsideDir($path, $packageDir)) {
                    return $path;
                }
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return null;
            }
        }
    }

    private function packageNameFromSpecifier(string $specifier): ?string
    {
        if (str_starts_with($specifier, '@')) {
            $parts = explode('/', $specifier);

            return count($parts) >= 2 && $parts[0] !== '@' && $parts[1] !== '' ? $parts[0] . '/' . $parts[1] : null;
        }

        $slash = strpos($specifier, '/');
        $packageName = $slash === false ? $specifier : substr($specifier, 0, $slash);

        return $packageName !== '' && $packageName !== '.' && $packageName !== '..' ? $packageName : null;
    }

    private function jsonConfigPath(string $candidate): ?string
    {
        $paths = str_ends_with($candidate, '.json') ? [$candidate] : [$candidate, $candidate . '.json'];
        foreach ($paths as $path) {
            if (is_file($path)) {
                return realpath($path) ?: $path;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function mergeTsConfigJson(array $target, array $source): array
    {
        $targetOptions = $target['compilerOptions'] ?? [];
        $sourceOptions = $source['compilerOptions'] ?? [];
        if (is_array($sourceOptions) && !array_is_list($sourceOptions)) {
            $target['compilerOptions'] = is_array($targetOptions) ? $this->mergeCompilerOptions($targetOptions, $sourceOptions) : $sourceOptions;
        }

        return $target;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function mergeCompilerOptions(array $target, array $source): array
    {
        $merged = array_replace($target, $source);
        $targetPaths = $target['paths'] ?? null;
        $sourcePaths = $source['paths'] ?? null;
        if (
            is_array($targetPaths)
            && !array_is_list($targetPaths)
            && is_array($sourcePaths)
            && !array_is_list($sourcePaths)
        ) {
            $merged['paths'] = array_replace($targetPaths, $sourcePaths);
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function baseUrl(array $json, string $baseUrlDir, string $pathsDir): string
    {
        $baseUrl = $json['compilerOptions']['baseUrl'] ?? null;
        if (is_string($baseUrl) && $baseUrl !== '' && !$this->isAbsolutePath($baseUrl)) {
            $candidate = realpath($baseUrlDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $baseUrl));

            return $candidate === false ? $baseUrlDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $baseUrl) : $candidate;
        }
        if (is_string($baseUrl) && $this->isAbsolutePath($baseUrl)) {
            return str_replace('/', DIRECTORY_SEPARATOR, $baseUrl);
        }

        return $pathsDir;
    }

    /**
     * @param array<string, mixed> $paths
     * @return list<string>
     */
    private function sortedPathKeys(array $paths): array
    {
        $keys = array_values(array_filter(array_keys($paths), static fn ($key): bool => is_string($key)));
        usort($keys, static function (string $a, string $b): int {
            $prefixA = strpos($a, '*');
            $prefixB = strpos($b, '*');
            $prefixA = $prefixA === false ? strlen($a) : $prefixA;
            $prefixB = $prefixB === false ? strlen($b) : $prefixB;
            if ($prefixA !== $prefixB) {
                return $prefixB <=> $prefixA;
            }

            return strlen($b) <=> strlen($a);
        });

        return $keys;
    }

    /**
     * @return list<string>|null
     */
    private function matchPattern(string $pattern, string $source): ?array
    {
        $starCount = substr_count($pattern, '*');
        if ($starCount === 0) {
            return $pattern === $source ? [] : null;
        }
        if ($starCount !== 1) {
            return null;
        }

        [$prefix, $suffix] = explode('*', $pattern, 2);
        if (!str_starts_with($source, $prefix) || ($suffix !== '' && !str_ends_with($source, $suffix))) {
            return null;
        }
        if (strlen($source) < strlen($prefix) + strlen($suffix)) {
            return null;
        }

        return [substr($source, strlen($prefix), strlen($source) - strlen($prefix) - strlen($suffix))];
    }

    /**
     * @param list<string> $matched
     */
    private function targetPath(string $target, array $matched, string $baseUrl): string
    {
        foreach ($matched as $match) {
            $target = preg_replace('/\*/', $match, $target, 1) ?? $target;
        }

        $target = str_replace('/', DIRECTORY_SEPARATOR, $target);
        if ($this->isAbsolutePath($target)) {
            return $target;
        }

        return rtrim($baseUrl, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $target;
    }

    private function isAllowedTarget(string $target): bool
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
            return false;
        }

        $parts = preg_split('~[/\\\\]+~', $target) ?: [];
        foreach ($parts as $part) {
            if ($part === '..' || $part === 'node_modules') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $tried
     */
    private function resolvePath(string $path, array &$tried): ?string
    {
        $tried[] = $path;
        if (is_file($path)) {
            $real = realpath($path);

            return $real === false ? $path : $real;
        }

        foreach ($this->extensions as $extension) {
            $candidate = $path . $extension;
            $tried[] = $candidate;
            if (is_file($candidate)) {
                $real = realpath($candidate);

                return $real === false ? $candidate : $real;
            }
        }

        if (is_dir($path)) {
            return $this->resolvePath($path . DIRECTORY_SEPARATOR . 'index', $tried);
        }

        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private function isPathInsideDir(string $path, string $dir): bool
    {
        $path = str_replace('\\', '/', $path);
        $dir = rtrim(str_replace('\\', '/', (string) realpath($dir)), '/') . '/';

        return str_starts_with($path, $dir);
    }
}
