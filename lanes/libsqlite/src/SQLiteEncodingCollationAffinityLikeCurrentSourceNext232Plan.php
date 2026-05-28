<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext232Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionValueMalformedByteLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@231',
        string $nextSource = 'main.wp_options@232',
        int $currentSchemaCookie = 231,
        int $nextSchemaCookie = 232,
    ): array {
        if ($escape !== null && self::sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite malformed-byte LIKE next232 ESCAPE must be one SQLite pattern character');
        }

        $prefixPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $changedBytes = [];
        $changedStorage = [];
        foreach ($retained as $rowid) {
            if ($currentByRowid[$rowid]['bytesHex'] !== $nextByRowid[$rowid]['bytesHex']) {
                $changedBytes[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storage'] !== $nextByRowid[$rowid]['storage']) {
                $changedStorage[] = $rowid;
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
            $reasons[] = 'malformed-byte-text';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next232',
            'operator' => 'LIKE',
            'expression' => 'CAST(option_value AS TEXT) COLLATE NOCASE LIKE ? ESCAPE ? /* malformed-byte current-source fence */',
            'pattern' => $pattern,
            'patternBytesHex' => bin2hex($pattern),
            'patternCharacterCount' => self::sqlitePatternLength($pattern),
            'escape' => $escape,
            'escapeBytesHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'prefix' => $prefixPlan['prefix'],
            'prefixBytesHex' => bin2hex($prefixPlan['prefix']),
            'prefixCharacters' => $prefixPlan['prefixCharacters'],
            'prefixIsAscii' => $prefixPlan['prefixIsAscii'],
            'rangeLowerInclusive' => $prefixPlan['noCaseRange']['lowerInclusive'],
            'rangeUpperBound' => $prefixPlan['noCaseRange']['upperBound'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedBytesRowids' => $changedBytes,
            'changedStorageRowids' => $changedStorage,
            'currentMalformedRowids' => self::rowidsWithField($current, 'malformed', true),
            'nextMalformedRowids' => self::rowidsWithField($next, 'malformed', true),
            'currentTextsHex' => self::fieldByRowid($currentByRowid, 'bytesHex'),
            'nextTextsHex' => self::fieldByRowid($nextByRowid, 'bytesHex'),
            'currentPatternTokens' => self::fieldByRowid($currentByRowid, 'patternTokens'),
            'nextPatternTokens' => self::fieldByRowid($nextByRowid, 'patternTokens'),
            'currentTokenCounts' => self::fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::fieldByRowid($nextByRowid, 'tokenCount'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storage'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'malformedBytesAreSingleCharacters' => true,
            'validUtf8CodepointsStayIntact' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-like-malformed-utf8-byte-tokenizer',
                'sqlite-text-affinity',
                'sqlite-nocase-ascii-collation',
                'sqlite-current-source-next232',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE pattern tokenization, text affinity, ASCII NOCASE folding, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'next232 covers malformed UTF-8 byte LIKE comparison after text affinity; avoids accepted UTF-16 malformed insert guards, Unicode GLOB ranges, UTF-16 NOCASE/RTRIM LIKE cursor fences, dynamic LIKE affinity next99, and VFS/WAL/B-tree/JSON/SQL executor clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite malformed-byte LIKE next232 row requires option_value');
            }
            $text = self::coerceText($row['option_value']);
            if ($text === null) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'bytesHex' => bin2hex($text),
                'tokenCount' => self::sqlitePatternLength($text),
                'patternTokens' => self::tokenHexList($text),
                'malformed' => preg_match('//u', $text) !== 1,
                'storage' => SQLiteAffinityComparison::storageClass($row['option_value']),
                'payload' => $row,
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['bytesHex'], $right['bytesHex']) ?: $left['rowid'] <=> $right['rowid']);

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

        throw new \InvalidArgumentException('SQLite malformed-byte LIKE next232 option_value must be scalar text-affinity input');
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

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowidsWithField(array $rows, string $field, mixed $expected): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if (($row[$field] ?? null) === $expected) {
                $rowids[] = $row['rowid'];
            }
        }

        return $rowids;
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
