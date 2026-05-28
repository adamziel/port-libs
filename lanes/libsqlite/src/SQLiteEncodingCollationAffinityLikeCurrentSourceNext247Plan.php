<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext247Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressUnicodeNoCaseLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $collation = 'NOCASE',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@246',
        string $nextSource = 'main.wp_options@247',
        int $currentSchemaCookie = 246,
        int $nextSchemaCookie = 247,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite LIKE next247 collation: {$collation}");
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentMatched = array_values(array_filter($current, static fn (array $row): bool => $row['residualMatch']));
        $nextMatched = array_values(array_filter($next, static fn (array $row): bool => $row['residualMatch']));
        $currentRowids = self::rowids($currentMatched);
        $nextRowids = self::rowids($nextMatched);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $currentByRowid = self::rowsByRowid($currentMatched);
        $nextByRowid = self::rowsByRowid($nextMatched);
        $changedText = [];
        $changedBytes = [];
        $changedEncoding = [];
        $changedStorage = [];

        foreach ($retained as $rowid) {
            if (($currentByRowid[$rowid]['likeText'] ?? null) !== ($nextByRowid[$rowid]['likeText'] ?? null)) {
                $changedText[] = $rowid;
            }
            if (($currentByRowid[$rowid]['likeTextHex'] ?? null) !== ($nextByRowid[$rowid]['likeTextHex'] ?? null)) {
                $changedBytes[] = $rowid;
            }
            if (($currentByRowid[$rowid]['textEncoding'] ?? null) !== ($nextByRowid[$rowid]['textEncoding'] ?? null)) {
                $changedEncoding[] = $rowid;
            }
            if (($currentByRowid[$rowid]['storageClass'] ?? null) !== ($nextByRowid[$rowid]['storageClass'] ?? null)) {
                $changedStorage[] = $rowid;
            }
        }

        $indexUsable = self::likeIndexUsable($patternPlan, $collation, $caseSensitiveLike);
        $currentRejected = self::rowids(array_values(array_filter($current, static fn (array $row): bool => !$row['residualMatch'])));
        $nextRejected = self::rowids(array_values(array_filter($next, static fn (array $row): bool => !$row['residualMatch'])));
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }
        if ($changedText !== []) {
            $reasons[] = 'like-text';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($currentRejected !== $nextRejected) {
            $reasons[] = 'residual-rejections';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next247',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE ' . $collation . ' LIKE ? ESCAPE ? /* non-ASCII prefix keeps residual authoritative */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $collation,
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'hasWildcard' => $patternPlan['hasWildcard'],
            'binaryRange' => $patternPlan['binaryRange'],
            'noCaseRange' => $patternPlan['noCaseRange'],
            'indexUsable' => $indexUsable,
            'rangeRejectedReason' => $indexUsable ? null : self::rangeRejectedReason($patternPlan, $collation, $caseSensitiveLike),
            'rangeLowerInclusive' => $indexUsable ? $patternPlan[$caseSensitiveLike ? 'binaryRange' : 'noCaseRange']['lowerInclusive'] ?? null : null,
            'rangeUpperBound' => $indexUsable ? $patternPlan[$caseSensitiveLike ? 'binaryRange' : 'noCaseRange']['upperBound'] ?? null : null,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::rowids($current),
            'nextCandidateRowids' => self::rowids($next),
            'currentMatchedRowids' => $currentRowids,
            'nextMatchedRowids' => $nextRowids,
            'currentResidualRejectedRowids' => $currentRejected,
            'nextResidualRejectedRowids' => $nextRejected,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedLikeTextRowids' => $changedText,
            'changedEncodedBytesRowids' => $changedBytes,
            'changedEncodingRowids' => $changedEncoding,
            'changedStorageClassRowids' => $changedStorage,
            'currentNames' => self::fieldByRowid($currentByRowid, 'likeText'),
            'nextNames' => self::fieldByRowid($nextByRowid, 'likeText'),
            'currentNameHex' => self::fieldByRowid($currentByRowid, 'likeTextHex'),
            'nextNameHex' => self::fieldByRowid($nextByRowid, 'likeTextHex'),
            'currentEncodings' => self::fieldByRowid($currentByRowid, 'textEncoding'),
            'nextEncodings' => self::fieldByRowid($nextByRowid, 'textEncoding'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storageClass'),
            'currentTrace' => self::traceByPosition($current),
            'nextTrace' => self::traceByPosition($next),
            'asciiNoCaseFoldsOnlyAscii' => true,
            'nonAsciiPrefixDisablesNoCaseRange' => $collation === 'NOCASE' && !$caseSensitiveLike && !$patternPlan['prefixIsAscii'],
            'unicodeLikeResidualRemainsCaseSensitiveForAccents' => true,
            'utf16LeAndBeDecodeBeforeLike' => true,
            'numericTextAffinityRunsBeforeLike' => true,
            'blobAndNullStayOutsideLike' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-escape-tokenizer',
                'sqlite-mixed-utf-source-decoder',
                'sqlite-nocase-ascii-collation',
                'sqlite-text-affinity-like',
                'sqlite-current-source-next247',
            ],
            'dependency_closure' => 'no new support component needed; reuses lane-local LIKE tokenization, mixed UTF decoding, ASCII-only NOCASE semantics, and text-affinity diagnostics',
            'non_overlap' => 'next247 covers non-ASCII LIKE prefixes under NOCASE with mixed UTF and scalar text affinity; avoids accepted Unicode GLOB ranges, UTF-16 malformed guards, numeric option_value LIKE next240, byte/NUL option_name LIKE next241, mixed UTF ASCII-prefix LIKE next244, SQL executor, JSON, WAL, VFS, and B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $scanned = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name', $row) && !array_key_exists('option_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite LIKE next247 rows require option_name or option_name_bytes');
            }
            $coerced = self::coerceLikeText($row, $index);
            if ($coerced === null) {
                continue;
            }
            $scanned[] = [
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'likeText' => $coerced['likeText'],
                'likeTextHex' => bin2hex($coerced['likeText']),
                'textEncoding' => $coerced['textEncoding'],
                'storageClass' => $coerced['storageClass'],
                'residualMatch' => SQLiteDatabase::likeMatches($coerced['likeText'], $pattern, $escape, $caseSensitiveLike),
            ];
        }

        usort($scanned, static fn (array $left, array $right): int => strcmp($left['likeText'], $right['likeText']) ?: $left['rowid'] <=> $right['rowid']);

        return $scanned;
    }

    /** @param array<string,mixed> $row @return array{likeText:string,textEncoding:string,storageClass:string}|null */
    private static function coerceLikeText(array $row, int $index): ?array
    {
        if (array_key_exists('option_name_bytes', $row)) {
            if (!is_string($row['option_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite LIKE next247 byte rows require option_name_bytes and integer text_encoding');
            }
            return [
                'likeText' => SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']),
                'textEncoding' => self::encodingName($row['text_encoding']),
                'storageClass' => 'text',
            ];
        }

        $value = $row['option_name'];
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value)) {
            return ['likeText' => (string) $value, 'textEncoding' => 'UTF-8', 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            return ['likeText' => self::formatReal($value), 'textEncoding' => 'UTF-8', 'storageClass' => 'real'];
        }
        if (is_bool($value)) {
            return ['likeText' => $value ? '1' : '0', 'textEncoding' => 'UTF-8', 'storageClass' => 'integer'];
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite LIKE next247 string option_name must be well-formed UTF-8');
            }
            return ['likeText' => $value, 'textEncoding' => 'UTF-8', 'storageClass' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite LIKE next247 option_name must be scalar text-affinity input');
    }

    /** @param array<string,mixed> $patternPlan */
    private static function likeIndexUsable(array $patternPlan, string $collation, bool $caseSensitiveLike): bool
    {
        if ($patternPlan['prefix'] === '' || !$patternPlan['prefixIsAscii']) {
            return false;
        }
        if ($caseSensitiveLike) {
            return $collation === 'BINARY';
        }

        return $collation === 'NOCASE';
    }

    /** @param array<string,mixed> $patternPlan */
    private static function rangeRejectedReason(array $patternPlan, string $collation, bool $caseSensitiveLike): string
    {
        if ($patternPlan['prefix'] === '') {
            return 'no_literal_prefix';
        }
        if (!$patternPlan['prefixIsAscii']) {
            return 'non_ascii_prefix_requires_residual_scan';
        }
        if ($caseSensitiveLike && $collation !== 'BINARY') {
            return 'case_sensitive_like_requires_binary_index';
        }
        if (!$caseSensitiveLike && $collation !== 'NOCASE') {
            return 'default_like_requires_nocase_index';
        }

        return 'range_not_available';
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
            default => throw new \InvalidArgumentException('SQLite LIKE next247 text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
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

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function traceByPosition(array $rows): array
    {
        $trace = [];
        foreach ($rows as $position => $row) {
            $trace[$position] = $row;
        }

        return $trace;
    }
}
