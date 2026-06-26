<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitPath
{
    public const RELATIVE_PATH_IS_ABSOLUTE = 'is_absolute';
    public const RELATIVE_PATH_CONTAINS_INVALID_COMPONENT = 'contains_invalid_component';

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
