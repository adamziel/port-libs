<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16LikeRtrimCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = true,
        string $currentSource = 'main.wp_options@current',
        string $nextSource = 'main.wp_options@next',
    ): array {
        $likePlan = SQLiteLikeCollationPlan::plan($pattern, 'RTRIM', $escape, $caseSensitiveLike);
        $current = self::scanSource($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::scanSource($nextRows, $pattern, $escape, $caseSensitiveLike);
        $changes = self::sourceChanges($current['decodedRows'], $next['decodedRows']);

        $currentMatchedRowids = self::rowids($current['matchedRows']);
        $nextMatchedRowids = self::rowids($next['matchedRows']);
        $currentCandidateRowids = self::rowids($current['candidateRows']);
        $nextCandidateRowids = self::rowids($next['candidateRows']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($likePlan['range'] !== null || $likePlan['indexUsable']) {
            $reasons[] = 'unexpected-rtrim-range';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatchedRowids !== $nextMatchedRowids) {
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
        if ($changes['rtrimKeyChangedRowids'] !== []) {
            $reasons[] = 'rtrim-key';
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
            'candidateSource' => 'full-scan',
            'likeResidualDoesNotApplyRtrim' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'currentRowids' => $currentMatchedRowids,
            'nextRowids' => $nextMatchedRowids,
            'currentResidualRejectedRowids' => self::rowids($current['residualRejectedRows']),
            'nextResidualRejectedRowids' => self::rowids($next['residualRejectedRows']),
            'retainedRowids' => array_values(array_intersect($currentMatchedRowids, $nextMatchedRowids)),
            'enteredRowids' => array_values(array_diff($nextMatchedRowids, $currentMatchedRowids)),
            'exitedRowids' => array_values(array_diff($currentMatchedRowids, $nextMatchedRowids)),
            'currentDecodedRows' => $current['decodedRows'],
            'nextDecodedRows' => $next['decodedRows'],
            'currentPlanSteps' => $current['planSteps'],
            'nextPlanSteps' => $next['planSteps'],
            'currentComparisonKeys' => self::mapByRowid($current['decodedRows'], 'rtrimKey'),
            'nextComparisonKeys' => self::mapByRowid($next['decodedRows'], 'rtrimKey'),
            'currentEncodings' => self::mapByRowid($current['decodedRows'], 'encoding'),
            'nextEncodings' => self::mapByRowid($next['decodedRows'], 'encoding'),
            'currentBytesHex' => self::mapByRowid($current['decodedRows'], 'bytesHex'),
            'nextBytesHex' => self::mapByRowid($next['decodedRows'], 'bytesHex'),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'retainedTextChangedRowids' => $changes['textChangedRowids'],
            'retainedEncodingChangedRowids' => $changes['encodingChangedRowids'],
            'retainedBytesChangedRowids' => $changes['bytesChangedRowids'],
            'retainedRtrimKeyChangedRowids' => $changes['rtrimKeyChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-rtrim-full-scan-current-next',
                'sqlite-like-residual-byte-preserving',
                'sqlite-current-source-next137',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-8/UTF-16 decoding, LIKE residual matching, RTRIM comparison-key metadata, and current-source invalidation state',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{
     *   decodedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   candidateRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   residualRejectedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   matchedRows:list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   planSteps:list<array{position:int,rowid:int,nextRowid:?int,text:string,nextText:?string,rtrimKey:string,nextRtrimKey:?string,residualMatch:bool,nextResidualMatch:?bool}>,
     *   malformedRowids:list<int>,
     *   errors:array<int,string>
     * }
     */
    private static function scanSource(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE current-source rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE current-source rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM LIKE current-source rows require integer text_encoding');
            }

            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $encoding = self::encodingName($row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
                continue;
            }

            $decoded[] = [
                'rowid' => $row['option_id'],
                'text' => $text,
                'rtrimKey' => rtrim($text, ' '),
                'encoding' => $encoding,
                'bytesHex' => bin2hex($row['option_name_bytes']),
                'payload' => $row,
            ];
        }

        usort($decoded, self::sortRows(...));

        $matched = [];
        $rejected = [];
        $steps = [];
        foreach ($decoded as $position => $row) {
            $match = SQLiteDatabase::likeMatches($row['text'], $pattern, $escape, $caseSensitiveLike);
            $next = $decoded[$position + 1] ?? null;
            $steps[] = [
                'position' => $position,
                'rowid' => $row['rowid'],
                'nextRowid' => $next['rowid'] ?? null,
                'text' => $row['text'],
                'nextText' => $next['text'] ?? null,
                'rtrimKey' => $row['rtrimKey'],
                'nextRtrimKey' => $next['rtrimKey'] ?? null,
                'residualMatch' => $match,
                'nextResidualMatch' => $next === null ? null : SQLiteDatabase::likeMatches($next['text'], $pattern, $escape, $caseSensitiveLike),
            ];
            if ($match) {
                $matched[] = $row;
            } else {
                $rejected[] = $row;
            }
        }

        return [
            'decodedRows' => $decoded,
            'candidateRows' => $decoded,
            'residualRejectedRows' => $rejected,
            'matchedRows' => $matched,
            'planSteps' => $steps,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
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
     * @param list<array{rowid:int}> $rows
     * @return array<int,mixed>
     */
    private static function mapByRowid(array $rows, string $field): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$field];
        }

        return $mapped;
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
        $key = [];
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
                $key[] = $rowid;
            }
        }

        sort($text);
        sort($encoding);
        sort($bytes);
        sort($key);

        return [
            'textChangedRowids' => $text,
            'encodingChangedRowids' => $encoding,
            'bytesChangedRowids' => $bytes,
            'rtrimKeyChangedRowids' => $key,
        ];
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
