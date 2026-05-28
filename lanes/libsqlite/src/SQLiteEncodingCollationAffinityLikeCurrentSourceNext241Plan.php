<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext241Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameByteAwareLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@240',
        string $nextSource = 'main.wp_options@241',
        int $currentSchemaCookie = 240,
        int $nextSchemaCookie = 241,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $caseSensitiveLike ? $patternPlan['binaryRange'] : $patternPlan['noCaseRange'];
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike, $range);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike, $range);

        $currentRowids = self::rowids($current['matched']);
        $nextRowids = self::rowids($next['matched']);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $currentByRowid = self::rowsByRowid($current['matched']);
        $nextByRowid = self::rowsByRowid($next['matched']);
        $changedBytes = [];
        foreach ($retained as $rowid) {
            if (($currentByRowid[$rowid]['nameHex'] ?? null) !== ($nextByRowid[$rowid]['nameHex'] ?? null)) {
                $changedBytes[] = $rowid;
            }
        }

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'option-name-bytes';
        }
        if (self::rowids($current['candidates']) !== $currentRowids || self::rowids($next['candidates']) !== $nextRowids) {
            $reasons[] = 'range-residual';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next241',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE NOCASE LIKE ? ESCAPE ? /* byte-aware residual cursor */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'hasWildcard' => $patternPlan['hasWildcard'],
            'range' => $range,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::rowids($current['candidates']),
            'nextCandidateRowids' => self::rowids($next['candidates']),
            'currentMatchedRowids' => $currentRowids,
            'nextMatchedRowids' => $nextRowids,
            'currentResidualRejectedRowids' => self::rowids($current['rejected']),
            'nextResidualRejectedRowids' => self::rowids($next['rejected']),
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedNameBytesRowids' => $changedBytes,
            'currentNames' => self::fieldByRowid($currentByRowid, 'name'),
            'nextNames' => self::fieldByRowid($nextByRowid, 'name'),
            'currentNameHex' => self::fieldByRowid($currentByRowid, 'nameHex'),
            'nextNameHex' => self::fieldByRowid($nextByRowid, 'nameHex'),
            'currentTokenHex' => self::fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTokenCounts' => self::fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::fieldByRowid($nextByRowid, 'tokenCount'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storage'),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentMalformedHex' => $current['malformedHex'],
            'nextMalformedHex' => $next['malformedHex'],
            'nulByteIsNotTerminator' => true,
            'malformedUtf8FallsBackToByteTokens' => true,
            'blobAffinityDoesNotParticipate' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-byte-tokenizer',
                'sqlite-text-affinity',
                'sqlite-nocase-ascii-collation',
                'sqlite-current-source-next241',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, text affinity coercion, byte fallback for malformed UTF-8, and current-source invalidation diagnostics',
            'non_overlap' => 'next241 covers embedded-NUL and malformed-byte LIKE residual cursor behavior over option_name; avoids accepted escaped wildcard next236, dynamic option_value LIKE next238, Unicode GLOB ranges, UTF-16 malformed guards, UTF-16 NOCASE/RTRIM cursor fences, and SQL executor/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{lowerInclusive:string,upperBound:?string} $range
     * @return array{candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,malformedRowids:list<int>,malformedHex:array<int,string>}
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, array $range): array
    {
        $candidates = [];
        $matched = [];
        $rejected = [];
        $malformedRowids = [];
        $malformedHex = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name', $row)) {
                throw new \InvalidArgumentException('SQLite byte-aware LIKE next241 row requires option_name');
            }
            $coerced = self::coerceText($row['option_name']);
            if ($coerced === null) {
                continue;
            }
            [$name, $storage] = $coerced;
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            if (preg_match('//u', $name) !== 1) {
                $malformedRowids[] = $rowid;
                $malformedHex[$rowid] = bin2hex($name);
            }
            if (!self::withinRange($name, $range, $caseSensitiveLike)) {
                continue;
            }

            $entry = [
                'rowid' => $rowid,
                'name' => $name,
                'nameHex' => bin2hex($name),
                'tokenHex' => self::tokenHexList($name),
                'tokenCount' => self::sqlitePatternLength($name),
                'storage' => $storage,
            ];
            $candidates[] = $entry;
            if (SQLiteDatabase::likeMatches($name, $pattern, $escape, $caseSensitiveLike)) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp($left['name'], $right['name']) ?: $left['rowid'] <=> $right['rowid'];
        usort($candidates, $sort);
        usort($matched, $sort);
        usort($rejected, $sort);

        return [
            'candidates' => $candidates,
            'matched' => $matched,
            'rejected' => $rejected,
            'malformedRowids' => $malformedRowids,
            'malformedHex' => $malformedHex,
        ];
    }

    /** @return null|array{0:string,1:string} */
    private static function coerceText(mixed $value): ?array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_string($value)) {
            return [$value, 'text'];
        }
        if (is_int($value)) {
            return [(string) $value, 'integer'];
        }
        if (is_float($value)) {
            return [rtrim(rtrim(sprintf('%.15G', $value), '0'), '.'), 'real'];
        }
        if (is_bool($value)) {
            return [$value ? '1' : '0', 'integer'];
        }

        throw new \InvalidArgumentException('SQLite byte-aware LIKE next241 option_name must be scalar text-affinity input');
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function withinRange(string $value, array $range, bool $caseSensitiveLike): bool
    {
        $key = $caseSensitiveLike ? $value : self::asciiLower($value);
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

    private static function sqlitePatternLength(string $text): int
    {
        return count(self::sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function sqlitePatternCharacters(string $text): array
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

        $characters = [];
        $length = strlen($text);
        for ($offset = 0; $offset < $length;) {
            $byte = ord($text[$offset]);
            $sequenceLength = match (true) {
                $byte < 0x80 => 1,
                $byte >= 0xc2 && $byte <= 0xdf => 2,
                $byte >= 0xe0 && $byte <= 0xef => 3,
                $byte >= 0xf0 && $byte <= 0xf4 => 4,
                default => 1,
            };
            $sequence = substr($text, $offset, $sequenceLength);
            if ($sequenceLength > 1 && strlen($sequence) === $sequenceLength && preg_match('//u', $sequence) === 1) {
                $characters[] = $sequence;
                $offset += $sequenceLength;
                continue;
            }
            $characters[] = $text[$offset];
            $offset++;
        }

        return $characters;
    }

    private static function asciiLower(string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($bytes[$i]);
            if ($ord >= 0x41 && $ord <= 0x5a) {
                $bytes[$i] = chr($ord + 0x20);
            }
        }

        return $bytes;
    }
}
