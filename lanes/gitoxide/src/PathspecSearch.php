<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PathspecSearch
{
    /** @var list<PathspecPattern> */
    private array $patterns;

    private bool $allPatternsAreExcluded;

    private string $commonPrefix;

    /**
     * @param list<PathspecPattern> $patterns
     */
    private function __construct(array $patterns)
    {
        $this->patterns = $patterns;
        usort(
            $this->patterns,
            static fn (PathspecPattern $a, PathspecPattern $b): int => ((int) $b->exclude <=> (int) $a->exclude)
                ?: ($a->sequenceNumber <=> $b->sequenceNumber),
        );
        $this->allPatternsAreExcluded = $this->patterns !== []
            && array_reduce(
                $this->patterns,
                static fn (bool $carry, PathspecPattern $pattern): bool => $carry && $pattern->exclude,
                true,
            );
        $this->commonPrefix = self::findCommonPrefix($this->patterns);
    }

    /**
     * @param list<string|PathspecPattern> $specs
     */
    public static function fromSpecs(
        array $specs,
        string $prefix = '',
        bool $literalDefault = false,
        string $defaultSearchMode = PathspecPattern::SEARCH_SHELL_GLOB,
        bool $defaultIgnoreCase = false,
        ?string $root = null,
        bool $emptyPatternsMatchPrefix = true,
    ): self
    {
        if (!in_array($defaultSearchMode, [
            PathspecPattern::SEARCH_SHELL_GLOB,
            PathspecPattern::SEARCH_PATH_AWARE_GLOB,
            PathspecPattern::SEARCH_LITERAL,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported pathspec search mode: {$defaultSearchMode}");
        }

        $patterns = [];
        $prefix = self::normalizePath($prefix);
        $root = self::normalizeAbsoluteRoot($root ?? '');
        foreach ($specs as $index => $spec) {
            $pattern = $spec instanceof PathspecPattern
                ? $spec
                : PathspecPattern::parse(
                    $spec,
                    $index,
                    literalDefault: $literalDefault,
                    defaultSearchMode: $defaultSearchMode,
                    defaultIgnoreCase: $defaultIgnoreCase,
                );
            $patterns[] = self::normalizePattern($pattern, $prefix, $root);
        }

        if ($patterns === [] && $prefix !== '' && $emptyPatternsMatchPrefix) {
            $patterns[] = new PathspecPattern(
                $prefix,
                mustBeDirectory: true,
                searchMode: PathspecPattern::SEARCH_LITERAL,
                prefixLength: strlen($prefix),
            );
        }

        return new self($patterns);
    }

    /**
     * @return list<PathspecPattern>
     */
    public function patterns(): array
    {
        return $this->patterns;
    }

    public function commonPrefix(): string
    {
        return $this->commonPrefix;
    }

    public function prefixDirectory(): string
    {
        foreach ($this->patterns as $pattern) {
            if (!$pattern->exclude) {
                return $pattern->prefixDirectory();
            }
        }

        return '';
    }

    public function longestCommonDirectory(): ?string
    {
        $firstNonExcluded = null;
        foreach ($this->patterns as $pattern) {
            if (!$pattern->exclude) {
                $firstNonExcluded = $pattern;
                break;
            }
        }

        if ($firstNonExcluded === null) {
            return null;
        }
        if ($this->commonPrefix === '') {
            return null;
        }

        if ($firstNonExcluded->mustBeDirectory) {
            return $this->commonPrefix;
        }

        $slash = strrpos($this->commonPrefix, '/');
        if ($slash === false) {
            return null;
        }

        return substr($this->commonPrefix, 0, $slash);
    }

    public function match(string $relativePath, ?bool $isDirectory = null, ?GitAttributes $attributes = null): ?PathspecMatch
    {
        $relativePath = self::validateRelativePath($relativePath);
        if ($relativePath === '') {
            return new PathspecMatch(
                new PathspecPattern('', nil: true),
                0,
                PathspecMatch::KIND_ALWAYS,
            );
        }
        if ($this->patterns === []) {
            return new PathspecMatch(
                new PathspecPattern('', nil: true),
                0,
                PathspecMatch::KIND_ALWAYS,
            );
        }

        if ($this->commonPrefix !== '' && !str_starts_with($relativePath, $this->commonPrefix)) {
            return null;
        }

        foreach ($this->patterns as $pattern) {
            $kind = $this->patternMatchKind($pattern, $relativePath, $isDirectory ?? false);
            if ($kind === null) {
                continue;
            }
            if ($pattern->attributes !== []) {
                if ($attributes === null || !$attributes->matchesRequirements($relativePath, $pattern->attributes, $isDirectory ?? false)) {
                    continue;
                }
            }

            return new PathspecMatch($pattern, $pattern->sequenceNumber, $kind);
        }

        if ($this->allPatternsAreExcluded) {
            return new PathspecMatch(
                new PathspecPattern('', nil: true, sequenceNumber: count($this->patterns)),
                count($this->patterns),
                PathspecMatch::KIND_ALWAYS,
            );
        }

        return null;
    }

    public function isIncluded(string $relativePath, ?bool $isDirectory = null, ?GitAttributes $attributes = null): bool
    {
        $match = $this->match($relativePath, $isDirectory, $attributes);

        return $match !== null && !$match->isExcluded();
    }

    public function canMatch(string $relativePath, ?bool $isDirectory = null): bool
    {
        $relativePath = self::validateRelativePath($relativePath);
        if ($this->patterns === [] || $relativePath === '') {
            return true;
        }
        if ($this->commonPrefix !== '' && !self::pathPrefixesOverlap($relativePath, $this->commonPrefix)) {
            return false;
        }

        foreach ($this->patterns as $pattern) {
            if ($pattern->firstWildcardPosition() === 0 && !$pattern->exclude) {
                return true;
            }
            $couldMatch = $pattern->alwaysMatches()
                || $this->patternCouldMatchPath($pattern, $relativePath, $isDirectory);
            if ($couldMatch && (!$pattern->exclude || $pattern->alwaysMatches())) {
                return !$pattern->exclude;
            }
        }

        return $this->allPatternsAreExcluded;
    }

    public function directoryMatchesPrefix(string $relativePath, bool $leading = false): bool
    {
        $relativePath = self::validateRelativePath($relativePath);
        if ($this->patterns === [] || $relativePath === '') {
            return true;
        }
        if ($this->commonPrefix !== '' && !self::pathPrefixesOverlap($relativePath, $this->commonPrefix)) {
            return false;
        }
        foreach ($this->patterns as $pattern) {
            if ($pattern->exclude && $pattern->firstWildcardPosition() !== null) {
                return true;
            }
            $isMatch = $pattern->alwaysMatches();
            $firstWildcard = $pattern->firstWildcardPosition();
            if ($firstWildcard === 0) {
                $isMatch = true;
            }
            if (!$isMatch) {
                $rightmost = $firstWildcard === null
                    ? strlen($pattern->path)
                    : strrpos(substr($pattern->path, 0, $firstWildcard), '/');
                if ($rightmost === false || $rightmost === null) {
                    $rightmost = $firstWildcard ?? strlen($pattern->path);
                }
                if ($leading && $rightmost > strlen($relativePath)) {
                    $before = strrpos(substr($pattern->path, 0, strlen($relativePath)), '/');
                    $after = strpos($pattern->path, '/', strlen($relativePath));
                    if ($before !== false) {
                        $rightmost = $before;
                    } elseif ($after !== false) {
                        $rightmost = $after;
                    }
                }
                $patternPrefix = substr($pattern->path, 0, $rightmost);
                $isMatch = $this->samePath($pattern, $patternPrefix, substr($relativePath, 0, strlen($patternPrefix)));
            }
            if ($isMatch && (!$pattern->exclude || $pattern->alwaysMatches())) {
                return !$pattern->exclude;
            }
        }

        return $this->allPatternsAreExcluded;
    }

    private static function normalizePattern(PathspecPattern $pattern, string $prefix, string $root): PathspecPattern
    {
        if ($pattern->nil || $pattern->path === '') {
            if ($prefix !== '' && !$pattern->top) {
                return $pattern->withPath($prefix, strlen($prefix));
            }

            return $pattern;
        }

        if ($root !== '' && str_starts_with($pattern->path, '/')) {
            [$path, $prefixLength] = self::normalizeAbsolutePatternPath(
                $pattern->path,
                $root,
                $pattern->mustBeDirectory,
            );

            return $pattern->withPath($path, $prefixLength);
        }

        if ($pattern->top || $prefix === '') {
            return $pattern->withPath(self::normalizePatternPath($pattern->path), 0);
        }

        [$path, $prefixLength] = self::normalizePrefixedPatternPath($prefix, $pattern->path);

        return $pattern->withPath($path, $prefixLength);
    }

    private function patternMatchKind(PathspecPattern $pattern, string $relativePath, bool $isDirectory): ?string
    {
        if ($pattern->alwaysMatches()) {
            return PathspecMatch::KIND_ALWAYS;
        }
        if (!$this->caseSensitivePrefixMatches($pattern, $relativePath, $isDirectory)) {
            return null;
        }

        if ($pattern->searchMode !== PathspecPattern::SEARCH_LITERAL && $pattern->firstWildcardPosition() !== null) {
            if ($this->globMatches($pattern, $relativePath, $isDirectory)) {
                return PathspecMatch::KIND_WILDCARD;
            }
        }

        return $this->verbatimMatchKind($pattern, $relativePath, $isDirectory);
    }

    private function caseSensitivePrefixMatches(PathspecPattern $pattern, string $relativePath, bool $isDirectory): bool
    {
        if (!$pattern->ignoreCase || $pattern->prefixLength === 0) {
            return true;
        }

        $prefix = $pattern->prefixDirectory();
        $next = $relativePath[$pattern->prefixLength] ?? null;
        if (($next === null && !$isDirectory) || ($next !== null && $next !== '/')) {
            return false;
        }

        return substr($relativePath, 0, $pattern->prefixLength) === $prefix;
    }

    private function verbatimMatchKind(PathspecPattern $pattern, string $relativePath, bool $isDirectory): ?string
    {
        $patternLength = strlen($pattern->path);
        $matchIsAllowed = strlen($relativePath) === $patternLength;
        $relativePathHasSlashAtPatternLength = false;
        if (!$matchIsAllowed && ($relativePath[$patternLength] ?? '') === '/') {
            $matchIsAllowed = true;
            $relativePathHasSlashAtPatternLength = true;
        }
        if (!$matchIsAllowed) {
            return null;
        }
        $patternRequirementIsMet = !$pattern->mustBeDirectory || $relativePathHasSlashAtPatternLength || $isDirectory;
        if (!$patternRequirementIsMet) {
            return null;
        }
        if (!$this->samePath($pattern, $pattern->path, substr($relativePath, 0, $patternLength))) {
            return null;
        }

        return $relativePathHasSlashAtPatternLength ? PathspecMatch::KIND_PREFIX : PathspecMatch::KIND_VERBATIM;
    }

    private function globMatches(PathspecPattern $pattern, string $relativePath, bool $isDirectory): bool
    {
        if ($pattern->mustBeDirectory && !$isDirectory) {
            return false;
        }
        $regex = self::globRegex(
            $pattern->path,
            $pattern->searchMode === PathspecPattern::SEARCH_PATH_AWARE_GLOB,
            $pattern->ignoreCase,
        );
        $modifiers = 's' . ($pattern->ignoreCase ? 'i' : '');

        return preg_match('#^' . $regex . '$#' . $modifiers, $relativePath) === 1;
    }

    private function patternCouldMatchPath(PathspecPattern $pattern, string $relativePath, ?bool $isDirectory): bool
    {
        $firstWildcard = $pattern->firstWildcardPosition();
        if ($firstWildcard === 0) {
            return true;
        }

        $maxPatternLength = $firstWildcard ?? strlen($pattern->path);
        $commonLength = min($maxPatternLength, strlen($relativePath));
        if ($commonLength === 0) {
            return false;
        }
        $patternPrefix = substr($pattern->path, 0, $commonLength);
        $relativePrefix = substr($relativePath, 0, $commonLength);
        if (!$this->samePath($pattern, $patternPrefix, $relativePrefix)) {
            return false;
        }

        if ($commonLength < $maxPatternLength) {
            if ($pattern->mustBeDirectory && $isDirectory === false) {
                return false;
            }

            return ($pattern->path[$commonLength] ?? '') === '/';
        }

        if (strlen($relativePath) > $maxPatternLength && $firstWildcard === null) {
            return ($relativePath[$commonLength] ?? '') === '/';
        }

        if ($pattern->mustBeDirectory && $isDirectory !== null) {
            if ($isDirectory) {
                return !isset($pattern->path[$commonLength]) || $pattern->path[$commonLength] === '/';
            }

            return ($relativePath[$commonLength] ?? '') === '/';
        }

        return true;
    }

    private function samePath(PathspecPattern $pattern, string $left, string $right): bool
    {
        return $pattern->ignoreCase ? strcasecmp($left, $right) === 0 : $left === $right;
    }

    private static function globRegex(string $pattern, bool $pathAware, bool $ignoreCase = false): string
    {
        $regex = '';
        $length = strlen($pattern);
        $matchSlash = !$pathAware;
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $regex .= preg_quote($pattern[++$i], '#');
                } else {
                    $regex .= preg_quote($char, '#');
                }
                continue;
            }
            if ($char === '*') {
                if (($pattern[$i + 1] ?? '') === '*') {
                    if ($matchSlash) {
                        while (($pattern[$i + 1] ?? '') === '*') {
                            $i++;
                        }
                        if (($pattern[$i + 1] ?? '') === '/') {
                            $regex .= '(?:.*/)?';
                            $i++;
                        } else {
                            $regex .= '.*';
                        }
                        continue;
                    }

                    $starStart = $i;
                    while (($pattern[$i + 1] ?? '') === '*') {
                        $i++;
                    }
                    $next = $pattern[$i + 1] ?? null;
                    $nextIsSlash = $next === '/';
                    $nextIsEscapedSlash = $next === '\\' && ($pattern[$i + 2] ?? null) === '/';
                    $atComponentBoundary = $starStart === 0 || ($pattern[$starStart - 1] ?? null) === '/';
                    if ($atComponentBoundary && ($next === null || $nextIsSlash || $nextIsEscapedSlash)) {
                        if ($next === null) {
                            $regex .= '.*';
                        } else {
                            $regex .= '(?:.*/)?';
                        }
                        if ($nextIsSlash) {
                            $i++;
                        } elseif ($nextIsEscapedSlash) {
                            $i += 2;
                        }
                    } else {
                        $regex .= '[^/]*';
                    }
                } else {
                    $regex .= $matchSlash ? '.*' : '[^/]*';
                }
                continue;
            }
            if ($char === '?') {
                $regex .= $matchSlash ? '.' : '[^/]';
                continue;
            }
            if ($char === '[') {
                $end = self::findCharacterClassEnd($pattern, $i);
                if ($end !== null) {
                    $regex .= (!$matchSlash ? '(?!/)' : '')
                        . self::characterClassRegex(substr($pattern, $i + 1, $end - $i - 1), $ignoreCase);
                    $i = $end;
                    continue;
                }
                $regex .= '(?!)';
                continue;
            }
            $regex .= preg_quote($char, '#');
        }

        return $regex;
    }

    private static function findCharacterClassEnd(string $pattern, int $start): ?int
    {
        $length = strlen($pattern);
        $cursor = $start + 1;
        if ($cursor >= $length) {
            return null;
        }
        if (($pattern[$cursor] ?? '') === '!' || ($pattern[$cursor] ?? '') === '^') {
            $cursor++;
        }
        if (($pattern[$cursor] ?? '') === ']') {
            $cursor++;
        }

        for (; $cursor < $length; $cursor++) {
            $char = $pattern[$cursor];
            if ($char === '\\') {
                $cursor++;
                continue;
            }
            if ($char === '[' && ($pattern[$cursor + 1] ?? '') === ':') {
                $classEnd = strpos($pattern, ':]', $cursor + 2);
                if ($classEnd !== false) {
                    $cursor = $classEnd + 1;
                    continue;
                }
            }
            if ($char === ']') {
                return $cursor;
            }
        }

        return null;
    }

    private static function characterClassRegex(string $class, bool $ignoreCase): string
    {
        if ($class === '') {
            return preg_quote('[]', '#');
        }

        $negated = false;
        if ($class[0] === '!' || $class[0] === '^') {
            $negated = true;
            $class = substr($class, 1);
        }
        if (str_starts_with($class, '[:') && strpos($class, ':]', 2) === false) {
            return '(?!)';
        }

        $body = '';
        $previousRangeByte = null;
        $length = strlen($class);
        for ($i = 0; $i < $length; $i++) {
            $char = $class[$i];
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $char = $class[++$i];
                    $body .= self::escapeCharacterClassByte($char);
                    $previousRangeByte = $char;
                } else {
                    $body .= '\\\\';
                    $previousRangeByte = '\\';
                }
                continue;
            }
            if ($char === '-' && $previousRangeByte !== null && $i + 1 < $length && ($class[$i + 1] ?? '') !== ']') {
                $rangeEnd = $class[++$i];
                if ($rangeEnd === '\\') {
                    if ($i + 1 >= $length) {
                        $previousRangeByte = null;
                        continue;
                    }
                    $rangeEnd = $class[++$i];
                }
                $body .= self::characterClassRangeTail($previousRangeByte, $rangeEnd, $ignoreCase);
                $previousRangeByte = null;
                continue;
            }
            if ($char === '[' && ($class[$i + 1] ?? '') === ':') {
                $end = strpos($class, ':]', $i + 2);
                if ($end !== false) {
                    $name = substr($class, $i + 2, $end - $i - 2);
                    $mapped = self::posixCharacterClassRegex($name);
                    if ($mapped === null) {
                        return '(?!)';
                    }
                    $body .= $mapped;
                    $i = $end + 1;
                    $previousRangeByte = null;
                    continue;
                }
            }
            $body .= self::escapeCharacterClassByte($char);
            $previousRangeByte = $char;
        }

        if ($body === '') {
            return preg_quote('[]', '#');
        }

        return '[' . ($negated ? '^' : '') . $body . ']';
    }

    private static function characterClassRangeTail(string $start, string $end, bool $ignoreCase): string
    {
        if ($ignoreCase && self::isAsciiAlpha($start) && self::isAsciiAlpha($end)) {
            $lowerStart = strtolower($start);
            $lowerEnd = strtolower($end);
            $rangeStart = min($lowerStart, $lowerEnd);
            $rangeEnd = max($lowerStart, $lowerEnd);

            return self::escapeCharacterClassByte($rangeStart)
                . '-'
                . self::escapeCharacterClassByte($rangeEnd);
        }

        if (ord($start) <= ord($end)) {
            return '-' . self::escapeCharacterClassByte($end);
        }

        return '';
    }

    private static function isAsciiAlpha(string $char): bool
    {
        $ord = ord($char);

        return ($ord >= 65 && $ord <= 90) || ($ord >= 97 && $ord <= 122);
    }

    private static function escapeCharacterClassByte(string $char): string
    {
        return match ($char) {
            '\\' => '\\\\',
            ']' => '\\]',
            '#' => '\\#',
            default => $char,
        };
    }

    private static function posixCharacterClassRegex(string $class): ?string
    {
        return match ($class) {
            'alnum' => 'A-Za-z0-9',
            'alpha' => 'A-Za-z',
            'blank' => '\\x09-\\x0d ',
            'cntrl' => '\\x00-\\x1f\\x7f',
            'digit' => '0-9',
            'graph' => '\\x21-\\x7e',
            'lower' => 'a-z',
            'print' => '\\x20-\\x7e',
            'punct' => '\\x21-\\x2f\\x3a-\\x40\\x5b-\\x60\\x7b-\\x7e',
            'space' => ' ',
            'upper' => 'A-Z',
            'xdigit' => 'A-Fa-f0-9',
            default => null,
        };
    }

    /**
     * @param list<PathspecPattern> $patterns
     */
    private static function findCommonPrefix(array $patterns): string
    {
        $includePatterns = array_values(array_filter(
            $patterns,
            static fn (PathspecPattern $pattern): bool => !$pattern->exclude,
        ));
        if ($includePatterns === []) {
            return '';
        }

        $prefixes = [];
        foreach ($includePatterns as $pattern) {
            if ($pattern->ignoreCase) {
                $prefixes[] = substr($pattern->path, 0, $pattern->prefixLength);
            } else {
                $firstWildcard = $pattern->firstWildcardPosition();
                $prefixes[] = substr($pattern->path, 0, $firstWildcard ?? strlen($pattern->path));
            }
        }

        $common = array_shift($prefixes) ?? '';
        foreach ($prefixes as $prefix) {
            $limit = min(strlen($common), strlen($prefix));
            $index = 0;
            while ($index < $limit && $common[$index] === $prefix[$index]) {
                $index++;
            }
            $common = substr($common, 0, $index);
        }

        return $common;
    }

    private static function pathPrefixesOverlap(string $path, string $prefix): bool
    {
        if ($prefix === '') {
            return true;
        }

        return str_starts_with($path, $prefix)
            || str_starts_with($prefix, $path)
            || str_starts_with($path, rtrim($prefix, '/') . '/')
            || str_starts_with($prefix, rtrim($path, '/') . '/');
    }

    private static function normalizePath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Pathspec relative path cannot contain NUL bytes');
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

    private static function normalizeAbsoluteRoot(string $root): string
    {
        if ($root === '') {
            return '';
        }
        if (str_contains($root, "\0")) {
            throw new \InvalidArgumentException('Pathspec root cannot contain NUL bytes');
        }
        if (!str_starts_with($root, '/')) {
            throw new \InvalidArgumentException("Pathspec root must be absolute: {$root}");
        }

        $parts = [];
        foreach (explode('/', trim($root, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return '/' . implode('/', $parts);
    }

    private static function validateRelativePath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Pathspec relative path cannot contain NUL bytes');
        }

        return $path;
    }

    private static function normalizePatternPath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Pathspec relative path cannot contain NUL bytes');
        }

        $parts = [];
        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    throw new \InvalidArgumentException('Pathspec path leaves the repository');
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /**
     * @return array{string,int}
     */
    private static function normalizeAbsolutePatternPath(string $path, string $root, bool $mustBeDirectory): array
    {
        $relative = self::pathRelativeToRoot($path, $root);
        $normalized = self::normalizePatternPath($relative);
        if ($normalized === '') {
            return ['', 0];
        }

        $parts = explode('/', $normalized);
        $prefixComponentCount = max(0, count($parts) - ($mustBeDirectory ? 0 : 1));
        $prefix = implode('/', array_slice($parts, 0, $prefixComponentCount));

        return [$normalized, strlen($prefix)];
    }

    private static function pathRelativeToRoot(string $path, string $root): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Pathspec path cannot contain NUL bytes');
        }
        if ($root === '/') {
            return ltrim($path, '/');
        }
        if ($path !== $root && !str_starts_with($path, $root . '/')) {
            throw new \InvalidArgumentException("Absolute pathspec is outside the pathspec root: {$path}");
        }

        return ltrim(substr($path, strlen($root)), '/');
    }

    /**
     * @return array{string,int}
     */
    private static function normalizePrefixedPatternPath(string $prefix, string $path): array
    {
        $segments = [];
        foreach (explode('/', $prefix) as $part) {
            if ($part === '') {
                continue;
            }
            $segments[] = [$part, true];
        }

        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Pathspec relative path cannot contain NUL bytes');
        }

        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($segments === []) {
                    throw new \InvalidArgumentException('Pathspec path leaves the repository');
                }
                array_pop($segments);
                continue;
            }
            $segments[] = [$part, false];
        }

        $parts = array_map(static fn (array $segment): string => $segment[0], $segments);
        $prefixParts = [];
        foreach ($segments as [$part, $fromPrefix]) {
            if (!$fromPrefix) {
                break;
            }
            $prefixParts[] = $part;
        }

        return [implode('/', $parts), strlen(implode('/', $prefixParts))];
    }

}
