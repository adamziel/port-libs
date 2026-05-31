<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PathspecPattern
{
    public const SEARCH_SHELL_GLOB = 'shell-glob';
    public const SEARCH_PATH_AWARE_GLOB = 'path-aware-glob';
    public const SEARCH_LITERAL = 'literal';

    /**
     * @param list<array{name:string,state:string,value:?string}> $attributes
     */
    public function __construct(
        public readonly string $path,
        public readonly bool $top = false,
        public readonly bool $exclude = false,
        public readonly bool $ignoreCase = false,
        public readonly bool $mustBeDirectory = false,
        public readonly string $searchMode = self::SEARCH_SHELL_GLOB,
        public readonly bool $nil = false,
        public readonly int $sequenceNumber = 0,
        public readonly int $prefixLength = 0,
        public readonly array $attributes = [],
    ) {
        if (!in_array($searchMode, [self::SEARCH_SHELL_GLOB, self::SEARCH_PATH_AWARE_GLOB, self::SEARCH_LITERAL], true)) {
            throw new \InvalidArgumentException("Unsupported pathspec search mode: {$searchMode}");
        }
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Pathspec path cannot contain NUL bytes');
        }
        if ($prefixLength < 0 || $prefixLength > strlen($path)) {
            throw new \InvalidArgumentException('Pathspec prefix length is outside the path bounds');
        }
    }

    public static function parse(
        string $input,
        int $sequenceNumber = 0,
        bool $literalDefault = false,
        string $defaultSearchMode = self::SEARCH_SHELL_GLOB,
        bool $defaultIgnoreCase = false,
    ): self {
        if ($input === '') {
            throw new \InvalidArgumentException('An empty string is not a valid pathspec');
        }
        if (!in_array($defaultSearchMode, [self::SEARCH_SHELL_GLOB, self::SEARCH_PATH_AWARE_GLOB, self::SEARCH_LITERAL], true)) {
            throw new \InvalidArgumentException("Unsupported pathspec search mode: {$defaultSearchMode}");
        }
        if ($literalDefault) {
            return new self(
                $input,
                ignoreCase: $defaultIgnoreCase,
                searchMode: self::SEARCH_LITERAL,
                sequenceNumber: $sequenceNumber,
            );
        }
        if ($input === ':') {
            return new self('', nil: true, sequenceNumber: $sequenceNumber);
        }

        $top = false;
        $exclude = false;
        $ignoreCase = $defaultIgnoreCase;
        $searchMode = self::SEARCH_SHELL_GLOB;
        $explicitSearchMode = null;
        $attributes = [];
        $cursor = 0;

        if ($input[0] === ':') {
            $cursor = 1;
            $length = strlen($input);
            while ($cursor < $length) {
                $char = $input[$cursor];
                $cursor++;
                if ($char === '/') {
                    $top = true;
                    continue;
                }
                if ($char === '!' || $char === '^') {
                    $exclude = true;
                    continue;
                }
                if ($char === ':') {
                    break;
                }
                $cursor--;
                break;
            }

            if (($input[$cursor] ?? '') === '(') {
                $end = strpos($input, ')', $cursor);
                if ($end === false) {
                    throw new \InvalidArgumentException('Missing closing parenthesis in pathspec magic signature');
                }
                $keywords = substr($input, $cursor + 1, $end - $cursor - 1);
                $sawAttributes = false;
                foreach (self::splitUnescaped($keywords, ',') as $keyword) {
                    if ($keyword === '') {
                        if ($keywords === '') {
                            continue;
                        }
                        throw new \InvalidArgumentException('Invalid pathspec keyword: ');
                    }
                    if ($keyword === 'top') {
                        $top = true;
                    } elseif ($keyword === 'icase') {
                        $ignoreCase = true;
                    } elseif ($keyword === 'exclude') {
                        $exclude = true;
                    } elseif ($keyword === 'literal') {
                        if ($explicitSearchMode === self::SEARCH_PATH_AWARE_GLOB) {
                            throw new \InvalidArgumentException("'literal' and 'glob' keywords cannot be used together in the same pathspec");
                        }
                        $explicitSearchMode = self::SEARCH_LITERAL;
                        $searchMode = self::SEARCH_LITERAL;
                    } elseif ($keyword === 'glob') {
                        if ($explicitSearchMode === self::SEARCH_LITERAL) {
                            throw new \InvalidArgumentException("'literal' and 'glob' keywords cannot be used together in the same pathspec");
                        }
                        $explicitSearchMode = self::SEARCH_PATH_AWARE_GLOB;
                        $searchMode = self::SEARCH_PATH_AWARE_GLOB;
                    } elseif ($keyword === 'attr' || str_starts_with($keyword, 'attr:')) {
                        if ($keyword === 'attr') {
                            continue;
                        }
                        if ($sawAttributes) {
                            throw new \InvalidArgumentException('Only one attribute specification is allowed in the same pathspec');
                        }
                        $sawAttributes = true;
                        $attributes = GitAttributes::parseRequirements(substr($keyword, 5));
                    } else {
                        throw new \InvalidArgumentException("Invalid pathspec keyword: {$keyword}");
                    }
                }
                $cursor = $end + 1;
            }
        }

        $path = substr($input, $cursor);
        $mustBeDirectory = str_ends_with($path, '/');
        if ($mustBeDirectory) {
            $path = substr($path, 0, -1);
        }
        if ($explicitSearchMode === null) {
            $searchMode = $defaultSearchMode;
        }

        return new self(
            $path,
            top: $top,
            exclude: $exclude,
            ignoreCase: $ignoreCase,
            mustBeDirectory: $mustBeDirectory,
            searchMode: $searchMode,
            sequenceNumber: $sequenceNumber,
            attributes: $attributes,
        );
    }

    public function withPath(string $path, ?int $prefixLength = null): self
    {
        return new self(
            $path,
            top: $this->top,
            exclude: $this->exclude,
            ignoreCase: $this->ignoreCase,
            mustBeDirectory: $this->mustBeDirectory,
            searchMode: $this->searchMode,
            nil: $this->nil,
            sequenceNumber: $this->sequenceNumber,
            prefixLength: $prefixLength ?? $this->prefixLength,
            attributes: $this->attributes,
        );
    }

    public function alwaysMatches(): bool
    {
        return $this->nil || $this->path === '';
    }

    public function firstWildcardPosition(): ?int
    {
        if ($this->searchMode === self::SEARCH_LITERAL) {
            return null;
        }

        $length = strlen($this->path);
        for ($i = 0; $i < $length; $i++) {
            if (str_contains('*?[\\', $this->path[$i])) {
                return $i;
            }
        }

        return null;
    }

    public function prefixDirectory(): string
    {
        return $this->prefixLength === 0 ? '' : substr($this->path, 0, $this->prefixLength);
    }

    /**
     * @return list<string>
     */
    private static function splitUnescaped(string $input, string $delimiter): array
    {
        $parts = [];
        $current = '';
        $escaped = false;
        $length = strlen($input);
        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }
            if ($char === $delimiter) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return $parts;
    }
}
