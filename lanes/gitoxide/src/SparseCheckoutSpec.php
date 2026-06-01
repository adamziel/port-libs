<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SparseCheckoutSpec
{
    public const MODE_CONE = 'cone';
    public const MODE_NON_CONE = 'non-cone';
    public const PATHSPEC_SEARCH_SHELL_GLOB = 'shell-glob';
    public const PATHSPEC_SEARCH_PATH_AWARE_GLOB = 'path-aware-glob';
    public const PATHSPEC_SEARCH_LITERAL = 'literal';

    /**
     * @param list<string> $recursiveDirectories
     * @param list<array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool,literal?:bool,matchSlash?:bool,ignoreCase?:bool,pathspec?:bool,always?:bool,caseSensitivePrefix?:string}> $patterns
     */
    private function __construct(
        public readonly string $mode,
        private readonly array $recursiveDirectories,
        private readonly array $patterns,
        public readonly bool $ignoreCase = false,
        private readonly bool $allPatternsExcludedFallback = false,
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
        foreach (self::lines(self::stripUtf8Bom($contents)) as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $line = self::truncateNonEscapedTrailingSpaces($line);
            if ($line === '' || self::isAsciiWhitespace($line)) {
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
                $line = substr($line, 0, -1);
            }
            $anchored = str_starts_with($line, '/');
            if ($anchored) {
                $line = substr($line, 1);
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
     * Build a sparse matcher from Gitoxide/Git pathspec patterns.
     *
     * This intentionally supports the pathspec subset that can be evaluated from
     * paths alone. Attribute pathspecs are rejected because sparse checkout
     * matching has no attribute provider at this layer. When `$root` is
     * provided, absolute pathspecs are normalized relative to that worktree root.
     *
     * @param list<string> $pathspecs
     */
    public static function fromPathspecs(
        array $pathspecs,
        bool $ignoreCase = false,
        string $prefix = '',
        string $root = '',
        string $defaultSearchMode = self::PATHSPEC_SEARCH_SHELL_GLOB,
        bool $literalDefault = false,
    ): self {
        self::validatePathspecSearchMode($defaultSearchMode);
        $prefix = self::normalizePath($prefix);
        $root = self::normalizeAbsoluteRoot($root);
        if ($pathspecs === []) {
            if ($prefix !== '') {
                return new self(self::MODE_NON_CONE, [], [[
                    'pattern' => $prefix,
                    'negative' => false,
                    'directoryOnly' => true,
                    'anchored' => true,
                    'literal' => true,
                    'ignoreCase' => false,
                    'pathspec' => true,
                    'always' => false,
                    'caseSensitivePrefix' => $prefix,
                ]], $ignoreCase);
            }

            return new self(self::MODE_NON_CONE, [], [[
                'pattern' => '',
                'negative' => false,
                'directoryOnly' => false,
                'anchored' => true,
                'literal' => true,
                'ignoreCase' => $ignoreCase,
                'pathspec' => true,
                'always' => true,
                'caseSensitivePrefix' => $prefix,
            ]], $ignoreCase);
        }

        $patterns = [];
        foreach ($pathspecs as $pathspec) {
            $patterns[] = self::parsePathspec(
                $pathspec,
                $ignoreCase,
                $prefix,
                $root,
                $defaultSearchMode,
                $literalDefault,
            );
        }

        $allExcluded = $patterns !== [] && array_reduce(
            $patterns,
            static fn (bool $carry, array $pattern): bool => $carry && $pattern['negative'],
            true
        );

        $positive = [];
        $negative = [];
        foreach ($patterns as $pattern) {
            if ($pattern['negative']) {
                $negative[] = $pattern;
            } else {
                $positive[] = $pattern;
            }
        }
        $patterns = [...$positive, ...$negative];

        return new self(self::MODE_NON_CONE, [], $patterns, $ignoreCase, $allExcluded);
    }

    /**
     * Build a sparse matcher using Gitoxide/Git pathspec environment defaults.
     *
     * @param list<string> $pathspecs
     * @param array<string, bool|int|string|null> $environment
     */
    public static function fromPathspecsWithEnvironment(
        array $pathspecs,
        array $environment,
        bool $ignoreCase = false,
        string $prefix = '',
        string $root = '',
    ): self {
        $literal = self::pathspecEnvironmentBoolean($environment, 'GIT_LITERAL_PATHSPECS') ?? false;
        $ignoreCase = $ignoreCase || (self::pathspecEnvironmentBoolean($environment, 'GIT_ICASE_PATHSPECS') ?? false);
        if ($literal) {
            return self::fromPathspecs(
                $pathspecs,
                $ignoreCase,
                $prefix,
                $root,
                self::PATHSPEC_SEARCH_LITERAL,
                true,
            );
        }

        $glob = self::pathspecEnvironmentBoolean($environment, 'GIT_GLOB_PATHSPECS');
        $noGlob = self::pathspecEnvironmentBoolean($environment, 'GIT_NOGLOB_PATHSPECS');
        if ($glob === true && $noGlob === true) {
            throw new \InvalidArgumentException('Glob and no-glob pathspec settings are mutually exclusive');
        }

        $defaultSearchMode = self::PATHSPEC_SEARCH_SHELL_GLOB;
        if ($glob === true) {
            $defaultSearchMode = self::PATHSPEC_SEARCH_PATH_AWARE_GLOB;
        }
        if ($noGlob !== null) {
            $defaultSearchMode = self::PATHSPEC_SEARCH_LITERAL;
        }

        return self::fromPathspecs(
            $pathspecs,
            $ignoreCase,
            $prefix,
            $root,
            $defaultSearchMode,
        );
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
        if ($this->ignoreCase && $this->mode !== self::MODE_NON_CONE) {
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
        if ($path === '') {
            return true;
        }

        $included = false;
        $matched = false;
        $matchedNegativePathspecRules = [];
        foreach ($this->patterns as $rule) {
            if (!$this->nonConeRuleMatches($rule, $path, $isDirectory)) {
                continue;
            }
            $matched = true;
            $included = !$rule['negative'];
            if (($rule['pathspec'] ?? false) && $rule['negative']) {
                $matchedNegativePathspecRules[] = $rule;
            }
        }

        if ($matchedNegativePathspecRules !== []) {
            if (
                $isDirectory === true
                && $this->excludedDirectoryCanContainIncludedPaths($path, $matchedNegativePathspecRules)
            ) {
                return true;
            }

            return false;
        }

        if (!$included && $isDirectory === true) {
            foreach ($this->patterns as $rule) {
                if ($rule['negative'] || !($rule['pathspec'] ?? false)) {
                    continue;
                }
                if ($this->pathspecRuleCanMatchDescendant($rule, $path)) {
                    return true;
                }
            }
        }

        if (!$included && !$matched && $this->allPatternsExcludedFallback) {
            return true;
        }

        return $included;
    }

    /**
     * @param list<array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool,literal?:bool,matchSlash?:bool,ignoreCase?:bool,pathspec?:bool,always?:bool,caseSensitivePrefix?:string}> $matchedNegativePathspecRules
     */
    private function excludedDirectoryCanContainIncludedPaths(string $path, array $matchedNegativePathspecRules): bool
    {
        $hasWildcardExclude = false;
        foreach ($matchedNegativePathspecRules as $rule) {
            if (!self::pathspecRuleHasActiveWildcard($rule)) {
                continue;
            }
            $hasWildcardExclude = true;
            break;
        }

        if (!$hasWildcardExclude) {
            return false;
        }

        if ($this->allPatternsExcludedFallback) {
            return true;
        }

        foreach ($this->patterns as $rule) {
            if ($rule['negative'] || !($rule['pathspec'] ?? false)) {
                continue;
            }
            if ($this->pathspecRuleCanMatchDescendant($rule, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool,literal?:bool,matchSlash?:bool,ignoreCase?:bool,pathspec?:bool,always?:bool,caseSensitivePrefix?:string} $rule
     */
    private function nonConeRuleMatches(array $rule, string $path, ?bool $isDirectory): bool
    {
        if ($rule['always'] ?? false) {
            return true;
        }

        if ($rule['directoryOnly'] && $isDirectory !== true && !str_starts_with($path, $rule['pattern'] . '/')) {
            return false;
        }

        $candidates = [$path];
        if (
            !($rule['pathspec'] ?? false)
            || $rule['directoryOnly']
            || ($rule['literal'] ?? false)
            || !self::patternHasWildcard($rule['pattern'])
        ) {
            $candidates = self::pathAndAncestors($path);
        }

        foreach ($candidates as $candidate) {
            if ($this->patternMatchesCandidate($rule, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool,literal?:bool,matchSlash?:bool,ignoreCase?:bool,pathspec?:bool,always?:bool,caseSensitivePrefix?:string} $rule
     */
    private function patternMatchesCandidate(array $rule, string $candidate): bool
    {
        if (!self::candidateMatchesCaseSensitivePrefix($rule, $candidate)) {
            return false;
        }

        $ignoreCase = $rule['ignoreCase'] ?? $this->ignoreCase;
        $pattern = $rule['pattern'];

        if (!$rule['anchored'] && !($rule['pathspec'] ?? false) && !str_contains($pattern, '/')) {
            $candidate = basename($candidate);
        }

        if ($rule['literal'] ?? false) {
            return $ignoreCase ? strtolower($pattern) === strtolower($candidate) : $pattern === $candidate;
        }

        $regex = self::globRegex($pattern, $rule['matchSlash'] ?? false, $ignoreCase);
        if ($regex !== null && preg_match($regex, $candidate) === 1) {
            return true;
        }

        return ($rule['pathspec'] ?? false) && self::verbatimPathspecMatches($pattern, $candidate, $ignoreCase);
    }

    private static function verbatimPathspecMatches(string $pattern, string $candidate, bool $ignoreCase): bool
    {
        $patternLength = strlen($pattern);
        $prefix = substr($candidate, 0, $patternLength);
        $same = $ignoreCase ? strtolower($pattern) === strtolower($prefix) : $pattern === $prefix;
        if (!$same) {
            return false;
        }
        $next = $candidate[$patternLength] ?? null;

        return $next === null || $next === '/';
    }

    /**
     * @param array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool,literal?:bool,matchSlash?:bool,ignoreCase?:bool,pathspec?:bool,always?:bool,caseSensitivePrefix?:string} $rule
     */
    private function pathspecRuleCanMatchDescendant(array $rule, string $path): bool
    {
        if ($path === '' || ($rule['always'] ?? false)) {
            return true;
        }

        $pattern = $rule['pattern'];
        $ignoreCase = $rule['ignoreCase'] ?? $this->ignoreCase;
        $candidate = $ignoreCase ? strtolower($path) : $path;
        $patternForCompare = $ignoreCase ? strtolower($pattern) : $pattern;
        $caseSensitivePrefix = $rule['caseSensitivePrefix'] ?? '';

        if ($caseSensitivePrefix !== '') {
            $overlapsPrefix = $path === $caseSensitivePrefix
                || str_starts_with($caseSensitivePrefix, $path . '/')
                || str_starts_with($path, $caseSensitivePrefix . '/');
            if (!$overlapsPrefix) {
                return false;
            }
        }

        if ($rule['literal'] ?? false) {
            return $patternForCompare === $candidate || str_starts_with($patternForCompare, $candidate . '/');
        }

        if (!self::pathspecRuleHasActiveWildcard($rule)) {
            return self::pathStartsWithDirectoryPrefix($candidate, $patternForCompare)
                || str_starts_with($patternForCompare, $candidate . '/');
        }

        $literalPrefix = self::literalPrefixBeforeWildcard($patternForCompare);
        if ($literalPrefix === '') {
            return true;
        }

        $commonLength = min(strlen($literalPrefix), strlen($candidate));
        if ($commonLength > 0 && substr($literalPrefix, 0, $commonLength) !== substr($candidate, 0, $commonLength)) {
            return false;
        }

        $literalPrefix = self::directoryPrefixForWildcard($literalPrefix);
        if ($literalPrefix === '') {
            return true;
        }

        return self::pathStartsWithDirectoryPrefix($candidate, $literalPrefix)
            || str_starts_with($literalPrefix, $candidate . '/');
    }

    private static function pathStartsWithDirectoryPrefix(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    /**
     * @param array{caseSensitivePrefix?:string} $rule
     */
    private static function candidateMatchesCaseSensitivePrefix(array $rule, string $candidate): bool
    {
        $prefix = $rule['caseSensitivePrefix'] ?? '';
        if ($prefix === '') {
            return true;
        }

        if (!str_starts_with($candidate, $prefix)) {
            return false;
        }

        $next = $candidate[strlen($prefix)] ?? null;

        return $next === null || $next === '/';
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

    private static function globRegex(string $pattern, bool $matchSlash, bool $ignoreCase = false): ?string
    {
        $regex = '';
        $length = strlen($pattern);
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
                $starStart = $i;
                if (($pattern[$i + 1] ?? '') === '*') {
                    while (($pattern[$i + 1] ?? '') === '*') {
                        $i++;
                    }
                    $next = $pattern[$i + 1] ?? null;
                    $wholePathComponent = !$matchSlash
                        && ($starStart === 0 || $pattern[$starStart - 1] === '/')
                        && ($next === null || $next === '/' || ($next === '\\' && ($pattern[$i + 2] ?? null) === '/'));

                    if ($matchSlash) {
                        $regex .= '.*';
                    } elseif ($wholePathComponent && $next === '/') {
                        $regex .= '(?:.*/)?';
                        $i++;
                    } elseif ($wholePathComponent && $next === '\\' && ($pattern[$i + 2] ?? null) === '/') {
                        $regex .= '(?:.*/)?';
                        $i += 2;
                    } elseif ($wholePathComponent) {
                        $regex .= '.*';
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
                    $classRegex = self::characterClassRegex(substr($pattern, $i + 1, $end - $i - 1), $ignoreCase);
                    if ($classRegex === null) {
                        return null;
                    }
                    $regex .= (!$matchSlash ? '(?!/)' : '') . $classRegex;
                    $i = $end;
                    continue;
                }
            }
            $regex .= preg_quote($char, '#');
        }

        return '#^' . $regex . '\z#' . ($ignoreCase ? 'is' : 's');
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

    private static function characterClassRegex(string $class, bool $ignoreCase): ?string
    {
        if ($class === '') {
            return preg_quote('[]', '#');
        }

        $negated = false;
        if ($class[0] === '!' || $class[0] === '^') {
            $negated = true;
            $class = substr($class, 1);
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
                        return null;
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
     * @return array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool,literal:bool,matchSlash:bool,ignoreCase:bool,pathspec:bool,always:bool,caseSensitivePrefix:string}
     */
    private static function parsePathspec(
        string $pathspec,
        bool $defaultIgnoreCase,
        string $prefix,
        string $root,
        string $defaultSearchMode,
        bool $literalDefault,
    ): array
    {
        if ($pathspec === '') {
            throw new \InvalidArgumentException('An empty string is not a valid pathspec');
        }

        $negative = false;
        $anchored = false;
        $ignoreCase = $defaultIgnoreCase;
        $literal = $defaultSearchMode === self::PATHSPEC_SEARCH_LITERAL;
        $matchSlash = $defaultSearchMode !== self::PATHSPEC_SEARCH_PATH_AWARE_GLOB;
        $cursor = 0;
        $explicitSearchMode = null;

        if ($literalDefault) {
            [$pattern, $caseSensitivePrefix] = self::normalizePathspecPath(
                $pathspec,
                $prefix,
                $root,
                false,
                true,
                $ignoreCase,
            );

            return self::pathspecRule(
                $pattern,
                false,
                false,
                false,
                true,
                true,
                $ignoreCase,
                $pattern === '',
                $caseSensitivePrefix,
            );
        }

        if ($pathspec === ':') {
            return self::pathspecRule('', false, false, true, true, $matchSlash, $ignoreCase, true, '');
        }

        if ($pathspec[0] === ':') {
            $cursor = 1;
            $length = strlen($pathspec);
            $unimplementedShortKeywords = "\"#%&'-',;<=>@_`~";
            while ($cursor < $length) {
                $char = $pathspec[$cursor];
                $cursor++;
                if ($char === '/') {
                    $anchored = true;
                    continue;
                }
                if ($char === '!' || $char === '^') {
                    $negative = true;
                    continue;
                }
                if ($char === ':') {
                    break;
                }
                if (str_contains($unimplementedShortKeywords, $char)) {
                    throw new \InvalidArgumentException("Unsupported pathspec short magic: {$char}");
                }
                $cursor--;
                break;
            }

            if (($pathspec[$cursor] ?? '') === '(') {
                $end = strpos($pathspec, ')', $cursor);
                if ($end === false) {
                    throw new \InvalidArgumentException('Missing closing parenthesis in pathspec signature');
                }
                $keywords = substr($pathspec, $cursor + 1, $end - $cursor - 1);
                foreach ($keywords === '' ? [] : explode(',', $keywords) as $keyword) {
                    if ($keyword === 'top') {
                        $anchored = true;
                        continue;
                    }
                    if ($keyword === 'icase') {
                        $ignoreCase = true;
                        continue;
                    }
                    if ($keyword === 'exclude') {
                        $negative = true;
                        continue;
                    }
                    if ($keyword === 'literal') {
                        if ($explicitSearchMode === self::PATHSPEC_SEARCH_PATH_AWARE_GLOB) {
                            throw new \InvalidArgumentException('literal and glob pathspec magic cannot be combined');
                        }
                        $literal = true;
                        $matchSlash = true;
                        $explicitSearchMode = self::PATHSPEC_SEARCH_LITERAL;
                        continue;
                    }
                    if ($keyword === 'glob') {
                        if ($explicitSearchMode === self::PATHSPEC_SEARCH_LITERAL) {
                            throw new \InvalidArgumentException('literal and glob pathspec magic cannot be combined');
                        }
                        $literal = false;
                        $matchSlash = false;
                        $explicitSearchMode = self::PATHSPEC_SEARCH_PATH_AWARE_GLOB;
                        continue;
                    }
                    if ($keyword === 'attr') {
                        continue;
                    }
                    if (str_starts_with($keyword, 'attr:')) {
                        throw new \InvalidArgumentException('Pathspec attributes are not available to sparse checkout matching');
                    }
                    throw new \InvalidArgumentException("Unsupported pathspec magic keyword: {$keyword}");
                }
                $cursor = $end + 1;
            }
        }

        $pattern = substr($pathspec, $cursor);
        $directoryOnly = str_ends_with($pattern, '/');
        if ($directoryOnly) {
            $pattern = substr($pattern, 0, -1);
        }
        [$pattern, $caseSensitivePrefix] = self::normalizePathspecPath(
            $pattern,
            $anchored ? '' : $prefix,
            $root,
            $directoryOnly,
            $literal,
            $ignoreCase,
        );
        $always = $pattern === '';

        return self::pathspecRule(
            $pattern,
            $negative,
            $directoryOnly,
            $anchored,
            $literal,
            $matchSlash,
            $ignoreCase,
            $always,
            $caseSensitivePrefix,
        );
    }

    /**
     * @return array{pattern:string,negative:bool,directoryOnly:bool,anchored:bool,literal:bool,matchSlash:bool,ignoreCase:bool,pathspec:bool,always:bool,caseSensitivePrefix:string}
     */
    private static function pathspecRule(
        string $pattern,
        bool $negative,
        bool $directoryOnly,
        bool $anchored,
        bool $literal,
        bool $matchSlash,
        bool $ignoreCase,
        bool $always,
        string $caseSensitivePrefix,
    ): array {
        return [
            'pattern' => $pattern,
            'negative' => $negative,
            'directoryOnly' => $directoryOnly,
            'anchored' => $anchored,
            'literal' => $literal,
            'matchSlash' => $matchSlash,
            'ignoreCase' => $ignoreCase,
            'pathspec' => true,
            'always' => $always,
            'caseSensitivePrefix' => $caseSensitivePrefix,
        ];
    }

    private static function literalPrefixBeforeWildcard(string $pattern): string
    {
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            if ($pattern[$i] === '*' || $pattern[$i] === '?' || $pattern[$i] === '[' || $pattern[$i] === '\\') {
                return substr($pattern, 0, $i);
            }
        }

        return $pattern;
    }

    private static function directoryPrefixForWildcard(string $literalPrefix): string
    {
        $literalPrefix = rtrim($literalPrefix, '/');
        $slash = strrpos($literalPrefix, '/');

        return $slash === false ? $literalPrefix : substr($literalPrefix, 0, $slash);
    }

    private static function patternHasWildcard(string $pattern): bool
    {
        return strpbrk($pattern, '*?[') !== false || str_contains($pattern, '\\');
    }

    /**
     * @param array{pattern:string,literal?:bool} $rule
     */
    private static function pathspecRuleHasActiveWildcard(array $rule): bool
    {
        if ($rule['literal'] ?? false) {
            return false;
        }

        $pattern = $rule['pattern'];
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            if ($pattern[$i] === '\\') {
                return true;
            }
            if (str_contains('*?[', $pattern[$i])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function normalizePathspecPath(
        string $path,
        string $prefix = '',
        string $root = '',
        bool $directoryOnly = false,
        bool $literal = false,
        bool $ignoreCase = false,
    ): array
    {
        if (str_contains($path, "\0") || str_contains($prefix, "\0")) {
            throw new \InvalidArgumentException('Sparse checkout path cannot contain NUL bytes');
        }

        $absolute = $root !== '' && str_starts_with($path, '/');
        if ($absolute) {
            $path = self::pathRelativeToRoot($path, $root);
            $prefix = '';
        }

        $parts = [];
        foreach (explode('/', $prefix) as $part) {
            if ($part === '') {
                continue;
            }
            $parts[] = ['part' => $part, 'fromPrefix' => true];
        }

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    throw new \InvalidArgumentException("Pathspec leaves the repository: {$path}");
                }
                array_pop($parts);
                continue;
            }
            $parts[] = ['part' => $part, 'fromPrefix' => false];
        }

        $caseSensitivePrefix = [];
        foreach ($parts as $part) {
            if (!$part['fromPrefix']) {
                break;
            }
            $caseSensitivePrefix[] = $part['part'];
        }

        $normalized = implode('/', array_map(static fn (array $part): string => $part['part'], $parts));

        if ($absolute) {
            $caseSensitivePrefix = self::absoluteCaseSensitivePrefix($normalized, $directoryOnly, $literal, $ignoreCase);
        }

        return [$normalized, implode('/', $caseSensitivePrefix)];
    }

    private static function normalizeAbsoluteRoot(string $root): string
    {
        if ($root === '') {
            return '';
        }
        $root = str_replace('\\', '/', $root);
        if (str_contains($root, "\0")) {
            throw new \InvalidArgumentException('Sparse checkout root cannot contain NUL bytes');
        }
        if (!str_starts_with($root, '/')) {
            throw new \InvalidArgumentException("Sparse checkout root must be absolute: {$root}");
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

    private static function pathRelativeToRoot(string $path, string $root): string
    {
        if ($root === '/') {
            return ltrim($path, '/');
        }
        if ($path !== $root && !str_starts_with($path, $root . '/')) {
            throw new \InvalidArgumentException("Absolute pathspec is outside the sparse checkout root: {$path}");
        }

        return ltrim(substr($path, strlen($root)), '/');
    }

    /**
     * @return list<string>
     */
    private static function absoluteCaseSensitivePrefix(
        string $path,
        bool $directoryOnly,
        bool $literal,
        bool $ignoreCase,
    ): array
    {
        if ($path === '') {
            return [];
        }
        if ($directoryOnly) {
            return !$literal && !$ignoreCase && self::patternHasWildcard($path) ? [] : explode('/', $path);
        }

        $directory = self::dirname($path);
        if ($directory === '' || (!$literal && !$ignoreCase && self::patternHasWildcard($directory))) {
            return [];
        }

        return explode('/', $directory);
    }

    private static function validatePathspecSearchMode(string $searchMode): void
    {
        if (!in_array($searchMode, [
            self::PATHSPEC_SEARCH_SHELL_GLOB,
            self::PATHSPEC_SEARCH_PATH_AWARE_GLOB,
            self::PATHSPEC_SEARCH_LITERAL,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported pathspec search mode: {$searchMode}");
        }
    }

    /**
     * @param array<string, bool|int|string|null> $environment
     */
    private static function pathspecEnvironmentBoolean(array $environment, string $name): ?bool
    {
        if (!array_key_exists($name, $environment) || $environment[$name] === null) {
            return null;
        }

        $value = $environment[$name];
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }

        $text = (string) $value;
        $lower = strtolower($text);
        if (in_array($lower, ['yes', 'on', 'true'], true)) {
            return true;
        }
        if ($text === '' || in_array($lower, ['no', 'off', 'false'], true)) {
            return false;
        }
        if (preg_match('/^[+-]?\d+$/', $text) === 1) {
            $isNegative = str_starts_with($text, '-');
            $digits = ltrim($text, '+-');
            $digits = ltrim($digits, '0');
            if ($digits === '') {
                return false;
            }

            $limit = $isNegative ? '9223372036854775808' : '9223372036854775807';
            if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
                throw new \InvalidArgumentException("Invalid boolean value for {$name}: {$text}");
            }

            return preg_match('/^[+-]?0+$/', $text) !== 1;
        }

        throw new \InvalidArgumentException("Invalid boolean value for {$name}: {$text}");
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
        // Git tree paths use "/" separators; a backslash is an ordinary path byte.
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

    private static function stripUtf8Bom(string $contents): string
    {
        return str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents;
    }

    private static function truncateNonEscapedTrailingSpaces(string $line): string
    {
        $lastSpacePosition = null;
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            $byte = $line[$i];
            if ($byte === ' ') {
                $lastSpacePosition ??= $i;
                continue;
            }
            if ($byte === '\\') {
                if ($i + 1 >= $length) {
                    return $line;
                }
                $i++;
            }

            $lastSpacePosition = null;
        }

        return $lastSpacePosition === null ? $line : substr($line, 0, $lastSpacePosition);
    }

    private static function isAsciiWhitespace(string $line): bool
    {
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($line[$i]);
            if ($ord !== 32 && ($ord < 9 || $ord > 13)) {
                return false;
            }
        }

        return true;
    }

    private static function dirname(string $path): string
    {
        $position = strrpos($path, '/');

        return $position === false ? '' : substr($path, 0, $position);
    }
}
