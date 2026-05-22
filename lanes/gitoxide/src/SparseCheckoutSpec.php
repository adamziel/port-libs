<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SparseCheckoutSpec
{
    public const MODE_CONE = 'cone';
    public const MODE_NON_CONE = 'non-cone';

    /**
     * @param list<string> $recursiveDirectories
     * @param list<array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool}> $patterns
     */
    private function __construct(
        public readonly string $mode,
        private readonly array $recursiveDirectories,
        private readonly array $patterns,
        public readonly bool $ignoreCase = false,
    ) {
        if (!in_array($mode, [self::MODE_CONE, self::MODE_NON_CONE], true)) {
            throw new \InvalidArgumentException("Unsupported sparse checkout mode: {$mode}");
        }
    }

    /**
     * @param list<string> $directories
     */
    public static function cone(array $directories, bool $ignoreCase = false): self
    {
        $normalized = [];
        foreach ($directories as $directory) {
            $directory = self::normalizePath($directory);
            if ($directory === '') {
                continue;
            }
            $normalized[$ignoreCase ? strtolower($directory) : $directory] = true;
        }

        $directories = array_keys($normalized);
        sort($directories, SORT_STRING);

        return new self(self::MODE_CONE, $directories, [], $ignoreCase);
    }

    public static function fromConeDirectoryRules(string $contents, bool $ignoreCase = false): self
    {
        $directories = [];
        foreach (self::lines($contents) as $line) {
            if ($line === '') {
                continue;
            }
            $directories[] = $line;
        }

        return self::cone($directories, $ignoreCase);
    }

    public static function fromConePatternFile(string $contents, bool $ignoreCase = false): self
    {
        $positiveDirectories = [];
        $parentDirectories = [];
        foreach (self::lines($contents) as $line) {
            if ($line === '' || $line === '/*' || $line === '!/*/') {
                continue;
            }
            if (preg_match('#^!/([^*].*)/\*/$#', $line, $matches) === 1) {
                $parentDirectories[self::normalizePath($matches[1])] = true;
                continue;
            }
            if (preg_match('#^/(.+)/$#', $line, $matches) === 1) {
                $positiveDirectories[self::normalizePath($matches[1])] = true;
            }
        }

        $recursive = [];
        foreach (array_keys($positiveDirectories) as $directory) {
            if (!isset($parentDirectories[$directory])) {
                $recursive[] = $directory;
            }
        }

        return self::cone($recursive, $ignoreCase);
    }

    public static function fromNonConePatternFile(string $contents, bool $ignoreCase = false): self
    {
        $patterns = [];
        foreach (self::lines($contents) as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $negative = false;
            if (str_starts_with($line, '!')) {
                $negative = true;
                $line = substr($line, 1);
            } elseif (str_starts_with($line, '\\!') || str_starts_with($line, '\\#')) {
                $line = substr($line, 1);
            }

            $directoryOnly = str_ends_with($line, '/');
            if ($directoryOnly) {
                $line = rtrim($line, '/');
            }
            $anchored = str_starts_with($line, '/');
            if ($anchored) {
                $line = ltrim($line, '/');
            }

            if ($line === '') {
                continue;
            }

            $patterns[] = [
                'pattern' => str_replace('\\/', '/', $line),
                'negative' => $negative,
                'directoryOnly' => $directoryOnly,
                'anchored' => $anchored,
            ];
        }

        return new self(self::MODE_NON_CONE, [], $patterns, $ignoreCase);
    }

    /**
     * @return list<string>
     */
    public function recursiveDirectories(): array
    {
        return $this->recursiveDirectories;
    }

    /**
     * @return list<string>
     */
    public function parentDirectories(): array
    {
        $parents = ['' => true];
        foreach ($this->recursiveDirectories as $directory) {
            $parts = explode('/', $directory);
            array_pop($parts);
            $prefix = '';
            foreach ($parts as $part) {
                $prefix = $prefix === '' ? $part : $prefix . '/' . $part;
                $parents[$this->comparisonPath($prefix)] = true;
            }
        }

        $out = array_keys($parents);
        sort($out, SORT_STRING);

        return $out;
    }

    public function includesPath(string $path, ?bool $isDirectory = null): bool
    {
        $path = self::normalizePath($path);
        if ($this->ignoreCase) {
            $path = strtolower($path);
        }

        if ($this->mode === self::MODE_NON_CONE) {
            return $this->matchesNonConePath($path, $isDirectory);
        }

        return $this->matchesConePath($path, $isDirectory);
    }

    public function skipWorktree(string $path, ?bool $isDirectory = null): bool
    {
        return !$this->includesPath($path, $isDirectory);
    }

    /**
     * @return list<TreeEntry>
     */
    public function includedTreeEntries(Tree $tree, string $directory = ''): array
    {
        $directory = self::normalizePath($directory);
        $entries = [];
        foreach ($tree->entries as $entry) {
            $path = $directory === '' ? $entry->filename : $directory . '/' . $entry->filename;
            if ($this->includesPath($path, $entry->isTree())) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function matchesConePath(string $path, ?bool $isDirectory): bool
    {
        if ($path === '') {
            return true;
        }

        if ($this->isInsideRecursiveDirectory($path)) {
            return true;
        }

        if ($isDirectory === true) {
            return $this->directoryCanContainIncludedPaths($path);
        }

        return in_array(self::dirname($path), $this->parentDirectories(), true);
    }

    private function directoryCanContainIncludedPaths(string $path): bool
    {
        foreach ($this->recursiveDirectories as $directory) {
            if ($path === $directory || str_starts_with($directory . '/', $path . '/')) {
                return true;
            }
        }

        return false;
    }

    private function isInsideRecursiveDirectory(string $path): bool
    {
        foreach ($this->recursiveDirectories as $directory) {
            if ($path === $directory || str_starts_with($path, $directory . '/')) {
                return true;
            }
        }

        return false;
    }

    private function matchesNonConePath(string $path, ?bool $isDirectory): bool
    {
        $included = false;
        foreach ($this->patterns as $rule) {
            if (!$this->nonConeRuleMatches($rule, $path, $isDirectory)) {
                continue;
            }
            $included = !$rule['negative'];
        }

        return $included;
    }

    /**
     * @param array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool} $rule
     */
    private function nonConeRuleMatches(array $rule, string $path, ?bool $isDirectory): bool
    {
        if ($rule['directoryOnly'] && $isDirectory === false && !str_starts_with($path, $rule['pattern'] . '/')) {
            return false;
        }

        foreach (self::pathAndAncestors($path) as $candidate) {
            if ($this->patternMatchesCandidate($rule, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool} $rule
     */
    private function patternMatchesCandidate(array $rule, string $candidate): bool
    {
        $pattern = $this->ignoreCase ? strtolower($rule['pattern']) : $rule['pattern'];
        $candidate = $this->ignoreCase ? strtolower($candidate) : $candidate;

        if (!$rule['anchored'] && !str_contains($pattern, '/')) {
            $candidate = basename($candidate);
        }

        return preg_match(self::globRegex($pattern), $candidate) === 1;
    }

    /**
     * @return list<string>
     */
    private static function pathAndAncestors(string $path): array
    {
        $paths = [$path];
        while (str_contains($path, '/')) {
            $path = self::dirname($path);
            $paths[] = $path;
        }

        return $paths;
    }

    private static function globRegex(string $pattern): string
    {
        $regex = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '*') {
                if (($pattern[$i + 1] ?? '') === '*') {
                    $regex .= '.*';
                    $i++;
                } else {
                    $regex .= '[^/]*';
                }
                continue;
            }
            if ($char === '?') {
                $regex .= '[^/]';
                continue;
            }
            $regex .= preg_quote($char, '#');
        }

        return '#^' . $regex . '$#';
    }

    private function comparisonPath(string $path): string
    {
        $path = self::normalizePath($path);

        return $this->ignoreCase ? strtolower($path) : $path;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $contents): array
    {
        $lines = [];
        foreach (explode("\n", $contents) as $line) {
            $lines[] = rtrim($line, "\r");
        }

        return $lines;
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Sparse checkout path cannot contain NUL bytes');
        }

        $parts = [];
        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private static function dirname(string $path): string
    {
        $position = strrpos($path, '/');

        return $position === false ? '' : substr($path, 0, $position);
    }
}
