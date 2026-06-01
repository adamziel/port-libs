<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PathspecMatcher
{
    private const SEARCH_SHELL_GLOB = 'shell-glob';
    private const SEARCH_PATH_GLOB = 'path-aware-glob';
    private const SEARCH_LITERAL = 'literal';

    /**
     * @param list<array{path:string,exclude:bool,icase:bool,mustBeDirectory:bool,searchMode:string,nil:bool,attributes:list<array{name:string,state:string,value:?string}>,sequence:int}> $patterns
     */
    private function __construct(private readonly array $patterns)
    {
    }

    /**
     * @param list<string> $specs
     */
    public static function fromSpecs(array $specs): self
    {
        $patterns = [];
        foreach ($specs as $sequence => $spec) {
            $patterns[] = self::parse($spec, $sequence);
        }

        usort(
            $patterns,
            static fn (array $left, array $right): int => (int) $right['exclude'] <=> (int) $left['exclude']
                ?: $left['sequence'] <=> $right['sequence'],
        );

        return new self($patterns);
    }

    public static function matchesOne(
        string $spec,
        string $path,
        ?bool $isDirectory = null,
        ?GitAttributes $attributes = null,
    ): bool {
        return self::fromSpecs([$spec])->matches($path, $isDirectory, $attributes);
    }

    public function matches(string $path, ?bool $isDirectory = null, ?GitAttributes $attributes = null): bool
    {
        if ($this->patterns === []) {
            return true;
        }

        $path = self::normalizePath($path);
        $allExcluded = true;
        foreach ($this->patterns as $pattern) {
            $allExcluded = $allExcluded && $pattern['exclude'];
            if (!self::patternMatches($pattern, $path, $isDirectory)) {
                continue;
            }
            if ($pattern['attributes'] !== []) {
                if ($attributes === null || !$attributes->matchesRequirements($path, $pattern['attributes'], $isDirectory)) {
                    continue;
                }
            }

            return !$pattern['exclude'];
        }

        return $allExcluded;
    }

    /**
     * @return list<string>
     */
    public function matchingPaths(array $paths, ?GitAttributes $attributes = null): array
    {
        $matches = [];
        foreach ($paths as $path => $isDirectory) {
            if (is_int($path)) {
                $path = (string) $isDirectory;
                $isDirectory = null;
            }
            if ($this->matches((string) $path, is_bool($isDirectory) ? $isDirectory : null, $attributes)) {
                $matches[] = (string) $path;
            }
        }

        sort($matches, SORT_STRING);

        return $matches;
    }

    /**
     * @return array{path:string,exclude:bool,icase:bool,mustBeDirectory:bool,searchMode:string,nil:bool,attributes:list<array{name:string,state:string,value:?string}>,sequence:int}
     */
    private static function parse(string $input, int $sequence): array
    {
        if ($input === '') {
            throw new \InvalidArgumentException('An empty string is not a valid pathspec');
        }

        $pattern = [
            'path' => '',
            'exclude' => false,
            'icase' => false,
            'mustBeDirectory' => false,
            'searchMode' => self::SEARCH_SHELL_GLOB,
            'nil' => false,
            'attributes' => [],
            'sequence' => $sequence,
        ];

        if ($input === ':') {
            $pattern['nil'] = true;

            return $pattern;
        }

        $cursor = 0;
        if ($input[0] === ':') {
            $cursor = 1;
            self::parseShortMagic($input, $cursor, $pattern);
            if (($input[$cursor] ?? '') === '(') {
                self::parseLongMagic($input, $cursor, $pattern);
            }
        }

        $path = substr($input, $cursor);
        if (str_ends_with($path, '/')) {
            $pattern['mustBeDirectory'] = true;
            $path = substr($path, 0, -1);
        }
        $pattern['path'] = self::normalizePath($path, false);

        return $pattern;
    }

    /**
     * @param array{path:string,exclude:bool,icase:bool,mustBeDirectory:bool,searchMode:string,nil:bool,attributes:list<array{name:string,state:string,value:?string}>,sequence:int} $pattern
     */
    private static function parseShortMagic(string $input, int &$cursor, array &$pattern): void
    {
        $unimplemented = "\"#%&'-,;<=>@_`~";
        $length = strlen($input);
        while ($cursor < $length) {
            $char = $input[$cursor++];
            if ($char === '/') {
                continue;
            }
            if ($char === '!' || $char === '^') {
                $pattern['exclude'] = true;
                continue;
            }
            if ($char === ':') {
                return;
            }
            if (str_contains($unimplemented, $char)) {
                throw new \InvalidArgumentException("Unimplemented short keyword: {$char}");
            }
            $cursor--;

            return;
        }
    }

    /**
     * @param array{path:string,exclude:bool,icase:bool,mustBeDirectory:bool,searchMode:string,nil:bool,attributes:list<array{name:string,state:string,value:?string}>,sequence:int} $pattern
     */
    private static function parseLongMagic(string $input, int &$cursor, array &$pattern): void
    {
        $end = strpos($input, ')', $cursor);
        if ($end === false) {
            throw new \InvalidArgumentException("Missing ')' at the end of pathspec signature");
        }

        $keywords = substr($input, $cursor + 1, $end - $cursor - 1);
        $cursor = $end + 1;
        if ($keywords === '') {
            return;
        }

        $sawAttributes = false;
        foreach (self::splitOnUnescapedComma($keywords) as $keyword) {
            if ($keyword === 'attr') {
                continue;
            }
            if ($keyword === 'top') {
                continue;
            }
            if ($keyword === 'icase') {
                $pattern['icase'] = true;
                continue;
            }
            if ($keyword === 'exclude') {
                $pattern['exclude'] = true;
                continue;
            }
            if ($keyword === 'literal') {
                if ($pattern['searchMode'] === self::SEARCH_PATH_GLOB) {
                    throw new \InvalidArgumentException("'literal' and 'glob' keywords cannot be used together in the same pathspec");
                }
                $pattern['searchMode'] = self::SEARCH_LITERAL;
                continue;
            }
            if ($keyword === 'glob') {
                if ($pattern['searchMode'] === self::SEARCH_LITERAL) {
                    throw new \InvalidArgumentException("'literal' and 'glob' keywords cannot be used together in the same pathspec");
                }
                $pattern['searchMode'] = self::SEARCH_PATH_GLOB;
                continue;
            }
            if (str_starts_with($keyword, 'attr:')) {
                if ($sawAttributes) {
                    throw new \InvalidArgumentException('Only one attribute specification is allowed in the same pathspec');
                }
                $sawAttributes = true;
                $pattern['attributes'] = GitAttributes::parseRequirements(substr($keyword, 5));
                continue;
            }

            throw new \InvalidArgumentException("Found {$keyword} in signature, which is not a valid keyword");
        }
    }

    /**
     * @return list<string>
     */
    private static function splitOnUnescapedComma(string $input): array
    {
        $parts = [];
        $start = 0;
        $length = strlen($input);
        for ($i = 0; $i < $length; $i++) {
            if ($input[$i] !== ',') {
                continue;
            }
            if ($i > 0 && $input[$i - 1] === '\\') {
                continue;
            }
            $parts[] = substr($input, $start, $i - $start);
            $start = $i + 1;
        }
        $parts[] = substr($input, $start);

        return $parts;
    }

    /**
     * @param array{path:string,exclude:bool,icase:bool,mustBeDirectory:bool,searchMode:string,nil:bool,attributes:list<array{name:string,state:string,value:?string}>,sequence:int} $pattern
     */
    private static function patternMatches(array $pattern, string $path, ?bool $isDirectory): bool
    {
        if ($pattern['nil'] || $pattern['path'] === '') {
            return true;
        }
        if ($pattern['mustBeDirectory'] && $isDirectory === false && !str_starts_with($path, $pattern['path'] . '/')) {
            return false;
        }

        if ($pattern['searchMode'] === self::SEARCH_LITERAL || !self::hasWildcard($pattern['path'])) {
            return self::verbatimMatches($pattern, $path, $isDirectory);
        }

        $pathAware = $pattern['searchMode'] === self::SEARCH_PATH_GLOB;
        if (GitAttributes::globMatches($pattern['path'], $path, $pathAware, $pattern['icase'])) {
            return true;
        }

        return self::verbatimMatches($pattern, $path, $isDirectory);
    }

    /**
     * @param array{path:string,exclude:bool,icase:bool,mustBeDirectory:bool,searchMode:string,nil:bool,attributes:list<array{name:string,state:string,value:?string}>,sequence:int} $pattern
     */
    private static function verbatimMatches(array $pattern, string $path, ?bool $isDirectory): bool
    {
        $spec = $pattern['path'];
        $patternLength = strlen($spec);
        $prefix = substr($path, 0, $patternLength);
        $same = $pattern['icase'] ? strcasecmp($prefix, $spec) === 0 : $prefix === $spec;
        if (!$same) {
            return false;
        }
        $next = $path[$patternLength] ?? null;
        $matchIsAllowed = $next === null || $next === '/';
        if (!$matchIsAllowed) {
            return false;
        }
        if (!$pattern['mustBeDirectory']) {
            return true;
        }

        return $next === '/' || $isDirectory === true;
    }

    private static function hasWildcard(string $pattern): bool
    {
        return strpbrk($pattern, '*?[\\') !== false;
    }

    private static function normalizePath(string $path, bool $trimLeadingSlash = true): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Pathspec paths cannot contain NUL bytes');
        }

        $parts = [];
        foreach (explode('/', $trimLeadingSlash ? trim($path, '/') : rtrim($path, '/')) as $part) {
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
}
