<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitIgnore
{
    public const KIND_EXPENDABLE = 'expendable';
    public const KIND_PRECIOUS = 'precious';
    public const CASE_SENSITIVE = 'sensitive';
    public const CASE_FOLD = 'fold';

    /**
     * @param list<list<array{pattern:GitGlob,kind:string,sequenceNumber:int}>> $patternLists
     */
    private function __construct(private readonly array $patternLists)
    {
    }

    /**
     * @return list<array{pattern:GitGlob,line:int,kind:string}>
     */
    public static function parse(string $contents, bool $supportPrecious = false): array
    {
        $contents = self::stripByteOrderMark($contents);
        $entries = [];
        $lines = preg_split('/\n/', $contents);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $index => $line) {
            if (str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $first = $line[0];
            $second = $line[1] ?? null;
            $kind = self::KIND_EXPENDABLE;
            $canNegate = true;
            if ($supportPrecious && $first === '$') {
                $line = substr($line, 1);
                $kind = self::KIND_PRECIOUS;
                $canNegate = false;
            } else {
                if ($first === '!' && $second === '$') {
                    continue;
                }
                if ($first === '\\' && $second === '$') {
                    $line = substr($line, 1);
                }
            }

            $line = self::truncateNonEscapedTrailingSpaces($line);
            $pattern = $canNegate ? GitGlob::parse($line) : GitGlob::fromBytesWithoutNegation($line);
            if ($pattern === null) {
                continue;
            }

            $entries[] = [
                'pattern' => $pattern,
                'line' => $index + 1,
                'kind' => $kind,
            ];
        }

        return $entries;
    }

    /**
     * @param list<string> $patterns
     */
    public static function fromOverrides(array $patterns, bool $supportPrecious = false): self
    {
        $mappings = [];
        foreach ($patterns as $index => $pattern) {
            $parsed = self::parse($pattern, $supportPrecious);
            if ($parsed === []) {
                continue;
            }

            $first = $parsed[0];
            $mappings[] = [
                'pattern' => $first['pattern'],
                'kind' => $first['kind'],
                'sequenceNumber' => $index + 1,
            ];
        }

        return new self([$mappings]);
    }

    /**
     * @return list<list<array{pattern:GitGlob,kind:string,sequenceNumber:int}>>
     */
    public function patternLists(): array
    {
        return $this->patternLists;
    }

    /**
     * @return array{pattern:GitGlob,source:null,sequenceNumber:int,kind:string}|null
     */
    public function patternMatchingRelativePath(
        string $relativePath,
        ?bool $isDirectory = null,
        string $case = self::CASE_SENSITIVE,
    ): ?array {
        $relativePath = self::normalizeRelativePath($relativePath);
        foreach (array_reverse($this->patternLists) as $list) {
            foreach (array_reverse($list) as $mapping) {
                if (!self::patternMatches($mapping['pattern'], $relativePath, $isDirectory, $case)) {
                    continue;
                }

                return [
                    'pattern' => $mapping['pattern'],
                    'source' => null,
                    'sequenceNumber' => $mapping['sequenceNumber'],
                    'kind' => $mapping['kind'],
                ];
            }
        }

        return null;
    }

    private static function stripByteOrderMark(string $contents): string
    {
        foreach (["\x00\x00\xFE\xFF", "\xFF\xFE\x00\x00", "\xEF\xBB\xBF", "\xFE\xFF", "\xFF\xFE"] as $bom) {
            if (str_starts_with($contents, $bom)) {
                return substr($contents, strlen($bom));
            }
        }

        return $contents;
    }

    private static function truncateNonEscapedTrailingSpaces(string $line): string
    {
        $lastSpacePosition = null;
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            if ($char === ' ') {
                $lastSpacePosition ??= $i;
                continue;
            }
            if ($char === '\\') {
                if ($i + 1 >= $length) {
                    return $line;
                }
                $i++;
            }
            $lastSpacePosition = null;
        }

        return $lastSpacePosition === null ? $line : substr($line, 0, $lastSpacePosition);
    }

    private static function patternMatches(GitGlob $pattern, string $relativePath, ?bool $isDirectory, string $case): bool
    {
        if ($pattern->hasMode(GitGlob::MUST_BE_DIR) && $isDirectory !== true) {
            return false;
        }

        $candidate = $relativePath;
        if ($pattern->hasMode(GitGlob::NO_SUB_DIR) && !$pattern->hasMode(GitGlob::ABSOLUTE)) {
            $slash = strrpos($relativePath, '/');
            $candidate = $slash === false ? $relativePath : substr($relativePath, $slash + 1);
        }

        return GitAttributes::globMatches($pattern->text, $candidate, true, $case === self::CASE_FOLD);
    }

    private static function normalizeRelativePath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Ignore search paths cannot contain NUL bytes');
        }

        $parts = [];
        foreach (explode('/', trim(str_replace('\\', '/', $path), '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    throw new \InvalidArgumentException('Ignore search path leaves the repository');
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
