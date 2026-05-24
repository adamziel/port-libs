<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class GlobImportResolver
{
    /**
     * @return list<GlobImportMatch>
     */
    public function resolve(ModuleAnalysis $analysis, string $sourceDir): array
    {
        $matches = [];
        foreach ($analysis->imports as $import) {
            foreach ($this->resolveImport($import, $sourceDir) as $match) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    /**
     * @return list<GlobImportMatch>
     */
    public function resolveImport(ModuleImport $import, string $sourceDir): array
    {
        if (!$this->isGlobImport($import)) {
            return [];
        }

        $parts = $this->parseGlobPattern(str_replace('\\', '/', $import->source));
        if (!$this->hasWildcard($parts)) {
            return [];
        }

        $firstPrefix = $parts[0]['prefix'];
        if (!str_starts_with($firstPrefix, './') && !str_starts_with($firstPrefix, '../')) {
            return [];
        }

        $dirPrefixLength = $this->leadingDirectoryPrefixLength($firstPrefix);
        $keyPrefix = substr($firstPrefix, 0, $dirPrefixLength);
        $scanDir = realpath(rtrim($sourceDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $keyPrefix));
        if ($scanDir === false || !is_dir($scanDir)) {
            return [];
        }

        $regex = $this->regexForPattern($parts, $firstPrefix);
        $recursive = $this->canMatchOnSlash($parts);
        $matches = [];
        foreach ($this->fileKeys($scanDir, $keyPrefix, $recursive) as [$key, $path]) {
            if (preg_match($regex, $key) === 1) {
                $matches[] = new GlobImportMatch($import, $key, $path);
            }
        }

        usort($matches, static fn (GlobImportMatch $a, GlobImportMatch $b): int => strcmp($a->key, $b->key));

        return $matches;
    }

    private function isGlobImport(ModuleImport $import): bool
    {
        return $import->kind === 'commonjs-require-glob' || $import->kind === 'dynamic-glob';
    }

    /**
     * @return list<array{prefix:string, wildcard:string}>
     */
    private function parseGlobPattern(string $text): array
    {
        $pattern = [];

        while (true) {
            $star = strpos($text, '*');
            if ($star === false) {
                $pattern[] = ['prefix' => $text, 'wildcard' => 'none'];
                break;
            }

            $count = 1;
            $length = strlen($text);
            while ($star + $count < $length && $text[$star + $count] === '*') {
                $count++;
            }

            $wildcard = 'except-slash';
            if ($count > 1
                && ($star === 0 || $text[$star - 1] === '/')
                && ($star + $count === $length || $text[$star + $count] === '/')
            ) {
                $wildcard = 'including-slash';
            }

            $pattern[] = ['prefix' => substr($text, 0, $star), 'wildcard' => $wildcard];
            $text = substr($text, $star + $count);
        }

        return $pattern;
    }

    /**
     * @param list<array{prefix:string, wildcard:string}> $parts
     */
    private function hasWildcard(array $parts): bool
    {
        foreach ($parts as $part) {
            if ($part['wildcard'] !== 'none') {
                return true;
            }
        }

        return false;
    }

    private function leadingDirectoryPrefixLength(string $firstPrefix): int
    {
        $dirPrefix = 0;

        while (true) {
            $remaining = substr($firstPrefix, $dirPrefix);
            $slash = strpos($remaining, '/');
            if ($slash === false) {
                break;
            }

            $star = strpos($remaining, '*');
            if ($star !== false && $slash > $star) {
                break;
            }

            $dirPrefix += $slash + 1;
        }

        return $dirPrefix;
    }

    /**
     * @param list<array{prefix:string, wildcard:string}> $parts
     */
    private function regexForPattern(array $parts, string $firstPrefix): string
    {
        $wasGlobStar = false;
        $regex = '^';
        foreach ($parts as $index => $part) {
            $prefix = $index === 0 ? $firstPrefix : $part['prefix'];
            if ($wasGlobStar && $prefix !== '' && $prefix[0] === '/') {
                $prefix = substr($prefix, 1);
            }

            $regex .= preg_quote($prefix, '~');
            if ($part['wildcard'] === 'including-slash') {
                $regex .= '(?:[^/]*(?:/|$))*';
                $wasGlobStar = true;
            } elseif ($part['wildcard'] === 'except-slash') {
                $regex .= '[^/]*';
                $wasGlobStar = false;
            }
        }

        return '~' . $regex . '$~';
    }

    /**
     * @param list<array{prefix:string, wildcard:string}> $parts
     */
    private function canMatchOnSlash(array $parts): bool
    {
        foreach ($parts as $part) {
            if ($part['wildcard'] === 'including-slash') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private function fileKeys(string $dir, string $keyPrefix, bool $recursive): array
    {
        $files = [];
        $this->collectFileKeys($dir, $keyPrefix, $recursive, $files);
        usort($files, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $files;
    }

    /**
     * @param list<array{0:string, 1:string}> $files
     */
    private function collectFileKeys(string $dir, string $keyPrefix, bool $recursive, array &$files): void
    {
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        sort($entries);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            $key = $keyPrefix . $entry;
            if (is_file($path)) {
                $files[] = [$key, $path];
                continue;
            }

            if ($recursive && is_dir($path) && !is_link($path)) {
                $this->collectFileKeys($path, $key . '/', true, $files);
            }
        }
    }
}
