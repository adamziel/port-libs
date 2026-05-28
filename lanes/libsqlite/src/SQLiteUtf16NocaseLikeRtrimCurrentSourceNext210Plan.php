<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext210Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEmbeddedNulPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = "plugin\0cache%",
        ?string $escape = null,
        string $currentSource = 'main.wp_options@209',
        string $nextSource = 'main.wp_options@210',
        int $currentSchemaCookie = 209,
        int $nextSchemaCookie = 210,
    ): array {
        if (!str_contains($pattern, "\0")) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next210 expects an embedded-NUL LIKE pattern');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentDecoded = self::decodeRows($currentRows);
        $nextDecoded = self::decodeRows($nextRows);
        $currentNul = self::nulDiagnostics($currentDecoded, $base['currentMatchedRowids'], $base['currentFalsePositiveRowids']);
        $nextNul = self::nulDiagnostics($nextDecoded, $base['nextMatchedRowids'], $base['nextFalsePositiveRowids']);

        $reasons = $base['invalidationReasons'];
        if ($currentNul['nulRowids'] !== $nextNul['nulRowids']) {
            $reasons[] = 'embedded-nul-rowset';
        }
        if ($currentNul['nulMatchedRowids'] !== $nextNul['nulMatchedRowids']) {
            $reasons[] = 'embedded-nul-matched-rowset';
        }
        if ($currentNul['nulFalsePositiveRowids'] !== $nextNul['nulFalsePositiveRowids']) {
            $reasons[] = 'embedded-nul-false-positive-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        $prefix = (string) $base['currentPrefix'];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next210',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? /* embedded NUL */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $prefix,
            'prefixHex' => bin2hex($prefix),
            'prefixContainsNul' => str_contains($prefix, "\0"),
            'rangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'rangeLowerInclusiveHex' => bin2hex((string) $base['currentRangeLowerInclusive']),
            'rangeUpperBound' => $base['currentRangeUpperBound'],
            'rangeUpperBoundHex' => bin2hex((string) $base['currentRangeUpperBound']),
            'nulBytePositionInPrefix' => strpos($prefix, "\0"),
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'usesPrefixRangeCursor' => $base['currentIndexUsable'] && $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedRetainedRowids' => self::retained($base['currentMatchedRowids'], $base['nextMatchedRowids']),
            'matchedExitedRowids' => self::exited($base['currentMatchedRowids'], $base['nextMatchedRowids']),
            'matchedEnteredRowids' => self::entered($base['currentMatchedRowids'], $base['nextMatchedRowids']),
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentRtrimHex' => self::hexMap($base['currentRtrimTexts']),
            'nextRtrimHex' => self::hexMap($base['nextRtrimTexts']),
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentNocaseKeyHex' => self::hexMap($base['currentNocaseKeys']),
            'nextNocaseKeyHex' => self::hexMap($base['nextNocaseKeys']),
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMatchedHex' => self::hexMap($base['currentMatchedTexts']),
            'nextMatchedHex' => self::hexMap($base['nextMatchedTexts']),
            'currentEmbeddedNulRowids' => $currentNul['nulRowids'],
            'nextEmbeddedNulRowids' => $nextNul['nulRowids'],
            'currentEmbeddedNulMatchedRowids' => $currentNul['nulMatchedRowids'],
            'nextEmbeddedNulMatchedRowids' => $nextNul['nulMatchedRowids'],
            'currentEmbeddedNulFalsePositiveRowids' => $currentNul['nulFalsePositiveRowids'],
            'nextEmbeddedNulFalsePositiveRowids' => $nextNul['nulFalsePositiveRowids'],
            'currentEmbeddedNulPositions' => $currentNul['nulPositions'],
            'nextEmbeddedNulPositions' => $nextNul['nulPositions'],
            'currentTextAfterNul' => $currentNul['textAfterNul'],
            'nextTextAfterNul' => $nextNul['textAfterNul'],
            'currentTextAfterNulHex' => self::hexMap($currentNul['textAfterNul']),
            'nextTextAfterNulHex' => self::hexMap($nextNul['textAfterNul']),
            'currentTruncatedPrefixWouldMatchRowids' => $currentNul['truncatedPrefixWouldMatchRowids'],
            'nextTruncatedPrefixWouldMatchRowids' => $nextNul['truncatedPrefixWouldMatchRowids'],
            'currentTruncatedPrefixFalsePositiveRowids' => array_values(array_diff($currentNul['truncatedPrefixWouldMatchRowids'], $base['currentMatchedRowids'])),
            'nextTruncatedPrefixFalsePositiveRowids' => array_values(array_diff($nextNul['truncatedPrefixWouldMatchRowids'], $base['nextMatchedRowids'])),
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'embeddedNulDoesNotTerminateText' => true,
            'likeResidualSeesBytesAfterNul' => true,
            'rtrimTrimsOnlyAsciiSpaceAfterNulAwareDecode' => true,
            'nocaseFoldsAsciiOnlyAcrossNul' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-embedded-nul-text',
                'sqlite-current-source-next210',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and binary-safe PHP string residual matching',
            'non_overlap' => 'next210 covers embedded NUL text and pattern bytes in UTF-16 NOCASE/RTRIM LIKE current-source scans; avoids accepted ASCII-space RTRIM next209, BOM normalization, escape rebind, no-prefix scans, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,string>
     */
    private static function decodeRows(array $rows): array
    {
        $decoded = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next210 rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next210 rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next210 rows require integer text_encoding');
            }

            try {
                $decoded[$row['option_id']] = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        ksort($decoded);

        return $decoded;
    }

    /**
     * @param array<int,string> $decoded
     * @param list<int> $matchedRowids
     * @param list<int> $falsePositiveRowids
     * @return array{nulRowids:list<int>,nulMatchedRowids:list<int>,nulFalsePositiveRowids:list<int>,nulPositions:array<int,int>,textAfterNul:array<int,string>,truncatedPrefixWouldMatchRowids:list<int>}
     */
    private static function nulDiagnostics(array $decoded, array $matchedRowids, array $falsePositiveRowids): array
    {
        $nulRowids = [];
        $positions = [];
        $afterNul = [];
        $truncatedPrefixWouldMatch = [];
        foreach ($decoded as $rowid => $text) {
            $position = strpos($text, "\0");
            if ($position === false) {
                continue;
            }
            $nulRowids[] = $rowid;
            $positions[$rowid] = $position;
            $afterNul[$rowid] = substr($text, $position + 1);
            if (str_starts_with(strtolower(substr($text, 0, $position)), 'plugin')) {
                $truncatedPrefixWouldMatch[] = $rowid;
            }
        }

        sort($nulRowids);
        sort($truncatedPrefixWouldMatch);
        ksort($positions);
        ksort($afterNul);

        return [
            'nulRowids' => $nulRowids,
            'nulMatchedRowids' => array_values(array_intersect($nulRowids, $matchedRowids)),
            'nulFalsePositiveRowids' => array_values(array_intersect($nulRowids, $falsePositiveRowids)),
            'nulPositions' => $positions,
            'textAfterNul' => $afterNul,
            'truncatedPrefixWouldMatchRowids' => $truncatedPrefixWouldMatch,
        ];
    }

    /** @param array<int,string> $values @return array<int,string> */
    private static function hexMap(array $values): array
    {
        $hex = [];
        foreach ($values as $rowid => $value) {
            $hex[$rowid] = bin2hex($value);
        }

        return $hex;
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
}
