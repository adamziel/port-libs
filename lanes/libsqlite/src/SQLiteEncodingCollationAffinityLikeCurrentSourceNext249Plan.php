<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressRtrimLikeSourcePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@248',
        string $nextSource = 'main.wp_options@249',
        int $currentSchemaCookie = 248,
        int $nextSchemaCookie = 249,
    ): array {
        if ($escape !== null && self::sqliteCharacterCount($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 ESCAPE must be one SQLite pattern character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $patternPlan['binaryRange'];
        $current = self::scanRows($currentRows, $pattern, $escape, $range);
        $next = self::scanRows($nextRows, $pattern, $escape, $range);
        $currentCandidates = self::rowids($current);
        $nextCandidates = self::rowids($next);
        $currentMatchedRows = array_values(array_filter($current, static fn (array $row): bool => $row['residualMatch']));
        $nextMatchedRows = array_values(array_filter($next, static fn (array $row): bool => $row['residualMatch']));
        $currentRejectedRows = array_values(array_filter($current, static fn (array $row): bool => !$row['residualMatch']));
        $nextRejectedRows = array_values(array_filter($next, static fn (array $row): bool => !$row['residualMatch']));
        $currentMatched = self::rowids($currentMatchedRows);
        $nextMatched = self::rowids($nextMatchedRows);
        $currentRejected = self::rowids($currentRejectedRows);
        $nextRejected = self::rowids($nextRejectedRows);
        $retainedCandidates = array_values(array_intersect($currentCandidates, $nextCandidates));
        $exitedCandidates = array_values(array_diff($currentCandidates, $nextCandidates));
        $enteredCandidates = array_values(array_diff($nextCandidates, $currentCandidates));
        $retainedMatched = array_values(array_intersect($currentMatched, $nextMatched));
        $exitedMatched = array_values(array_diff($currentMatched, $nextMatched));
        $enteredMatched = array_values(array_diff($nextMatched, $currentMatched));

        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $changedBytes = [];
        $changedEncoding = [];
        $changedResidual = [];
        foreach ($retainedCandidates as $rowid) {
            if (($currentByRowid[$rowid]['keyBytesHex'] ?? null) !== ($nextByRowid[$rowid]['keyBytesHex'] ?? null)) {
                $changedBytes[] = $rowid;
            }
            if (($currentByRowid[$rowid]['textEncoding'] ?? null) !== ($nextByRowid[$rowid]['textEncoding'] ?? null)) {
                $changedEncoding[] = $rowid;
            }
            if (($currentByRowid[$rowid]['residualMatch'] ?? null) !== ($nextByRowid[$rowid]['residualMatch'] ?? null)) {
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
        if ($enteredCandidates !== [] || $exitedCandidates !== []) {
            $reasons[] = 'candidate-rowset';
        }
        if ($enteredMatched !== [] || $exitedMatched !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedResidual !== []) {
            $reasons[] = 'rtrim-like-residual';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next249',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE RTRIM LIKE ? ESCAPE ? /* RTRIM range, LIKE residual */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'patternTokenHex' => self::tokenHexList($pattern),
            'patternCharacters' => self::sqliteCharacterCount($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => false,
            'collation' => 'RTRIM',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixTokenHex' => self::tokenHexList($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'hasWildcard' => $patternPlan['hasWildcard'],
            'rtrimRangeLowerInclusive' => $range['lowerInclusive'],
            'rtrimRangeUpperBound' => $range['upperBound'],
            'rtrimRangeLowerHex' => bin2hex($range['lowerInclusive']),
            'rtrimRangeUpperHex' => $range['upperBound'] === null ? null : bin2hex($range['upperBound']),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'retainedCandidateRowids' => $retainedCandidates,
            'exitedCandidateRowids' => $exitedCandidates,
            'enteredCandidateRowids' => $enteredCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedMatchedRowids' => $retainedMatched,
            'exitedMatchedRowids' => $exitedMatched,
            'enteredMatchedRowids' => $enteredMatched,
            'currentRtrimResidualRejectedRowids' => $currentRejected,
            'nextRtrimResidualRejectedRowids' => $nextRejected,
            'changedEncodedBytesRowids' => $changedBytes,
            'changedEncodingRowids' => $changedEncoding,
            'changedResidualRowids' => $changedResidual,
            'currentNames' => self::fieldByRowid($currentByRowid, 'key'),
            'nextNames' => self::fieldByRowid($nextByRowid, 'key'),
            'currentKeyBytesHex' => self::fieldByRowid($currentByRowid, 'keyBytesHex'),
            'nextKeyBytesHex' => self::fieldByRowid($nextByRowid, 'keyBytesHex'),
            'currentEncodings' => self::fieldByRowid($currentByRowid, 'textEncoding'),
            'nextEncodings' => self::fieldByRowid($nextByRowid, 'textEncoding'),
            'currentResidualMatches' => self::fieldByRowid($currentByRowid, 'residualMatch'),
            'nextResidualMatches' => self::fieldByRowid($nextByRowid, 'residualMatch'),
            'currentPositions' => self::fieldByRowid($currentByRowid, 'position'),
            'nextPositions' => self::fieldByRowid($nextByRowid, 'position'),
            'rtrimRangeMayAdmitPaddedKeys' => true,
            'likeResidualDoesNotUseRtrimCollation' => true,
            'escapedUnderscoreIsLiteralPrefix' => true,
            'utf16LeAndBeKeysCompareAfterDecode' => true,
            'blobAndNullStayOutsideEncodedCursor' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-like-escape-tokenizer',
                'sqlite-rtrim-collation-range',
                'sqlite-current-source-next249',
            ],
            'dependency_closure' => 'no new support component needed; reuses native mixed UTF source decoding, LIKE tokenization, RTRIM collation range checks, and current-source invalidation diagnostics',
            'non_overlap' => 'next249 covers RTRIM-collation LIKE range admission with trailing-space residual rejection across mixed UTF current/next sources; avoids accepted next245 dangling ESCAPE residuals, next244 mixed UTF NOCASE LIKE, Unicode GLOB ranges, UTF-16 malformed guards, SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int,residualMatch:bool}>
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, array $range): array
    {
        $ranged = [];
        foreach (self::normalizeRows($rows) as $position => $row) {
            $key = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            if (!self::inRtrimRange($key, $range)) {
                continue;
            }
            $ranged[] = [
                'rowid' => $row['option_id'],
                'key' => $key,
                'keyBytesHex' => bin2hex($row['option_name_bytes']),
                'textEncoding' => self::encodingName($row['text_encoding']),
                'payload' => $row,
                'position' => $position,
                'residualMatch' => SQLiteDatabase::likeMatches($key, $pattern, $escape, false),
            ];
        }

        usort($ranged, static fn (array $left, array $right): int => strcmp(rtrim($left['key'], ' '), rtrim($right['key'], ' ')) ?: $left['rowid'] <=> $right['rowid']);

        return $ranged;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 rows require integer option_id');
            }
            if (array_key_exists('option_name_bytes', $row)) {
                if (!is_string($row['option_name_bytes'])) {
                    throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 option_name_bytes must be a string');
                }
                if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                    throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 byte rows require integer text_encoding');
                }
                $normalized[] = $row;
                continue;
            }
            if (!array_key_exists('option_name', $row)) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 rows require option_name or option_name_bytes');
            }
            $value = $row['option_name'];
            if ($value === null || $value instanceof SQLiteBlobValue) {
                continue;
            }
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 option_name must be scalar text-affinity input');
            }
            $encoding = self::encodingCode($row['text_encoding'] ?? 'UTF-8');
            $row['option_name_bytes'] = SQLiteEncodingCollationSourceCursor::encodeText((string) $value, $encoding);
            $row['text_encoding'] = $encoding;
            $row['option_id'] = $row['option_id'] ?? $index + 1;
            $normalized[] = $row;
        }

        return $normalized;
    }

    private static function encodingCode(mixed $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }
        if (!is_string($encoding)) {
            throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite RTRIM LIKE next249 text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRtrimRange(string $key, array $range): bool
    {
        $key = rtrim($key, ' ');
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
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

    private static function sqliteCharacterCount(string $text): int
    {
        return count(self::sqliteCharacters($text));
    }

    /** @return list<string> */
    private static function tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::sqliteCharacters($text));
    }

    /** @return list<string> */
    private static function sqliteCharacters(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (preg_match('//u', $text) === 1) {
            $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
            if ($characters !== false) {
                return $characters;
            }
        }

        return str_split($text);
    }
}
