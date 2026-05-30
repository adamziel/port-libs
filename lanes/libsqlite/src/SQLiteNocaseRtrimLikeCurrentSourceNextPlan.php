<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteNocaseRtrimLikeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPlan(
        array $currentRows,
        array $nextRows,
        string $currentPattern,
        string $nextPattern,
        string $currentCollation = 'NOCASE',
        string $nextCollation = 'RTRIM',
        ?string $currentEscape = null,
        ?string $nextEscape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@current',
        string $nextSource = 'main.app_settings@next',
    ): array {
        $currentCollation = self::assertCollation($currentCollation);
        $nextCollation = self::assertCollation($nextCollation);
        $currentLike = SQLiteLikeCollationPlan::plan($currentPattern, $currentCollation, $currentEscape, $caseSensitiveLike);
        $nextLike = SQLiteLikeCollationPlan::plan($nextPattern, $nextCollation, $nextEscape, $caseSensitiveLike);
        $current = self::scanRows($currentRows, $currentPattern, $currentCollation, $currentEscape, $caseSensitiveLike, $currentLike['range']);
        $next = self::scanRows($nextRows, $nextPattern, $nextCollation, $nextEscape, $caseSensitiveLike, $nextLike['range']);

        $currentRowids = self::rowids($current['matchedRows']);
        $nextRowids = self::rowids($next['matchedRows']);
        $currentCandidateRowids = self::rowids($current['candidateRows']);
        $nextCandidateRowids = self::rowids($next['candidateRows']);
        $currentResidualRejectedRowids = self::rowids($current['residualRejectedRows']);
        $nextResidualRejectedRowids = self::rowids($next['residualRejectedRows']);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $changes = self::sourceChanges($current['decodedRows'], $next['decodedRows']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentCollation !== $nextCollation) {
            $reasons[] = 'collation-switch';
        }
        if ($currentPattern !== $nextPattern || $currentEscape !== $nextEscape || self::rangeFingerprint($currentLike['range']) !== self::rangeFingerprint($nextLike['range'])) {
            $reasons[] = 'pattern-range';
        }
        if (($currentCollation === 'RTRIM' && !$currentLike['indexUsable']) || ($nextCollation === 'RTRIM' && !$nextLike['indexUsable'])) {
            $reasons[] = 'full-scan-rtrim-like';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentRowids !== $nextRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($changes['textChangedRowids'] !== []) {
            $reasons[] = 'text-value';
        }
        if ($changes['encodingChangedRowids'] !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changes['bytesChangedRowids'] !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($changes['currentKeyChangedRowids'] !== [] || $changes['nextKeyChangedRowids'] !== []) {
            $reasons[] = 'collation-key';
        }

        return [
            'operator' => 'LIKE',
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentCollation' => $currentCollation,
            'nextCollation' => $nextCollation,
            'currentRange' => $currentLike['range'],
            'nextRange' => $nextLike['range'],
            'currentIndexUsable' => $currentLike['indexUsable'],
            'nextIndexUsable' => $nextLike['indexUsable'],
            'currentRejectedReason' => $currentLike['rejectedReason'],
            'nextRejectedReason' => $nextLike['rejectedReason'],
            'likeResidualIgnoresCollationTrim' => true,
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'currentResidualRejectedRowids' => $currentResidualRejectedRowids,
            'nextResidualRejectedRowids' => $nextResidualRejectedRowids,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'enteredRowids' => $entered,
            'exitedRowids' => $exited,
            'currentDecodedRows' => $current['decodedRows'],
            'nextDecodedRows' => $next['decodedRows'],
            'currentMatchedRows' => $current['matchedRows'],
            'nextMatchedRows' => $next['matchedRows'],
            'currentComparisonKeys' => self::comparisonKeys($current['decodedRows'], 'currentKey'),
            'nextComparisonKeys' => self::comparisonKeys($next['decodedRows'], 'nextKey'),
            'currentBytesHex' => self::bytesHex($current['decodedRows']),
            'nextBytesHex' => self::bytesHex($next['decodedRows']),
            'currentEncodings' => self::encodings($current['decodedRows']),
            'nextEncodings' => self::encodings($next['decodedRows']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'retainedTextChangedRowids' => $changes['textChangedRowids'],
            'retainedEncodingChangedRowids' => $changes['encodingChangedRowids'],
            'retainedBytesChangedRowids' => $changes['bytesChangedRowids'],
            'retainedCurrentKeyChangedRowids' => $changes['currentKeyChangedRowids'],
            'retainedNextKeyChangedRowids' => $changes['nextKeyChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-range',
                'sqlite-rtrim-like-full-scan',
                'sqlite-current-source-next134',
            ],
            'dependency_closure' => 'no new support component needed; composes existing UTF-8/UTF-16 text decoding, LIKE pattern planning, NOCASE range comparison, RTRIM collation keys, and current-source invalidation metadata',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     * @return array{
     *   decodedRows:list<array{rowid:int,text:string,currentKey:string,nextKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   candidateRows:list<array{rowid:int,text:string,currentKey:string,nextKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   residualRejectedRows:list<array{rowid:int,text:string,currentKey:string,nextKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   matchedRows:list<array{rowid:int,text:string,currentKey:string,nextKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   malformedRowids:list<int>,
     *   errors:array<int,string>
     * }
     */
    private static function scanRows(array $rows, string $pattern, string $collation, ?string $escape, bool $caseSensitiveLike, ?array $range): array
    {
        $decoded = [];
        $candidates = [];
        $rejected = [];
        $matched = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite NOCASE/RTRIM LIKE current-source rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite NOCASE/RTRIM LIKE current-source rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite NOCASE/RTRIM LIKE current-source rows require integer text_encoding');
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
                'currentKey' => self::keyFor($text, 'NOCASE'),
                'nextKey' => self::keyFor($text, 'RTRIM'),
                'encoding' => $encoding,
                'bytesHex' => bin2hex($row['option_name_bytes']),
                'payload' => $row,
            ];
            $decoded[] = $entry;

            if ($range === null || self::inRange($text, $collation, $range)) {
                $candidates[] = $entry;
                if (SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)) {
                    $matched[] = $entry;
                } else {
                    $rejected[] = $entry;
                }
            }
        }

        usort($decoded, static fn (array $left, array $right): int => self::compareRows($left, $right, $collation));
        usort($candidates, static fn (array $left, array $right): int => self::compareRows($left, $right, $collation));
        usort($rejected, static fn (array $left, array $right): int => self::compareRows($left, $right, $collation));
        usort($matched, static fn (array $left, array $right): int => self::compareRows($left, $right, $collation));

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
     * @param list<array{rowid:int,text:string,currentKey:string,nextKey:string,encoding:string,bytesHex:string}> $currentRows
     * @param list<array{rowid:int,text:string,currentKey:string,nextKey:string,encoding:string,bytesHex:string}> $nextRows
     * @return array{textChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,currentKeyChangedRowids:list<int>,nextKeyChangedRowids:list<int>}
     */
    private static function sourceChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $text = [];
        $encoding = [];
        $bytes = [];
        $currentKey = [];
        $nextKey = [];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $text[] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $encoding[] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $bytes[] = $rowid;
            }
            if ($current[$rowid]['currentKey'] !== $row['currentKey']) {
                $currentKey[] = $rowid;
            }
            if ($current[$rowid]['nextKey'] !== $row['nextKey']) {
                $nextKey[] = $rowid;
            }
        }

        sort($text);
        sort($encoding);
        sort($bytes);
        sort($currentKey);
        sort($nextKey);

        return [
            'textChangedRowids' => $text,
            'encodingChangedRowids' => $encoding,
            'bytesChangedRowids' => $bytes,
            'currentKeyChangedRowids' => $currentKey,
            'nextKeyChangedRowids' => $nextKey,
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
     * @param array{lowerInclusive:string,upperBound:?string} $range
     */
    private static function inRange(string $text, string $collation, array $range): bool
    {
        if (self::compareText($text, $range['lowerInclusive'], $collation) < 0) {
            return false;
        }
        if ($range['upperBound'] !== null && self::compareText($text, $range['upperBound'], $collation) >= 0) {
            return false;
        }

        return true;
    }

    private static function compareRows(array $left, array $right, string $collation): int
    {
        $comparison = self::compareText($left['text'], $right['text'], $collation);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    private static function compareText(string $left, string $right, string $collation): int
    {
        return strcmp(self::keyFor($left, $collation), self::keyFor($right, $collation));
    }

    private static function keyFor(string $text, string $collation): string
    {
        return match ($collation) {
            'NOCASE' => self::asciiLower($text),
            'RTRIM' => rtrim($text, ' '),
            default => $text,
        };
    }

    /**
     * @param list<array{rowid:int}> $rows
     * @return list<int>
     */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /**
     * @param list<array{rowid:int,currentKey:string,nextKey:string}> $rows
     * @return array<int,string>
     */
    private static function comparisonKeys(array $rows, string $key): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['rowid']] = $row[$key];
        }

        return $keys;
    }

    /**
     * @param list<array{rowid:int,bytesHex:string}> $rows
     * @return array<int,string>
     */
    private static function bytesHex(array $rows): array
    {
        $bytes = [];
        foreach ($rows as $row) {
            $bytes[$row['rowid']] = $row['bytesHex'];
        }

        return $bytes;
    }

    /**
     * @param list<array{rowid:int,encoding:string}> $rows
     * @return array<int,string>
     */
    private static function encodings(array $rows): array
    {
        $encodings = [];
        foreach ($rows as $row) {
            $encodings[$row['rowid']] = $row['encoding'];
        }

        return $encodings;
    }

    private static function assertCollation(string $collation): string
    {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite NOCASE/RTRIM LIKE current-source plan requires NOCASE or RTRIM collation');
        }

        return $collation;
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

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
