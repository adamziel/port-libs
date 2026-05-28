<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext238Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressRealTextAffinityLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = '100.%',
        ?string $escape = null,
        bool $caseSensitiveLike = true,
        string $currentSource = 'main.wp_options@237',
        string $nextSource = 'main.wp_options@238',
        int $currentSchemaCookie = 237,
        int $nextSchemaCookie = 238,
    ): array {
        if ($escape !== null && self::sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite real-affinity LIKE next238 ESCAPE must be one SQLite pattern character');
        }

        $prefix = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentByRowid = self::rowsByRowid($current['decisions']);
        $nextByRowid = self::rowsByRowid($next['decisions']);
        $currentMatched = array_column($current['matchedRows'], 'rowid');
        $nextMatched = array_column($next['matchedRows'], 'rowid');
        $entered = array_values(array_diff($nextMatched, $currentMatched));
        $exited = array_values(array_diff($currentMatched, $nextMatched));
        $retained = array_values(array_intersect($currentMatched, $nextMatched));

        $changedText = [];
        $changedTruth = [];
        $changedStorage = [];
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['text'] !== $nextByRowid[$rowid]['text']) {
                $changedText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['likeResult'] !== $nextByRowid[$rowid]['likeResult']) {
                $changedTruth[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storage'] !== $nextByRowid[$rowid]['storage']) {
                $changedStorage[] = $rowid;
            }
        }
        sort($changedText);
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
        if ($changedText !== []) {
            $reasons[] = 'real-text-affinity';
        }
        if ($changedTruth !== []) {
            $reasons[] = 'like-truth';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next238',
            'operator' => 'LIKE',
            'expression' => 'CAST(option_value AS TEXT) COLLATE ' . ($caseSensitiveLike ? 'BINARY' : 'NOCASE') . ' LIKE ? /* REAL text-affinity decimal/exponent preservation */',
            'pattern' => $pattern,
            'patternBytesHex' => bin2hex($pattern),
            'patternCharacterCount' => self::sqlitePatternLength($pattern),
            'escape' => $escape,
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'prefix' => $prefix['prefix'],
            'prefixBytesHex' => bin2hex($prefix['prefix']),
            'prefixCharacters' => $prefix['prefixCharacters'],
            'rangeLowerInclusive' => $caseSensitiveLike ? $prefix['binaryRange']['lowerInclusive'] : $prefix['noCaseRange']['lowerInclusive'],
            'rangeUpperBound' => $caseSensitiveLike ? $prefix['binaryRange']['upperBound'] : $prefix['noCaseRange']['upperBound'],
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
            'changedTextRowids' => $changedText,
            'changedLikeTruthRowids' => $changedTruth,
            'changedStorageRowids' => $changedStorage,
            'currentTexts' => self::fieldByRowid($currentByRowid, 'text'),
            'nextTexts' => self::fieldByRowid($nextByRowid, 'text'),
            'currentTextHex' => self::fieldByRowid($currentByRowid, 'textHex'),
            'nextTextHex' => self::fieldByRowid($nextByRowid, 'textHex'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storage'),
            'currentLikeResults' => self::fieldByRowid($currentByRowid, 'likeResult'),
            'nextLikeResults' => self::fieldByRowid($nextByRowid, 'likeResult'),
            'currentPatternTokens' => self::fieldByRowid($currentByRowid, 'patternTokens'),
            'nextPatternTokens' => self::fieldByRowid($nextByRowid, 'patternTokens'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'realIntegerValuedTextKeepsDecimal' => true,
            'realExponentTextKeepsExponentMarker' => true,
            'integerTextDoesNotGainDecimal' => true,
            'nullAndBlobRemainUnknown' => true,
            'likeResidualRunsAfterTextAffinity' => true,
            'dependencies' => [
                'sqlite-real-text-affinity',
                'sqlite-like-prefix-range',
                'sqlite-like-residual',
                'sqlite-current-source-next238',
            ],
            'dependency_closure' => 'no new support component needed; reuses native scalar storage classification, SQLite REAL text-affinity formatting, LIKE prefix/range planning, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'next238 covers REAL-to-TEXT decimal/exponent preservation before LIKE; avoids accepted next235 malformed-byte NOT LIKE complement, next232 positive malformed-byte LIKE, Unicode GLOB ranges, UTF-16 NOCASE/RTRIM cursor fences, and unrelated VFS/WAL/B-tree/JSON/SQL executor clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>,matchedRows:list<array<string,mixed>>,unknownRowids:list<int>}
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $decisions = [];
        $matched = [];
        $unknown = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite real-affinity LIKE next238 row requires option_value');
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
                'text' => $text,
                'textHex' => bin2hex($text),
                'patternTokens' => self::tokenHexList($text),
                'storage' => SQLiteAffinityComparison::storageClass($row['option_value']),
                'likeResult' => $like,
                'payload' => $row,
            ];
            $decisions[] = $decision;
            if ($like) {
                $matched[] = $decision;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp($left['text'], $right['text']) ?: $left['rowid'] <=> $right['rowid'];
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
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            return self::formatRealText($value);
        }

        throw new \InvalidArgumentException('SQLite real-affinity LIKE next238 option_value must be scalar text-affinity input');
    }

    private static function formatRealText(float $value): string
    {
        if (!is_finite($value)) {
            return (string) $value;
        }
        $text = sprintf('%.15g', $value);
        $text = preg_replace_callback('/e([+-])([0-9])$/', static fn (array $match): string => 'e' . $match[1] . '0' . $match[2], $text) ?? $text;

        return str_contains($text, '.') || stripos($text, 'e') !== false ? $text : $text . '.0';
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
    private static function sqlitePatternCharacters(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (preg_match_all('/./us', $text, $matches) === false || implode('', $matches[0]) !== $text) {
            return str_split($text);
        }

        return $matches[0];
    }

    /** @return list<string> */
    private static function tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::sqlitePatternCharacters($text));
    }
}
