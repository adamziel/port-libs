<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext244Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressUtf16OptionNameLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $collation = 'NOCASE',
        string $currentSource = 'main.wp_options@243',
        string $nextSource = 'main.wp_options@244',
        int $currentSchemaCookie = 243,
        int $nextSchemaCookie = 244,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite LIKE next244 collation: {$collation}");
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike, $collation);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike, $collation);
        $currentMatched = array_values(array_filter($current, static fn (array $row): bool => $row['residualMatch']));
        $nextMatched = array_values(array_filter($next, static fn (array $row): bool => $row['residualMatch']));
        $currentRowids = self::rowids($currentMatched);
        $nextRowids = self::rowids($nextMatched);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $currentByRowid = self::rowsByRowid($currentMatched);
        $nextByRowid = self::rowsByRowid($nextMatched);
        $changedBytes = [];
        $changedEncoding = [];
        foreach ($retained as $rowid) {
            if (($currentByRowid[$rowid]['keyBytesHex'] ?? null) !== ($nextByRowid[$rowid]['keyBytesHex'] ?? null)) {
                $changedBytes[] = $rowid;
            }
            if (($currentByRowid[$rowid]['textEncoding'] ?? null) !== ($nextByRowid[$rowid]['textEncoding'] ?? null)) {
                $changedEncoding[] = $rowid;
            }
        }

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }
        $currentRejectedRowids = self::rowids(array_values(array_filter($current, static fn (array $row): bool => !$row['residualMatch'])));
        $nextRejectedRowids = self::rowids(array_values(array_filter($next, static fn (array $row): bool => !$row['residualMatch'])));
        if ($currentRejectedRowids !== $nextRejectedRowids) {
            $reasons[] = 'range-residual';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next244',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE ' . $collation . ' LIKE ? ESCAPE ? /* mixed UTF source cursor */',
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
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::rowids($current),
            'nextCandidateRowids' => self::rowids($next),
            'currentMatchedRowids' => $currentRowids,
            'nextMatchedRowids' => $nextRowids,
            'currentResidualRejectedRowids' => $currentRejectedRowids,
            'nextResidualRejectedRowids' => $nextRejectedRowids,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedEncodedBytesRowids' => $changedBytes,
            'changedEncodingRowids' => $changedEncoding,
            'currentNames' => self::fieldByRowid($currentByRowid, 'key'),
            'nextNames' => self::fieldByRowid($nextByRowid, 'key'),
            'currentKeyBytesHex' => self::fieldByRowid($currentByRowid, 'keyBytesHex'),
            'nextKeyBytesHex' => self::fieldByRowid($nextByRowid, 'keyBytesHex'),
            'currentEncodings' => self::fieldByRowid($currentByRowid, 'textEncoding'),
            'nextEncodings' => self::fieldByRowid($nextByRowid, 'textEncoding'),
            'asciiNoCaseDoesNotFoldAccents' => true,
            'utf16LeAndBeKeysCompareAfterDecode' => true,
            'likeIgnoresRtrimCollationForResidual' => true,
            'blobAndNullStayOutsideTextAffinityScan' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-like-escape-tokenizer',
                'sqlite-nocase-ascii-collation',
                'sqlite-current-source-next244',
            ],
            'dependency_closure' => 'no new support component needed; reuses native mixed UTF source decoding, LIKE tokenization, ASCII-only NOCASE comparison, and current-source invalidation diagnostics',
            'non_overlap' => 'next244 covers mixed UTF-8/UTF-16 option_name LIKE current-source invalidation with ASCII-only NOCASE around accented bytes; avoids accepted next240 numeric LIKE, next241 embedded-NUL/malformed byte LIKE, Unicode GLOB ranges, UTF-16 malformed guards, UTF-16 RTRIM cursor fences, SQL executor, JSON, WAL, VFS, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int,residualMatch:bool}>
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, string $collation): array
    {
        return SQLiteEncodingCollationSourceCursor::wordpressOptionNameRangeScan(
            self::normalizeRows($rows),
            $pattern,
            'LIKE',
            $collation,
            $escape,
            $caseSensitiveLike,
        );
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
                throw new \InvalidArgumentException('SQLite LIKE next244 rows require integer option_id');
            }
            if (array_key_exists('option_name_bytes', $row)) {
                if (!is_string($row['option_name_bytes'])) {
                    throw new \InvalidArgumentException('SQLite LIKE next244 option_name_bytes must be a string');
                }
                if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                    throw new \InvalidArgumentException('SQLite LIKE next244 byte rows require integer text_encoding');
                }
                $normalized[] = $row;
                continue;
            }
            if (!array_key_exists('option_name', $row)) {
                throw new \InvalidArgumentException('SQLite LIKE next244 rows require option_name or option_name_bytes');
            }
            $value = $row['option_name'];
            if ($value === null || $value instanceof SQLiteBlobValue) {
                continue;
            }
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('SQLite LIKE next244 option_name must be scalar text-affinity input');
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
            throw new \InvalidArgumentException('SQLite LIKE next244 text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }
        if (!is_string($encoding)) {
            throw new \InvalidArgumentException('SQLite LIKE next244 text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite LIKE next244 text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
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
}
