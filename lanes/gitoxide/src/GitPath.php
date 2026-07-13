<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitPath
{
    public const RELATIVE_PATH_IS_ABSOLUTE = 'is_absolute';
    public const RELATIVE_PATH_CONTAINS_INVALID_COMPONENT = 'contains_invalid_component';
    public const REALPATH_EMPTY_PATH = 'empty_path';
    public const REALPATH_MAX_SYMLINKS_EXCEEDED = 'max_symlinks_exceeded';
    public const REALPATH_EXCESSIVE_COMPONENT_COUNT = 'excessive_component_count';
    public const REALPATH_READ_LINK = 'read_link';
    public const REALPATH_MISSING_PARENT = 'missing_parent';
    public const REALPATH_MAX_SYMLINK_CHECKS = 2048;
    public const REALPATH_DEFAULT_MAX_SYMLINKS = 32;

    private function __construct()
    {
    }

    public static function toUnixSeparators(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public static function toWindowsSeparators(string $path): string
    {
        return str_replace('/', '\\', $path);
    }

    public static function joinBstrUnixPathsep(string $base, string $path): string
    {
        if ($base !== '' && !str_ends_with($base, '/')) {
            $base .= '/';
        }

        return $base . $path;
    }

    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/');
    }

    public static function exeInvocation(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? 'git.exe' : 'git';
    }

    public static function shell(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? 'sh.exe' : '/bin/sh';
    }

    public static function installationConfigFromGitConfigOrigin(string $source): ?string
    {
        if (!str_starts_with($source, 'file:')) {
            return null;
        }

        $file = substr($source, strlen('file:'));
        $end = strpos($file, "\0");
        if ($end === false) {
            return null;
        }

        $path = substr($file, 0, $end);
        return $path === '' ? null : $path;
    }

    public static function installationConfigPrefix(string $installationConfig): string
    {
        return self::dirnamePath($installationConfig);
    }

    public static function coreDirFromExecPathOutput(string $stdout, bool $success = true): ?string
    {
        if (!$success || !str_ends_with($stdout, "\n")) {
            return null;
        }

        $path = substr($stdout, 0, -1);
        return $path === '' ? null : $path;
    }

    public static function systemPrefix(): ?string
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return '/';
        }

        return null;
    }

    public static function homeDirFromEnvironment(?string $home, ?string $fallback = null): ?string
    {
        return $home ?? $fallback;
    }

    /**
     * @param callable(string): ?string $envVar
     */
    public static function xdgConfig(string $file, callable $envVar): ?string
    {
        $xdgConfigHome = $envVar('XDG_CONFIG_HOME');
        if ($xdgConfigHome !== null) {
            return self::joinPath(self::joinPath($xdgConfigHome, 'git'), $file);
        }

        $home = $envVar('HOME');
        if ($home === null) {
            return null;
        }

        return self::joinPath(self::joinPath(self::joinPath($home, '.config'), 'git'), $file);
    }

    /**
     * @return array{ok:true,path:string}|array{ok:false,error:string,maxSymlinks?:int,maxSymlinkChecks?:int}
     */
    public static function realpathOpts(
        string $path,
        string $cwd,
        int $maxSymlinks = self::REALPATH_DEFAULT_MAX_SYMLINKS
    ): array {
        if ($path === '') {
            return ['ok' => false, 'error' => self::REALPATH_EMPTY_PATH];
        }

        $realPath = self::isPlatformAbsolute($path) ? '' : rtrim($cwd, '/');
        $components = self::realpathComponents($path);
        $numSymlinks = 0;
        $symlinkChecks = 0;

        while ($components !== []) {
            $component = array_shift($components);

            if ($component === '/') {
                $realPath = '/';
                continue;
            }

            if ($component === '..') {
                $parent = self::popPathComponent($realPath);
                if ($parent === null) {
                    return ['ok' => false, 'error' => self::REALPATH_MISSING_PARENT];
                }

                $realPath = $parent;
                continue;
            }

            $realPath = self::joinPath($realPath, $component);
            $symlinkChecks++;

            if (is_link($realPath)) {
                $numSymlinks++;
                if ($numSymlinks > $maxSymlinks) {
                    return [
                        'ok' => false,
                        'error' => self::REALPATH_MAX_SYMLINKS_EXCEEDED,
                        'maxSymlinks' => $maxSymlinks,
                    ];
                }

                $linkDestination = readlink($realPath);
                if ($linkDestination === false) {
                    return ['ok' => false, 'error' => self::REALPATH_READ_LINK];
                }

                if (!self::isPlatformAbsolute($linkDestination)) {
                    $parent = self::popPathComponent($realPath);
                    if ($parent === null) {
                        return ['ok' => false, 'error' => self::REALPATH_MISSING_PARENT];
                    }

                    $realPath = $parent;
                }

                $components = array_merge(self::realpathComponents($linkDestination), $components);
            }

            if ($symlinkChecks > self::REALPATH_MAX_SYMLINK_CHECKS) {
                return [
                    'ok' => false,
                    'error' => self::REALPATH_EXCESSIVE_COMPONENT_COUNT,
                    'maxSymlinkChecks' => self::REALPATH_MAX_SYMLINK_CHECKS,
                ];
            }
        }

        return ['ok' => true, 'path' => $realPath];
    }

    public static function normalize(string $path, string $currentDir): ?string
    {
        if (!self::hasParentDirComponent($path)) {
            return $path;
        }

        $wasRelative = !self::isAbsolute($path);
        $currentDirAvailable = true;
        $currentComponents = self::pathComponents($currentDir, false);
        $components = self::pathComponents($path, true);
        $stack = [];
        $leadingDotTrailingSlash = false;

        $componentCount = count($components);
        foreach ($components as $index => $component) {
            if ($component === '..') {
                if ($stack === [] || $stack === ['.']) {
                    if (!$currentDirAvailable) {
                        return null;
                    }

                    $stack = $currentComponents;
                    $currentDirAvailable = false;
                } else {
                    self::dropInternalCurrentDir($stack);
                }

                if ($stack === ['/']) {
                    return null;
                }

                if ($stack === []) {
                    return null;
                }

                array_pop($stack);
                if ($stack === ['.'] && $index + 1 < $componentCount) {
                    $leadingDotTrailingSlash = true;
                }

                continue;
            }

            if ($component === '.') {
                if ($stack === [] && $wasRelative) {
                    $stack[] = '.';
                } elseif ($stack !== ['/']) {
                    $stack[] = '.';
                }

                continue;
            }

            self::dropInternalCurrentDir($stack);
            $stack[] = $component;
        }

        $rendered = self::renderComponents($stack, $leadingDotTrailingSlash);
        $renderedCurrentDir = self::renderComponents($currentComponents, false);

        if (
            $wasRelative
            && str_starts_with($path, './')
            && preg_match('#/\./[^/]+/\.\.$#', $path) === 1
            && $rendered !== '.'
            && !str_ends_with($rendered, '/.')
        ) {
            $rendered .= '/.';
        }

        if (($rendered === '' || $rendered === $renderedCurrentDir) && $wasRelative) {
            return '.';
        }

        return $rendered;
    }

    public static function relativizeWithPrefix(string $relativePath, string $prefix): string
    {
        if ($prefix === '') {
            return $relativePath;
        }

        $relativeComponents = self::normalComponents($relativePath);
        $prefixComponents = self::normalComponents($prefix);
        $remainingRelativeIndex = 0;
        $equalThusFar = true;
        $result = [];

        foreach ($prefixComponents as $prefixComponent) {
            if ($equalThusFar && isset($relativeComponents[$remainingRelativeIndex])) {
                if ($prefixComponent === $relativeComponents[$remainingRelativeIndex]) {
                    $remainingRelativeIndex++;
                    continue;
                }

                $equalThusFar = false;
            }

            $result[] = '..';
        }

        for ($i = $remainingRelativeIndex; $i < count($relativeComponents); $i++) {
            $result[] = $relativeComponents[$i];
        }

        if ($result === []) {
            return '.';
        }

        return implode('/', $result);
    }

    public static function relativePathError(string $path): ?string
    {
        if (self::isAbsolute($path)) {
            return self::RELATIVE_PATH_IS_ABSOLUTE;
        }

        foreach (self::relativeValidationComponents($path) as $component) {
            if ($component === '.' || $component === '..') {
                return self::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT;
            }

            if ($component === '' || str_contains($component, '\\') || str_contains($component, '/')) {
                return self::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT;
            }

            if (strlen($component) > 1 && $component[1] === ':') {
                return self::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT;
            }

            if (strpbrk($component, ':<>"|?*') !== false || preg_match('/[\x00-\x1f]/', $component) === 1) {
                return self::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT;
            }

            if (str_ends_with($component, '.') || str_ends_with($component, ' ')) {
                return self::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function pathComponents(string $path, bool $preserveCurrentDirs): array
    {
        $parts = explode('/', $path);
        $components = [];
        $isAbsolute = self::isAbsolute($path);

        if ($isAbsolute) {
            $components[] = '/';
        }

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            if ($part === '.') {
                if ($preserveCurrentDirs && !$isAbsolute) {
                    $components[] = '.';
                } elseif ($index === 0 && !$isAbsolute) {
                    $components[] = '.';
                }
                continue;
            }

            $components[] = $part;
        }

        return $components;
    }

    /**
     * @param list<string> $components
     */
    private static function renderComponents(array $components, bool $leadingDotTrailingSlash): string
    {
        if ($components === []) {
            return '';
        }

        if ($components === ['/']) {
            return '/';
        }

        if ($components[0] === '/') {
            return '/' . implode('/', array_slice($components, 1));
        }

        if ($components === ['.']) {
            return $leadingDotTrailingSlash ? './' : '.';
        }

        return implode('/', $components);
    }

    /**
     * @param list<string> $stack
     */
    private static function dropInternalCurrentDir(array &$stack): void
    {
        if (count($stack) > 1 && end($stack) === '.') {
            array_pop($stack);
        }
    }

    private static function hasParentDirComponent(string $path): bool
    {
        foreach (explode('/', $path) as $component) {
            if ($component === '..') {
                return true;
            }
        }

        return false;
    }

    private static function isPlatformAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @return list<string>
     */
    private static function realpathComponents(string $path): array
    {
        $components = [];
        if (str_starts_with($path, '/')) {
            $components[] = '/';
        }

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            $components[] = $part;
        }

        return $components;
    }

    private static function joinPath(string $base, string $component): string
    {
        if ($base === '' || self::isPlatformAbsolute($component)) {
            return $component;
        }

        if ($base === '/') {
            return '/' . ltrim($component, '/');
        }

        return rtrim($base, '/') . '/' . $component;
    }

    private static function popPathComponent(string $path): ?string
    {
        $path = rtrim($path, '/');
        if ($path === '' || $path === '/') {
            return null;
        }

        $slash = strrpos($path, '/');
        if ($slash === false) {
            return '';
        }
        if ($slash === 0) {
            return '/';
        }

        return substr($path, 0, $slash);
    }

    private static function dirnamePath(string $path): string
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            return '.';
        }

        $slash = strrpos($path, '/');
        if ($slash === false) {
            return '.';
        }
        if ($slash === 0) {
            return '/';
        }

        return substr($path, 0, $slash);
    }

    /**
     * @return list<string>
     */
    private static function normalComponents(string $path): array
    {
        if ($path === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $path), static fn (string $component): bool => $component !== ''));
    }

    /**
     * @return list<string>
     */
    private static function relativeValidationComponents(string $path): array
    {
        if ($path === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $path), static fn (string $component): bool => $component !== ''));
    }
}
