<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext236Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEscapedLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@235',
        string $nextSource = 'main.wp_options@236',
        int $currentSchemaCookie = 235,
        int $nextSchemaCookie = 236,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $changedNameBytes = [];
        foreach ($retained as $rowid) {
            if ($currentByRowid[$rowid]['nameHex'] !== $nextByRowid[$rowid]['nameHex']) {
                $changedNameBytes[] = $rowid;
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
        if ($changedNameBytes !== []) {
            $reasons[] = 'option-name-bytes';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next236',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE NOCASE LIKE ? ESCAPE ? /* escaped wildcard current-source fence */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'patternCharacters' => $patternPlan['prefixCharacters'] + ($patternPlan['hasWildcard'] ? 1 : 0),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
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
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedNameBytesRowids' => $changedNameBytes,
            'currentNames' => self::fieldByRowid($currentByRowid, 'name'),
            'nextNames' => self::fieldByRowid($nextByRowid, 'name'),
            'currentNameHex' => self::fieldByRowid($currentByRowid, 'nameHex'),
            'nextNameHex' => self::fieldByRowid($nextByRowid, 'nameHex'),
            'currentTokenHex' => self::fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTokenCounts' => self::fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::fieldByRowid($nextByRowid, 'tokenCount'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'literalPercentAndUnderscoreRequireEscape' => true,
            'trailingEscapeDoesNotMatchLiteralEscape' => true,
            'multibyteEscapeIsOneSQLiteCharacter' => true,
            'likeNocaseFoldsAsciiOnly' => true,
            'collationDoesNotMakeLikeUnicodeCaseFold' => true,
            'dependencies' => [
                'sqlite-like-escape-tokenizer',
                'sqlite-nocase-ascii-collation',
                'sqlite-text-affinity',
                'sqlite-current-source-next236',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, SQLite text affinity coercion, ASCII-only NOCASE folding, and current-source invalidation diagnostics',
            'non_overlap' => 'next236 covers escaped LIKE wildcard semantics over option_name current-source scans; avoids accepted Unicode GLOB range next113/next218, malformed-byte option_value LIKE next232, UTF-16 malformed guards, UTF-16 NOCASE/RTRIM LIKE cursor fences, and SQL executor/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name', $row)) {
                throw new \InvalidArgumentException('SQLite escaped LIKE next236 row requires option_name');
            }
            $name = self::coerceText($row['option_name']);
            if ($name === null) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($name, $pattern, $escape, $caseSensitiveLike)) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'name' => $name,
                'nameHex' => bin2hex($name),
                'tokenHex' => self::tokenHexList($name),
                'tokenCount' => self::sqlitePatternLength($name),
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    private static function coerceText(mixed $value): ?string
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        throw new \InvalidArgumentException('SQLite escaped LIKE next236 option_name must be scalar text-affinity input');
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
}
