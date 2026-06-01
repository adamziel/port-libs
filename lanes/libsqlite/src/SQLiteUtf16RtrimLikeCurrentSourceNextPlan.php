<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16RtrimLikeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = true,
        string $currentSource = 'main.app_settings',
        string $nextSource = 'main.app_settings',
    ): array {
        $likePlan = SQLiteLikeCollationPlan::plan($pattern, 'RTRIM', $escape, $caseSensitiveLike);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentRowids = array_column($current['matchedRows'], 'rowid');
        $nextRowids = array_column($next['matchedRows'], 'rowid');
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
                $changedText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['encoding'] !== $nextByRowid[$rowid]['encoding']) {
                $changedEncoding[] = $rowid;
            }
            if ($currentByRowid[$rowid]['bytesHex'] !== $nextByRowid[$rowid]['bytesHex']) {
                $changedBytes[] = $rowid;
            }
        }
        sort($changedText);
        sort($changedEncoding);
        sort($changedBytes);

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
        if ($changedText !== []) {
            $reasons[] = 'text-value';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'operator' => 'LIKE',
            'collation' => 'RTRIM',
            'pattern' => $pattern,
            'escape' => $escape,
            'caseSensitiveLike' => $caseSensitiveLike,
            'range' => $likePlan['range'],
            'indexUsable' => false,
            'rejectedReason' => $likePlan['rejectedReason'],
            'residualScan' => true,
            'likeDoesNotTrimTrailingSpaces' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
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
                'sqlite-like-rtrim-residual-scan',
                'sqlite-current-source-nextoneTwoOne',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{
     *   decodedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   matchedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   malformedRowids:list<int>,
     *   errors:array<int,string>
     * }
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $decoded = [];
        $matched = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            if (!isset($row['setting_id']) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE rows require integer setting_id');
            }
            if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE rows require key_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE rows require integer text_encoding');
            }

            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $encoding = self::encodingName($row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
                continue;
            }

            $entry = [
                'rowid' => $row['setting_id'],
                'text' => $text,
                'rtrimKey' => rtrim($text, ' '),
                'encoding' => $encoding,
                'bytesHex' => bin2hex($row['key_name_bytes']),
                'payload' => $row,
            ];
            $decoded[] = $entry;
            if (SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)) {
                $matched[] = $entry;
            }
        }

        usort($decoded, self::sortRows(...));
        usort($matched, self::sortRows(...));

        return [
            'decodedRows' => $decoded,
            'matchedRows' => $matched,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /**
     * @param list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}> $rows
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
