<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext260Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressRtrimCollationLikeResidualPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin_cache',
        ?string $escape = null,
        string $currentSource = 'main.wp_options@259',
        string $nextSource = 'main.wp_options@260',
        int $currentSchemaCookie = 259,
        int $nextSchemaCookie = 260,
    ): array {
        if ($escape !== null && self::sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite RTRIM LIKE next260 ESCAPE must be one SQLite character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $patternPlan['binaryRange'];
        $current = self::scan($currentRows, $pattern, $escape, $range);
        $next = self::scan($nextRows, $pattern, $escape, $range);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentRejected = self::rowids($current['rejected']);
        $nextRejected = self::rowids($next['rejected']);
        $currentTrace = self::rowsByRowid($current['trace']);
        $nextTrace = self::rowsByRowid($next['trace']);
        $retainedCandidates = self::intersectSorted($currentCandidates, $nextCandidates);
        $enteredCandidates = self::diffSorted($nextCandidates, $currentCandidates);
        $exitedCandidates = self::diffSorted($currentCandidates, $nextCandidates);

        $changedText = [];
        $changedBytes = [];
        $changedEncoding = [];
        $changedRtrimKey = [];
        $changedResidual = [];
        foreach (self::intersectSorted(array_keys($currentTrace), array_keys($nextTrace)) as $rowid) {
            if ($currentTrace[$rowid]['text'] !== $nextTrace[$rowid]['text']) {
                $changedText[] = $rowid;
            }
            if ($currentTrace[$rowid]['bytesHex'] !== $nextTrace[$rowid]['bytesHex']) {
                $changedBytes[] = $rowid;
            }
            if ($currentTrace[$rowid]['encoding'] !== $nextTrace[$rowid]['encoding']) {
                $changedEncoding[] = $rowid;
            }
            if ($currentTrace[$rowid]['rtrimKey'] !== $nextTrace[$rowid]['rtrimKey']) {
                $changedRtrimKey[] = $rowid;
            }
            if ($currentTrace[$rowid]['residualMatch'] !== $nextTrace[$rowid]['residualMatch']) {
                $changedResidual[] = $rowid;
            }
        }

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'candidate-rowset' => array_merge($enteredCandidates, $exitedCandidates),
            'matched-rowset' => $currentMatched === $nextMatched ? [] : array_values(array_unique(array_merge($currentMatched, $nextMatched))),
            'residual-result' => $changedResidual,
            'text-value' => $changedText,
            'text-bytes' => $changedBytes,
            'text-encoding' => $changedEncoding,
            'rtrim-collation-key' => $changedRtrimKey,
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['unknownRowids'] !== [] || $next['unknownRowids'] !== []) {
            $reasons[] = 'unknown-like';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next260',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE RTRIM LIKE ? /* RTRIM index candidates still require raw LIKE residual */',
            'pattern' => $pattern,
            'patternHex' => strtoupper(bin2hex($pattern)),
            'escape' => $escape,
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => strtoupper(bin2hex($patternPlan['prefix'])),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'rangeLowerInclusive' => $range['lowerInclusive'],
            'rangeUpperBound' => $range['upperBound'],
            'collation' => 'RTRIM',
            'rtrimCollationCanShareEqualityKey' => true,
            'likeResidualDoesNotTrimTrailingSpaces' => true,
            'rangeMayAdmitTrailingSpaceFalsePositives' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'retainedCandidateRowids' => $retainedCandidates,
            'enteredCandidateRowids' => $enteredCandidates,
            'exitedCandidateRowids' => $exitedCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentResidualRejectedRowids' => $currentRejected,
            'nextResidualRejectedRowids' => $nextRejected,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentText' => self::fieldByRowid($currentTrace, 'text'),
            'nextText' => self::fieldByRowid($nextTrace, 'text'),
            'currentBytesHex' => self::fieldByRowid($currentTrace, 'bytesHex'),
            'nextBytesHex' => self::fieldByRowid($nextTrace, 'bytesHex'),
            'currentEncodings' => self::fieldByRowid($currentTrace, 'encoding'),
            'nextEncodings' => self::fieldByRowid($nextTrace, 'encoding'),
            'currentRtrimKeys' => self::fieldByRowid($currentTrace, 'rtrimKey'),
            'nextRtrimKeys' => self::fieldByRowid($nextTrace, 'rtrimKey'),
            'changedTextRowids' => $changedText,
            'changedBytesRowids' => $changedBytes,
            'changedEncodingRowids' => $changedEncoding,
            'changedRtrimKeyRowids' => $changedRtrimKey,
            'changedResidualRowids' => $changedResidual,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-rtrim-collation-key',
                'sqlite-like-raw-residual',
                'sqlite-mixed-utf-source-decoder',
                'sqlite-current-source-next260',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-8/UTF-16 decode, LIKE residual matching, scalar text-affinity coercion, and RTRIM collation-key diagnostics',
            'non_overlap' => 'next260 covers RTRIM-collated LIKE candidate false positives caused by trailing spaces; avoids accepted next255 GLOB bracket fallback, next256 dynamic pattern affinity, Unicode GLOB ranges, UTF-16 malformed guards, JSON, WAL, VFS, B-tree, and SQL planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, array $range): array
    {
        $trace = [];
        $unknown = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name', $row) && !array_key_exists('option_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE next260 rows require option_name or option_name_bytes');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            try {
                $coerced = self::coerceText($row);
                if ($coerced === null) {
                    $unknown[] = $rowid;
                    continue;
                }
                $entry = [
                    'rowid' => $rowid,
                    'text' => $coerced['text'],
                    'bytesHex' => strtoupper(bin2hex($coerced['bytes'])),
                    'encoding' => $coerced['encoding'],
                    'storage' => $coerced['storage'],
                    'rtrimKey' => rtrim($coerced['text'], ' '),
                    'residualMatch' => SQLiteDatabase::likeMatches($coerced['text'], $pattern, $escape, true),
                ];
                $trace[] = $entry;
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, static fn (array $left, array $right): int => strcmp($left['rtrimKey'], $right['rtrimKey']) ?: strcmp($left['text'], $right['text']) ?: $left['rowid'] <=> $right['rowid']);
        sort($unknown);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $rejected = [];
        foreach ($trace as $entry) {
            if (!self::inRange($entry['rtrimKey'], $range)) {
                continue;
            }
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        return [
            'trace' => $trace,
            'candidates' => $candidates,
            'matched' => $matched,
            'rejected' => $rejected,
            'unknownRowids' => $unknown,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row @return array{text:string,bytes:string,encoding:string,storage:string}|null */
    private static function coerceText(array $row): ?array
    {
        if (array_key_exists('option_name_bytes', $row)) {
            if (!is_string($row['option_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE next260 byte rows require option_name_bytes and integer text_encoding');
            }
            $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);

            return [
                'text' => $text,
                'bytes' => $row['option_name_bytes'],
                'encoding' => self::encodingName($row['text_encoding']),
                'storage' => 'text',
            ];
        }

        $value = $row['option_name'];
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value) || is_bool($value)) {
            $text = (string) (int) $value;
            return ['text' => $text, 'bytes' => $text, 'encoding' => 'UTF-8', 'storage' => 'integer'];
        }
        if (is_float($value)) {
            $text = self::formatReal($value);
            return ['text' => $text, 'bytes' => $text, 'encoding' => 'UTF-8', 'storage' => 'real'];
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE next260 string option_name must be well-formed UTF-8');
            }

            return ['text' => $value, 'bytes' => $value, 'encoding' => 'UTF-8', 'storage' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite RTRIM LIKE next260 option_name must be scalar text-affinity input');
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $key, array $range): bool
    {
        return strcmp($key, $range['lowerInclusive']) >= 0 && ($range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0);
    }

    private static function formatReal(float $value): string
    {
        $formatted = sprintf('%.15G', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite RTRIM LIKE next260 text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function sqliteTextLength(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return strlen($text);
        }

        return count($characters);
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function intersectSorted(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function diffSorted(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }
}
