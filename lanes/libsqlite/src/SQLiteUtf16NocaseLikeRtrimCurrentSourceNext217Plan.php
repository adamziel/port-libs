<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext217Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePreparedPatternSpacePlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@216',
        string $nextSource = 'main.wp_options@217',
        int $currentSchemaCookie = 216,
        int $nextSchemaCookie = 217,
    ): array {
        $currentPattern = self::decodePreparedPattern($currentPatternBytes, $currentPatternEncoding, 'current pattern');
        $nextPattern = self::decodePreparedPattern($nextPatternBytes, $nextPatternEncoding, 'next pattern');
        $currentSpace = self::spaceBeforeFirstWildcard($currentPattern, $escape);
        $nextSpace = self::spaceBeforeFirstWildcard($nextPattern, $escape);

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $currentPattern,
            $escape,
            $nextPattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentWithoutSpace = self::removeSpaceBeforeFirstWildcard($currentPattern, $escape);
        $nextWithoutSpace = self::removeSpaceBeforeFirstWildcard($nextPattern, $escape);
        $currentWithoutSpacePlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $currentRows,
            $currentRows,
            $currentWithoutSpace,
            $escape,
            $currentWithoutSpace,
            $escape,
            $currentSource . '#without-pattern-space',
            $currentSource . '#without-pattern-space',
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $nextWithoutSpacePlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $nextRows,
            $nextRows,
            $nextWithoutSpace,
            $escape,
            $nextWithoutSpace,
            $escape,
            $nextSource . '#without-pattern-space',
            $nextSource . '#without-pattern-space',
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $currentSpaceFiltered = self::sortedDiff($currentWithoutSpacePlan['currentMatchedRowids'], $base['currentMatchedRowids']);
        $nextSpaceFiltered = self::sortedDiff($nextWithoutSpacePlan['currentMatchedRowids'], $base['nextMatchedRowids']);
        $currentRtrimHadSpace = self::rowsWithTrimmedAsciiSpace($base['currentRtrimTexts'], self::decodeRows($currentRows));
        $nextRtrimHadSpace = self::rowsWithTrimmedAsciiSpace($base['nextRtrimTexts'], self::decodeRows($nextRows));

        $reasons = $base['invalidationReasons'];
        if ($currentSpace['spaceCount'] !== $nextSpace['spaceCount']) {
            $reasons[] = 'prepared-pattern-space-count';
        }
        if ($currentSpaceFiltered !== $nextSpaceFiltered) {
            $reasons[] = 'prepared-pattern-space-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next217',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 pattern space */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentPatternEncoding' => self::encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::encodingName($nextPatternEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'escape' => $escape,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentSpaceBeforeWildcardCount' => $currentSpace['spaceCount'],
            'nextSpaceBeforeWildcardCount' => $nextSpace['spaceCount'],
            'currentSpaceBeforeWildcardOffset' => $currentSpace['offset'],
            'nextSpaceBeforeWildcardOffset' => $nextSpace['offset'],
            'currentPatternWithoutSpaceBeforeWildcard' => $currentWithoutSpace,
            'nextPatternWithoutSpaceBeforeWildcard' => $nextWithoutSpace,
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'currentRangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedWithoutPatternSpaceRowids' => $currentWithoutSpacePlan['currentMatchedRowids'],
            'nextMatchedWithoutPatternSpaceRowids' => $nextWithoutSpacePlan['currentMatchedRowids'],
            'currentPatternSpaceFilteredRowids' => $currentSpaceFiltered,
            'nextPatternSpaceFilteredRowids' => $nextSpaceFiltered,
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
            'currentRowsWithTrimmedAsciiSpace' => $currentRtrimHadSpace,
            'nextRowsWithTrimmedAsciiSpace' => $nextRtrimHadSpace,
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustDecodePatternBeforePrefixPlanning' => true,
            'preparedPatternSpacesRemainSignificant' => true,
            'leftRtrimDoesNotTrimPattern' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-pattern-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next217',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE pattern text, ASCII NOCASE prefix planning, RTRIM expression keys, and residual matching',
            'non_overlap' => 'next217 covers decoded UTF-16 prepared LIKE pattern spaces before the first wildcard remaining significant while rtrim(option_name) trims only the left expression; avoids accepted embedded-NUL next210, Unicode ESCAPE next212, source refresh next211, ASCII-space row RTRIM next209, Unicode GLOB, and malformed UTF-16 insert guards',
        ];
    }

    private static function decodePreparedPattern(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next217 prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    /** @return array{spaceCount:int,offset:?int} */
    private static function spaceBeforeFirstWildcard(string $pattern, ?string $escape): array
    {
        $chars = self::characters($pattern);
        $escaped = false;
        $firstWildcard = count($chars);
        foreach ($chars as $offset => $char) {
            if ($escape !== null && $char === $escape && !$escaped) {
                $escaped = true;
                continue;
            }
            if (!$escaped && ($char === '%' || $char === '_')) {
                $firstWildcard = $offset;
                break;
            }
            $escaped = false;
        }

        $count = 0;
        for ($i = $firstWildcard - 1; $i >= 0 && ($chars[$i] ?? null) === ' '; $i--) {
            $count++;
        }

        return [
            'spaceCount' => $count,
            'offset' => $count > 0 ? $firstWildcard - $count : null,
        ];
    }

    private static function removeSpaceBeforeFirstWildcard(string $pattern, ?string $escape): string
    {
        $space = self::spaceBeforeFirstWildcard($pattern, $escape);
        if ($space['spaceCount'] === 0 || $space['offset'] === null) {
            return $pattern;
        }

        $chars = self::characters($pattern);
        array_splice($chars, $space['offset'], $space['spaceCount']);

        return implode('', $chars);
    }

    /**
     * @param array<int,string> $rtrimTexts
     * @param array<int,string> $decodedTexts
     * @return list<int>
     */
    private static function rowsWithTrimmedAsciiSpace(array $rtrimTexts, array $decodedTexts): array
    {
        $rowids = [];
        foreach ($decodedTexts as $rowid => $text) {
            if (($rtrimTexts[$rowid] ?? null) !== $text) {
                $rowids[] = $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,string>
     */
    private static function decodeRows(array $rows): array
    {
        $decoded = [];
        foreach ($rows as $row) {
            if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next217 rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next217 rows require option_name_bytes');
            }
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next217 rows require integer text_encoding');
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

    /** @return list<string> */
    private static function characters(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : str_split($value);
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function sortedDiff(array $left, array $right): array
    {
        $diff = array_values(array_diff($left, $right));
        sort($diff);

        return $diff;
    }

    private static function encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next217 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
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
