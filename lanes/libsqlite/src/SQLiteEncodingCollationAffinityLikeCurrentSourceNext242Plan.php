<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext242Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressEmbeddedNulLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = "plugin\0cache!_%",
        ?string $escape = '!',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@241',
        string $nextSource = 'main.wp_options@242',
        int $currentSchemaCookie = 241,
        int $nextSchemaCookie = 242,
    ): array {
        if ($escape !== null && self::sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite embedded-NUL LIKE next242 ESCAPE must be one SQLite pattern character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentByRowid = self::rowsByRowid($current['decisions']);
        $nextByRowid = self::rowsByRowid($next['decisions']);
        $currentMatched = array_column($current['matchedRows'], 'rowid');
        $nextMatched = array_column($next['matchedRows'], 'rowid');
        $retained = array_values(array_intersect($currentMatched, $nextMatched));
        $exited = array_values(array_diff($currentMatched, $nextMatched));
        $entered = array_values(array_diff($nextMatched, $currentMatched));
        $changedBytes = [];
        $changedTruth = [];
        $changedStorage = [];
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['textHex'] !== $nextByRowid[$rowid]['textHex']) {
                $changedBytes[] = $rowid;
            }
            if ($currentByRowid[$rowid]['likeResult'] !== $nextByRowid[$rowid]['likeResult']) {
                $changedTruth[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storage'] !== $nextByRowid[$rowid]['storage']) {
                $changedStorage[] = $rowid;
            }
        }
        sort($changedBytes);
        sort($changedTruth);
        sort($changedStorage);

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
            $reasons[] = 'embedded-nul-text-bytes';
        }
        if ($changedTruth !== []) {
            $reasons[] = 'like-truth';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }

        $range = $caseSensitiveLike ? $patternPlan['binaryRange'] : $patternPlan['noCaseRange'];

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next242',
            'operator' => 'LIKE',
            'expression' => 'CAST(option_value AS TEXT) COLLATE ' . ($caseSensitiveLike ? 'BINARY' : 'NOCASE') . ' LIKE ? ESCAPE ? /* embedded-NUL literal prefix */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'patternTokenHex' => self::tokenHexList($pattern),
            'patternCharacterCount' => self::sqlitePatternLength($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixTokenHex' => self::tokenHexList($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixContainsNul' => str_contains($patternPlan['prefix'], "\0"),
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'rangeLowerInclusiveHex' => bin2hex($range['lowerInclusive']),
            'rangeUpperBoundHex' => $range['upperBound'] === null ? null : bin2hex($range['upperBound']),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedMatchedRowids' => $retained,
            'exitedMatchedRowids' => $exited,
            'enteredMatchedRowids' => $entered,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'changedBytesRowids' => $changedBytes,
            'changedLikeTruthRowids' => $changedTruth,
            'changedStorageRowids' => $changedStorage,
            'currentTextsHex' => self::fieldByRowid($currentByRowid, 'textHex'),
            'nextTextsHex' => self::fieldByRowid($nextByRowid, 'textHex'),
            'currentTokenHex' => self::fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTokenCounts' => self::fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::fieldByRowid($nextByRowid, 'tokenCount'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storage'),
            'currentLikeResults' => self::fieldByRowid($currentByRowid, 'likeResult'),
            'nextLikeResults' => self::fieldByRowid($nextByRowid, 'likeResult'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'embeddedNulIsOrdinaryLikeCharacter' => true,
            'escapedUnderscoreIsLiteral' => true,
            'percentWildcardRunsAfterNulPrefix' => true,
            'nocaseFoldsAsciiOnlyAroundNul' => true,
            'nullAndBlobRemainUnknown' => true,
            'dependencies' => [
                'sqlite-like-embedded-nul-tokenizer',
                'sqlite-like-escape-prefix-range',
                'sqlite-nocase-ascii-collation',
                'sqlite-text-affinity',
                'sqlite-current-source-next242',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, embedded-NUL PHP string handling, text-affinity coercion, ASCII-only NOCASE folding, and current-source invalidation diagnostics',
            'non_overlap' => 'next242 covers embedded-NUL TEXT LIKE prefixes with escaped literal underscore current-source fences; avoids accepted next239 Unicode/malformed GLOB ranges, next236 escaped option_name LIKE, next237 option_value escaped wildcard, next238 REAL text-affinity LIKE, next235 malformed-byte NOT LIKE, UTF-16 malformed guards, and unrelated SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>, matchedRows:list<array<string,mixed>>, unknownRowids:list<int>}
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $decisions = [];
        $matched = [];
        $unknown = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite embedded-NUL LIKE next242 row requires option_value');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            $text = self::coerceText($row['option_value']);
            if ($text === null) {
                $unknown[] = $rowid;
                continue;
            }
            $like = SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike);
            $decision = [
                'rowid' => $rowid,
                'textHex' => bin2hex($text),
                'tokenHex' => self::tokenHexList($text),
                'tokenCount' => self::sqlitePatternLength($text),
                'storage' => SQLiteAffinityComparison::storageClass($row['option_value']),
                'likeResult' => $like,
            ];
            $decisions[] = $decision;
            if ($like) {
                $matched[] = $decision;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp($left['textHex'], $right['textHex']) ?: $left['rowid'] <=> $right['rowid'];
        usort($decisions, $sort);
        usort($matched, $sort);
        sort($unknown);

        return ['decisions' => $decisions, 'matchedRows' => $matched, 'unknownRowids' => $unknown];
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

        throw new \InvalidArgumentException('SQLite embedded-NUL LIKE next242 option_value must be scalar text-affinity input');
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
