<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcPackagePath
{
    public static function canonicalPartName(string $partName, bool $allowRoot = false): string
    {
        if ($partName === '') {
            throw new \InvalidArgumentException('OPC part name must not be empty');
        }

        if (str_contains($partName, "\0") || str_contains($partName, '\\')) {
            throw new \InvalidArgumentException('OPC part names must use slash-separated package paths');
        }

        if (str_contains($partName, '?') || str_contains($partName, '#')) {
            throw new \InvalidArgumentException('OPC part names must not include URI query or fragment components');
        }

        $path = str_starts_with($partName, '/') ? $partName : '/' . $partName;
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw new \InvalidArgumentException('OPC part path must not traverse above the package root');
                }
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            if ($allowRoot) {
                return '/';
            }

            throw new \InvalidArgumentException('OPC part name must identify a package part');
        }

        return '/' . implode('/', $segments);
    }

    public static function resolveInternalTarget(string $sourcePartName, string $target): string
    {
        if ($target === '') {
            throw new \InvalidArgumentException('OPC relationship target must not be empty');
        }

        if (str_contains($target, "\0") || str_contains($target, '\\')) {
            throw new \InvalidArgumentException('OPC relationship targets must use slash-separated paths');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $target) === 1) {
            throw new \InvalidArgumentException('OPC internal relationship target must not be an absolute URI');
        }

        $split = strcspn($target, '?#');
        $path = substr($target, 0, $split);
        $suffix = substr($target, $split);
        if ($path === '') {
            throw new \InvalidArgumentException('OPC relationship target path must not be empty');
        }

        if (str_starts_with($path, '/')) {
            return self::canonicalPartName($path) . $suffix;
        }

        $source = self::canonicalPartName($sourcePartName, true);
        $base = $source === '/' ? '/' : dirname($source);

        return self::canonicalPartName($base . '/' . $path) . $suffix;
    }

    public static function stripQueryAndFragment(string $partName): string
    {
        $split = strcspn($partName, '?#');

        return substr($partName, 0, $split);
    }
}
