<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext212Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameUnicodeEscapePlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        string $currentEscapeBytes,
        int|string $currentEscapeEncoding,
        string $nextEscapeBytes,
        int|string $nextEscapeEncoding,
        string $currentSource = 'main.wp_options@211',
        string $nextSource = 'main.wp_options@212',
        int $currentSchemaCookie = 211,
        int $nextSchemaCookie = 212,
    ): array {
        $currentPattern = self::decodePreparedText($currentPatternBytes, $currentPatternEncoding, 'current pattern');
        $nextPattern = self::decodePreparedText($nextPatternBytes, $nextPatternEncoding, 'next pattern');
        $currentEscape = self::decodePreparedText($currentEscapeBytes, $currentEscapeEncoding, 'current escape');
        $nextEscape = self::decodePreparedText($nextEscapeBytes, $nextEscapeEncoding, 'next escape');

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $currentPattern,
            $currentEscape,
            $nextPattern,
            $nextEscape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentAsciiEscape = self::replaceEscapeCharacter($currentPattern, $currentEscape, '!');
        $nextAsciiEscape = self::replaceEscapeCharacter($nextPattern, $nextEscape, '!');
        $currentAscii = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $currentRows,
            $currentRows,
            $currentAsciiEscape,
            '!',
            $currentAsciiEscape,
            '!',
            $currentSource . '#ascii-escape',
            $currentSource . '#ascii-escape',
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $nextAscii = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $nextRows,
            $nextRows,
            $nextAsciiEscape,
            '!',
            $nextAsciiEscape,
            '!',
            $nextSource . '#ascii-escape',
            $nextSource . '#ascii-escape',
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $unicodeEscape = self::sqliteTextLength($currentEscape) === 1
            && self::sqliteTextLength($nextEscape) === 1
            && (!self::isAscii($currentEscape) || !self::isAscii($nextEscape));
        $normalizedCurrentEquivalent = $base['currentMatchedRowids'] === $currentAscii['currentMatchedRowids']
            && $base['currentCandidateRowids'] === $currentAscii['currentCandidateRowids'];
        $normalizedNextEquivalent = $base['nextMatchedRowids'] === $nextAscii['currentMatchedRowids']
            && $base['nextCandidateRowids'] === $nextAscii['currentCandidateRowids'];

        $reasons = $base['invalidationReasons'];
        if ($unicodeEscape) {
            $reasons[] = 'unicode-escape-character';
        }
        if (!$normalizedCurrentEquivalent || !$normalizedNextEquivalent) {
            $reasons[] = 'unicode-escape-normalization-mismatch';
        }
        if ($currentPattern !== $nextPattern) {
            $reasons[] = 'decoded-pattern';
        }
        if ($currentEscape !== $nextEscape) {
            $reasons[] = 'decoded-escape';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next212',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* UTF-16 Unicode ESCAPE */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentPatternEncoding' => self::encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::encodingName($nextPatternEncoding),
            'currentEscapeEncoding' => self::encodingName($currentEscapeEncoding),
            'nextEscapeEncoding' => self::encodingName($nextEscapeEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentEscapeBytesHex' => bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => bin2hex($nextEscapeBytes),
            'unicodeEscapeCharacter' => $unicodeEscape,
            'currentEscapeTextLength' => self::sqliteTextLength($currentEscape),
            'nextEscapeTextLength' => self::sqliteTextLength($nextEscape),
            'currentAsciiEquivalentPattern' => $currentAsciiEscape,
            'nextAsciiEquivalentPattern' => $nextAsciiEscape,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'rangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'rangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentAsciiEquivalentMatchedRowids' => $currentAscii['currentMatchedRowids'],
            'nextAsciiEquivalentMatchedRowids' => $nextAscii['currentMatchedRowids'],
            'currentAsciiEquivalentCandidateRowids' => $currentAscii['currentCandidateRowids'],
            'nextAsciiEquivalentCandidateRowids' => $nextAscii['currentCandidateRowids'],
            'unicodeEscapeNormalizedCurrentEquivalent' => $normalizedCurrentEquivalent,
            'unicodeEscapeNormalizedNextEquivalent' => $normalizedNextEquivalent,
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustNormalizeEscapeBeforePrefixPlanning' => true,
            'mustReprepareForUnicodeEscapeChange' => $currentEscape !== $nextEscape || $currentPattern !== $nextPattern,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-unicode-escape',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next212',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, SQLite LIKE ESCAPE character splitting, ASCII NOCASE prefix planning, RTRIM expression keys, and residual matching',
            'non_overlap' => 'next212 covers UTF-16 prepared non-ASCII single-character ESCAPE normalization before NOCASE/RTRIM LIKE prefix planning; avoids accepted ASCII escape rebind next200, BOM next206, no-prefix next203, ASCII-space RTRIM next209, Unicode GLOB, and malformed UTF-16 insert guards',
        ];
    }

    private static function decodePreparedText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next212 prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    private static function replaceEscapeCharacter(string $pattern, string $escape, string $replacement): string
    {
        return implode('', array_map(
            static fn (string $character): string => $character === $escape ? $replacement : $character,
            self::characters($pattern),
        ));
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

    private static function isAscii(string $value): bool
    {
        return preg_match('/^[\x00-\x7f]*$/', $value) === 1;
    }

    private static function encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next212 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
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
