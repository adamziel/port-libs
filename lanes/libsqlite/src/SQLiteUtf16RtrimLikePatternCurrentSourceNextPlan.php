<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function optionRowNamePlan(
        array $currentRows,
        array $nextRows,
        string $patternBytes,
        string $patternEncoding,
        ?string $escapeBytes = null,
        ?string $escapeEncoding = null,
        bool $caseSensitiveLike = true,
        string $currentSource = 'main.wp_options@137',
        string $nextSource = 'main.wp_options@138',
    ): array {
        $patternEncoding = self::normalizeEncoding($patternEncoding);
        $escapeEncoding = $escapeBytes === null ? null : self::normalizeEncoding($escapeEncoding ?? $patternEncoding);
        $pattern = SQLiteEncodingCollationSourceCursor::decodeText($patternBytes, self::encodingNumber($patternEncoding));
        $escape = $escapeBytes === null ? null : SQLiteEncodingCollationSourceCursor::decodeText($escapeBytes, self::encodingNumber($escapeEncoding));
        if ($escape !== null && mb_strlen($escape, 'UTF-8') !== 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE ESCAPE must decode to exactly one character');
        }

        $likePlan = SQLiteLikeCollationPlan::plan($pattern, 'RTRIM', $escape, $caseSensitiveLike);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentRowids = self::rowids($current['matchedRows']);
        $nextRowids = self::rowids($next['matchedRows']);
        $currentRejected = self::rowids($current['residualRejectedRows']);
        $nextRejected = self::rowids($next['residualRejectedRows']);
        $changes = self::sourceChanges($current['decodedRows'], $next['decodedRows']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if (!$likePlan['indexUsable']) {
            $reasons[] = 'full-scan-rtrim-like';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        foreach ([
            'text-value' => $changes['textChangedRowids'],
            'text-encoding' => $changes['encodingChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'rtrim-key' => $changes['rtrimKeyChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($currentRowids !== $nextRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($currentRejected !== $nextRejected) {
            $reasons[] = 'residual-rejected-rowset';
        }

        return [
            'operator' => 'LIKE',
            'collation' => 'RTRIM',
            'decodedPattern' => $pattern,
            'patternEncoding' => $patternEncoding,
            'patternBytesHex' => bin2hex($patternBytes),
            'decodedEscape' => $escape,
            'escapeEncoding' => $escapeEncoding,
            'escapeBytesHex' => $escapeBytes === null ? null : bin2hex($escapeBytes),
            'caseSensitiveLike' => $caseSensitiveLike,
            'range' => $likePlan['range'],
            'indexUsable' => false,
            'rejectedReason' => $likePlan['rejectedReason'],
            'residualScan' => true,
            'likeDoesNotTrimTrailingSpaces' => true,
            'patternDecodedBeforeRtrimLike' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => array_values(array_intersect($currentRowids, $nextRowids)),
            'enteredRowids' => array_values(array_diff($nextRowids, $currentRowids)),
            'exitedRowids' => array_values(array_diff($currentRowids, $nextRowids)),
            'currentResidualRejectedRowids' => $currentRejected,
            'nextResidualRejectedRowids' => $nextRejected,
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedRtrimKeyRowids' => $changes['rtrimKeyChangedRowids'],
            'currentDecodedRows' => $current['decodedRows'],
            'nextDecodedRows' => $next['decodedRows'],
            'currentMatchedRows' => $current['matchedRows'],
            'nextMatchedRows' => $next['matchedRows'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-pattern-decode',
                'sqlite-utf16-row-decode',
                'sqlite-rtrim-like-full-scan',
                'sqlite-current-source-nextoneThreeEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 text decoding, LIKE pattern planning, RTRIM collation keys, and current-source invalidation metadata',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{
     *   decodedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   matchedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   residualRejectedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   malformedRowids:list<int>,
     *   errors:array<int,string>
     * }
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $decoded = [];
        $matched = [];
        $rejected = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
                continue;
            }

            $entry = [
                'rowid' => $row['option_id'],
                'text' => $text,
                'rtrimKey' => rtrim($text, ' '),
                'encoding' => self::encodingName($row['text_encoding']),
                'bytesHex' => bin2hex($row['option_name_bytes']),
                'payload' => $row,
            ];
            $decoded[] = $entry;
            if (SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        usort($decoded, self::sortRows(...));
        usort($matched, self::sortRows(...));
        usort($rejected, self::sortRows(...));
        sort($malformed);
        ksort($errors);

        return [
            'decodedRows' => $decoded,
            'matchedRows' => $matched,
            'residualRejectedRows' => $rejected,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE pattern rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE pattern rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE pattern rows require integer text_encoding');
        }
    }

    /**
     * @param list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string}> $nextRows
     * @return array{textChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,rtrimKeyChangedRowids:list<int>}
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
        $rtrimKey = [];
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
            if ($current[$rowid]['rtrimKey'] !== $row['rtrimKey']) {
                $rtrimKey[] = $rowid;
            }
        }

        sort($text);
        sort($encoding);
        sort($bytes);
        sort($rtrimKey);

        return [
            'textChangedRowids' => $text,
            'encodingChangedRowids' => $encoding,
            'bytesChangedRowids' => $bytes,
            'rtrimKeyChangedRowids' => $rtrimKey,
        ];
    }

    /**
     * @param list<array{rowid:int}> $rows
     * @return list<int>
     */
    private static function rowids(array $rows): array
    {
        return array_column($rows, 'rowid');
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

    private static function normalizeEncoding(string $encoding): string
    {
        $encoding = strtoupper($encoding);

        return match ($encoding) {
            'UTF-8', 'UTF8' => 'UTF-8',
            'UTF-16', 'UTF-16LE', 'UTF16LE' => 'UTF-16LE',
            'UTF-16BE', 'UTF16BE' => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE pattern encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function encodingNumber(string $encoding): int
    {
        return match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        };
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
