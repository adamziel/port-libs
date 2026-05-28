<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext202Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array<string,mixed> $currentPatternRow
     * @param array<string,mixed> $nextPatternRow
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameSourcePatternPlan(
        array $currentRows,
        array $nextRows,
        array $currentPatternRow,
        array $nextPatternRow,
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@201',
        string $nextSource = 'main.wp_options@202',
        int $currentSchemaCookie = 201,
        int $nextSchemaCookie = 202,
    ): array {
        $currentPattern = self::decodePatternRow($currentPatternRow, 'current');
        $nextPattern = self::decodePatternRow($nextPatternRow, 'next');

        $current = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Plan::wordpressOptionNameAsciiPrefixRangePlan(
            $currentRows,
            $currentRows,
            $currentPattern,
            $escape,
            $currentSource,
            $currentSource,
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $next = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Plan::wordpressOptionNameAsciiPrefixRangePlan(
            $nextRows,
            $nextRows,
            $nextPattern,
            $escape,
            $nextSource,
            $nextSource,
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $sourceReasons = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $sourceReasons[] = 'source-or-schema-changed';
        }
        if (($currentPatternRow['option_id'] ?? null) !== ($nextPatternRow['option_id'] ?? null)) {
            $sourceReasons[] = 'rhs-pattern-source-rowid-changed';
        }
        if (($currentPatternRow['text_encoding'] ?? null) !== ($nextPatternRow['text_encoding'] ?? null)
            || ($currentPatternRow['option_value_bytes'] ?? null) !== ($nextPatternRow['option_value_bytes'] ?? null)) {
            $sourceReasons[] = 'rhs-pattern-source-bytes-changed';
        }
        if ($currentPattern !== $nextPattern) {
            $sourceReasons[] = 'decoded-rhs-pattern-changed';
        }
        foreach ([
            'range-bound' => self::rangeChanged($current, $next),
            'candidate-rowset' => $current['currentCandidateRowids'] !== $next['currentCandidateRowids'],
            'matched-rowset' => $current['currentMatchedRowids'] !== $next['currentMatchedRowids'],
            'range-false-positive-rowset' => $current['currentRangeFalsePositiveRowids'] !== $next['currentRangeFalsePositiveRowids'],
        ] as $reason => $changed) {
            if ($changed) {
                $sourceReasons[] = $reason;
            }
        }
        if ($current['currentMalformedRowids'] !== [] || $next['currentMalformedRowids'] !== []) {
            $sourceReasons[] = 'malformed-text';
        }
        $sourceReasons = array_values(array_unique($sourceReasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next202',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE (SELECT option_value FROM wp_options WHERE option_name = ?)',
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPatternSourceRowid' => (int) $currentPatternRow['option_id'],
            'nextPatternSourceRowid' => (int) $nextPatternRow['option_id'],
            'currentPatternEncoding' => self::encodingName((int) $currentPatternRow['text_encoding']),
            'nextPatternEncoding' => self::encodingName((int) $nextPatternRow['text_encoding']),
            'currentPatternBytesHex' => bin2hex((string) $currentPatternRow['option_value_bytes']),
            'nextPatternBytesHex' => bin2hex((string) $nextPatternRow['option_value_bytes']),
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'sameDecodedPattern' => $currentPattern === $nextPattern,
            'sameSourcePatternBytes' => ($currentPatternRow['option_value_bytes'] ?? null) === ($nextPatternRow['option_value_bytes'] ?? null),
            'currentPrefix' => $current['prefix'],
            'nextPrefix' => $next['prefix'],
            'currentRangeLowerInclusive' => $current['rangeLowerInclusive'],
            'nextRangeLowerInclusive' => $next['rangeLowerInclusive'],
            'currentRangeUpperBound' => $current['rangeUpperBound'],
            'nextRangeUpperBound' => $next['rangeUpperBound'],
            'currentUsesPrefixRangeCursor' => $current['usesPrefixRangeCursor'],
            'nextUsesPrefixRangeCursor' => $next['usesPrefixRangeCursor'],
            'currentCandidateRowids' => $current['currentCandidateRowids'],
            'nextCandidateRowids' => $next['currentCandidateRowids'],
            'candidateRetainedRowids' => self::retained($current['currentCandidateRowids'], $next['currentCandidateRowids']),
            'candidateExitedRowids' => self::exited($current['currentCandidateRowids'], $next['currentCandidateRowids']),
            'candidateEnteredRowids' => self::entered($current['currentCandidateRowids'], $next['currentCandidateRowids']),
            'currentMatchedRowids' => $current['currentMatchedRowids'],
            'nextMatchedRowids' => $next['currentMatchedRowids'],
            'matchedRetainedRowids' => self::retained($current['currentMatchedRowids'], $next['currentMatchedRowids']),
            'matchedExitedRowids' => self::exited($current['currentMatchedRowids'], $next['currentMatchedRowids']),
            'matchedEnteredRowids' => self::entered($current['currentMatchedRowids'], $next['currentMatchedRowids']),
            'currentRangeFalsePositiveRowids' => $current['currentRangeFalsePositiveRowids'],
            'nextRangeFalsePositiveRowids' => $next['currentRangeFalsePositiveRowids'],
            'currentRtrimTexts' => $current['currentRtrimTexts'],
            'nextRtrimTexts' => $next['currentRtrimTexts'],
            'currentNocaseKeys' => $current['currentNocaseKeys'],
            'nextNocaseKeys' => $next['currentNocaseKeys'],
            'currentMatchedTexts' => $current['currentMatchedTexts'],
            'nextMatchedTexts' => $next['currentMatchedTexts'],
            'currentMalformedRowids' => $current['currentMalformedRowids'],
            'nextMalformedRowids' => $next['currentMalformedRowids'],
            'currentErrors' => $current['currentErrors'],
            'nextErrors' => $next['currentErrors'],
            'rhsPatternInvalidationReasons' => $sourceReasons,
            'cursorInvalidated' => $sourceReasons !== [],
            'cursorReusable' => $sourceReasons === [],
            'mustReprepareForSourcePatternChange' => $currentPattern !== $nextPattern || $sourceReasons !== [],
            'canReuseResidualForStableSourcePattern' => $sourceReasons === [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'rhsPatternComesFromCurrentSourceRow' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-source-row-like-pattern',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next202',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, source-row pattern extraction, ASCII NOCASE LIKE prefix ranges, RTRIM expression keys, and current-source diagnostics',
            'non_overlap' => 'next202 covers UTF-16 LIKE patterns read from current/next source rows; it avoids accepted prepared-pattern byte rebind next191, duplicate peer resume next196, escaped literal tail next195, Unicode GLOB ranges, and malformed insert guards',
        ];
    }

    /** @param array<string,mixed> $row */
    private static function decodePatternRow(array $row, string $label): string
    {
        foreach (['option_id', 'option_value_bytes', 'text_encoding'] as $key) {
            if (!array_key_exists($key, $row)) {
                throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next202 {$label} pattern row missing {$key}");
            }
        }
        if (!is_int($row['option_id'])) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next202 {$label} pattern row option_id must be integer");
        }
        if (!is_string($row['option_value_bytes'])) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next202 {$label} pattern bytes must be string");
        }
        if (!is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next202 {$label} pattern encoding must be integer");
        }

        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($row['option_value_bytes'], $row['text_encoding']);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next202 {$label} pattern row is malformed: " . $exception->getMessage());
        }
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $next */
    private static function rangeChanged(array $current, array $next): bool
    {
        return ($current['rangeLowerInclusive'] ?? null) !== ($next['rangeLowerInclusive'] ?? null)
            || ($current['rangeUpperBound'] ?? null) !== ($next['rangeUpperBound'] ?? null);
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function retained(array $current, array $next): array
    {
        return array_values(array_intersect($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function exited(array $current, array $next): array
    {
        return array_values(array_diff($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function entered(array $current, array $next): array
    {
        return array_values(array_diff($next, $current));
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next202 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
