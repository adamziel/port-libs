<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext221Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePreparedByteSignaturePlan(
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
        string $currentSource = 'main.wp_options@220',
        string $nextSource = 'main.wp_options@221',
        int $currentSchemaCookie = 220,
        int $nextSchemaCookie = 221,
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

        $currentSignature = self::preparedSignature($currentPatternBytes, $currentPatternEncoding, $currentEscapeBytes, $currentEscapeEncoding);
        $nextSignature = self::preparedSignature($nextPatternBytes, $nextPatternEncoding, $nextEscapeBytes, $nextEscapeEncoding);
        $sameDecodedSql = $currentPattern === $nextPattern && $currentEscape === $nextEscape;
        $samePreparedBytes = $currentSignature === $nextSignature;

        $stableSourceReasons = array_values(array_diff($base['invalidationReasons'], ['source-name', 'schema-cookie']));
        $reasons = $base['invalidationReasons'];
        if (!$samePreparedBytes) {
            $reasons[] = 'prepared-byte-signature';
        }
        if ($sameDecodedSql && !$samePreparedBytes) {
            $reasons[] = 'decoded-sql-byte-signature';
        }
        if ($currentPatternEncoding !== $nextPatternEncoding || $currentEscapeEncoding !== $nextEscapeEncoding) {
            $reasons[] = 'prepared-encoding';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next221',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 byte signature */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'sameDecodedSql' => $sameDecodedSql,
            'samePreparedBytes' => $samePreparedBytes,
            'currentPatternEncoding' => self::encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::encodingName($nextPatternEncoding),
            'currentEscapeEncoding' => self::encodingName($currentEscapeEncoding),
            'nextEscapeEncoding' => self::encodingName($nextEscapeEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentEscapeBytesHex' => bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => bin2hex($nextEscapeBytes),
            'currentPreparedSignature' => $currentSignature,
            'nextPreparedSignature' => $nextSignature,
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
            'stableSourceInvalidationReasons' => $stableSourceReasons,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustDecodePatternBeforePrefixPlanning' => true,
            'mustReprepareForPreparedByteSignature' => !$samePreparedBytes,
            'decodedSqlCanStillShareRange' => $sameDecodedSql && $base['currentPrefix'] === $base['nextPrefix'],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-byte-signature',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next221',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE byte metadata, ASCII NOCASE prefix planning, RTRIM expression keys, and residual matching',
            'non_overlap' => 'next221 covers prepared UTF-16 pattern/escape byte-signature invalidation when decoded SQL text is stable; avoids accepted BOM normalization next206, Unicode ESCAPE next212, pattern-space next217, ASCII RTRIM next209, escaped literal next194/195, Unicode GLOB, and malformed UTF-16 insert guards',
        ];
    }

    private static function decodePreparedText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next221 prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    /** @return array{patternEncoding:string,patternBytesHex:string,escapeEncoding:string,escapeBytesHex:string} */
    private static function preparedSignature(
        string $patternBytes,
        int|string $patternEncoding,
        string $escapeBytes,
        int|string $escapeEncoding,
    ): array {
        return [
            'patternEncoding' => self::encodingName($patternEncoding),
            'patternBytesHex' => bin2hex($patternBytes),
            'escapeEncoding' => self::encodingName($escapeEncoding),
            'escapeBytesHex' => bin2hex($escapeBytes),
        ];
    }

    private static function encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next221 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
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
