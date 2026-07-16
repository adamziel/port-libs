<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class PackageResolver
{
    /**
     * @param list<string>|null $mainFields
     * @param list<string> $extensions
     */
    public function __construct(
        private readonly string $platform = 'browser',
        private readonly ?array $mainFields = null,
        private readonly array $extensions = ['.tsx', '.ts', '.jsx', '.js', '.css', '.json'],
        private readonly array $conditions = [],
    ) {
    }

    /**
     * @return list<PackageResolution>
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

    public function resolveImport(ModuleImport $import, string $sourceDir): ?PackageResolution
    {
        if (str_starts_with($import->source, '#')) {
            return $this->resolvePackageImport($import, $sourceDir);
        }

        if (!$import->isPackage() && !str_starts_with($import->source, 'node:')) {
            return null;
        }

        $parsed = $this->parsePackageSource($import->source);
        if ($parsed === null) {
            return null;
        }

        [$packageName, $subpath] = $parsed;
        $sourceDir = realpath($sourceDir);
        if ($sourceDir === false || !is_dir($sourceDir)) {
            return null;
        }

        $browserMapped = $this->resolveContainingPackageBrowserMap($import, $sourceDir);
        if (($browserMapped['disabled'] ?? false) === true) {
            return null;
        }
        if ($browserMapped !== null) {
            if (($browserMapped['package'] ?? false) === true) {
                return $this->resolvePackageSpecifierFromDir($browserMapped['path'], $browserMapped['scopeDir'], $import);
            }

            $tried = [];
            $resolved = $this->resolvePath($browserMapped['path'], $tried);
            if ($resolved === null || !$this->isPathInsideDir($resolved, $browserMapped['scopeDir'])) {
                return null;
            }

            return new PackageResolution($import, $packageName, $subpath, $resolved, $browserMapped['scopeDir'], $browserMapped['packageJsonPath'], 'browser', $tried);
        }

        if (str_starts_with($import->source, 'node:') && $this->platform !== 'node') {
            return null;
        }

        if ($this->platform === 'node' && $this->isNodeBuiltin($import->source)) {
            return new PackageResolution($import, $packageName, $subpath, '', '', null, 'node-builtin', [], true);
        }

        $packageDir = $this->findPackageDir($sourceDir, $packageName);
        if ($packageDir === null) {
            $packageDir = $this->findSelfPackageDir($sourceDir, $packageName);
        }
        if ($packageDir === null) {
            return null;
        }

        return $this->resolvePackageDir($import, $packageName, $subpath, $packageDir);
    }

    /**
     * @return list<ImportRecord>
     */
    public function importRecords(ModuleAnalysis $analysis, string $sourceDir): array
    {
        return array_map(
            static fn (PackageResolution $resolution): ImportRecord => new ImportRecord(
                $resolution->import->kind,
                $resolution->import->source,
                $resolution->path,
                $resolution->external,
                $resolution->mainField,
                $resolution->packageName,
                $resolution->subpath,
            ),
            $this->resolve($analysis, $sourceDir),
        );
    }

    /**
     * @return array{path:string, scopeDir:string, packageJsonPath:string, disabled?:bool, package?:bool}|null
     */
    private function resolveContainingPackageBrowserMap(ModuleImport $import, string $sourceDir): ?array
    {
        if ($this->platform !== 'browser') {
            return null;
        }

        $scope = $this->findPackageJsonScope($sourceDir);
        if ($scope === null) {
            return null;
        }

        $target = $this->resolveBrowserPackageMapTarget($scope['json'], $scope['dir'], $import->source);
        if ($target === null) {
            return null;
        }
        if (($target['disabled'] ?? false) === true) {
            return [
                'path' => '',
                'scopeDir' => $scope['dir'],
                'packageJsonPath' => $scope['packageJsonPath'],
                'disabled' => true,
            ];
        }

        return [
            'path' => $target['path'],
            'scopeDir' => $scope['dir'],
            'packageJsonPath' => $scope['packageJsonPath'],
            'package' => $target['package'] ?? false,
        ];
    }

    private function resolvePackageImport(ModuleImport $import, string $sourceDir): ?PackageResolution
    {
        if ($import->source === '#') {
            return null;
        }

        $sourceDir = realpath($sourceDir);
        if ($sourceDir === false || !is_dir($sourceDir)) {
            return null;
        }

        $scope = $this->findPackageJsonScope($sourceDir);
        if ($scope === null || !array_key_exists('imports', $scope['json'])) {
            return null;
        }

        $imports = $scope['json']['imports'];
        if (!is_array($imports) || $imports !== [] && array_is_list($imports)) {
            return null;
        }

        $target = $this->resolvePackageImportsExportsMap(
            $imports,
            $scope['dir'],
            $import->source,
            $this->activeConditions($import),
            true,
        );
        if ($target === null) {
            return null;
        }

        $scopeName = is_string($scope['json']['name'] ?? null) ? $scope['json']['name'] : '';
        if (($target['package'] ?? false) === true) {
            $resolved = $this->resolvePackageSpecifierFromDir($target['path'], $scope['dir'], $import);
            if ($resolved === null) {
                return null;
            }

            return new PackageResolution($import, $scopeName, $import->source, $resolved->path, $scope['dir'], $scope['packageJsonPath'], 'imports', $resolved->tried);
        }

        $tried = [];
        $resolved = $target['exact']
            ? $this->resolveFileExactly($target['path'], $tried)
            : $this->resolvePath($target['path'], $tried);
        if ($resolved === null || !$this->isPathInsideDir($resolved, $scope['dir'])) {
            return null;
        }

        return new PackageResolution($import, $scopeName, $import->source, $resolved, $scope['dir'], $scope['packageJsonPath'], 'imports', $tried);
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function parsePackageSource(string $source): ?array
    {
        if ($source === '' || str_starts_with($source, '#')) {
            return null;
        }
        if (str_starts_with($source, 'node:')) {
            $source = substr($source, 5);
        }

        $parts = explode('/', $source);
        if (str_starts_with($source, '@')) {
            if (count($parts) < 2 || $parts[0] === '@' || $parts[1] === '') {
                return null;
            }

            $packageName = $parts[0] . '/' . $parts[1];
            $rest = array_slice($parts, 2);

            return [$packageName, $rest === [] ? '.' : './' . implode('/', $rest)];
        }

        $packageName = $parts[0];
        if ($packageName === '' || $packageName === '.' || $packageName === '..') {
            return null;
        }

        $rest = array_slice($parts, 1);

        return [$packageName, $rest === [] ? '.' : './' . implode('/', $rest)];
    }

    private function findPackageDir(string $sourceDir, string $packageName): ?string
    {
        for ($dir = $sourceDir; true; $dir = dirname($dir)) {
            $candidate = $dir . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageName);
            if (is_dir($candidate)) {
                $real = realpath($candidate);

                return $real === false ? $candidate : $real;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return null;
            }
        }
    }

    private function findSelfPackageDir(string $sourceDir, string $packageName): ?string
    {
        for ($dir = $sourceDir; true; $dir = dirname($dir)) {
            $packageJsonPath = $dir . DIRECTORY_SEPARATOR . 'package.json';
            if (is_file($packageJsonPath)) {
                $json = $this->readPackageJson($packageJsonPath);
                if (($json['name'] ?? null) === $packageName) {
                    return $dir;
                }
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return null;
            }
        }
    }

    private function resolvePackageSpecifierFromDir(string $source, string $sourceDir, ModuleImport $originalImport): ?PackageResolution
    {
        $parsed = $this->parsePackageSource($source);
        if ($parsed === null) {
            return null;
        }

        [$packageName, $subpath] = $parsed;
        $packageDir = $this->findPackageDir($sourceDir, $packageName);
        if ($packageDir === null) {
            $packageDir = $this->findSelfPackageDir($sourceDir, $packageName);
        }
        if ($packageDir === null) {
            return null;
        }

        $remappedImport = new ModuleImport(
            $originalImport->kind,
            $source,
            $originalImport->specifiers,
            $originalImport->offset,
            $originalImport->attributesKeyword,
            $originalImport->attributes,
            $originalImport->typeOnly,
            $originalImport->typeSpecifiers,
        );

        return $this->resolvePackageDir($remappedImport, $packageName, $subpath, $packageDir);
    }

    /**
     * @return array{dir:string, packageJsonPath:string, json:array<string, mixed>}|null
     */
    private function findPackageJsonScope(string $sourceDir): ?array
    {
        for ($dir = $sourceDir; true; $dir = dirname($dir)) {
            $packageJsonPath = $dir . DIRECTORY_SEPARATOR . 'package.json';
            if (is_file($packageJsonPath)) {
                return [
                    'dir' => $dir,
                    'packageJsonPath' => $packageJsonPath,
                    'json' => $this->readPackageJson($packageJsonPath),
                ];
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return null;
            }
        }
    }

    private function resolvePackageDir(ModuleImport $import, string $packageName, string $subpath, string $packageDir): ?PackageResolution
    {
        $tried = [];
        $packageJsonPath = $this->packageJsonPath($packageDir);
        $json = $packageJsonPath === null ? [] : $this->readPackageJson($packageJsonPath);
        if (array_key_exists('exports', $json)) {
            $target = $this->resolvePackageExportsTarget($json['exports'], $packageDir, $subpath, $import);
            if ($target === null) {
                return null;
            }

            $resolved = $target['exact']
                ? $this->resolveFileExactly($target['path'], $tried)
                : $this->resolvePath($target['path'], $tried);
            if ($resolved === null || !$this->isPathInsideDir($resolved, $packageDir)) {
                return null;
            }

            return new PackageResolution($import, $packageName, $subpath, $resolved, $packageDir, $packageJsonPath, 'exports', $tried);
        }

        if ($subpath !== '.') {
            $browserMapTarget = $this->resolveBrowserMapTarget($json, $packageDir, $subpath);
            if (($browserMapTarget['disabled'] ?? false) === true) {
                return null;
            }
            if (($browserMapTarget['package'] ?? false) === true) {
                return $this->resolvePackageSpecifierFromDir($browserMapTarget['path'], $packageDir, $import);
            }

            $resolved = $browserMapTarget !== null
                ? $this->resolvePath($browserMapTarget['path'], $tried)
                : $this->resolvePath($packageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, substr($subpath, 2)), $tried);
            if ($resolved === null) {
                return null;
            }

            return new PackageResolution($import, $packageName, $subpath, $resolved, $packageDir, $this->packageJsonPath($packageDir), $browserMapTarget !== null ? 'browser' : null, $tried);
        }

        if ($packageJsonPath !== null) {
            foreach ($this->effectiveMainFields() as $field) {
                $main = $json[$field] ?? null;
                if (!is_string($main) || $main === '') {
                    continue;
                }

                $browserMapTarget = $this->resolveBrowserMapTarget($json, $packageDir, $main);
                if (($browserMapTarget['disabled'] ?? false) === true) {
                    return null;
                }
                if (($browserMapTarget['package'] ?? false) === true) {
                    return $this->resolvePackageSpecifierFromDir($browserMapTarget['path'], $packageDir, $import);
                }

                $resolved = $browserMapTarget !== null
                    ? $this->resolvePath($browserMapTarget['path'], $tried)
                    : $this->resolvePath($this->joinPackagePath($packageDir, $main), $tried);
                if ($resolved !== null) {
                    return new PackageResolution($import, $packageName, $subpath, $resolved, $packageDir, $packageJsonPath, $browserMapTarget !== null ? 'browser' : $field, $tried);
                }
            }
        }

        $resolved = $this->resolvePath($packageDir . DIRECTORY_SEPARATOR . 'index', $tried);
        if ($resolved === null) {
            return null;
        }

        return new PackageResolution($import, $packageName, $subpath, $resolved, $packageDir, $packageJsonPath, null, $tried);
    }

    /**
     * @return array{path:string, exact:bool, package?:bool}|null
     */
    private function resolvePackageExportsTarget(mixed $exports, string $packageDir, string $subpath, ModuleImport $import): ?array
    {
        if (is_string($exports) || $this->isConditionalExportsObject($exports) || is_array($exports) && array_is_list($exports)) {
            if ($subpath !== '.') {
                return null;
            }

            return $this->resolvePackageTarget($exports, $packageDir, '', false, true, $this->activeConditions($import), false);
        }

        if (!is_array($exports)) {
            return null;
        }

        return $this->resolvePackageImportsExportsMap($exports, $packageDir, $subpath, $this->activeConditions($import), false);
    }

    /**
     * @param array<string, mixed> $map
     * @param array<string, true> $conditions
     * @return array{path:string, exact:bool, package?:bool}|null
     */
    private function resolvePackageImportsExportsMap(array $map, string $packageDir, string $subpath, array $conditions, bool $internal): ?array
    {
        if (!str_ends_with($subpath, '/') && !str_contains($subpath, '*') && array_key_exists($subpath, $map)) {
            return $this->resolvePackageTarget($map[$subpath], $packageDir, '', false, true, $conditions, $internal);
        }

        foreach ($this->sortedExpansionKeys($map) as $key) {
            if (str_contains($key, '*')) {
                $star = strpos($key, '*');
                if ($star === false) {
                    continue;
                }

                $prefix = substr($key, 0, $star);
                $suffix = substr($key, $star + 1);
                if (!str_starts_with($subpath, $prefix)) {
                    continue;
                }
                if ($suffix !== '' && !str_ends_with($subpath, $suffix)) {
                    continue;
                }
                if (strlen($subpath) < strlen($key)) {
                    continue;
                }

                $matched = substr($subpath, strlen($prefix), strlen($subpath) - strlen($prefix) - strlen($suffix));

                return $this->resolvePackageTarget($map[$key], $packageDir, $matched, true, true, $conditions, $internal);
            }

            if (str_starts_with($subpath, $key)) {
                $matched = substr($subpath, strlen($key));

                return $this->resolvePackageTarget($map[$key], $packageDir, $matched, false, false, $conditions, $internal);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $map
     * @return list<string>
     */
    private function sortedExpansionKeys(array $map): array
    {
        $keys = [];
        foreach (array_keys($map) as $key) {
            if (!is_string($key)) {
                continue;
            }
            if (str_ends_with($key, '/') || substr_count($key, '*') === 1) {
                $keys[] = $key;
            }
        }

        usort($keys, static function (string $a, string $b): int {
            $starA = strpos($a, '*');
            $starB = strpos($b, '*');
            $baseA = $starA === false ? strlen($a) : $starA;
            $baseB = $starB === false ? strlen($b) : $starB;
            if ($baseA !== $baseB) {
                return $baseB <=> $baseA;
            }
            if ($starA === false && $starB !== false) {
                return 1;
            }
            if ($starA !== false && $starB === false) {
                return -1;
            }

            return strlen($b) <=> strlen($a);
        });

        return $keys;
    }

    /**
     * @param array<string, true> $conditions
     * @return array{path:string, exact:bool, package?:bool}|null
     */
    private function resolvePackageTarget(mixed $target, string $packageDir, string $subpath, bool $pattern, bool $exact, array $conditions, bool $internal): ?array
    {
        if (is_string($target)) {
            if ($target === '') {
                return null;
            }

            if (!str_starts_with($target, './')) {
                if ($internal && !str_starts_with($target, '../') && !str_starts_with($target, '/')) {
                    return [
                        'path' => $pattern ? str_replace('*', $subpath, $target) : $target . $subpath,
                        'exact' => false,
                        'package' => true,
                    ];
                }

                return null;
            }

            if (!$pattern && $subpath !== '' && !str_ends_with($target, '/')) {
                return null;
            }
            if ($this->hasInvalidPackagePathSegment($target) || $this->hasInvalidPackagePathSegment('./' . $subpath)) {
                return null;
            }

            $relative = $pattern ? str_replace('*', $subpath, $target) : $target . $subpath;

            return [
                'path' => $this->joinPackagePath($packageDir, $relative),
                'exact' => $exact,
            ];
        }

        if (is_array($target) && array_is_list($target)) {
            foreach ($target as $item) {
                $resolved = $this->resolvePackageTarget($item, $packageDir, $subpath, $pattern, $exact, $conditions, $internal);
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            return null;
        }

        if (is_array($target) && $this->isConditionalExportsObject($target)) {
            foreach ($target as $condition => $value) {
                if ($condition === 'default' || isset($conditions[$condition])) {
                    $resolved = $this->resolvePackageTarget($value, $packageDir, $subpath, $pattern, $exact, $conditions, $internal);
                    if ($resolved !== null) {
                        return $resolved;
                    }
                }
            }
        }

        return null;
    }

    private function isConditionalExportsObject(mixed $value): bool
    {
        if (!is_array($value) || array_is_list($value) || $value === []) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key) || str_starts_with($key, '.')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, true>
     */
    private function activeConditions(ModuleImport $import): array
    {
        $conditions = ['default' => true];
        if ($this->platform === 'browser') {
            $conditions['browser'] = true;
        } elseif ($this->platform === 'node') {
            $conditions['node'] = true;
        }

        $conditions[$this->isRequireLikeImport($import) ? 'require' : 'import'] = true;
        foreach ($this->conditions as $condition) {
            if (is_string($condition) && $condition !== '') {
                $conditions[$condition] = true;
            }
        }

        return $conditions;
    }

    private function isRequireLikeImport(ModuleImport $import): bool
    {
        return str_starts_with($import->kind, 'commonjs-')
            || $import->kind === 'ts-import-equals-require';
    }

    private function isNodeBuiltin(string $source): bool
    {
        if (str_starts_with($source, 'node:')) {
            return true;
        }

        return in_array($source, [
            '_http_agent',
            '_http_client',
            '_http_common',
            '_http_incoming',
            '_http_outgoing',
            '_http_server',
            '_stream_duplex',
            '_stream_passthrough',
            '_stream_readable',
            '_stream_transform',
            '_stream_wrap',
            '_stream_writable',
            '_tls_common',
            '_tls_wrap',
            'assert',
            'assert/strict',
            'async_hooks',
            'buffer',
            'child_process',
            'cluster',
            'console',
            'constants',
            'crypto',
            'dgram',
            'diagnostics_channel',
            'dns',
            'dns/promises',
            'domain',
            'events',
            'fs',
            'fs/promises',
            'http',
            'http2',
            'https',
            'inspector',
            'inspector/promises',
            'module',
            'net',
            'os',
            'path',
            'path/posix',
            'path/win32',
            'perf_hooks',
            'process',
            'punycode',
            'querystring',
            'readline',
            'readline/promises',
            'repl',
            'stream',
            'stream/consumers',
            'stream/promises',
            'stream/web',
            'string_decoder',
            'sys',
            'timers',
            'timers/promises',
            'tls',
            'trace_events',
            'tty',
            'url',
            'util',
            'util/types',
            'v8',
            'vm',
            'wasi',
            'worker_threads',
            'zlib',
        ], true);
    }

    /**
     * @param array<string, mixed> $json
     * @return array{path:string, disabled?:bool, package?:bool}|null
     */
    private function resolveBrowserPackageMapTarget(array $json, string $packageDir, string $source): ?array
    {
        if ($this->platform !== 'browser') {
            return null;
        }

        $browser = $json['browser'] ?? null;
        if (!is_array($browser) || array_is_list($browser) || !array_key_exists($source, $browser)) {
            return null;
        }

        $target = $browser[$source];
        if ($target === false) {
            return ['path' => '', 'disabled' => true];
        }
        if (!is_string($target) || $target === '') {
            return null;
        }
        if (!str_starts_with($target, './') && !str_starts_with($target, '../') && !str_starts_with($target, '/')) {
            return ['path' => $target, 'package' => true];
        }
        if ($this->hasInvalidPackagePathSegment($target)) {
            return null;
        }

        return ['path' => $this->joinPackagePath($packageDir, $target)];
    }

    /**
     * @return array{path:string, disabled?:bool, package?:bool}|null
     */
    private function resolveBrowserMapTarget(array $json, string $packageDir, string $source): ?array
    {
        if ($this->platform !== 'browser') {
            return null;
        }

        $browser = $json['browser'] ?? null;
        if (!is_array($browser) || array_is_list($browser)) {
            return null;
        }

        foreach ($this->browserMapKeys($source) as $key) {
            if (!array_key_exists($key, $browser)) {
                continue;
            }

            $target = $browser[$key];
            if ($target === false) {
                return ['path' => '', 'disabled' => true];
            }
            if (!is_string($target) || $target === '') {
                return null;
            }
            if (!str_starts_with($target, './') && !str_starts_with($target, '../') && !str_starts_with($target, '/')) {
                return ['path' => $target, 'package' => true];
            }
            if ($this->hasInvalidPackagePathSegment($target)) {
                return null;
            }

            return ['path' => $this->joinPackagePath($packageDir, $target)];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function browserMapKeys(string $source): array
    {
        $source = str_replace('\\', '/', $source);
        if ($source === '.') {
            return ['./index.js', './index'];
        }
        if (!str_starts_with($source, './')) {
            $source = './' . ltrim($source, '/');
        }

        $keys = [$source];
        if (!str_contains(basename($source), '.')) {
            $keys[] = $source . '.js';
        }

        return array_values(array_unique($keys));
    }

    private function hasInvalidPackagePathSegment(string $path): bool
    {
        $parts = preg_split('~[/\\\\]+~', $path) ?: [];
        foreach (array_slice($parts, 1) as $part) {
            if ($part === '.' || $part === '..' || $part === 'node_modules') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function effectiveMainFields(): array
    {
        if ($this->mainFields !== null) {
            return $this->mainFields;
        }

        return match ($this->platform) {
            'browser' => ['browser', 'module', 'main'],
            'node' => ['main', 'module'],
            default => [],
        };
    }

    private function packageJsonPath(string $dir): ?string
    {
        $path = $dir . DIRECTORY_SEPARATOR . 'package.json';

        return is_file($path) ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function readPackageJson(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $json = json_decode($contents, true);

        return is_array($json) ? $json : [];
    }

    private function joinPackagePath(string $packageDir, string $path): string
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (str_starts_with($path, '.' . DIRECTORY_SEPARATOR)) {
            $path = substr($path, 2);
        }

        return $packageDir . DIRECTORY_SEPARATOR . $path;
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
            $packageJsonPath = $this->packageJsonPath($path);
            if ($packageJsonPath !== null) {
                $json = $this->readPackageJson($packageJsonPath);
                foreach ($this->effectiveMainFields() as $field) {
                    $main = $json[$field] ?? null;
                    if (!is_string($main) || $main === '') {
                        continue;
                    }

                    $resolved = $this->resolvePath($this->joinPackagePath($path, $main), $tried);
                    if ($resolved !== null) {
                        return $resolved;
                    }
                }
            }

            return $this->resolvePath($path . DIRECTORY_SEPARATOR . 'index', $tried);
        }

        return null;
    }

    /**
     * @param list<string> $tried
     */
    private function resolveFileExactly(string $path, array &$tried): ?string
    {
        $tried[] = $path;
        if (!is_file($path)) {
            return null;
        }

        $real = realpath($path);

        return $real === false ? $path : $real;
    }

    private function isPathInsideDir(string $path, string $dir): bool
    {
        $path = str_replace('\\', '/', $path);
        $dir = rtrim(str_replace('\\', '/', (string) realpath($dir)), '/') . '/';

        return str_starts_with($path, $dir);
    }
}
