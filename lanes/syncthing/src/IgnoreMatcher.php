<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class IgnoreMatcher
{
    /**
     * @param list<array{pattern:string, regex:string, result:IgnoreMatchResult}> $patterns
     */
    private function __construct(
        private readonly array $patterns,
    ) {
    }

    /**
     * Builds a focused Syncthing-style matcher from `.stignore` lines.
     *
     * This maps the request-serving boundary: comments/blank lines are skipped,
     * `!`, `(?i)`, and `(?d)` prefixes are parsed in upstream order, rooted
     * patterns match from the folder root, unrooted patterns match in the root
     * and subdirectories, `#escape=` directives can change the escape
     * character before local patterns, and `#include` directives are parsed
     * when using {@see fromFile()}.
     *
     * @param list<string> $lines
     */
    public static function fromLines(array $lines): self
    {
        $seenLines = [];

        return new self(self::parseLines($lines, '.stignore', null, $seenLines));
    }

    public static function fromFile(string $file): self
    {
        $realFile = realpath($file);
        if ($realFile === false || !is_file($realFile)) {
            throw new \InvalidArgumentException('Ignore file not found: ' . $file);
        }

        $seenFiles = [];
        $fileReader = static function (string $includeFile): array {
            $realIncludeFile = realpath($includeFile);
            if ($realIncludeFile === false || !is_file($realIncludeFile)) {
                throw new \InvalidArgumentException('Ignore include file not found: ' . $includeFile);
            }

            $lines = file($realIncludeFile, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                throw new \RuntimeException('Failed to read ignore include file: ' . $realIncludeFile);
            }

            foreach ($lines as $line) {
                if (!is_string($line)) {
                    throw new \RuntimeException('Ignore include file contained a non-string line');
                }
            }

            /** @var list<string> $lines */
            return ['file' => $realIncludeFile, 'lines' => $lines];
        };

        return new self(self::parseFile($realFile, $fileReader, $seenFiles, []));
    }

    public function match(string $name): IgnoreMatchResult
    {
        if (RequestServer::isInternalName($name) || self::isTemporaryName($name)) {
            return IgnoreMatchResult::ignored(canSkipDir: true);
        }

        if ($name === '.') {
            return IgnoreMatchResult::notIgnored();
        }

        $name = str_replace('\\', '/', $name);
        $canSkipDir = true;
        foreach ($this->patterns as $pattern) {
            if ($canSkipDir && !self::allowsSkippingIgnoredDirs($pattern['pattern'], $pattern['result'])) {
                $canSkipDir = false;
            }

            if (preg_match($pattern['regex'], $pattern['result']->isCaseFolded() ? strtolower($name) : $name) === 1) {
                return self::withSkipDir($pattern['result'], $canSkipDir);
            }
        }

        return IgnoreMatchResult::notIgnored();
    }

    private static function isTemporaryName(string $name): bool
    {
        $base = basename(str_replace('\\', '/', $name));

        return str_starts_with($base, '.syncthing.') && str_ends_with($base, '.tmp');
    }

    /**
     * @param callable(string): array{file:string, lines:list<string>}|null $fileReader
     * @param array<string, true> $seenFiles
     *
     * @return list<array{pattern:string, regex:string, result:IgnoreMatchResult}>
     */
    private static function parseFile(
        string $file,
        ?callable $fileReader,
        array &$seenFiles,
        array $seenLines,
    ): array {
        if (isset($seenFiles[$file])) {
            throw new \InvalidArgumentException('Ignore include file included more than once: ' . $file);
        }
        $seenFiles[$file] = true;

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Failed to read ignore file: ' . $file);
        }

        foreach ($lines as $line) {
            if (!is_string($line)) {
                throw new \RuntimeException('Ignore file contained a non-string line');
            }
        }

        /** @var list<string> $lines */
        return self::parseLines($lines, $file, $fileReader, $seenLines, $seenFiles);
    }

    /**
     * @param list<string> $lines
     * @param callable(string): array{file:string, lines:list<string>}|null $fileReader
     * @param array<string, true> $seenLines
     * @param array<string, true>|null $seenFiles
     *
     * @return list<array{pattern:string, regex:string, result:IgnoreMatchResult}>
     */
    private static function parseLines(
        array $lines,
        string $currentFile,
        ?callable $fileReader,
        array &$seenLines,
        ?array &$seenFiles = null,
    ): array {
        $patterns = [];
        $escapeChar = '\\';
        $escapePrefixSeen = false;
        $localPatternCount = 0;

        foreach ($lines as $line) {
            if (!is_string($line)) {
                throw new \InvalidArgumentException('Ignore lines must be strings');
            }

            $line = trim($line);

            if (str_starts_with($line, '#escape')) {
                if ($escapePrefixSeen) {
                    throw new \InvalidArgumentException('Multiple #escape= lines found in ignore file');
                }
                if ($localPatternCount > 0) {
                    throw new \InvalidArgumentException('#escape= line found after patterns in ignore file');
                }

                $escapeChar = self::parseEscapeDirective($line);
                $escapePrefixSeen = true;
                continue;
            }

            if ($escapeChar !== '\\') {
                $line = self::applyCustomEscape($line, $escapeChar);
            }

            if (isset($seenLines[$line])) {
                continue;
            }
            $seenLines[$line] = true;

            if ($line === '' || str_starts_with($line, '//')) {
                continue;
            }

            if (str_starts_with($line, '#include')) {
                if ($fileReader === null || $seenFiles === null) {
                    throw new \InvalidArgumentException('Ignore include file not found: ' . $line);
                }

                $includeTail = substr($line, strlen('#include'));
                if ($includeTail === '' || $includeTail[0] !== ' ') {
                    throw new \InvalidArgumentException('Failed to parse #include line: no file');
                }

                $includeRel = trim($includeTail);
                if ($includeRel === '') {
                    throw new \InvalidArgumentException('Failed to parse #include line: no file');
                }

                $includePath = self::joinPath(dirname($currentFile), $includeRel);
                $include = $fileReader($includePath);
                if (isset($seenFiles[$include['file']])) {
                    throw new \InvalidArgumentException('Ignore include file included more than once: ' . $include['file']);
                }
                $seenFiles[$include['file']] = true;
                $patterns = array_merge(
                    $patterns,
                    self::parseLines($include['lines'], $include['file'], $fileReader, $seenLines, $seenFiles),
                );
                continue;
            }

            foreach (self::expandedPatternLines($line) as $expanded) {
                foreach (self::parseLine($expanded) as $pattern) {
                    $patterns[] = $pattern;
                    $localPatternCount++;
                }
            }
        }

        return $patterns;
    }

    private static function parseEscapeDirective(string $line): string
    {
        $suffix = trim(substr($line, strlen('#escape')));
        if (!str_starts_with($suffix, '=')) {
            throw new \InvalidArgumentException('Failed to parse #escape= line in ignore file: ' . $line);
        }

        $escape = trim(substr($suffix, 1));
        if (preg_match('/^.$/us', $escape) !== 1) {
            throw new \InvalidArgumentException('Failed to parse #escape= line in ignore file: ' . $line);
        }

        return $escape;
    }

    private static function applyCustomEscape(string $line, string $escapeChar): string
    {
        $line = str_replace($escapeChar, '\\', $line);

        return str_replace('\\\\', '\\' . $escapeChar, $line);
    }

    private static function joinPath(string $dir, string $path): string
    {
        if ($path === '') {
            return $dir;
        }
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @return list<string>
     */
    private static function expandedPatternLines(string $line): array
    {
        if (str_ends_with($line, '/**')) {
            return [$line];
        }
        if (str_ends_with($line, '/')) {
            return [$line . '**'];
        }

        return [$line, $line . '/**'];
    }

    /**
     * @return list<array{pattern:string, regex:string, result:IgnoreMatchResult}>
     */
    private static function parseLine(string $line): array
    {
        $ignored = true;
        $caseFolded = false;
        $deletable = false;
        $seenNegation = false;
        $seenCaseFold = false;
        $seenDeletable = false;

        while (true) {
            if (!$seenNegation && str_starts_with($line, '!')) {
                $seenNegation = true;
                $ignored = !$ignored;
                $line = substr($line, 1);
                continue;
            }
            if (!$seenCaseFold && str_starts_with($line, '(?i)')) {
                $seenCaseFold = true;
                $caseFolded = true;
                $line = substr($line, 4);
                continue;
            }
            if (!$seenDeletable && str_starts_with($line, '(?d)')) {
                $seenDeletable = true;
                $deletable = true;
                $line = substr($line, 4);
                continue;
            }

            break;
        }

        if ($line === '') {
            throw new \InvalidArgumentException('Missing ignore pattern');
        }

        $result = $ignored
            ? IgnoreMatchResult::ignored(deletable: $deletable, caseFolded: $caseFolded)
            : IgnoreMatchResult::notIgnored(caseFolded: $caseFolded);

        if ($caseFolded) {
            $line = strtolower($line);
        }

        if (str_starts_with($line, '/')) {
            return [[
                'pattern' => $line,
                'regex' => self::globRegex(substr($line, 1)),
                'result' => $result,
            ]];
        }

        if (str_starts_with($line, '**/')) {
            $withoutGlobstar = substr($line, 3);

            return [
                ['pattern' => $line, 'regex' => self::globRegex($line), 'result' => $result],
                ['pattern' => $withoutGlobstar, 'regex' => self::globRegex($withoutGlobstar), 'result' => $result],
            ];
        }

        return [
            ['pattern' => $line, 'regex' => self::globRegex($line), 'result' => $result],
            ['pattern' => '**/' . $line, 'regex' => self::globRegex('**/' . $line), 'result' => $result],
        ];
    }

    private static function globRegex(string $pattern): string
    {
        return '#^' . self::globRegexBody($pattern) . '$#u';
    }

    private static function globRegexBody(string $pattern): string
    {
        $regex = '';
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $i++;
                    $regex .= preg_quote($pattern[$i], '#');
                } else {
                    $regex .= preg_quote($char, '#');
                }
                continue;
            }

            if ($char === '*') {
                if ($i + 1 < $length && $pattern[$i + 1] === '*') {
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

            if ($char === '[') {
                $classEnd = self::findUnescaped($pattern, ']', $i + 1);
                if ($classEnd !== null && $classEnd > $i + 1) {
                    $regex .= '[' . self::regexCharClass(substr($pattern, $i + 1, $classEnd - $i - 1)) . ']';
                    $i = $classEnd;
                    continue;
                }
            }

            if ($char === '{') {
                $setEnd = self::findUnescaped($pattern, '}', $i + 1);
                if ($setEnd !== null && $setEnd > $i + 1) {
                    $alternatives = self::splitUnescapedCommas(substr($pattern, $i + 1, $setEnd - $i - 1));
                    if (count($alternatives) > 1) {
                        $regex .= '(?:' . implode('|', array_map(self::globRegexBody(...), $alternatives)) . ')';
                        $i = $setEnd;
                        continue;
                    }
                }
            }

            $regex .= preg_quote($char, '#');
        }

        return $regex;
    }

    private static function findUnescaped(string $pattern, string $needle, int $offset): ?int
    {
        $length = strlen($pattern);
        $escaped = false;
        for ($i = $offset; $i < $length; $i++) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($pattern[$i] === '\\') {
                $escaped = true;
                continue;
            }
            if ($pattern[$i] === $needle) {
                return $i;
            }
        }

        return null;
    }

    private static function regexCharClass(string $class): string
    {
        $regex = '';
        $length = strlen($class);
        for ($i = 0; $i < $length; $i++) {
            $char = $class[$i];
            if ($char === '\\' && $i + 1 < $length) {
                $i++;
                $regex .= '\\' . $class[$i];
                continue;
            }
            if ($char === '\\' || $char === '[' || $char === ']' || $char === '#' || ($char === '^' && $i === 0)) {
                $regex .= '\\' . $char;
                continue;
            }
            $regex .= $char;
        }

        return $regex;
    }

    /**
     * @return list<string>
     */
    private static function splitUnescapedCommas(string $value): array
    {
        $parts = [''];
        $escaped = false;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($escaped) {
                $parts[array_key_last($parts)] .= '\\' . $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === ',') {
                $parts[] = '';
                continue;
            }
            $parts[array_key_last($parts)] .= $char;
        }
        if ($escaped) {
            $parts[array_key_last($parts)] .= '\\';
        }

        return $parts;
    }

    private static function allowsSkippingIgnoredDirs(string $pattern, IgnoreMatchResult $result): bool
    {
        if ($result->isIgnored()) {
            return true;
        }
        if ($pattern === '' || $pattern[0] !== '/') {
            return false;
        }

        $trimmed = str_ends_with($pattern, '/**') ? substr($pattern, 0, -3) : $pattern;
        if ($trimmed === '') {
            return true;
        }
        if (str_contains(substr($trimmed, 1), '/')) {
            return false;
        }

        return !str_contains(str_ends_with($trimmed, '**') ? substr($trimmed, 0, -2) : $trimmed, '**');
    }

    private static function withSkipDir(IgnoreMatchResult $result, bool $canSkipDir): IgnoreMatchResult
    {
        if (!$result->isIgnored()) {
            return $result;
        }

        return IgnoreMatchResult::ignored(
            deletable: $result->isDeletable(),
            caseFolded: $result->isCaseFolded(),
            canSkipDir: $canSkipDir,
        );
    }
}
