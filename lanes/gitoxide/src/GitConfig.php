<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitConfig
{
    /**
     * @param list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}> $sections
     */
    private function __construct(private array $sections)
    {
    }

    /**
     * @param array{
     *     gitDir?: ?string,
     *     branchName?: ?string,
     *     homeDir?: ?string,
     *     maxDepth?: int,
     *     errOnMaxDepthExceeded?: bool,
     *     errOnMissingConfigPath?: bool,
     *     errOnInterpolationFailure?: bool
     * } $options
     */
    public static function fromFile(string $path, array $options = []): self
    {
        $normalized = self::normalizeOptions($options);
        $config = self::parseFile($path);
        self::resolveIncludes($config, null, 0, $normalized);

        return new self($config['sections']);
    }

    /**
     * @param array{
     *     gitDir?: ?string,
     *     branchName?: ?string,
     *     homeDir?: ?string,
     *     maxDepth?: int,
     *     errOnMaxDepthExceeded?: bool,
     *     errOnMissingConfigPath?: bool,
     *     errOnInterpolationFailure?: bool
     * } $options
     */
    public static function fromString(string $contents, ?string $path = null, array $options = []): self
    {
        $normalized = self::normalizeOptions($options);
        $config = self::parse($contents, $path);
        self::resolveIncludes($config, null, 0, $normalized);

        return new self($config['sections']);
    }

    /**
     * @return list<string>
     */
    public function values(string $section, ?string $subsection, string $key): array
    {
        $section = strtolower($section);
        $key = strtolower($key);
        $values = [];

        foreach ($this->sections as $entrySection) {
            if ($entrySection['name'] !== $section || $entrySection['subsection'] !== $subsection) {
                continue;
            }

            foreach ($entrySection['entries'] as $entry) {
                if ($entry['key'] === $key) {
                    $values[] = $entry['value'];
                }
            }
        }

        return $values;
    }

    public function value(string $section, ?string $subsection, string $key): ?string
    {
        $values = $this->values($section, $subsection, $key);

        return $values === [] ? null : $values[array_key_last($values)];
    }

    /**
     * @return list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * @param array<string, mixed> $options
     * @return array{
     *     gitDir: ?string,
     *     branchName: ?string,
     *     homeDir: ?string,
     *     maxDepth: int,
     *     errOnMaxDepthExceeded: bool,
     *     errOnMissingConfigPath: bool,
     *     errOnInterpolationFailure: bool
     * }
     */
    private static function normalizeOptions(array $options): array
    {
        $maxDepth = (int) ($options['maxDepth'] ?? 10);
        if ($maxDepth < 0 || $maxDepth > 255) {
            throw new \InvalidArgumentException('Git config include maxDepth must be between 0 and 255');
        }

        return [
            'gitDir' => isset($options['gitDir']) ? (string) $options['gitDir'] : null,
            'branchName' => isset($options['branchName']) ? (string) $options['branchName'] : null,
            'homeDir' => isset($options['homeDir']) ? (string) $options['homeDir'] : null,
            'maxDepth' => $maxDepth,
            'errOnMaxDepthExceeded' => (bool) ($options['errOnMaxDepthExceeded'] ?? true),
            'errOnMissingConfigPath' => (bool) ($options['errOnMissingConfigPath'] ?? true),
            'errOnInterpolationFailure' => (bool) ($options['errOnInterpolationFailure'] ?? false),
        ];
    }

    /**
     * @return array{sections:list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}>,path:?string}
     */
    private static function parseFile(string $path): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Unable to read Git config file: {$path}");
        }

        return self::parse($bytes, $path);
    }

    /**
     * @return array{sections:list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}>,path:?string}
     */
    private static function parse(string $contents, ?string $path): array
    {
        $sections = [];
        $current = null;
        $logicalLines = self::logicalLines($contents);

        foreach ($logicalLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === ';') {
                continue;
            }

            if (preg_match('/^\s*\[([A-Za-z0-9_.-]+)(?:\s+"((?:\\\\.|[^"])*)")?\]\s*(?:[#;].*)?$/', $line, $matches) === 1) {
                $section = [
                    'name' => strtolower($matches[1]),
                    'subsection' => array_key_exists(2, $matches) ? self::unquoteSubsection($matches[2]) : null,
                    'entries' => [],
                    'path' => $path,
                ];
                $sections[] = $section;
                $current = array_key_last($sections);
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^\s*([A-Za-z][A-Za-z0-9-]*)\s*(?:=\s*(.*))?$/', $line, $matches) !== 1) {
                continue;
            }

            $sections[$current]['entries'][] = [
                'key' => strtolower($matches[1]),
                'value' => self::parseValue($matches[2] ?? 'true'),
            ];
        }

        return ['sections' => $sections, 'path' => $path];
    }

    /**
     * @return list<string>
     */
    private static function logicalLines(string $contents): array
    {
        $rawLines = preg_split('/\r\n|\n|\r/', $contents);
        if ($rawLines === false) {
            return [];
        }

        $lines = [];
        $pending = '';
        foreach ($rawLines as $line) {
            $backslashes = 0;
            for ($index = strlen($line) - 1; $index >= 0 && $line[$index] === '\\'; $index--) {
                $backslashes++;
            }

            if ($backslashes % 2 === 1) {
                $pending .= substr($line, 0, -1);
                continue;
            }

            $lines[] = $pending . $line;
            $pending = '';
        }

        if ($pending !== '') {
            $lines[] = $pending;
        }

        return $lines;
    }

    private static function parseValue(string $value): string
    {
        $value = ltrim(self::stripInlineComment($value));
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            return self::unquote(substr($value, 1, -1));
        }

        return rtrim($value);
    }

    private static function stripInlineComment(string $value): string
    {
        $inQuote = false;
        $escaped = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $byte = $value[$index];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($byte === '\\') {
                $escaped = true;
                continue;
            }
            if ($byte === '"') {
                $inQuote = !$inQuote;
                continue;
            }
            if (!$inQuote && ($byte === '#' || $byte === ';') && ($index === 0 || ctype_space($value[$index - 1]))) {
                return substr($value, 0, $index);
            }
        }

        return $value;
    }

    private static function unquote(string $value): string
    {
        $out = '';
        $escaped = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $byte = $value[$index];
            if (!$escaped && $byte === '\\') {
                $escaped = true;
                continue;
            }

            if ($escaped) {
                $out .= match ($byte) {
                    'n' => "\n",
                    't' => "\t",
                    'b' => "\x08",
                    '"' => '"',
                    '\\' => '\\',
                    default => $byte,
                };
                $escaped = false;
                continue;
            }

            $out .= $byte;
        }

        if ($escaped) {
            $out .= '\\';
        }

        return $out;
    }

    private static function unquoteSubsection(string $value): string
    {
        $out = '';
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $byte = $value[$index];
            if ($byte === '\\' && $index + 1 < $length) {
                $index++;
                $out .= $value[$index];
                continue;
            }

            $out .= $byte;
        }

        return $out;
    }

    /**
     * @param array{sections:list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}>,path:?string} $target
     * @param list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}>|null $searchSections
     * @param array{
     *     gitDir: ?string,
     *     branchName: ?string,
     *     homeDir: ?string,
     *     maxDepth: int,
     *     errOnMaxDepthExceeded: bool,
     *     errOnMissingConfigPath: bool,
     *     errOnInterpolationFailure: bool
     * } $options
     */
    private static function resolveIncludes(array &$target, ?array $searchSections, int $depth, array $options): void
    {
        if ($options['maxDepth'] === 0) {
            return;
        }
        if ($depth >= $options['maxDepth']) {
            if ($options['errOnMaxDepthExceeded']) {
                throw new \RuntimeException("Git config include depth {$options['maxDepth']} exceeded");
            }
            return;
        }

        $original = $target['sections'];
        $currentSearchSections = $searchSections ?? $original;
        $resolved = [];

        foreach ($original as $section) {
            $resolved[] = $section;
            $paths = self::includePathsForSection($section, $currentSearchSections, $options);
            foreach ($paths as $includePath) {
                $resolvedPath = self::resolvePath($includePath, $section['path'], $options);
                if ($resolvedPath === null || !is_file($resolvedPath)) {
                    continue;
                }

                $includeConfig = self::parseFile($resolvedPath);
                self::resolveIncludes($includeConfig, $currentSearchSections, $depth + 1, $options);
                foreach ($includeConfig['sections'] as $includeSection) {
                    $resolved[] = $includeSection;
                }
                if ($searchSections === null) {
                    array_push($currentSearchSections, ...$includeConfig['sections']);
                }
            }
        }

        $target['sections'] = $resolved;
    }

    /**
     * @param array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string} $section
     * @param list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}> $searchSections
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private static function includePathsForSection(array $section, array $searchSections, array $options): array
    {
        if ($section['name'] === 'include' && $section['subsection'] === null) {
            return self::sectionValues($section, 'path');
        }

        if ($section['name'] !== 'includeif' || $section['subsection'] === null) {
            return [];
        }

        if (!self::includeConditionMatches($section['subsection'], $section['path'], $searchSections, $options)) {
            return [];
        }

        return self::sectionValues($section, 'path');
    }

    /**
     * @param array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string} $section
     * @return list<string>
     */
    private static function sectionValues(array $section, string $key): array
    {
        $values = [];
        foreach ($section['entries'] as $entry) {
            if ($entry['key'] === $key) {
                $values[] = $entry['value'];
            }
        }

        return $values;
    }

    /**
     * @param list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}> $searchSections
     * @param array<string, mixed> $options
     */
    private static function includeConditionMatches(string $condition, ?string $targetConfigPath, array $searchSections, array $options): bool
    {
        $colon = strpos($condition, ':');
        if ($colon === false) {
            return false;
        }

        $prefix = substr($condition, 0, $colon);
        $body = substr($condition, $colon + 1);

        return match ($prefix) {
            'gitdir' => self::gitDirMatches($body, $targetConfigPath, $options, false),
            'gitdir/i' => self::gitDirMatches($body, $targetConfigPath, $options, true),
            'onbranch' => self::branchMatches($body, $options),
            'hasconfig' => self::hasConfigMatches($body, $searchSections),
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function gitDirMatches(string $pattern, ?string $targetConfigPath, array $options, bool $ignoreCase): bool
    {
        $gitDir = $options['gitDir'];
        if ($gitDir === null || $gitDir === '') {
            if ($options['errOnInterpolationFailure']) {
                throw new \RuntimeException('The git directory must be provided to support gitdir conditional includes');
            }
            return false;
        }

        $pattern = self::interpolatePath($pattern, $options);
        if ($pattern === null) {
            return false;
        }

        if (str_starts_with($pattern, './')) {
            if ($targetConfigPath === null) {
                if ($options['errOnMissingConfigPath']) {
                    throw new \RuntimeException('Relative gitdir conditions require a config file path');
                }
                return false;
            }
            $base = self::normalizePath(dirname($targetConfigPath));
            $pattern = $base . '/' . substr($pattern, 2);
        }

        if (!self::isAbsolutePath($pattern)) {
            $pattern = '**/' . $pattern;
        }
        if (str_ends_with($pattern, '/')) {
            $pattern .= '**';
        }

        $gitDirPath = self::normalizePath($gitDir);
        if (self::wildmatch($pattern, $gitDirPath, $ignoreCase)) {
            return true;
        }

        $real = realpath($gitDir);
        return is_string($real) && self::wildmatch($pattern, self::normalizePath($real), $ignoreCase);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function branchMatches(string $pattern, array $options): bool
    {
        $branchName = $options['branchName'];
        if (!is_string($branchName) || !str_starts_with($branchName, 'refs/heads/')) {
            return false;
        }

        $shortName = substr($branchName, strlen('refs/heads/'));
        if (str_ends_with($pattern, '/')) {
            $pattern .= '**';
        }

        return self::wildmatch($pattern, $shortName, false);
    }

    /**
     * @param list<array{name:string,subsection:?string,entries:list<array{key:string,value:string}>,path:?string}> $searchSections
     */
    private static function hasConfigMatches(string $condition, array $searchSections): bool
    {
        $colon = strpos($condition, ':');
        if ($colon === false) {
            return false;
        }

        $keyGlob = substr($condition, 0, $colon);
        $valueGlob = substr($condition, $colon + 1);
        if ($keyGlob !== 'remote.*.url') {
            return false;
        }

        foreach ($searchSections as $section) {
            if ($section['name'] !== 'remote') {
                continue;
            }

            foreach (self::sectionValues($section, 'url') as $url) {
                if (self::wildmatch($valueGlob, $url, false)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function resolvePath(string $path, ?string $targetConfigPath, array $options): ?string
    {
        $path = self::interpolatePath($path, $options);
        if ($path === null) {
            return null;
        }
        $path = self::normalizePath($path);

        if (!self::isAbsolutePath($path)) {
            if ($targetConfigPath === null) {
                if ($options['errOnMissingConfigPath']) {
                    throw new \RuntimeException('Relative include paths require a config file path');
                }
                return null;
            }
            $path = self::normalizePath(dirname($targetConfigPath)) . '/' . $path;
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function interpolatePath(string $path, array $options): ?string
    {
        if ($path === '~' || str_starts_with($path, '~/')) {
            $home = $options['homeDir'];
            if (!is_string($home) || $home === '') {
                if ($options['errOnInterpolationFailure']) {
                    throw new \RuntimeException('Home directory is required for Git config path interpolation');
                }
                return null;
            }

            return rtrim($home, "\\/") . substr($path, 1);
        }

        if (str_contains($path, '%(prefix)')) {
            if ($options['errOnInterpolationFailure']) {
                throw new \RuntimeException('Git install prefix interpolation is unsupported by this bounded config reader');
            }
            return null;
        }

        return $path;
    }

    private static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1;
    }

    private static function wildmatch(string $pattern, string $text, bool $ignoreCase): bool
    {
        $text = self::normalizePath($text);
        $regex = '';
        $length = strlen($pattern);

        for ($index = 0; $index < $length; $index++) {
            $byte = $pattern[$index];
            if ($byte === '\\' && $index + 1 < $length) {
                $index++;
                $regex .= preg_quote($pattern[$index], '~');
                continue;
            }

            if ($byte === '*') {
                if ($index + 1 < $length && $pattern[$index + 1] === '*') {
                    if ($index + 2 < $length && $pattern[$index + 2] === '/') {
                        $regex .= '(?:.*/)?';
                        $index += 2;
                        continue;
                    }
                    $regex .= '.*';
                    $index++;
                } else {
                    $regex .= '[^/]*';
                }
                continue;
            }

            if ($byte === '?') {
                $regex .= '[^/]';
                continue;
            }

            if ($byte === '[') {
                $end = strpos($pattern, ']', $index + 1);
                if ($end !== false) {
                    $class = substr($pattern, $index + 1, $end - $index - 1);
                    if ($class !== '') {
                        $negated = $class[0] === '!' || $class[0] === '^';
                        if ($negated) {
                            $class = substr($class, 1);
                        }
                        $class = str_replace(['\\', '~', ']'], ['\\\\', '\\~', '\\]'], $class);
                        $regex .= '[' . ($negated ? '^' : '') . $class . ']';
                        $index = $end;
                        continue;
                    }
                }
            }

            $regex .= preg_quote($byte, '~');
        }

        return preg_match('~\A' . $regex . '\z~u' . ($ignoreCase ? 'i' : ''), $text) === 1;
    }
}
