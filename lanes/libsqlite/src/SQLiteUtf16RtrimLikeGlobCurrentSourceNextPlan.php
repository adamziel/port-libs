<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameOperatorSwitchPlan(
        array $currentRows,
        array $nextRows,
        string $currentPattern,
        string $nextPattern,
        string $currentOperator = 'LIKE',
        string $nextOperator = 'GLOB',
        ?string $currentEscape = null,
        ?string $nextEscape = null,
        bool $caseSensitiveLike = true,
        string $currentSource = 'main.wp_options@current',
        string $nextSource = 'main.wp_options@next',
    ): array {
        $currentOperator = strtoupper($currentOperator);
        $nextOperator = strtoupper($nextOperator);
        self::assertOperator($currentOperator);
        self::assertOperator($nextOperator);

        $currentRange = self::rangeFor($currentOperator, $currentPattern, $currentEscape, $caseSensitiveLike);
        $nextRange = self::rangeFor($nextOperator, $nextPattern, $nextEscape, $caseSensitiveLike);
        $current = self::scanRows($currentRows, $currentOperator, $currentPattern, $currentRange, $currentEscape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $nextOperator, $nextPattern, $nextRange, $nextEscape, $caseSensitiveLike);
        $currentRowids = array_column($current['matchedRows'], 'rowid');
        $nextRowids = array_column($next['matchedRows'], 'rowid');
        $currentCandidateRowids = array_column($current['candidateRows'], 'rowid');
        $nextCandidateRowids = array_column($next['candidateRows'], 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));

        $currentByRowid = self::rowsByRowid($current['decodedRows']);
        $nextByRowid = self::rowsByRowid($next['decodedRows']);
        $changedText = [];
        $changedEncoding = [];
        $changedBytes = [];
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['text'] !== $nextByRowid[$rowid]['text']) {
                $changedText[] = (int) $rowid;
            }
            if ($currentByRowid[$rowid]['encoding'] !== $nextByRowid[$rowid]['encoding']) {
                $changedEncoding[] = (int) $rowid;
            }
            if ($currentByRowid[$rowid]['bytesHex'] !== $nextByRowid[$rowid]['bytesHex']) {
                $changedBytes[] = (int) $rowid;
            }
        }
        sort($changedText);
        sort($changedEncoding);
        sort($changedBytes);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentOperator !== $nextOperator) {
            $reasons[] = 'operator-switch';
        }
        if ($currentPattern !== $nextPattern || $currentEscape !== $nextEscape || self::rangeFingerprint($currentRange) !== self::rangeFingerprint($nextRange)) {
            $reasons[] = 'pattern-range';
        }
        if (($currentOperator === 'LIKE' && $currentRange === null) || ($nextOperator === 'LIKE' && $nextRange === null)) {
            $reasons[] = 'full-scan-rtrim-like';
        }
        if (($currentOperator === 'GLOB' && $currentRange === null) || ($nextOperator === 'GLOB' && $nextRange === null)) {
            $reasons[] = 'unusable-range';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($changedText !== []) {
            $reasons[] = 'text-value';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $reasons[] = 'candidate-rowset';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'collation' => 'RTRIM',
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentOperator' => $currentOperator,
            'nextOperator' => $nextOperator,
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentRange' => $currentRange,
            'nextRange' => $nextRange,
            'currentIndexUsable' => $currentRange !== null && $currentOperator === 'GLOB',
            'nextIndexUsable' => $nextRange !== null && $nextOperator === 'GLOB',
            'likeRtrimRequiresFullScan' => $currentOperator === 'LIKE' || $nextOperator === 'LIKE',
            'residualDoesNotTrimTrailingSpaces' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'currentResidualRejectedRowids' => array_column($current['residualRejectedRows'], 'rowid'),
            'nextResidualRejectedRowids' => array_column($next['residualRejectedRows'], 'rowid'),
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'enteredRowids' => $entered,
            'exitedRowids' => $exited,
            'changedTextRowids' => $changedText,
            'changedEncodingRowids' => $changedEncoding,
            'changedBytesRowids' => $changedBytes,
            'currentMatchedRows' => $current['matchedRows'],
            'nextMatchedRows' => $next['matchedRows'],
            'currentDecodedRows' => $current['decodedRows'],
            'nextDecodedRows' => $next['decodedRows'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-collation-key',
                'sqlite-like-glob-residual-match',
                'sqlite-current-source-nextoneTwoEight',
            ],
        ];
    }

    /**
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     */
    private static function rangeFingerprint(?array $range): string
    {
        return $range === null ? 'null' : $range['lowerInclusive'] . "\0" . ($range['upperBound'] ?? '');
    }

    /**
     * @return null|array{lowerInclusive:string,upperBound:?string}
     */
    private static function rangeFor(string $operator, string $pattern, ?string $escape, bool $caseSensitiveLike): ?array
    {
        if ($operator === 'GLOB') {
            return SQLiteDatabase::globPrefixRangeBounds($pattern);
        }

        $plan = SQLiteLikeCollationPlan::plan($pattern, 'RTRIM', $escape, $caseSensitiveLike);
        return $plan['range'];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     * @return array{
     *   decodedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   candidateRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   residualRejectedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   matchedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   malformedRowids:list<int>,
     *   errors:array<int,string>
     * }
     */
    private static function scanRows(array $rows, string $operator, string $pattern, ?array $range, ?string $escape, bool $caseSensitiveLike): array
    {
        $decoded = [];
        $candidates = [];
        $rejected = [];
        $matched = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE/GLOB rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE/GLOB rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE/GLOB rows require integer text_encoding');
            }

            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $encoding = self::encodingName($row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
                continue;
            }

            $entry = [
                'rowid' => $row['option_id'],
                'text' => $text,
                'rtrimKey' => rtrim($text, ' '),
                'encoding' => $encoding,
                'bytesHex' => bin2hex($row['option_name_bytes']),
                'payload' => $row,
            ];
            $decoded[] = $entry;

            if ($operator === 'GLOB' && !self::inRtrimRange($text, $range)) {
                continue;
            }
            if ($operator === 'LIKE' && $range === null) {
                $candidates[] = $entry;
            } elseif ($operator === 'LIKE') {
                $candidates[] = $entry;
            } else {
                $candidates[] = $entry;
            }

            if (self::matches($operator, $text, $pattern, $escape, $caseSensitiveLike)) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        usort($decoded, self::sortRows(...));
        usort($candidates, self::sortRows(...));
        usort($rejected, self::sortRows(...));
        usort($matched, self::sortRows(...));

        return [
            'decodedRows' => $decoded,
            'candidateRows' => $candidates,
            'residualRejectedRows' => $rejected,
            'matchedRows' => $matched,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /**
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     */
    private static function inRtrimRange(string $text, ?array $range): bool
    {
        if ($range === null) {
            return false;
        }
        $key = rtrim($text, ' ');
        if (strcmp($key, rtrim($range['lowerInclusive'], ' ')) < 0) {
            return false;
        }
        if ($range['upperBound'] !== null && strcmp($key, rtrim($range['upperBound'], ' ')) >= 0) {
            return false;
        }

        return true;
    }

    private static function matches(string $operator, string $text, string $pattern, ?string $escape, bool $caseSensitiveLike): bool
    {
        return $operator === 'LIKE'
            ? SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)
            : SQLiteDatabase::globMatches($text, $pattern);
    }

    /**
     * @param list<array{rowid:int,text:string,encoding:string,bytesHex:string}> $rows
     * @return array<int,array{text:string,encoding:string,bytesHex:string}>
     */
    private static function rowsByRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            $byRowid[$row['rowid']] = [
                'text' => $row['text'],
                'encoding' => $row['encoding'],
                'bytesHex' => $row['bytesHex'],
            ];
        }

        return $byRowid;
    }

    /**
     * @param array{rowid:int,rtrimKey:string} $left
     * @param array{rowid:int,rtrimKey:string} $right
     */
    private static function sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['rtrimKey'], $right['rtrimKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    private static function assertOperator(string $operator): void
    {
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 RTRIM current-source plan operator must be LIKE or GLOB');
        }
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
