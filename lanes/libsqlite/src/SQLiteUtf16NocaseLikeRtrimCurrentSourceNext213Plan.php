<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext213Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameSelfEscapedEscapePlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        string $escapeBytes,
        int|string $escapeEncoding,
        string $currentSource = 'main.wp_options@212',
        string $nextSource = 'main.wp_options@213',
        int $currentSchemaCookie = 212,
        int $nextSchemaCookie = 213,
    ): array {
        $escape = self::decodePreparedText($escapeBytes, $escapeEncoding, 'escape');
        if (self::sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next213 ESCAPE must decode to one SQLite text character');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext212Plan::wordpressOptionNameUnicodeEscapePlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $escapeBytes,
            $escapeEncoding,
            $escapeBytes,
            $escapeEncoding,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentTokens = self::likeTokens($base['currentPattern'], $escape);
        $nextTokens = self::likeTokens($base['nextPattern'], $escape);
        $currentEscapedEscapeOffsets = self::escapedEscapeOffsets($currentTokens, $escape);
        $nextEscapedEscapeOffsets = self::escapedEscapeOffsets($nextTokens, $escape);
        $currentEscapedWildcardOffsets = self::escapedWildcardOffsets($currentTokens);
        $nextEscapedWildcardOffsets = self::escapedWildcardOffsets($nextTokens);
        $currentFirstWildcardOffset = self::firstWildcardOffset($currentTokens);
        $nextFirstWildcardOffset = self::firstWildcardOffset($nextTokens);

        $reasons = $base['invalidationReasons'];
        if ($currentEscapedEscapeOffsets !== $nextEscapedEscapeOffsets) {
            $reasons[] = 'escaped-escape-prefix';
        }
        if ($currentEscapedWildcardOffsets !== $nextEscapedWildcardOffsets) {
            $reasons[] = 'escaped-wildcard-prefix';
        }
        $reasons = array_values(array_unique($reasons));

        return array_replace($base, [
            'status' => 'utf16-nocase-like-rtrim-current-source-next213',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* UTF-16 self-escaped Unicode ESCAPE */',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'selfEscapedEscapeCharacter' => true,
            'escape' => $escape,
            'escapeEncoding' => self::encodingName($escapeEncoding),
            'escapeBytesHex' => bin2hex($escapeBytes),
            'currentTokens' => $currentTokens,
            'nextTokens' => $nextTokens,
            'currentEscapedEscapeOffsets' => $currentEscapedEscapeOffsets,
            'nextEscapedEscapeOffsets' => $nextEscapedEscapeOffsets,
            'currentEscapedWildcardOffsets' => $currentEscapedWildcardOffsets,
            'nextEscapedWildcardOffsets' => $nextEscapedWildcardOffsets,
            'currentFirstWildcardOffset' => $currentFirstWildcardOffset,
            'nextFirstWildcardOffset' => $nextFirstWildcardOffset,
            'currentPrefixCharacters' => self::sqliteTextLength($base['prefix']),
            'nextPrefixCharacters' => self::sqliteTextLength($base['nextPrefix']),
            'currentPrefixContainsEscapeLiteral' => str_contains($base['prefix'], $escape),
            'nextPrefixContainsEscapeLiteral' => str_contains($base['nextPrefix'], $escape),
            'currentPrefixContainsEscapedWildcardLiteral' => str_contains($base['prefix'], '_') || str_contains($base['prefix'], '%'),
            'nextPrefixContainsEscapedWildcardLiteral' => str_contains($base['nextPrefix'], '_') || str_contains($base['nextPrefix'], '%'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustDecodeEscapeBeforeSelfEscapePlanning' => true,
            'mustKeepEscapedEscapeInPrefix' => true,
            'mustKeepEscapedWildcardInPrefix' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-self-escaped-unicode-escape',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next213',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE ESCAPE tokenization, ASCII NOCASE prefix ranges, RTRIM expression keys, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'next213 covers UTF-16 prepared non-ASCII ESCAPE characters that escape themselves before escaped wildcard literals; avoids next212 single Unicode ESCAPE normalization, accepted Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ]);
    }

    /**
     * @return list<array{offset:int,character:string,escaped:bool,kind:string}>
     */
    private static function likeTokens(string $pattern, string $escape): array
    {
        $characters = self::characters($pattern);
        $tokens = [];
        $count = count($characters);
        for ($offset = 0; $offset < $count; $offset++) {
            $character = $characters[$offset];
            if ($character === $escape) {
                $offset++;
                if ($offset >= $count) {
                    $tokens[] = ['offset' => $offset - 1, 'character' => $escape, 'escaped' => false, 'kind' => 'dangling-escape'];
                    break;
                }
                $escaped = $characters[$offset];
                $tokens[] = ['offset' => $offset, 'character' => $escaped, 'escaped' => true, 'kind' => self::tokenKind($escaped, $escape)];
                continue;
            }

            $tokens[] = ['offset' => $offset, 'character' => $character, 'escaped' => false, 'kind' => self::tokenKind($character, $escape)];
        }

        return $tokens;
    }

    private static function tokenKind(string $character, string $escape): string
    {
        if ($character === $escape) {
            return 'escape-literal';
        }
        if ($character === '%' || $character === '_') {
            return 'wildcard';
        }

        return 'literal';
    }

    /** @param list<array{offset:int,character:string,escaped:bool,kind:string}> $tokens @return list<int> */
    private static function escapedEscapeOffsets(array $tokens, string $escape): array
    {
        return self::tokenOffsets($tokens, static fn (array $token): bool => $token['escaped'] && $token['character'] === $escape);
    }

    /** @param list<array{offset:int,character:string,escaped:bool,kind:string}> $tokens @return list<int> */
    private static function escapedWildcardOffsets(array $tokens): array
    {
        return self::tokenOffsets($tokens, static fn (array $token): bool => $token['escaped'] && ($token['character'] === '%' || $token['character'] === '_'));
    }

    /** @param list<array{offset:int,character:string,escaped:bool,kind:string}> $tokens */
    private static function firstWildcardOffset(array $tokens): ?int
    {
        foreach ($tokens as $token) {
            if (!$token['escaped'] && ($token['character'] === '%' || $token['character'] === '_')) {
                return $token['offset'];
            }
        }

        return null;
    }

    /**
     * @param list<array{offset:int,character:string,escaped:bool,kind:string}> $tokens
     * @param callable(array{offset:int,character:string,escaped:bool,kind:string}):bool $filter
     * @return list<int>
     */
    private static function tokenOffsets(array $tokens, callable $filter): array
    {
        $offsets = [];
        foreach ($tokens as $token) {
            if ($filter($token)) {
                $offsets[] = $token['offset'];
            }
        }

        return $offsets;
    }

    private static function decodePreparedText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next213 prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    /** @return list<string> */
    private static function characters(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : str_split($value);
    }

    private static function sqliteTextLength(string $value): int
    {
        return count(self::characters($value));
    }

    private static function encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next213 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function encodingName(int|string $encoding): string
    {
        return match (self::encodingId($encoding)) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
        };
    }
}
