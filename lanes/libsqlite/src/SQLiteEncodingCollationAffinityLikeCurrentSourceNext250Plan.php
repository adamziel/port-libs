<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext250Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressRtrimLikeResidualPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@249',
        string $nextSource = 'main.wp_options@250',
        int $currentSchemaCookie = 249,
        int $nextSchemaCookie = 250,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentMatched = array_values(array_filter($current, static fn (array $row): bool => $row['residualMatch']));
        $nextMatched = array_values(array_filter($next, static fn (array $row): bool => $row['residualMatch']));
        $currentRejectedPeers = self::rtrimPeerRowids($current);
        $nextRejectedPeers = self::rtrimPeerRowids($next);
        $currentRowids = self::rowids($currentMatched);
        $nextRowids = self::rowids($nextMatched);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $changedRaw = [];
        $changedRawBytes = [];
        $changedRtrimKey = [];
        $changedEncoding = [];
        $changedStorage = [];
        $changedResidualTruth = [];

        foreach (array_values(array_intersect(array_keys($currentByRowid), array_keys($nextByRowid))) as $rowid) {
            if ($currentByRowid[$rowid]['likeText'] !== $nextByRowid[$rowid]['likeText']) {
                $changedRaw[] = $rowid;
            }
            if ($currentByRowid[$rowid]['likeTextHex'] !== $nextByRowid[$rowid]['likeTextHex']) {
                $changedRawBytes[] = $rowid;
            }
            if ($currentByRowid[$rowid]['rtrimKey'] !== $nextByRowid[$rowid]['rtrimKey']) {
                $changedRtrimKey[] = $rowid;
            }
            if ($currentByRowid[$rowid]['textEncoding'] !== $nextByRowid[$rowid]['textEncoding']) {
                $changedEncoding[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storageClass'] !== $nextByRowid[$rowid]['storageClass']) {
                $changedStorage[] = $rowid;
            }
            if ($currentByRowid[$rowid]['residualMatch'] !== $nextByRowid[$rowid]['residualMatch']) {
                $changedResidualTruth[] = $rowid;
            }
        }
        sort($changedRaw);
        sort($changedRawBytes);
        sort($changedRtrimKey);
        sort($changedEncoding);
        sort($changedStorage);
        sort($changedResidualTruth);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'raw-like-text' => $changedRaw,
            'raw-like-bytes' => $changedRawBytes,
            'rtrim-collation-key' => $changedRtrimKey,
            'text-encoding' => $changedEncoding,
            'storage-class' => $changedStorage,
            'residual-truth' => $changedResidualTruth,
            'matched-rowset' => ($entered !== [] || $exited !== []) ? array_values(array_unique(array_merge($entered, $exited))) : [],
            'rtrim-peer-rejections' => $currentRejectedPeers === $nextRejectedPeers ? [] : array_values(array_unique(array_merge($currentRejectedPeers, $nextRejectedPeers))),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next250',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE RTRIM LIKE ? ESCAPE ? /* RTRIM key never trims LIKE residual */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => 'RTRIM',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'binaryRange' => $patternPlan['binaryRange'],
            'noCaseRange' => $patternPlan['noCaseRange'],
            'rtrimIndexMayFindTrailingSpacePeers' => true,
            'likeResidualUsesRawTextBeforeRtrimCollation' => true,
            'tabIsNotRtrimSpace' => true,
            'asciiNoCaseLikeStillFoldsAscii' => !$caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::rowids($current),
            'nextCandidateRowids' => self::rowids($next),
            'currentMatchedRowids' => $currentRowids,
            'nextMatchedRowids' => $nextRowids,
            'currentRtrimPeerRejectedRowids' => $currentRejectedPeers,
            'nextRtrimPeerRejectedRowids' => $nextRejectedPeers,
            'retainedRowids' => $retained,
            'enteredRowids' => $entered,
            'exitedRowids' => $exited,
            'changedRawLikeTextRowids' => $changedRaw,
            'changedRawLikeBytesRowids' => $changedRawBytes,
            'changedRtrimKeyRowids' => $changedRtrimKey,
            'changedEncodingRowids' => $changedEncoding,
            'changedStorageClassRowids' => $changedStorage,
            'changedResidualTruthRowids' => $changedResidualTruth,
            'currentRawText' => self::fieldByRowid($currentByRowid, 'likeText'),
            'nextRawText' => self::fieldByRowid($nextByRowid, 'likeText'),
            'currentRawHex' => self::fieldByRowid($currentByRowid, 'likeTextHex'),
            'nextRawHex' => self::fieldByRowid($nextByRowid, 'likeTextHex'),
            'currentRtrimKeys' => self::fieldByRowid($currentByRowid, 'rtrimKey'),
            'nextRtrimKeys' => self::fieldByRowid($nextByRowid, 'rtrimKey'),
            'currentEncodings' => self::fieldByRowid($currentByRowid, 'textEncoding'),
            'nextEncodings' => self::fieldByRowid($nextByRowid, 'textEncoding'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storageClass'),
            'currentTrace' => self::traceByPosition($current),
            'nextTrace' => self::traceByPosition($next),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-escape-tokenizer',
                'sqlite-rtrim-collation-key',
                'sqlite-like-residual-raw-text',
                'sqlite-mixed-utf-source-decoder',
                'sqlite-current-source-next250',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, RTRIM collation-key diagnostics, mixed UTF decoding, and current-source invalidation tracking',
            'non_overlap' => 'next250 covers RTRIM collation peers that must still fail raw LIKE residuals when trailing spaces remain; avoids next247 non-ASCII NOCASE prefixes, next246 dynamic ESCAPE affinity, next241 embedded-NUL/malformed-byte LIKE, numeric option_value LIKE next240, Unicode GLOB ranges, UTF-16 malformed guards, and SQL/JSON/WAL/VFS/B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $scanned = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name', $row) && !array_key_exists('option_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE next250 rows require option_name or option_name_bytes');
            }
            $coerced = self::coerceLikeText($row, $index);
            if ($coerced === null) {
                continue;
            }
            $likeText = $coerced['likeText'];
            $scanned[] = [
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'likeText' => $likeText,
                'likeTextHex' => bin2hex($likeText),
                'rtrimKey' => rtrim($likeText, ' '),
                'rtrimKeyHex' => bin2hex(rtrim($likeText, ' ')),
                'textEncoding' => $coerced['textEncoding'],
                'storageClass' => $coerced['storageClass'],
                'residualMatch' => SQLiteDatabase::likeMatches($likeText, $pattern, $escape, $caseSensitiveLike),
            ];
        }

        usort($scanned, static fn (array $left, array $right): int => strcmp($left['rtrimKey'], $right['rtrimKey']) ?: strcmp($left['likeText'], $right['likeText']) ?: $left['rowid'] <=> $right['rowid']);

        return $scanned;
    }

    /** @param array<string,mixed> $row @return array{likeText:string,textEncoding:string,storageClass:string}|null */
    private static function coerceLikeText(array $row, int $index): ?array
    {
        if (array_key_exists('option_name_bytes', $row)) {
            if (!is_string($row['option_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE next250 byte rows require option_name_bytes and integer text_encoding');
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
                throw new \InvalidArgumentException('SQLite RTRIM LIKE next250 string option_name must be well-formed UTF-8');
            }

            return ['likeText' => $value, 'textEncoding' => 'UTF-8', 'storageClass' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite RTRIM LIKE next250 option_name must be scalar text-affinity input');
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
            default => throw new \InvalidArgumentException('SQLite RTRIM LIKE next250 text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rtrimPeerRowids(array $rows): array
    {
        $matchedKeys = [];
        foreach ($rows as $row) {
            if ($row['residualMatch']) {
                $matchedKeys[$row['rtrimKey']] = true;
            }
        }

        $peers = [];
        foreach ($rows as $row) {
            if (!$row['residualMatch'] && isset($matchedKeys[$row['rtrimKey']])) {
                $peers[] = $row['rowid'];
            }
        }

        return $peers;
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
