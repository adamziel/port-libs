<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext235Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionValueNotLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        bool $negate = true,
        string $currentSource = 'main.wp_options@234',
        string $nextSource = 'main.wp_options@235',
        int $currentSchemaCookie = 234,
        int $nextSchemaCookie = 235,
    ): array {
        if ($escape !== null && self::sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite malformed-byte NOT LIKE next235 ESCAPE must be one SQLite pattern character');
        }

        $prefixPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike, $negate);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike, $negate);
        $currentByRowid = self::rowsByRowid($current['decisions']);
        $nextByRowid = self::rowsByRowid($next['decisions']);
        $currentResultRowids = array_column($current['resultRows'], 'rowid');
        $nextResultRowids = array_column($next['resultRows'], 'rowid');
        $currentLikeRowids = array_column($current['likeRows'], 'rowid');
        $nextLikeRowids = array_column($next['likeRows'], 'rowid');
        $retained = array_values(array_intersect($currentResultRowids, $nextResultRowids));
        $exited = array_values(array_diff($currentResultRowids, $nextResultRowids));
        $entered = array_values(array_diff($nextResultRowids, $currentResultRowids));
        $changedBytes = [];
        $changedStorage = [];
        $changedTruth = [];
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['bytesHex'] !== $nextByRowid[$rowid]['bytesHex']) {
                $changedBytes[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storage'] !== $nextByRowid[$rowid]['storage']) {
                $changedStorage[] = $rowid;
            }
            if ($currentByRowid[$rowid]['predicateResult'] !== $nextByRowid[$rowid]['predicateResult']) {
                $changedTruth[] = $rowid;
            }
        }

        sort($changedBytes);
        sort($changedStorage);
        sort($changedTruth);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'result-rowset';
        }
        if ($changedTruth !== []) {
            $reasons[] = 'predicate-truth';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'malformed-byte-text';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next235',
            'operator' => $negate ? 'NOT LIKE' : 'LIKE',
            'expression' => 'CAST(option_value AS TEXT) COLLATE NOCASE ' . ($negate ? 'NOT LIKE' : 'LIKE') . ' ? ESCAPE ? /* malformed-byte complement current-source fence */',
            'pattern' => $pattern,
            'patternBytesHex' => bin2hex($pattern),
            'patternCharacterCount' => self::sqlitePatternLength($pattern),
            'escape' => $escape,
            'escapeBytesHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'negate' => $negate,
            'prefix' => $prefixPlan['prefix'],
            'prefixBytesHex' => bin2hex($prefixPlan['prefix']),
            'prefixCharacters' => $prefixPlan['prefixCharacters'],
            'prefixIsAscii' => $prefixPlan['prefixIsAscii'],
            'rangeLowerInclusive' => $caseSensitiveLike ? $prefixPlan['binaryRange']['lowerInclusive'] : $prefixPlan['noCaseRange']['lowerInclusive'],
            'rangeUpperBound' => $caseSensitiveLike ? $prefixPlan['binaryRange']['upperBound'] : $prefixPlan['noCaseRange']['upperBound'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentResultRowids' => $currentResultRowids,
            'nextResultRowids' => $nextResultRowids,
            'currentLikeRowids' => $currentLikeRowids,
            'nextLikeRowids' => $nextLikeRowids,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'retainedResultRowids' => $retained,
            'exitedResultRowids' => $exited,
            'enteredResultRowids' => $entered,
            'changedBytesRowids' => $changedBytes,
            'changedStorageRowids' => $changedStorage,
            'changedPredicateTruthRowids' => $changedTruth,
            'currentMalformedRowids' => self::rowidsWithField($current['decisions'], 'malformed', true),
            'nextMalformedRowids' => self::rowidsWithField($next['decisions'], 'malformed', true),
            'currentTextsHex' => self::fieldByRowid($currentByRowid, 'bytesHex'),
            'nextTextsHex' => self::fieldByRowid($nextByRowid, 'bytesHex'),
            'currentPatternTokens' => self::fieldByRowid($currentByRowid, 'patternTokens'),
            'nextPatternTokens' => self::fieldByRowid($nextByRowid, 'patternTokens'),
            'currentPredicateResults' => self::fieldByRowid($currentByRowid, 'predicateResult'),
            'nextPredicateResults' => self::fieldByRowid($nextByRowid, 'predicateResult'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storage'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'notLikeUsesLikeTruthComplement' => true,
            'unknownValuesDoNotEnterComplement' => true,
            'malformedBytesAreSingleCharacters' => true,
            'validUtf8CodepointsStayIntact' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-like-malformed-utf8-byte-tokenizer',
                'sqlite-text-affinity',
                'sqlite-nocase-ascii-collation',
                'sqlite-not-like-truth-complement',
                'sqlite-current-source-next235',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, text affinity, ASCII NOCASE folding, three-valued predicate handling, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'next235 covers NOT LIKE complement semantics over malformed-byte text-affinity rows; avoids accepted next232 positive LIKE malformed-byte matching, UTF-16 malformed insert guards, Unicode GLOB ranges, UTF-16 NOCASE/RTRIM LIKE cursor fences, and VFS/WAL/B-tree/JSON/SQL executor clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>, resultRows:list<array<string,mixed>>, likeRows:list<array<string,mixed>>, unknownRowids:list<int>}
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, bool $negate): array
    {
        $decisions = [];
        $resultRows = [];
        $likeRows = [];
        $unknownRowids = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite malformed-byte NOT LIKE next235 row requires option_value');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            $text = self::coerceText($row['option_value']);
            if ($text === null) {
                $unknownRowids[] = $rowid;
                continue;
            }
            $like = SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike);
            $predicateResult = $negate ? !$like : $like;
            $decision = [
                'rowid' => $rowid,
                'bytesHex' => bin2hex($text),
                'patternTokens' => self::tokenHexList($text),
                'malformed' => preg_match('//u', $text) !== 1,
                'storage' => SQLiteAffinityComparison::storageClass($row['option_value']),
                'likeResult' => $like,
                'predicateResult' => $predicateResult,
                'payload' => $row,
            ];
            $decisions[] = $decision;
            if ($like) {
                $likeRows[] = $decision;
            }
            if ($predicateResult) {
                $resultRows[] = $decision;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp($left['bytesHex'], $right['bytesHex']) ?: $left['rowid'] <=> $right['rowid'];
        usort($decisions, $sort);
        usort($resultRows, $sort);
        usort($likeRows, $sort);
        sort($unknownRowids);

        return [
            'decisions' => $decisions,
            'resultRows' => $resultRows,
            'likeRows' => $likeRows,
            'unknownRowids' => $unknownRowids,
        ];
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

        throw new \InvalidArgumentException('SQLite malformed-byte NOT LIKE next235 option_value must be scalar text-affinity input');
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
