<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan
{

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowValueMalformedByteLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@231',
        string $nextSource = 'main.app_settings@232',
        int $currentSchemaCookie = 231,
        int $nextSchemaCookie = 232,
    ): array {
        if ($escape !== null && self::nextTwoThreeTwo_sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite malformed-byte LIKE nextTwoThreeTwo ESCAPE must be one SQLite pattern character');
        }

        $prefixPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::nextTwoThreeTwo_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::nextTwoThreeTwo_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentByRowid = self::nextTwoThreeTwo_rowsByRowid($current);
        $nextByRowid = self::nextTwoThreeTwo_rowsByRowid($next);
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
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoThreeTwo',
            'operator' => 'LIKE',
            'expression' => 'CAST(key_value AS TEXT) COLLATE NOCASE LIKE ? ESCAPE ? /* malformed-byte current-source fence */',
            'pattern' => $pattern,
            'patternBytesHex' => bin2hex($pattern),
            'patternCharacterCount' => self::nextTwoThreeTwo_sqlitePatternLength($pattern),
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
            'currentMalformedRowids' => self::nextTwoThreeTwo_rowidsWithField($current, 'malformed', true),
            'nextMalformedRowids' => self::nextTwoThreeTwo_rowidsWithField($next, 'malformed', true),
            'currentTextsHex' => self::nextTwoThreeTwo_fieldByRowid($currentByRowid, 'bytesHex'),
            'nextTextsHex' => self::nextTwoThreeTwo_fieldByRowid($nextByRowid, 'bytesHex'),
            'currentPatternTokens' => self::nextTwoThreeTwo_fieldByRowid($currentByRowid, 'patternTokens'),
            'nextPatternTokens' => self::nextTwoThreeTwo_fieldByRowid($nextByRowid, 'patternTokens'),
            'currentTokenCounts' => self::nextTwoThreeTwo_fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::nextTwoThreeTwo_fieldByRowid($nextByRowid, 'tokenCount'),
            'currentStorage' => self::nextTwoThreeTwo_fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::nextTwoThreeTwo_fieldByRowid($nextByRowid, 'storage'),
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
                'sqlite-current-source-nexttwoThreeTwo',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE pattern tokenization, text affinity, ASCII NOCASE folding, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'nextTwoThreeTwo covers malformed UTF-8 byte LIKE comparison after text affinity; avoids accepted UTF-16 malformed insert guards, Unicode GLOB ranges, UTF-16 NOCASE/RTRIM LIKE cursor fences, dynamic LIKE affinity nextNineNine, and VFS/WAL/B-tree/JSON/SQL executor clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function nextTwoThreeTwo_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite malformed-byte LIKE nextTwoThreeTwo row requires key_value');
            }
            $text = self::nextTwoThreeTwo_coerceText($row['key_value']);
            if ($text === null) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1,
                'bytesHex' => bin2hex($text),
                'tokenCount' => self::nextTwoThreeTwo_sqlitePatternLength($text),
                'patternTokens' => self::nextTwoThreeTwo_tokenHexList($text),
                'malformed' => preg_match('//u', $text) !== 1,
                'storage' => SQLiteAffinityComparison::storageClass($row['key_value']),
                'payload' => $row,
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['bytesHex'], $right['bytesHex']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    private static function nextTwoThreeTwo_coerceText(mixed $value): ?string
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

        throw new \InvalidArgumentException('SQLite malformed-byte LIKE nextTwoThreeTwo key_value must be scalar text-affinity input');
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoThreeTwo_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoThreeTwo_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoThreeTwo_rowidsWithField(array $rows, string $field, mixed $expected): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if (($row[$field] ?? null) === $expected) {
                $rowids[] = $row['rowid'];
            }
        }

        return $rowids;
    }

    private static function nextTwoThreeTwo_sqlitePatternLength(string $text): int
    {
        return count(self::nextTwoThreeTwo_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoThreeTwo_tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::nextTwoThreeTwo_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoThreeTwo_sqlitePatternCharacters(string $text): array
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

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowValueNotLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        bool $negate = true,
        string $currentSource = 'main.app_settings@234',
        string $nextSource = 'main.app_settings@235',
        int $currentSchemaCookie = 234,
        int $nextSchemaCookie = 235,
    ): array {
        if ($escape !== null && self::nextTwoThreeFive_sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite malformed-byte NOT LIKE nextTwoThreeFive ESCAPE must be one SQLite pattern character');
        }

        $prefixPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::nextTwoThreeFive_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike, $negate);
        $next = self::nextTwoThreeFive_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike, $negate);
        $currentByRowid = self::nextTwoThreeFive_rowsByRowid($current['decisions']);
        $nextByRowid = self::nextTwoThreeFive_rowsByRowid($next['decisions']);
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
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoThreeFive',
            'operator' => $negate ? 'NOT LIKE' : 'LIKE',
            'expression' => 'CAST(key_value AS TEXT) COLLATE NOCASE ' . ($negate ? 'NOT LIKE' : 'LIKE') . ' ? ESCAPE ? /* malformed-byte complement current-source fence */',
            'pattern' => $pattern,
            'patternBytesHex' => bin2hex($pattern),
            'patternCharacterCount' => self::nextTwoThreeFive_sqlitePatternLength($pattern),
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
            'currentMalformedRowids' => self::nextTwoThreeFive_rowidsWithField($current['decisions'], 'malformed', true),
            'nextMalformedRowids' => self::nextTwoThreeFive_rowidsWithField($next['decisions'], 'malformed', true),
            'currentTextsHex' => self::nextTwoThreeFive_fieldByRowid($currentByRowid, 'bytesHex'),
            'nextTextsHex' => self::nextTwoThreeFive_fieldByRowid($nextByRowid, 'bytesHex'),
            'currentPatternTokens' => self::nextTwoThreeFive_fieldByRowid($currentByRowid, 'patternTokens'),
            'nextPatternTokens' => self::nextTwoThreeFive_fieldByRowid($nextByRowid, 'patternTokens'),
            'currentPredicateResults' => self::nextTwoThreeFive_fieldByRowid($currentByRowid, 'predicateResult'),
            'nextPredicateResults' => self::nextTwoThreeFive_fieldByRowid($nextByRowid, 'predicateResult'),
            'currentStorage' => self::nextTwoThreeFive_fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::nextTwoThreeFive_fieldByRowid($nextByRowid, 'storage'),
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
                'sqlite-current-source-nexttwoThreeFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, text affinity, ASCII NOCASE folding, three-valued predicate handling, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'nextTwoThreeFive covers NOT LIKE complement semantics over malformed-byte text-affinity rows; avoids accepted nextTwoThreeTwo positive LIKE malformed-byte matching, UTF-16 malformed insert guards, Unicode GLOB ranges, UTF-16 NOCASE/RTRIM LIKE cursor fences, and VFS/WAL/B-tree/JSON/SQL executor clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>, resultRows:list<array<string,mixed>>, likeRows:list<array<string,mixed>>, unknownRowids:list<int>}
     */
    private static function nextTwoThreeFive_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, bool $negate): array
    {
        $decisions = [];
        $resultRows = [];
        $likeRows = [];
        $unknownRowids = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite malformed-byte NOT LIKE nextTwoThreeFive row requires key_value');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            $text = self::nextTwoThreeFive_coerceText($row['key_value']);
            if ($text === null) {
                $unknownRowids[] = $rowid;
                continue;
            }
            $like = SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike);
            $predicateResult = $negate ? !$like : $like;
            $decision = [
                'rowid' => $rowid,
                'bytesHex' => bin2hex($text),
                'patternTokens' => self::nextTwoThreeFive_tokenHexList($text),
                'malformed' => preg_match('//u', $text) !== 1,
                'storage' => SQLiteAffinityComparison::storageClass($row['key_value']),
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

    private static function nextTwoThreeFive_coerceText(mixed $value): ?string
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

        throw new \InvalidArgumentException('SQLite malformed-byte NOT LIKE nextTwoThreeFive key_value must be scalar text-affinity input');
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoThreeFive_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoThreeFive_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoThreeFive_rowidsWithField(array $rows, string $field, mixed $expected): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if (($row[$field] ?? null) === $expected) {
                $rowids[] = $row['rowid'];
            }
        }

        return $rowids;
    }

    private static function nextTwoThreeFive_sqlitePatternLength(string $text): int
    {
        return count(self::nextTwoThreeFive_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoThreeFive_tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::nextTwoThreeFive_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoThreeFive_sqlitePatternCharacters(string $text): array
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

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyEscapedLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@235',
        string $nextSource = 'main.app_settings@236',
        int $currentSchemaCookie = 235,
        int $nextSchemaCookie = 236,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::nextTwoThreeSix_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::nextTwoThreeSix_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $currentByRowid = self::nextTwoThreeSix_rowsByRowid($current);
        $nextByRowid = self::nextTwoThreeSix_rowsByRowid($next);
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
            $reasons[] = 'key-name-bytes';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoThreeSix',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE NOCASE LIKE ? ESCAPE ? /* escaped wildcard current-source fence */',
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
            'currentNames' => self::nextTwoThreeSix_fieldByRowid($currentByRowid, 'name'),
            'nextNames' => self::nextTwoThreeSix_fieldByRowid($nextByRowid, 'name'),
            'currentNameHex' => self::nextTwoThreeSix_fieldByRowid($currentByRowid, 'nameHex'),
            'nextNameHex' => self::nextTwoThreeSix_fieldByRowid($nextByRowid, 'nameHex'),
            'currentTokenHex' => self::nextTwoThreeSix_fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::nextTwoThreeSix_fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTokenCounts' => self::nextTwoThreeSix_fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::nextTwoThreeSix_fieldByRowid($nextByRowid, 'tokenCount'),
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
                'sqlite-current-source-nexttwoThreeSix',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, SQLite text affinity coercion, ASCII-only NOCASE folding, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoThreeSix covers escaped LIKE wildcard semantics over key_name current-source scans; avoids accepted Unicode GLOB range nextOneOneThree/nextTwoOneEight, malformed-byte key_value LIKE nextTwoThreeTwo, UTF-16 malformed guards, UTF-16 NOCASE/RTRIM LIKE cursor fences, and SQL executor/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function nextTwoThreeSix_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name', $row)) {
                throw new \InvalidArgumentException('SQLite escaped LIKE nextTwoThreeSix row requires key_name');
            }
            $name = self::nextTwoThreeSix_coerceText($row['key_name']);
            if ($name === null) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($name, $pattern, $escape, $caseSensitiveLike)) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1,
                'name' => $name,
                'nameHex' => bin2hex($name),
                'tokenHex' => self::nextTwoThreeSix_tokenHexList($name),
                'tokenCount' => self::nextTwoThreeSix_sqlitePatternLength($name),
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    private static function nextTwoThreeSix_coerceText(mixed $value): ?string
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

        throw new \InvalidArgumentException('SQLite escaped LIKE nextTwoThreeSix key_name must be scalar text-affinity input');
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoThreeSix_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoThreeSix_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    private static function nextTwoThreeSix_sqlitePatternLength(string $text): int
    {
        return count(self::nextTwoThreeSix_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoThreeSix_tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::nextTwoThreeSix_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoThreeSix_sqlitePatternCharacters(string $text): array
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

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowValueEscapePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'module!_%!%%',
        ?string $escape = '!',
        string $collation = 'NOCASE',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@236',
        string $nextSource = 'main.app_settings@237',
        int $currentSchemaCookie = 236,
        int $nextSchemaCookie = 237,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE nextTwoThreeSeven collation must be BINARY, NOCASE, or RTRIM');
        }

        $like = SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike);
        $current = self::nextTwoThreeSeven_scan($currentRows, $pattern, $escape, $collation, $caseSensitiveLike, $like['range']);
        $next = self::nextTwoThreeSeven_scan($nextRows, $pattern, $escape, $collation, $caseSensitiveLike, $like['range']);
        $currentMatched = self::nextTwoThreeSeven_rowids($current['matched']);
        $nextMatched = self::nextTwoThreeSeven_rowids($next['matched']);
        $currentCandidates = self::nextTwoThreeSeven_rowids($current['candidates']);
        $nextCandidates = self::nextTwoThreeSeven_rowids($next['candidates']);
        $changes = self::nextTwoThreeSeven_changes($current['trace'], $next['trace']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'storage-class' => $changes['storageRowids'],
            'affinity-text' => $changes['likeTextRowids'],
            'collation-key' => $changes['collationKeyRowids'],
            'range-membership' => $currentCandidates === $nextCandidates ? [] : self::nextTwoThreeSeven_uniqueSortedInts(array_merge($currentCandidates, $nextCandidates)),
            'residual-result' => $changes['residualRowids'],
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::nextTwoThreeSeven_uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoThreeSeven',
            'operator' => 'LIKE',
            'expression' => 'key_value LIKE ? ESCAPE ? COLLATE ' . $collation . ' /* text affinity before residual */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => $collation,
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'prefixCharacters' => $like['prefixCharacters'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'indexUsable' => $like['indexUsable'],
            'rangeRejectedReason' => $like['rejectedReason'],
            'rangeLowerInclusive' => $like['range']['lowerInclusive'] ?? null,
            'rangeUpperBound' => $like['range']['upperBound'] ?? null,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentFalsePositiveRowids' => self::nextTwoThreeSeven_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::nextTwoThreeSeven_rowids($next['falsePositive']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedLikeTextRowids' => $changes['likeTextRowids'],
            'changedCollationKeyRowids' => $changes['collationKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'escapeTreatsUnderscoreAsLiteral' => true,
            'escapeTreatsPercentAsLiteralUntilTrailingWildcard' => true,
            'textAffinityBeforeLike' => true,
            'nullLikeResultIsUnknown' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-escape-prefix-range',
                'sqlite-text-affinity-like',
                'sqlite-like-nocase-collation',
                'sqlite-current-source-nexttwoThreeSeven',
            ],
            'dependency_closure' => 'no new support component needed; reuses LIKE ESCAPE prefix planning, scalar text-affinity conversion, ASCII NOCASE collation keys, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoThreeSeven covers escaped wildcard literals after text affinity under LIKE/NOCASE current-source scans; avoids accepted Unicode GLOB ranges, UTF-16 malformed record guards, UTF-16 NOCASE/RTRIM canonical-equivalent scans, blob LIKE/GLOB affinity nextTwoThreeFour, SQL expression ORDER BY, and JSON/WAL/B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function nextTwoThreeSeven_scan(array $rows, string $pattern, ?string $escape, string $collation, bool $caseSensitiveLike, ?array $range): array
    {
        $trace = [];
        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $row) {
            self::nextTwoThreeSeven_assertRow($row);
            $rowid = $row['setting_id'];
            try {
                $likeText = self::nextTwoThreeSeven_likeText($row['key_value']);
                $collationKey = $likeText === null ? null : self::nextTwoThreeSeven_collationKey($likeText, $collation);
                $inRange = $collationKey !== null && self::nextTwoThreeSeven_inRange($collationKey, $range);
                $residual = $likeText === null ? null : SQLiteDatabase::likeMatches($likeText, $pattern, $escape, $caseSensitiveLike);
                $entry = [
                    'rowid' => $rowid,
                    'keyName' => (string) ($row['key_name'] ?? ''),
                    'storage' => SQLiteAffinityComparison::storageClass($row['key_value']),
                    'likeText' => $likeText,
                    'likeTextHex' => $likeText === null ? null : strtoupper(bin2hex($likeText)),
                    'collationKey' => $collationKey,
                    'collationKeyHex' => $collationKey === null ? null : strtoupper(bin2hex($collationKey)),
                    'inRange' => $inRange,
                    'residualMatch' => $residual,
                    'matched' => $inRange && $residual === true,
                    'load_policy' => $row['load_policy'] ?? null,
                ];
                $trace[] = $entry;
                if ($inRange) {
                    $candidates[] = $entry;
                    if ($entry['matched']) {
                        $matched[] = $entry;
                    } else {
                        $falsePositive[] = $entry;
                    }
                }
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, self::nextTwoThreeSeven_sortTrace(...));
        usort($candidates, self::nextTwoThreeSeven_sortTrace(...));
        usort($matched, self::nextTwoThreeSeven_sortTrace(...));
        usort($falsePositive, self::nextTwoThreeSeven_sortTrace(...));
        sort($malformed);
        ksort($errors);

        return [
            'trace' => $trace,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoThreeSeven_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE nextTwoThreeSeven rows require integer setting_id');
        }
        if (!array_key_exists('key_value', $row)) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE nextTwoThreeSeven rows require key_value');
        }
    }

    private static function nextTwoThreeSeven_likeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE nextTwoThreeSeven rows require scalar key_value');
        }
        if (preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE nextTwoThreeSeven text value is malformed UTF-8');
        }

        return $value;
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoThreeSeven_inRange(string $collationKey, ?array $range): bool
    {
        if ($range === null) {
            return false;
        }
        if (strcmp($collationKey, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($collationKey, $range['upperBound']) < 0;
    }

    private static function nextTwoThreeSeven_collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'BINARY' => $text,
            'NOCASE' => strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
            'RTRIM' => rtrim($text, ' '),
        };
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private static function nextTwoThreeSeven_sortTrace(array $left, array $right): int
    {
        $comparison = strcmp((string) ($left['collationKey'] ?? ''), (string) ($right['collationKey'] ?? ''));

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoThreeSeven_rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{storageRowids:list<int>,likeTextRowids:list<int>,collationKeyRowids:list<int>,residualRowids:list<int>}
     */
    private static function nextTwoThreeSeven_changes(array $current, array $next): array
    {
        $currentByRowid = [];
        foreach ($current as $row) {
            $currentByRowid[$row['rowid']] = $row;
        }

        $storage = [];
        $text = [];
        $key = [];
        $residual = [];
        foreach ($next as $row) {
            $rowid = $row['rowid'];
            if (!isset($currentByRowid[$rowid])) {
                $storage[] = $rowid;
                $text[] = $rowid;
                $key[] = $rowid;
                $residual[] = $rowid;
                continue;
            }
            $currentRow = $currentByRowid[$rowid];
            if ($currentRow['storage'] !== $row['storage']) {
                $storage[] = $rowid;
            }
            if ($currentRow['likeText'] !== $row['likeText']) {
                $text[] = $rowid;
            }
            if ($currentRow['collationKey'] !== $row['collationKey']) {
                $key[] = $rowid;
            }
            if ($currentRow['residualMatch'] !== $row['residualMatch']) {
                $residual[] = $rowid;
            }
        }
        $nextRowids = array_column($next, 'rowid');
        foreach ($currentByRowid as $rowid => $_row) {
            if (!in_array($rowid, $nextRowids, true)) {
                $storage[] = $rowid;
                $text[] = $rowid;
                $key[] = $rowid;
                $residual[] = $rowid;
            }
        }

        return [
            'storageRowids' => self::nextTwoThreeSeven_uniqueSortedInts($storage),
            'likeTextRowids' => self::nextTwoThreeSeven_uniqueSortedInts($text),
            'collationKeyRowids' => self::nextTwoThreeSeven_uniqueSortedInts($key),
            'residualRowids' => self::nextTwoThreeSeven_uniqueSortedInts($residual),
        ];
    }

    /** @param list<int> $values @return list<int> */
    private static function nextTwoThreeSeven_uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationRealTextAffinityLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = '100.%',
        ?string $escape = null,
        bool $caseSensitiveLike = true,
        string $currentSource = 'main.app_settings@237',
        string $nextSource = 'main.app_settings@238',
        int $currentSchemaCookie = 237,
        int $nextSchemaCookie = 238,
    ): array {
        if ($escape !== null && self::nextTwoThreeEight_sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite real-affinity LIKE nextTwoThreeEight ESCAPE must be one SQLite pattern character');
        }

        $prefix = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::nextTwoThreeEight_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::nextTwoThreeEight_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentByRowid = self::nextTwoThreeEight_rowsByRowid($current['decisions']);
        $nextByRowid = self::nextTwoThreeEight_rowsByRowid($next['decisions']);
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
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoThreeEight',
            'operator' => 'LIKE',
            'expression' => 'CAST(key_value AS TEXT) COLLATE ' . ($caseSensitiveLike ? 'BINARY' : 'NOCASE') . ' LIKE ? /* REAL text-affinity decimal/exponent preservation */',
            'pattern' => $pattern,
            'patternBytesHex' => bin2hex($pattern),
            'patternCharacterCount' => self::nextTwoThreeEight_sqlitePatternLength($pattern),
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
            'currentTexts' => self::nextTwoThreeEight_fieldByRowid($currentByRowid, 'text'),
            'nextTexts' => self::nextTwoThreeEight_fieldByRowid($nextByRowid, 'text'),
            'currentTextHex' => self::nextTwoThreeEight_fieldByRowid($currentByRowid, 'textHex'),
            'nextTextHex' => self::nextTwoThreeEight_fieldByRowid($nextByRowid, 'textHex'),
            'currentStorage' => self::nextTwoThreeEight_fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::nextTwoThreeEight_fieldByRowid($nextByRowid, 'storage'),
            'currentLikeResults' => self::nextTwoThreeEight_fieldByRowid($currentByRowid, 'likeResult'),
            'nextLikeResults' => self::nextTwoThreeEight_fieldByRowid($nextByRowid, 'likeResult'),
            'currentPatternTokens' => self::nextTwoThreeEight_fieldByRowid($currentByRowid, 'patternTokens'),
            'nextPatternTokens' => self::nextTwoThreeEight_fieldByRowid($nextByRowid, 'patternTokens'),
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
                'sqlite-current-source-nexttwoThreeEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native scalar storage classification, SQLite REAL text-affinity formatting, LIKE prefix/range planning, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'nextTwoThreeEight covers REAL-to-TEXT decimal/exponent preservation before LIKE; avoids accepted nextTwoThreeFive malformed-byte NOT LIKE complement, nextTwoThreeTwo positive malformed-byte LIKE, Unicode GLOB ranges, UTF-16 NOCASE/RTRIM cursor fences, and unrelated VFS/WAL/B-tree/JSON/SQL executor clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>,matchedRows:list<array<string,mixed>>,unknownRowids:list<int>}
     */
    private static function nextTwoThreeEight_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $decisions = [];
        $matched = [];
        $unknown = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite real-affinity LIKE nextTwoThreeEight row requires key_value');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            $text = self::nextTwoThreeEight_coerceText($row['key_value']);
            if ($text === null) {
                $unknown[] = $rowid;
                continue;
            }
            $like = SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike);
            $decision = [
                'rowid' => $rowid,
                'text' => $text,
                'textHex' => bin2hex($text),
                'patternTokens' => self::nextTwoThreeEight_tokenHexList($text),
                'storage' => SQLiteAffinityComparison::storageClass($row['key_value']),
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

    private static function nextTwoThreeEight_coerceText(mixed $value): ?string
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
            return self::nextTwoThreeEight_formatRealText($value);
        }

        throw new \InvalidArgumentException('SQLite real-affinity LIKE nextTwoThreeEight key_value must be scalar text-affinity input');
    }

    private static function nextTwoThreeEight_formatRealText(float $value): string
    {
        if (!is_finite($value)) {
            return (string) $value;
        }
        $text = sprintf('%.15g', $value);
        $text = preg_replace_callback('/e([+-])([0-9])$/', static fn (array $match): string => 'e' . $match[1] . '0' . $match[2], $text) ?? $text;

        return str_contains($text, '.') || stripos($text, 'e') !== false ? $text : $text . '.0';
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoThreeEight_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoThreeEight_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    private static function nextTwoThreeEight_sqlitePatternLength(string $text): int
    {
        return count(self::nextTwoThreeEight_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoThreeEight_sqlitePatternCharacters(string $text): array
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
    private static function nextTwoThreeEight_tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::nextTwoThreeEight_sqlitePatternCharacters($text));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowValueNumericLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@239',
        string $nextSource = 'main.app_settings@240',
        int $currentSchemaCookie = 239,
        int $nextSchemaCookie = 240,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::nextTwoFourZero_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::nextTwoFourZero_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $currentByRowid = self::nextTwoFourZero_rowsByRowid($current);
        $nextByRowid = self::nextTwoFourZero_rowsByRowid($next);
        $changedFormatted = [];
        $changedStorage = [];
        $changedBytes = [];

        foreach ($retained as $rowid) {
            if ($currentByRowid[$rowid]['formatted'] !== $nextByRowid[$rowid]['formatted']) {
                $changedFormatted[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storageClass'] !== $nextByRowid[$rowid]['storageClass']) {
                $changedStorage[] = $rowid;
            }
            if ($currentByRowid[$rowid]['formattedHex'] !== $nextByRowid[$rowid]['formattedHex']) {
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
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedFormatted !== []) {
            $reasons[] = 'numeric-affinity-format';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourZero',
            'operator' => 'LIKE',
            'expression' => 'CAST(key_value AS NUMERIC) LIKE ? ESCAPE ? /* numeric affinity current-source fence */',
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
            'changedFormattedRowids' => $changedFormatted,
            'changedStorageClassRowids' => $changedStorage,
            'changedFormattedBytesRowids' => $changedBytes,
            'currentFormatted' => self::nextTwoFourZero_fieldByRowid($currentByRowid, 'formatted'),
            'nextFormatted' => self::nextTwoFourZero_fieldByRowid($nextByRowid, 'formatted'),
            'currentFormattedHex' => self::nextTwoFourZero_fieldByRowid($currentByRowid, 'formattedHex'),
            'nextFormattedHex' => self::nextTwoFourZero_fieldByRowid($nextByRowid, 'formattedHex'),
            'currentStorageClasses' => self::nextTwoFourZero_fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorageClasses' => self::nextTwoFourZero_fieldByRowid($nextByRowid, 'storageClass'),
            'currentKeyNames' => self::nextTwoFourZero_fieldByRowid($currentByRowid, 'keyName'),
            'nextKeyNames' => self::nextTwoFourZero_fieldByRowid($nextByRowid, 'keyName'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'integerRealAndBooleanUseTextAffinityForLike' => true,
            'blobAndNullStayNonTextForNumericLike' => true,
            'storageClassChangeInvalidatesEvenWhenLikeTextMatches' => true,
            'sqliteRealFormattingUsesSignificantDigits' => true,
            'dependencies' => [
                'sqlite-numeric-affinity-format',
                'sqlite-like-escape-tokenizer',
                'sqlite-current-source-nexttwoFourZero',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization and lane-local SQLite numeric/text-affinity formatting diagnostics',
            'non_overlap' => 'nextTwoFourZero covers NUMERIC-affinity LIKE current-source invalidation over key_value formatting and storage classes; avoids nextTwoThreeSix escaped key_name LIKE, UTF-16 RTRIM/NOCASE cursors, Unicode GLOB ranges, malformed text guards, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function nextTwoFourZero_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite numeric LIKE nextTwoFourZero row requires key_value');
            }
            $coerced = self::nextTwoFourZero_coerceLikeText($row['key_value']);
            if ($coerced === null) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($coerced['formatted'], $pattern, $escape, $caseSensitiveLike)) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1,
                'keyName' => self::nextTwoFourZero_keyName($row, $index),
                'formatted' => $coerced['formatted'],
                'formattedHex' => bin2hex($coerced['formatted']),
                'storageClass' => $coerced['storageClass'],
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['formatted'], $right['formatted']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    /** @return array{formatted:string,storageClass:string}|null */
    private static function nextTwoFourZero_coerceLikeText(mixed $value): ?array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value)) {
            return ['formatted' => (string) $value, 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            return ['formatted' => self::nextTwoFourZero_formatReal($value), 'storageClass' => 'real'];
        }
        if (is_bool($value)) {
            return ['formatted' => $value ? '1' : '0', 'storageClass' => 'integer'];
        }
        if (is_string($value)) {
            return ['formatted' => $value, 'storageClass' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite numeric LIKE nextTwoFourZero key_value must be scalar or SQLiteBlobValue');
    }

    private static function nextTwoFourZero_formatReal(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }
        if ($value === INF) {
            return 'Inf';
        }
        if ($value === -INF) {
            return '-Inf';
        }

        $formatted = sprintf('%.15G', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoFourZero_keyName(array $row, int $index): string
    {
        $name = $row['key_name'] ?? 'setting_' . ($index + 1);

        return is_scalar($name) ? (string) $name : 'setting_' . ($index + 1);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourZero_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFourZero_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyByteAwareLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@240',
        string $nextSource = 'main.app_settings@241',
        int $currentSchemaCookie = 240,
        int $nextSchemaCookie = 241,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $caseSensitiveLike ? $patternPlan['binaryRange'] : $patternPlan['noCaseRange'];
        $current = self::nextTwoFourOne_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike, $range);
        $next = self::nextTwoFourOne_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike, $range);

        $currentRowids = self::nextTwoFourOne_rowids($current['matched']);
        $nextRowids = self::nextTwoFourOne_rowids($next['matched']);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $currentByRowid = self::nextTwoFourOne_rowsByRowid($current['matched']);
        $nextByRowid = self::nextTwoFourOne_rowsByRowid($next['matched']);
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
            $reasons[] = 'key-name-bytes';
        }
        if (self::nextTwoFourOne_rowids($current['candidates']) !== $currentRowids || self::nextTwoFourOne_rowids($next['candidates']) !== $nextRowids) {
            $reasons[] = 'range-residual';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourOne',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE NOCASE LIKE ? ESCAPE ? /* byte-aware residual cursor */',
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
            'currentCandidateRowids' => self::nextTwoFourOne_rowids($current['candidates']),
            'nextCandidateRowids' => self::nextTwoFourOne_rowids($next['candidates']),
            'currentMatchedRowids' => $currentRowids,
            'nextMatchedRowids' => $nextRowids,
            'currentResidualRejectedRowids' => self::nextTwoFourOne_rowids($current['rejected']),
            'nextResidualRejectedRowids' => self::nextTwoFourOne_rowids($next['rejected']),
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedNameBytesRowids' => $changedBytes,
            'currentNames' => self::nextTwoFourOne_fieldByRowid($currentByRowid, 'name'),
            'nextNames' => self::nextTwoFourOne_fieldByRowid($nextByRowid, 'name'),
            'currentNameHex' => self::nextTwoFourOne_fieldByRowid($currentByRowid, 'nameHex'),
            'nextNameHex' => self::nextTwoFourOne_fieldByRowid($nextByRowid, 'nameHex'),
            'currentTokenHex' => self::nextTwoFourOne_fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::nextTwoFourOne_fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTokenCounts' => self::nextTwoFourOne_fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::nextTwoFourOne_fieldByRowid($nextByRowid, 'tokenCount'),
            'currentStorage' => self::nextTwoFourOne_fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::nextTwoFourOne_fieldByRowid($nextByRowid, 'storage'),
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
                'sqlite-current-source-nexttwoFourOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, text affinity coercion, byte fallback for malformed UTF-8, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFourOne covers embedded-NUL and malformed-byte LIKE residual cursor behavior over key_name; avoids accepted escaped wildcard nextTwoThreeSix, dynamic key_value LIKE nextTwoThreeEight, Unicode GLOB ranges, UTF-16 malformed guards, UTF-16 NOCASE/RTRIM cursor fences, and SQL executor/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{lowerInclusive:string,upperBound:?string} $range
     * @return array{candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,malformedRowids:list<int>,malformedHex:array<int,string>}
     */
    private static function nextTwoFourOne_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, array $range): array
    {
        $candidates = [];
        $matched = [];
        $rejected = [];
        $malformedRowids = [];
        $malformedHex = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name', $row)) {
                throw new \InvalidArgumentException('SQLite byte-aware LIKE nextTwoFourOne row requires key_name');
            }
            $coerced = self::nextTwoFourOne_coerceText($row['key_name']);
            if ($coerced === null) {
                continue;
            }
            [$name, $storage] = $coerced;
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            if (preg_match('//u', $name) !== 1) {
                $malformedRowids[] = $rowid;
                $malformedHex[$rowid] = bin2hex($name);
            }
            if (!self::nextTwoFourOne_withinRange($name, $range, $caseSensitiveLike)) {
                continue;
            }

            $entry = [
                'rowid' => $rowid,
                'name' => $name,
                'nameHex' => bin2hex($name),
                'tokenHex' => self::nextTwoFourOne_tokenHexList($name),
                'tokenCount' => self::nextTwoFourOne_sqlitePatternLength($name),
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
    private static function nextTwoFourOne_coerceText(mixed $value): ?array
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

        throw new \InvalidArgumentException('SQLite byte-aware LIKE nextTwoFourOne key_name must be scalar text-affinity input');
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoFourOne_withinRange(string $value, array $range, bool $caseSensitiveLike): bool
    {
        $key = $caseSensitiveLike ? $value : self::nextTwoFourOne_asciiLower($value);
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFourOne_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourOne_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFourOne_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    private static function nextTwoFourOne_sqlitePatternLength(string $text): int
    {
        return count(self::nextTwoFourOne_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourOne_tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::nextTwoFourOne_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourOne_sqlitePatternCharacters(string $text): array
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

    private static function nextTwoFourOne_asciiLower(string $value): string
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

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationEmbeddedNulLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = "module\0cache!_%",
        ?string $escape = '!',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@241',
        string $nextSource = 'main.app_settings@242',
        int $currentSchemaCookie = 241,
        int $nextSchemaCookie = 242,
    ): array {
        if ($escape !== null && self::nextTwoFourTwo_sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite embedded-NUL LIKE nextTwoFourTwo ESCAPE must be one SQLite pattern character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::nextTwoFourTwo_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::nextTwoFourTwo_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentByRowid = self::nextTwoFourTwo_rowsByRowid($current['decisions']);
        $nextByRowid = self::nextTwoFourTwo_rowsByRowid($next['decisions']);
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
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourTwo',
            'operator' => 'LIKE',
            'expression' => 'CAST(key_value AS TEXT) COLLATE ' . ($caseSensitiveLike ? 'BINARY' : 'NOCASE') . ' LIKE ? ESCAPE ? /* embedded-NUL literal prefix */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'patternTokenHex' => self::nextTwoFourTwo_tokenHexList($pattern),
            'patternCharacterCount' => self::nextTwoFourTwo_sqlitePatternLength($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixTokenHex' => self::nextTwoFourTwo_tokenHexList($patternPlan['prefix']),
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
            'currentTextsHex' => self::nextTwoFourTwo_fieldByRowid($currentByRowid, 'textHex'),
            'nextTextsHex' => self::nextTwoFourTwo_fieldByRowid($nextByRowid, 'textHex'),
            'currentTokenHex' => self::nextTwoFourTwo_fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::nextTwoFourTwo_fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTokenCounts' => self::nextTwoFourTwo_fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::nextTwoFourTwo_fieldByRowid($nextByRowid, 'tokenCount'),
            'currentStorage' => self::nextTwoFourTwo_fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::nextTwoFourTwo_fieldByRowid($nextByRowid, 'storage'),
            'currentLikeResults' => self::nextTwoFourTwo_fieldByRowid($currentByRowid, 'likeResult'),
            'nextLikeResults' => self::nextTwoFourTwo_fieldByRowid($nextByRowid, 'likeResult'),
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
                'sqlite-current-source-nexttwoFourTwo',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, embedded-NUL PHP string handling, text-affinity coercion, ASCII-only NOCASE folding, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFourTwo covers embedded-NUL TEXT LIKE prefixes with escaped literal underscore current-source fences; avoids accepted nextTwoThreeNine Unicode/malformed GLOB ranges, nextTwoThreeSix escaped key_name LIKE, nextTwoThreeSeven key_value escaped wildcard, nextTwoThreeEight REAL text-affinity LIKE, nextTwoThreeFive malformed-byte NOT LIKE, UTF-16 malformed guards, and unrelated SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>, matchedRows:list<array<string,mixed>>, unknownRowids:list<int>}
     */
    private static function nextTwoFourTwo_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $decisions = [];
        $matched = [];
        $unknown = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite embedded-NUL LIKE nextTwoFourTwo row requires key_value');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            $text = self::nextTwoFourTwo_coerceText($row['key_value']);
            if ($text === null) {
                $unknown[] = $rowid;
                continue;
            }
            $like = SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike);
            $decision = [
                'rowid' => $rowid,
                'textHex' => bin2hex($text),
                'tokenHex' => self::nextTwoFourTwo_tokenHexList($text),
                'tokenCount' => self::nextTwoFourTwo_sqlitePatternLength($text),
                'storage' => SQLiteAffinityComparison::storageClass($row['key_value']),
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

    private static function nextTwoFourTwo_coerceText(mixed $value): ?string
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

        throw new \InvalidArgumentException('SQLite embedded-NUL LIKE nextTwoFourTwo key_value must be scalar text-affinity input');
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourTwo_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFourTwo_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    private static function nextTwoFourTwo_sqlitePatternLength(string $text): int
    {
        return count(self::nextTwoFourTwo_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourTwo_tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::nextTwoFourTwo_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourTwo_sqlitePatternCharacters(string $text): array
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

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationRtrimLikeResidualPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'cache_%',
        ?string $escape = null,
        string $collation = 'RTRIM',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@242',
        string $nextSource = 'main.app_settings@243',
        int $currentSchemaCookie = 242,
        int $nextSchemaCookie = 243,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite LIKE current-source nextTwoFourThree collation must be BINARY, NOCASE, or RTRIM');
        }

        $like = SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike);
        $current = self::nextTwoFourThree_scan($currentRows, $pattern, $escape, $collation, $caseSensitiveLike);
        $next = self::nextTwoFourThree_scan($nextRows, $pattern, $escape, $collation, $caseSensitiveLike);
        $currentMatched = self::nextTwoFourThree_rowids($current['matched']);
        $nextMatched = self::nextTwoFourThree_rowids($next['matched']);
        $currentRtrimCandidates = self::nextTwoFourThree_rowids($current['rtrimPrefixCandidates']);
        $nextRtrimCandidates = self::nextTwoFourThree_rowids($next['rtrimPrefixCandidates']);
        $changes = self::nextTwoFourThree_changes($current['trace'], $next['trace']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'storage-class' => $changes['storageRowids'],
            'like-text' => $changes['likeTextRowids'],
            'rtrim-key' => $changes['rtrimKeyRowids'],
            'rtrim-prefix-candidates' => $currentRtrimCandidates === $nextRtrimCandidates ? [] : self::nextTwoFourThree_uniqueSortedInts(array_merge($currentRtrimCandidates, $nextRtrimCandidates)),
            'like-residual-result' => $changes['residualRowids'],
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::nextTwoFourThree_uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourThree',
            'operator' => 'LIKE',
            'expression' => 'key_value COLLATE ' . $collation . ' LIKE ? /* RTRIM collation does not trim LIKE residual */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => $collation,
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'prefixCharacters' => $like['prefixCharacters'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'indexUsable' => $like['indexUsable'],
            'rangeRejectedReason' => $like['rejectedReason'],
            'rangeLowerInclusive' => $like['range']['lowerInclusive'] ?? null,
            'rangeUpperBound' => $like['range']['upperBound'] ?? null,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentRtrimPrefixCandidateRowids' => $currentRtrimCandidates,
            'nextRtrimPrefixCandidateRowids' => $nextRtrimCandidates,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedLikeTextRowids' => $changes['likeTextRowids'],
            'changedRtrimKeyRowids' => $changes['rtrimKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'rtrimCollationTrimsSpacesForKeyOnly' => true,
            'likeResidualSeesTrailingSpaces' => true,
            'likeResidualDoesNotUseRtrimEquality' => true,
            'nocaseLikeFoldsAsciiOnly' => true,
            'textAffinityBeforeLike' => true,
            'nullAndBlobLikeAreUnknown' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-collation-prefix-range',
                'sqlite-rtrim-collation-key',
                'sqlite-text-affinity-like',
                'sqlite-current-source-nexttwoFourThree',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE planning, scalar text-affinity coercion, RTRIM key normalization, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'nextTwoFourThree covers RTRIM collation key versus LIKE residual behavior over current/next key_value scans; avoids accepted Unicode GLOB ranges, UTF-16 malformed guards, UTF-16 NOCASE/RTRIM cursor fences, REAL LIKE nextTwoThreeEight, escaped key_name LIKE nextTwoThreeSix, malformed-byte LIKE/NOT LIKE, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{trace:list<array<string,mixed>>,matched:list<array<string,mixed>>,rtrimPrefixCandidates:list<array<string,mixed>>,unknownRowids:list<int>}
     */
    private static function nextTwoFourThree_scan(array $rows, string $pattern, ?string $escape, string $collation, bool $caseSensitiveLike): array
    {
        $trace = [];
        $matched = [];
        $rtrimPrefixCandidates = [];
        $unknown = [];
        $prefix = SQLiteDatabase::likePatternPlan($pattern, $escape)['prefix'];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite LIKE current-source nextTwoFourThree row requires key_value');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            $likeText = self::nextTwoFourThree_likeText($row['key_value']);
            if ($likeText === null) {
                $unknown[] = $rowid;
                continue;
            }

            $rtrimKey = rtrim($likeText, ' ');
            $collationKey = self::nextTwoFourThree_collationKey($likeText, $collation);
            $rtrimPrefix = self::nextTwoFourThree_startsWith($rtrimKey, $prefix, $caseSensitiveLike);
            $residual = SQLiteDatabase::likeMatches($likeText, $pattern, $escape, $caseSensitiveLike);
            $entry = [
                'rowid' => $rowid,
                'keyName' => (string) ($row['key_name'] ?? ''),
                'storage' => SQLiteAffinityComparison::storageClass($row['key_value']),
                'likeText' => $likeText,
                'likeTextHex' => strtoupper(bin2hex($likeText)),
                'rtrimKey' => $rtrimKey,
                'rtrimKeyHex' => strtoupper(bin2hex($rtrimKey)),
                'collationKey' => $collationKey,
                'collationKeyHex' => strtoupper(bin2hex($collationKey)),
                'rtrimPrefixCandidate' => $rtrimPrefix,
                'residualMatch' => $residual,
                'matched' => $residual === true,
            ];
            $trace[] = $entry;
            if ($rtrimPrefix) {
                $rtrimPrefixCandidates[] = $entry;
            }
            if ($entry['matched']) {
                $matched[] = $entry;
            }
        }

        usort($trace, self::nextTwoFourThree_sortTrace(...));
        usort($matched, self::nextTwoFourThree_sortTrace(...));
        usort($rtrimPrefixCandidates, self::nextTwoFourThree_sortTrace(...));
        sort($unknown);

        return [
            'trace' => $trace,
            'matched' => $matched,
            'rtrimPrefixCandidates' => $rtrimPrefixCandidates,
            'unknownRowids' => $unknown,
        ];
    }

    private static function nextTwoFourThree_likeText(mixed $value): ?string
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $text = sprintf('%.15g', $value);
            return str_contains($text, '.') || stripos($text, 'e') !== false ? $text : $text . '.0';
        }
        if (is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite LIKE current-source nextTwoFourThree key_value must be scalar text-affinity input');
    }

    private static function nextTwoFourThree_collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'NOCASE' => self::nextTwoFourThree_asciiLower($text),
            'RTRIM' => rtrim($text, ' '),
            default => $text,
        };
    }

    private static function nextTwoFourThree_startsWith(string $text, string $prefix, bool $caseInsensitiveAscii): bool
    {
        if ($caseInsensitiveAscii) {
            $text = self::nextTwoFourThree_asciiLower($text);
            $prefix = self::nextTwoFourThree_asciiLower($prefix);
        }

        return strncmp($text, $prefix, strlen($prefix)) === 0;
    }

    private static function nextTwoFourThree_asciiLower(string $text): string
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private static function nextTwoFourThree_sortTrace(array $left, array $right): int
    {
        return strcmp((string) $left['collationKey'], (string) $right['collationKey']) ?: $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFourThree_rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{storageRowids:list<int>,likeTextRowids:list<int>,rtrimKeyRowids:list<int>,residualRowids:list<int>}
     */
    private static function nextTwoFourThree_changes(array $current, array $next): array
    {
        $currentByRowid = self::nextTwoFourThree_byRowid($current);
        $nextByRowid = self::nextTwoFourThree_byRowid($next);
        $storage = [];
        $text = [];
        $rtrim = [];
        $residual = [];

        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['storage'] !== $nextByRowid[$rowid]['storage']) {
                $storage[] = (int) $rowid;
            }
            if ($currentByRowid[$rowid]['likeText'] !== $nextByRowid[$rowid]['likeText']) {
                $text[] = (int) $rowid;
            }
            if ($currentByRowid[$rowid]['rtrimKey'] !== $nextByRowid[$rowid]['rtrimKey']) {
                $rtrim[] = (int) $rowid;
            }
            if ($currentByRowid[$rowid]['residualMatch'] !== $nextByRowid[$rowid]['residualMatch']) {
                $residual[] = (int) $rowid;
            }
        }

        return [
            'storageRowids' => self::nextTwoFourThree_uniqueSortedInts($storage),
            'likeTextRowids' => self::nextTwoFourThree_uniqueSortedInts($text),
            'rtrimKeyRowids' => self::nextTwoFourThree_uniqueSortedInts($rtrim),
            'residualRowids' => self::nextTwoFourThree_uniqueSortedInts($residual),
        ];
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourThree_byRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param list<int> $values @return list<int> */
    private static function nextTwoFourThree_uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique(array_map('intval', $values)));
        sort($values);

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationUtf16KeyNameLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $collation = 'NOCASE',
        string $currentSource = 'main.app_settings@243',
        string $nextSource = 'main.app_settings@244',
        int $currentSchemaCookie = 243,
        int $nextSchemaCookie = 244,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite LIKE nextTwoFourFour collation: {$collation}");
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::nextTwoFourFour_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike, $collation);
        $next = self::nextTwoFourFour_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike, $collation);
        $currentMatched = array_values(array_filter($current, static fn (array $row): bool => $row['residualMatch']));
        $nextMatched = array_values(array_filter($next, static fn (array $row): bool => $row['residualMatch']));
        $currentRowids = self::nextTwoFourFour_rowids($currentMatched);
        $nextRowids = self::nextTwoFourFour_rowids($nextMatched);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $currentByRowid = self::nextTwoFourFour_rowsByRowid($currentMatched);
        $nextByRowid = self::nextTwoFourFour_rowsByRowid($nextMatched);
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
        $currentRejectedRowids = self::nextTwoFourFour_rowids(array_values(array_filter($current, static fn (array $row): bool => !$row['residualMatch'])));
        $nextRejectedRowids = self::nextTwoFourFour_rowids(array_values(array_filter($next, static fn (array $row): bool => !$row['residualMatch'])));
        if ($currentRejectedRowids !== $nextRejectedRowids) {
            $reasons[] = 'range-residual';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourFour',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE ' . $collation . ' LIKE ? ESCAPE ? /* mixed UTF source cursor */',
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
            'currentCandidateRowids' => self::nextTwoFourFour_rowids($current),
            'nextCandidateRowids' => self::nextTwoFourFour_rowids($next),
            'currentMatchedRowids' => $currentRowids,
            'nextMatchedRowids' => $nextRowids,
            'currentResidualRejectedRowids' => $currentRejectedRowids,
            'nextResidualRejectedRowids' => $nextRejectedRowids,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedEncodedBytesRowids' => $changedBytes,
            'changedEncodingRowids' => $changedEncoding,
            'currentNames' => self::nextTwoFourFour_fieldByRowid($currentByRowid, 'key'),
            'nextNames' => self::nextTwoFourFour_fieldByRowid($nextByRowid, 'key'),
            'currentKeyBytesHex' => self::nextTwoFourFour_fieldByRowid($currentByRowid, 'keyBytesHex'),
            'nextKeyBytesHex' => self::nextTwoFourFour_fieldByRowid($nextByRowid, 'keyBytesHex'),
            'currentEncodings' => self::nextTwoFourFour_fieldByRowid($currentByRowid, 'textEncoding'),
            'nextEncodings' => self::nextTwoFourFour_fieldByRowid($nextByRowid, 'textEncoding'),
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
                'sqlite-current-source-nexttwoFourFour',
            ],
            'dependency_closure' => 'no new support component needed; reuses native mixed UTF source decoding, LIKE tokenization, ASCII-only NOCASE comparison, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFourFour covers mixed UTF-8/UTF-16 key_name LIKE current-source invalidation with ASCII-only NOCASE around accented bytes; avoids accepted nextTwoFourZero numeric LIKE, nextTwoFourOne embedded-NUL/malformed byte LIKE, Unicode GLOB ranges, UTF-16 malformed guards, UTF-16 RTRIM cursor fences, SQL executor, JSON, WAL, VFS, and B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int,residualMatch:bool}>
     */
    private static function nextTwoFourFour_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, string $collation): array
    {
        return SQLiteEncodingCollationSourceCursor::keyValueRowKeyRangeScan(
            self::nextTwoFourFour_normalizeRows($rows),
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
    private static function nextTwoFourFour_normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            if (!isset($row['setting_id']) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite LIKE nextTwoFourFour rows require integer setting_id');
            }
            if (array_key_exists('key_name_bytes', $row)) {
                if (!is_string($row['key_name_bytes'])) {
                    throw new \InvalidArgumentException('SQLite LIKE nextTwoFourFour key_name_bytes must be a string');
                }
                if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                    throw new \InvalidArgumentException('SQLite LIKE nextTwoFourFour byte rows require integer text_encoding');
                }
                $normalized[] = $row;
                continue;
            }
            if (!array_key_exists('key_name', $row)) {
                throw new \InvalidArgumentException('SQLite LIKE nextTwoFourFour rows require key_name or key_name_bytes');
            }
            $value = $row['key_name'];
            if ($value === null || $value instanceof SQLiteBlobValue) {
                continue;
            }
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('SQLite LIKE nextTwoFourFour key_name must be scalar text-affinity input');
            }
            $encoding = self::nextTwoFourFour_encodingCode($row['text_encoding'] ?? 'UTF-8');
            $row['key_name_bytes'] = SQLiteEncodingCollationSourceCursor::encodeText((string) $value, $encoding);
            $row['text_encoding'] = $encoding;
            $row['setting_id'] = $row['setting_id'] ?? $index + 1;
            $normalized[] = $row;
        }

        return $normalized;
    }

    private static function nextTwoFourFour_encodingCode(mixed $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite LIKE nextTwoFourFour text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }
        if (!is_string($encoding)) {
            throw new \InvalidArgumentException('SQLite LIKE nextTwoFourFour text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite LIKE nextTwoFourFour text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFourFour_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourFour_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFourFour_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationDanglingEscapeLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'module!_cache!',
        ?string $escape = '!',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@244',
        string $nextSource = 'main.app_settings@245',
        int $currentSchemaCookie = 244,
        int $nextSchemaCookie = 245,
    ): array {
        if ($escape !== null && self::nextTwoFourFive_sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite dangling-escape LIKE nextTwoFourFive ESCAPE must be one SQLite pattern character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $caseSensitiveLike ? $patternPlan['binaryRange'] : $patternPlan['noCaseRange'];
        $current = self::nextTwoFourFive_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike, $range);
        $next = self::nextTwoFourFive_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike, $range);
        $currentCandidates = self::nextTwoFourFive_rowids($current['candidates']);
        $nextCandidates = self::nextTwoFourFive_rowids($next['candidates']);
        $currentMatched = self::nextTwoFourFive_rowids($current['matched']);
        $nextMatched = self::nextTwoFourFive_rowids($next['matched']);
        $currentRejected = self::nextTwoFourFive_rowids($current['rejected']);
        $nextRejected = self::nextTwoFourFive_rowids($next['rejected']);
        $retainedCandidates = array_values(array_intersect($currentCandidates, $nextCandidates));
        $exitedCandidates = array_values(array_diff($currentCandidates, $nextCandidates));
        $enteredCandidates = array_values(array_diff($nextCandidates, $currentCandidates));
        $changedBytes = [];
        $changedStorage = [];
        $currentByRowid = self::nextTwoFourFive_rowsByRowid($current['candidates']);
        $nextByRowid = self::nextTwoFourFive_rowsByRowid($next['candidates']);
        foreach ($retainedCandidates as $rowid) {
            if (($currentByRowid[$rowid]['nameHex'] ?? null) !== ($nextByRowid[$rowid]['nameHex'] ?? null)) {
                $changedBytes[] = $rowid;
            }
            if (($currentByRowid[$rowid]['storage'] ?? null) !== ($nextByRowid[$rowid]['storage'] ?? null)) {
                $changedStorage[] = $rowid;
            }
        }
        sort($changedBytes);
        sort($changedStorage);

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
        if ($enteredCandidates !== [] || $exitedCandidates !== []) {
            $reasons[] = 'candidate-rowset';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'key-name-bytes';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }
        if ($currentRejected !== [] || $nextRejected !== []) {
            $reasons[] = 'dangling-escape-residual';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourFive',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE ' . ($caseSensitiveLike ? 'BINARY' : 'NOCASE') . ' LIKE ? ESCAPE ? /* dangling ESCAPE residual */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'patternTokenHex' => self::nextTwoFourFive_tokenHexList($pattern),
            'patternCharacters' => self::nextTwoFourFive_sqlitePatternLength($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixTokenHex' => self::nextTwoFourFive_tokenHexList($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'hasWildcard' => $patternPlan['hasWildcard'],
            'rangeLowerInclusive' => $range['lowerInclusive'],
            'rangeUpperBound' => $range['upperBound'],
            'rangeLowerInclusiveHex' => bin2hex($range['lowerInclusive']),
            'rangeUpperBoundHex' => $range['upperBound'] === null ? null : bin2hex($range['upperBound']),
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
            'currentResidualRejectedRowids' => $currentRejected,
            'nextResidualRejectedRowids' => $nextRejected,
            'changedNameBytesRowids' => $changedBytes,
            'changedStorageRowids' => $changedStorage,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentMalformedHex' => $current['malformedHex'],
            'nextMalformedHex' => $next['malformedHex'],
            'currentNames' => self::nextTwoFourFive_fieldByRowid($currentByRowid, 'name'),
            'nextNames' => self::nextTwoFourFive_fieldByRowid($nextByRowid, 'name'),
            'currentNameHex' => self::nextTwoFourFive_fieldByRowid($currentByRowid, 'nameHex'),
            'nextNameHex' => self::nextTwoFourFive_fieldByRowid($nextByRowid, 'nameHex'),
            'currentTokenHex' => self::nextTwoFourFive_fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::nextTwoFourFive_fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTokenCounts' => self::nextTwoFourFive_fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::nextTwoFourFive_fieldByRowid($nextByRowid, 'tokenCount'),
            'currentStorage' => self::nextTwoFourFive_fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::nextTwoFourFive_fieldByRowid($nextByRowid, 'storage'),
            'danglingEscapeMakesResidualFalse' => true,
            'rangeMayAdmitResidualRejectedRows' => true,
            'escapedUnderscoreIsPrefixLiteral' => true,
            'nocaseFoldsAsciiOnly' => true,
            'blobAndNullRemainUnknown' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-dangling-escape-residual',
                'sqlite-like-escape-prefix-range',
                'sqlite-text-affinity',
                'sqlite-current-source-nexttwoFourFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, ESCAPE prefix planning, text-affinity coercion, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFourFive covers dangling ESCAPE LIKE residual rejection after prefix range admission; avoids accepted nextTwoFourTwo embedded-NUL value LIKE, nextTwoFourOne byte-aware key_name LIKE, Unicode GLOB ranges, UTF-16 malformed guards, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{lowerInclusive:string,upperBound:?string} $range
     * @return array{candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,malformedHex:array<int,string>}
     */
    private static function nextTwoFourFive_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, array $range): array
    {
        $candidates = [];
        $matched = [];
        $rejected = [];
        $unknown = [];
        $malformedRowids = [];
        $malformedHex = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name', $row)) {
                throw new \InvalidArgumentException('SQLite dangling-escape LIKE nextTwoFourFive row requires key_name');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            $coerced = self::nextTwoFourFive_coerceText($row['key_name']);
            if ($coerced === null) {
                $unknown[] = $rowid;
                continue;
            }
            [$name, $storage] = $coerced;
            if (preg_match('//u', $name) !== 1) {
                $malformedRowids[] = $rowid;
                $malformedHex[$rowid] = bin2hex($name);
            }
            if (!self::nextTwoFourFive_withinRange($name, $range, $caseSensitiveLike)) {
                continue;
            }
            $entry = [
                'rowid' => $rowid,
                'name' => $name,
                'nameHex' => bin2hex($name),
                'tokenHex' => self::nextTwoFourFive_tokenHexList($name),
                'tokenCount' => self::nextTwoFourFive_sqlitePatternLength($name),
                'storage' => $storage,
            ];
            $candidates[] = $entry;
            if (SQLiteDatabase::likeMatches($name, $pattern, $escape, $caseSensitiveLike)) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp(self::nextTwoFourFive_asciiLower($left['name']), self::nextTwoFourFive_asciiLower($right['name'])) ?: $left['rowid'] <=> $right['rowid'];
        usort($candidates, $sort);
        usort($matched, $sort);
        usort($rejected, $sort);
        sort($unknown);
        sort($malformedRowids);
        ksort($malformedHex);

        return [
            'candidates' => $candidates,
            'matched' => $matched,
            'rejected' => $rejected,
            'unknownRowids' => $unknown,
            'malformedRowids' => $malformedRowids,
            'malformedHex' => $malformedHex,
        ];
    }

    /** @return null|array{0:string,1:string} */
    private static function nextTwoFourFive_coerceText(mixed $value): ?array
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

        throw new \InvalidArgumentException('SQLite dangling-escape LIKE nextTwoFourFive key_name must be scalar text-affinity input');
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoFourFive_withinRange(string $value, array $range, bool $caseSensitiveLike): bool
    {
        $key = $caseSensitiveLike ? $value : self::nextTwoFourFive_asciiLower($value);
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFourFive_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourFive_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFourFive_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    private static function nextTwoFourFive_sqlitePatternLength(string $text): int
    {
        return count(self::nextTwoFourFive_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourFive_tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::nextTwoFourFive_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourFive_sqlitePatternCharacters(string $text): array
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

    private static function nextTwoFourFive_asciiLower(string $value): string
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

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationDynamicEscapeLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        mixed $currentEscape,
        mixed $nextEscape,
        string $collation = 'NOCASE',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@245',
        string $nextSource = 'main.app_settings@246',
        int $currentSchemaCookie = 245,
        int $nextSchemaCookie = 246,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite dynamic ESCAPE LIKE nextTwoFourSix collation must be BINARY, NOCASE, or RTRIM');
        }

        $currentEscapePlan = self::nextTwoFourSix_escapePlan($currentEscape, 'current');
        $nextEscapePlan = self::nextTwoFourSix_escapePlan($nextEscape, 'next');
        $current = self::nextTwoFourSix_scan($currentRows, $pattern, $currentEscapePlan['escape'], $collation, $caseSensitiveLike);
        $next = self::nextTwoFourSix_scan($nextRows, $pattern, $nextEscapePlan['escape'], $collation, $caseSensitiveLike);
        $currentMatched = self::nextTwoFourSix_rowids($current['matched']);
        $nextMatched = self::nextTwoFourSix_rowids($next['matched']);
        $changes = self::nextTwoFourSix_changes($current['trace'], $next['trace']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentEscapePlan['escapeHex'] !== $nextEscapePlan['escapeHex'] || $currentEscapePlan['storage'] !== $nextEscapePlan['storage']) {
            $reasons[] = 'escape-affinity';
        }
        if ($currentEscapePlan['error'] !== null || $nextEscapePlan['error'] !== null) {
            $reasons[] = 'escape-malformed';
        }
        foreach ([
            'storage-class' => $changes['storageRowids'],
            'like-text' => $changes['likeTextRowids'],
            'collation-key' => $changes['collationKeyRowids'],
            'residual-result' => $changes['residualRowids'],
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::nextTwoFourSix_uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourSix',
            'operator' => 'LIKE',
            'expression' => 'key_value COLLATE ' . $collation . ' LIKE ? ESCAPE dynamic_escape /* ESCAPE text affinity current-source fence */',
            'pattern' => $pattern,
            'patternHex' => strtoupper(bin2hex($pattern)),
            'collation' => $collation,
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentEscape' => $currentEscapePlan,
            'nextEscape' => $nextEscapePlan,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedLikeTextRowids' => $changes['likeTextRowids'],
            'changedCollationKeyRowids' => $changes['collationKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'dynamicEscapeUsesTextAffinity' => true,
            'escapeMustBeOneSqlCharacter' => true,
            'escapeRebindInvalidatesCursor' => true,
            'nullEscapeMakesLikeUnknown' => true,
            'blobEscapeIsNotTextAffinityInput' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-dynamic-escape-affinity',
                'sqlite-like-residual',
                'sqlite-nocase-ascii-collation',
                'sqlite-current-source-nexttwoFourSix',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE residual matching, scalar text-affinity conversion, ASCII NOCASE collation keys, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFourSix covers dynamic ESCAPE operand affinity and rebind fencing; avoids fixed escaped wildcard nextTwoThreeSix/nextTwoThreeSeven, malformed-byte LIKE/NOT LIKE, UTF-16 NOCASE/RTRIM cursor fences, Unicode GLOB ranges, and VFS/WAL/B-tree/JSON/SQL executor clusters',
        ];
    }

    /** @return array{storage:string,escape:?string,escapeHex:?string,error:?string,unknown:bool} */
    private static function nextTwoFourSix_escapePlan(mixed $value, string $label): array
    {
        $storage = SQLiteAffinityComparison::storageClass($value);
        if ($value === null) {
            return ['storage' => $storage, 'escape' => null, 'escapeHex' => null, 'error' => null, 'unknown' => true];
        }
        if ($value instanceof SQLiteBlobValue) {
            return ['storage' => $storage, 'escape' => null, 'escapeHex' => strtoupper(bin2hex($value->bytes)), 'error' => "SQLite dynamic ESCAPE LIKE nextTwoFourSix {$label} ESCAPE is BLOB, not text", 'unknown' => true];
        }
        $escape = self::nextTwoFourSix_likeText($value);
        if ($escape === null) {
            return ['storage' => $storage, 'escape' => null, 'escapeHex' => null, 'error' => "SQLite dynamic ESCAPE LIKE nextTwoFourSix {$label} ESCAPE is not scalar text", 'unknown' => true];
        }
        if (self::nextTwoFourSix_sqlitePatternLength($escape) !== 1) {
            return ['storage' => $storage, 'escape' => null, 'escapeHex' => strtoupper(bin2hex($escape)), 'error' => "SQLite dynamic ESCAPE LIKE nextTwoFourSix {$label} ESCAPE must be one SQLite character after affinity", 'unknown' => true];
        }

        return ['storage' => $storage, 'escape' => $escape, 'escapeHex' => strtoupper(bin2hex($escape)), 'error' => null, 'unknown' => false];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{trace:list<array<string,mixed>>,matched:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function nextTwoFourSix_scan(array $rows, string $pattern, ?string $escape, string $collation, bool $caseSensitiveLike): array
    {
        $trace = [];
        $matched = [];
        $unknown = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite dynamic ESCAPE LIKE nextTwoFourSix row requires key_value');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            try {
                $likeText = self::nextTwoFourSix_likeText($row['key_value']);
                if ($likeText === null || $escape === null) {
                    $unknown[] = $rowid;
                    continue;
                }
                if (preg_match('//u', $likeText) !== 1) {
                    throw new \InvalidArgumentException('SQLite dynamic ESCAPE LIKE nextTwoFourSix key_value text is malformed UTF-8');
                }
                $residual = SQLiteDatabase::likeMatches($likeText, $pattern, $escape, $caseSensitiveLike);
                $entry = [
                    'rowid' => $rowid,
                    'keyName' => (string) ($row['key_name'] ?? ''),
                    'storage' => SQLiteAffinityComparison::storageClass($row['key_value']),
                    'likeText' => $likeText,
                    'likeTextHex' => strtoupper(bin2hex($likeText)),
                    'collationKey' => self::nextTwoFourSix_collationKey($likeText, $collation),
                    'residualMatch' => $residual,
                    'matched' => $residual,
                ];
                $trace[] = $entry;
                if ($residual) {
                    $matched[] = $entry;
                }
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, self::nextTwoFourSix_sortTrace(...));
        usort($matched, self::nextTwoFourSix_sortTrace(...));
        sort($unknown);
        sort($malformed);
        ksort($errors);

        return ['trace' => $trace, 'matched' => $matched, 'unknownRowids' => $unknown, 'malformedRowids' => $malformed, 'errors' => $errors];
    }

    private static function nextTwoFourSix_likeText(mixed $value): ?string
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $text = sprintf('%.15g', $value);
            return str_contains($text, '.') || stripos($text, 'e') !== false ? $text : $text . '.0';
        }
        if (is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite dynamic ESCAPE LIKE nextTwoFourSix key_value must be scalar text-affinity input');
    }

    private static function nextTwoFourSix_collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'NOCASE' => strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
            'RTRIM' => rtrim($text, ' '),
            default => $text,
        };
    }

    private static function nextTwoFourSix_sortTrace(array $left, array $right): int
    {
        return strcmp($left['collationKey'], $right['collationKey']) ?: $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFourSix_rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{storageRowids:list<int>,likeTextRowids:list<int>,collationKeyRowids:list<int>,residualRowids:list<int>}
     */
    private static function nextTwoFourSix_changes(array $current, array $next): array
    {
        $currentByRowid = self::nextTwoFourSix_rowsByRowid($current);
        $nextByRowid = self::nextTwoFourSix_rowsByRowid($next);
        $rowids = self::nextTwoFourSix_uniqueSortedInts(array_merge(array_keys($currentByRowid), array_keys($nextByRowid)));
        $storage = [];
        $text = [];
        $key = [];
        $residual = [];
        foreach ($rowids as $rowid) {
            $left = $currentByRowid[$rowid] ?? null;
            $right = $nextByRowid[$rowid] ?? null;
            if ($left === null || $right === null) {
                $storage[] = $rowid;
                $text[] = $rowid;
                $key[] = $rowid;
                $residual[] = $rowid;
                continue;
            }
            if ($left['storage'] !== $right['storage']) {
                $storage[] = $rowid;
            }
            if ($left['likeText'] !== $right['likeText']) {
                $text[] = $rowid;
            }
            if ($left['collationKey'] !== $right['collationKey']) {
                $key[] = $rowid;
            }
            if ($left['residualMatch'] !== $right['residualMatch']) {
                $residual[] = $rowid;
            }
        }

        return [
            'storageRowids' => self::nextTwoFourSix_uniqueSortedInts($storage),
            'likeTextRowids' => self::nextTwoFourSix_uniqueSortedInts($text),
            'collationKeyRowids' => self::nextTwoFourSix_uniqueSortedInts($key),
            'residualRowids' => self::nextTwoFourSix_uniqueSortedInts($residual),
        ];
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourSix_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param list<int> $values @return list<int> */
    private static function nextTwoFourSix_uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    private static function nextTwoFourSix_sqlitePatternLength(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        if (preg_match_all('/./us', $text, $matches) === false || implode('', $matches[0]) !== $text) {
            return strlen($text);
        }

        return count($matches[0]);
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationUnicodeNoCaseLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $collation = 'NOCASE',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@246',
        string $nextSource = 'main.app_settings@247',
        int $currentSchemaCookie = 246,
        int $nextSchemaCookie = 247,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite LIKE nextTwoFourSeven collation: {$collation}");
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::nextTwoFourSeven_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::nextTwoFourSeven_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentMatched = array_values(array_filter($current, static fn (array $row): bool => $row['residualMatch']));
        $nextMatched = array_values(array_filter($next, static fn (array $row): bool => $row['residualMatch']));
        $currentRowids = self::nextTwoFourSeven_rowids($currentMatched);
        $nextRowids = self::nextTwoFourSeven_rowids($nextMatched);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $currentByRowid = self::nextTwoFourSeven_rowsByRowid($currentMatched);
        $nextByRowid = self::nextTwoFourSeven_rowsByRowid($nextMatched);
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

        $indexUsable = self::nextTwoFourSeven_likeIndexUsable($patternPlan, $collation, $caseSensitiveLike);
        $currentRejected = self::nextTwoFourSeven_rowids(array_values(array_filter($current, static fn (array $row): bool => !$row['residualMatch'])));
        $nextRejected = self::nextTwoFourSeven_rowids(array_values(array_filter($next, static fn (array $row): bool => !$row['residualMatch'])));
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
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourSeven',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE ' . $collation . ' LIKE ? ESCAPE ? /* non-ASCII prefix keeps residual authoritative */',
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
            'rangeRejectedReason' => $indexUsable ? null : self::nextTwoFourSeven_rangeRejectedReason($patternPlan, $collation, $caseSensitiveLike),
            'rangeLowerInclusive' => $indexUsable ? $patternPlan[$caseSensitiveLike ? 'binaryRange' : 'noCaseRange']['lowerInclusive'] ?? null : null,
            'rangeUpperBound' => $indexUsable ? $patternPlan[$caseSensitiveLike ? 'binaryRange' : 'noCaseRange']['upperBound'] ?? null : null,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::nextTwoFourSeven_rowids($current),
            'nextCandidateRowids' => self::nextTwoFourSeven_rowids($next),
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
            'currentNames' => self::nextTwoFourSeven_fieldByRowid($currentByRowid, 'likeText'),
            'nextNames' => self::nextTwoFourSeven_fieldByRowid($nextByRowid, 'likeText'),
            'currentNameHex' => self::nextTwoFourSeven_fieldByRowid($currentByRowid, 'likeTextHex'),
            'nextNameHex' => self::nextTwoFourSeven_fieldByRowid($nextByRowid, 'likeTextHex'),
            'currentEncodings' => self::nextTwoFourSeven_fieldByRowid($currentByRowid, 'textEncoding'),
            'nextEncodings' => self::nextTwoFourSeven_fieldByRowid($nextByRowid, 'textEncoding'),
            'currentStorage' => self::nextTwoFourSeven_fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorage' => self::nextTwoFourSeven_fieldByRowid($nextByRowid, 'storageClass'),
            'currentTrace' => self::nextTwoFourSeven_traceByPosition($current),
            'nextTrace' => self::nextTwoFourSeven_traceByPosition($next),
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
                'sqlite-current-source-nexttwoFourSeven',
            ],
            'dependency_closure' => 'no new support component needed; reuses lane-local LIKE tokenization, mixed UTF decoding, ASCII-only NOCASE semantics, and text-affinity diagnostics',
            'non_overlap' => 'nextTwoFourSeven covers non-ASCII LIKE prefixes under NOCASE with mixed UTF and scalar text affinity; avoids accepted Unicode GLOB ranges, UTF-16 malformed guards, numeric key_value LIKE nextTwoFourZero, byte/NUL key_name LIKE nextTwoFourOne, mixed UTF ASCII-prefix LIKE nextTwoFourFour, SQL executor, JSON, WAL, VFS, and B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function nextTwoFourSeven_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $scanned = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name', $row) && !array_key_exists('key_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite LIKE nextTwoFourSeven rows require key_name or key_name_bytes');
            }
            $coerced = self::nextTwoFourSeven_coerceLikeText($row, $index);
            if ($coerced === null) {
                continue;
            }
            $scanned[] = [
                'rowid' => is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1,
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
    private static function nextTwoFourSeven_coerceLikeText(array $row, int $index): ?array
    {
        if (array_key_exists('key_name_bytes', $row)) {
            if (!is_string($row['key_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite LIKE nextTwoFourSeven byte rows require key_name_bytes and integer text_encoding');
            }
            return [
                'likeText' => SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']),
                'textEncoding' => self::nextTwoFourSeven_encodingName($row['text_encoding']),
                'storageClass' => 'text',
            ];
        }

        $value = $row['key_name'];
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value)) {
            return ['likeText' => (string) $value, 'textEncoding' => 'UTF-8', 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            return ['likeText' => self::nextTwoFourSeven_formatReal($value), 'textEncoding' => 'UTF-8', 'storageClass' => 'real'];
        }
        if (is_bool($value)) {
            return ['likeText' => $value ? '1' : '0', 'textEncoding' => 'UTF-8', 'storageClass' => 'integer'];
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite LIKE nextTwoFourSeven string key_name must be well-formed UTF-8');
            }
            return ['likeText' => $value, 'textEncoding' => 'UTF-8', 'storageClass' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite LIKE nextTwoFourSeven key_name must be scalar text-affinity input');
    }

    /** @param array<string,mixed> $patternPlan */
    private static function nextTwoFourSeven_likeIndexUsable(array $patternPlan, string $collation, bool $caseSensitiveLike): bool
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
    private static function nextTwoFourSeven_rangeRejectedReason(array $patternPlan, string $collation, bool $caseSensitiveLike): string
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

    private static function nextTwoFourSeven_formatReal(float $value): string
    {
        $formatted = sprintf('%.15G', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    private static function nextTwoFourSeven_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite LIKE nextTwoFourSeven text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFourSeven_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourSeven_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFourSeven_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourSeven_traceByPosition(array $rows): array
    {
        $trace = [];
        foreach ($rows as $position => $row) {
            $trace[$position] = $row;
        }

        return $trace;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationNonAsciiEscapeLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'moduleé_cacheé%%',
        ?string $escape = 'é',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@247',
        string $nextSource = 'main.app_settings@248',
        int $currentSchemaCookie = 247,
        int $nextSchemaCookie = 248,
    ): array {
        if ($escape !== null && self::nextTwoFourEight_sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite non-ASCII ESCAPE LIKE nextTwoFourEight ESCAPE must be one SQLite pattern character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $caseSensitiveLike ? $patternPlan['binaryRange'] : $patternPlan['noCaseRange'];
        $current = self::nextTwoFourEight_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike, $range);
        $next = self::nextTwoFourEight_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike, $range);
        $currentCandidates = self::nextTwoFourEight_rowids($current['candidates']);
        $nextCandidates = self::nextTwoFourEight_rowids($next['candidates']);
        $currentMatched = self::nextTwoFourEight_rowids($current['matched']);
        $nextMatched = self::nextTwoFourEight_rowids($next['matched']);
        $currentRejected = self::nextTwoFourEight_rowids($current['rejected']);
        $nextRejected = self::nextTwoFourEight_rowids($next['rejected']);
        $retainedMatched = array_values(array_intersect($currentMatched, $nextMatched));
        $exitedMatched = array_values(array_diff($currentMatched, $nextMatched));
        $enteredMatched = array_values(array_diff($nextMatched, $currentMatched));
        $currentByRowid = self::nextTwoFourEight_rowsByRowid($current['trace']);
        $nextByRowid = self::nextTwoFourEight_rowsByRowid($next['trace']);
        $changedDecoded = [];
        $changedEncoding = [];
        $changedResidual = [];
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if (($currentByRowid[$rowid]['decodedHex'] ?? null) !== ($nextByRowid[$rowid]['decodedHex'] ?? null)) {
                $changedDecoded[] = $rowid;
            }
            if (($currentByRowid[$rowid]['textEncoding'] ?? null) !== ($nextByRowid[$rowid]['textEncoding'] ?? null)) {
                $changedEncoding[] = $rowid;
            }
            if (($currentByRowid[$rowid]['residualMatch'] ?? null) !== ($nextByRowid[$rowid]['residualMatch'] ?? null)) {
                $changedResidual[] = $rowid;
            }
        }
        sort($changedDecoded);
        sort($changedEncoding);
        sort($changedResidual);

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
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedDecoded !== []) {
            $reasons[] = 'decoded-text';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedResidual !== []) {
            $reasons[] = 'like-residual-result';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourEight',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE ' . ($caseSensitiveLike ? 'BINARY' : 'NOCASE') . ' LIKE ? ESCAPE ? /* non-ASCII ESCAPE */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'patternTokenHex' => self::nextTwoFourEight_tokenHexList($pattern),
            'patternCharacters' => self::nextTwoFourEight_sqlitePatternLength($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'escapeTokenHex' => $escape === null ? null : self::nextTwoFourEight_tokenHexList($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixTokenHex' => self::nextTwoFourEight_tokenHexList($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'hasWildcard' => $patternPlan['hasWildcard'],
            'rangeLowerInclusive' => $range['lowerInclusive'],
            'rangeUpperBound' => $range['upperBound'],
            'rangeLowerInclusiveHex' => bin2hex($range['lowerInclusive']),
            'rangeUpperBoundHex' => $range['upperBound'] === null ? null : bin2hex($range['upperBound']),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedMatchedRowids' => $retainedMatched,
            'exitedMatchedRowids' => $exitedMatched,
            'enteredMatchedRowids' => $enteredMatched,
            'currentResidualRejectedRowids' => $currentRejected,
            'nextResidualRejectedRowids' => $nextRejected,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentDecodedHex' => self::nextTwoFourEight_fieldByRowid($currentByRowid, 'decodedHex'),
            'nextDecodedHex' => self::nextTwoFourEight_fieldByRowid($nextByRowid, 'decodedHex'),
            'currentTokenHex' => self::nextTwoFourEight_fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::nextTwoFourEight_fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTextEncoding' => self::nextTwoFourEight_fieldByRowid($currentByRowid, 'textEncoding'),
            'nextTextEncoding' => self::nextTwoFourEight_fieldByRowid($nextByRowid, 'textEncoding'),
            'currentResidualMatches' => self::nextTwoFourEight_fieldByRowid($currentByRowid, 'residualMatch'),
            'nextResidualMatches' => self::nextTwoFourEight_fieldByRowid($nextByRowid, 'residualMatch'),
            'changedDecodedRowids' => $changedDecoded,
            'changedEncodingRowids' => $changedEncoding,
            'changedResidualRowids' => $changedResidual,
            'nonAsciiEscapeIsSinglePatternCharacter' => true,
            'escapedUnderscoreAndPercentAreLiterals' => true,
            'prefixRangeUsesDecodedTextNotEncodedBytes' => true,
            'nocaseFoldsAsciiOnlyAfterUtf16Decode' => true,
            'malformedUtf16RowsDoNotEnterRange' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-non-ascii-escape-tokenizer',
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-nocase-ascii-collation',
                'sqlite-current-source-nexttwoFourEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, UTF-16 decode guards, escaped-prefix range planning, ASCII-only NOCASE comparison, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFourEight covers non-ASCII single-character ESCAPE handling for UTF-8/UTF-16 key_name LIKE scans; avoids accepted nextTwoFourFive dangling ASCII ESCAPE residuals, nextTwoFourTwo embedded-NUL value LIKE, Unicode GLOB ranges, UTF-16 malformed insert guards, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function nextTwoFourEight_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, array $range): array
    {
        $trace = [];
        $candidates = [];
        $matched = [];
        $rejected = [];
        $unknown = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite non-ASCII ESCAPE LIKE nextTwoFourEight row requires key_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite non-ASCII ESCAPE LIKE nextTwoFourEight row requires integer text_encoding');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            if ($row['key_name_bytes'] === null || $row['key_name_bytes'] instanceof SQLiteBlobValue) {
                $unknown[] = $rowid;
                continue;
            }
            if (!is_string($row['key_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite non-ASCII ESCAPE LIKE nextTwoFourEight key_name_bytes must be text bytes');
            }

            try {
                $decoded = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                if (str_contains($exception->getMessage(), 'SQLite text encoding must be')) {
                    throw $exception;
                }
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
                continue;
            }

            $residual = SQLiteDatabase::likeMatches($decoded, $pattern, $escape, $caseSensitiveLike);
            $entry = [
                'rowid' => $rowid,
                'decoded' => $decoded,
                'decodedHex' => bin2hex($decoded),
                'tokenHex' => self::nextTwoFourEight_tokenHexList($decoded),
                'textEncoding' => self::nextTwoFourEight_encodingName($row['text_encoding']),
                'rangeCandidate' => self::nextTwoFourEight_withinRange($decoded, $range, $caseSensitiveLike),
                'residualMatch' => $residual,
            ];
            $trace[] = $entry;
            if (!$entry['rangeCandidate']) {
                continue;
            }
            $candidates[] = $entry;
            if ($residual) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp(self::nextTwoFourEight_asciiLower($left['decoded']), self::nextTwoFourEight_asciiLower($right['decoded'])) ?: $left['rowid'] <=> $right['rowid'];
        usort($trace, $sort);
        usort($candidates, $sort);
        usort($matched, $sort);
        usort($rejected, $sort);
        sort($unknown);
        sort($malformed);
        ksort($errors);

        return [
            'trace' => $trace,
            'candidates' => $candidates,
            'matched' => $matched,
            'rejected' => $rejected,
            'unknownRowids' => $unknown,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoFourEight_withinRange(string $value, array $range, bool $caseSensitiveLike): bool
    {
        $key = $caseSensitiveLike ? $value : self::nextTwoFourEight_asciiLower($value);
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFourEight_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourEight_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFourEight_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    private static function nextTwoFourEight_sqlitePatternLength(string $text): int
    {
        return count(self::nextTwoFourEight_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourEight_tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::nextTwoFourEight_sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourEight_sqlitePatternCharacters(string $text): array
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

    private static function nextTwoFourEight_asciiLower(string $text): string
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function nextTwoFourEight_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationRtrimLikeSourcePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'module!_cache',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@248',
        string $nextSource = 'main.app_settings@249',
        int $currentSchemaCookie = 248,
        int $nextSchemaCookie = 249,
    ): array {
        if ($escape !== null && self::nextTwoFourNine_sqliteCharacterCount($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine ESCAPE must be one SQLite pattern character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $patternPlan['binaryRange'];
        $current = self::nextTwoFourNine_scanRows($currentRows, $pattern, $escape, $range);
        $next = self::nextTwoFourNine_scanRows($nextRows, $pattern, $escape, $range);
        $currentCandidates = self::nextTwoFourNine_rowids($current);
        $nextCandidates = self::nextTwoFourNine_rowids($next);
        $currentMatchedRows = array_values(array_filter($current, static fn (array $row): bool => $row['residualMatch']));
        $nextMatchedRows = array_values(array_filter($next, static fn (array $row): bool => $row['residualMatch']));
        $currentRejectedRows = array_values(array_filter($current, static fn (array $row): bool => !$row['residualMatch']));
        $nextRejectedRows = array_values(array_filter($next, static fn (array $row): bool => !$row['residualMatch']));
        $currentMatched = self::nextTwoFourNine_rowids($currentMatchedRows);
        $nextMatched = self::nextTwoFourNine_rowids($nextMatchedRows);
        $currentRejected = self::nextTwoFourNine_rowids($currentRejectedRows);
        $nextRejected = self::nextTwoFourNine_rowids($nextRejectedRows);
        $retainedCandidates = array_values(array_intersect($currentCandidates, $nextCandidates));
        $exitedCandidates = array_values(array_diff($currentCandidates, $nextCandidates));
        $enteredCandidates = array_values(array_diff($nextCandidates, $currentCandidates));
        $retainedMatched = array_values(array_intersect($currentMatched, $nextMatched));
        $exitedMatched = array_values(array_diff($currentMatched, $nextMatched));
        $enteredMatched = array_values(array_diff($nextMatched, $currentMatched));

        $currentByRowid = self::nextTwoFourNine_rowsByRowid($current);
        $nextByRowid = self::nextTwoFourNine_rowsByRowid($next);
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
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFourNine',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE RTRIM LIKE ? ESCAPE ? /* RTRIM range, LIKE residual */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'patternTokenHex' => self::nextTwoFourNine_tokenHexList($pattern),
            'patternCharacters' => self::nextTwoFourNine_sqliteCharacterCount($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'caseSensitiveLike' => false,
            'collation' => 'RTRIM',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixTokenHex' => self::nextTwoFourNine_tokenHexList($patternPlan['prefix']),
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
            'currentNames' => self::nextTwoFourNine_fieldByRowid($currentByRowid, 'key'),
            'nextNames' => self::nextTwoFourNine_fieldByRowid($nextByRowid, 'key'),
            'currentKeyBytesHex' => self::nextTwoFourNine_fieldByRowid($currentByRowid, 'keyBytesHex'),
            'nextKeyBytesHex' => self::nextTwoFourNine_fieldByRowid($nextByRowid, 'keyBytesHex'),
            'currentEncodings' => self::nextTwoFourNine_fieldByRowid($currentByRowid, 'textEncoding'),
            'nextEncodings' => self::nextTwoFourNine_fieldByRowid($nextByRowid, 'textEncoding'),
            'currentResidualMatches' => self::nextTwoFourNine_fieldByRowid($currentByRowid, 'residualMatch'),
            'nextResidualMatches' => self::nextTwoFourNine_fieldByRowid($nextByRowid, 'residualMatch'),
            'currentPositions' => self::nextTwoFourNine_fieldByRowid($currentByRowid, 'position'),
            'nextPositions' => self::nextTwoFourNine_fieldByRowid($nextByRowid, 'position'),
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
                'sqlite-current-source-nexttwoFourNine',
            ],
            'dependency_closure' => 'no new support component needed; reuses native mixed UTF source decoding, LIKE tokenization, RTRIM collation range checks, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFourNine covers RTRIM-collation LIKE range admission with trailing-space residual rejection across mixed UTF current/next sources; avoids accepted nextTwoFourFive dangling ESCAPE residuals, nextTwoFourFour mixed UTF NOCASE LIKE, Unicode GLOB ranges, UTF-16 malformed guards, SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int,residualMatch:bool}>
     */
    private static function nextTwoFourNine_scanRows(array $rows, string $pattern, ?string $escape, array $range): array
    {
        $ranged = [];
        foreach (self::nextTwoFourNine_normalizeRows($rows) as $position => $row) {
            $key = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
            if (!self::nextTwoFourNine_inRtrimRange($key, $range)) {
                continue;
            }
            $ranged[] = [
                'rowid' => $row['setting_id'],
                'key' => $key,
                'keyBytesHex' => bin2hex($row['key_name_bytes']),
                'textEncoding' => self::nextTwoFourNine_encodingName($row['text_encoding']),
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
    private static function nextTwoFourNine_normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            if (!isset($row['setting_id']) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine rows require integer setting_id');
            }
            if (array_key_exists('key_name_bytes', $row)) {
                if (!is_string($row['key_name_bytes'])) {
                    throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine key_name_bytes must be a string');
                }
                if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                    throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine byte rows require integer text_encoding');
                }
                $normalized[] = $row;
                continue;
            }
            if (!array_key_exists('key_name', $row)) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine rows require key_name or key_name_bytes');
            }
            $value = $row['key_name'];
            if ($value === null || $value instanceof SQLiteBlobValue) {
                continue;
            }
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine key_name must be scalar text-affinity input');
            }
            $encoding = self::nextTwoFourNine_encodingCode($row['text_encoding'] ?? 'UTF-8');
            $row['key_name_bytes'] = SQLiteEncodingCollationSourceCursor::encodeText((string) $value, $encoding);
            $row['text_encoding'] = $encoding;
            $row['setting_id'] = $row['setting_id'] ?? $index + 1;
            $normalized[] = $row;
        }

        return $normalized;
    }

    private static function nextTwoFourNine_encodingCode(mixed $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }
        if (!is_string($encoding)) {
            throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function nextTwoFourNine_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFourNine text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoFourNine_inRtrimRange(string $key, array $range): bool
    {
        $key = rtrim($key, ' ');
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFourNine_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFourNine_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFourNine_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    private static function nextTwoFourNine_sqliteCharacterCount(string $text): int
    {
        return count(self::nextTwoFourNine_sqliteCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourNine_tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::nextTwoFourNine_sqliteCharacters($text));
    }

    /** @return list<string> */
    private static function nextTwoFourNine_sqliteCharacters(string $text): array
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

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationRtrimLikeResidualSourcePlan(        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@249',
        string $nextSource = 'main.app_settings@250',
        int $currentSchemaCookie = 249,
        int $nextSchemaCookie = 250,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::nextTwoFiveZero_scanRows($currentRows, $pattern, $escape, $caseSensitiveLike);
        $next = self::nextTwoFiveZero_scanRows($nextRows, $pattern, $escape, $caseSensitiveLike);
        $currentMatched = array_values(array_filter($current, static fn (array $row): bool => $row['residualMatch']));
        $nextMatched = array_values(array_filter($next, static fn (array $row): bool => $row['residualMatch']));
        $currentRejectedPeers = self::nextTwoFiveZero_rtrimPeerRowids($current);
        $nextRejectedPeers = self::nextTwoFiveZero_rtrimPeerRowids($next);
        $currentRowids = self::nextTwoFiveZero_rowids($currentMatched);
        $nextRowids = self::nextTwoFiveZero_rowids($nextMatched);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $currentByRowid = self::nextTwoFiveZero_rowsByRowid($current);
        $nextByRowid = self::nextTwoFiveZero_rowsByRowid($next);
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
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFiveZero',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE RTRIM LIKE ? ESCAPE ? /* RTRIM key never trims LIKE residual */',
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
            'currentCandidateRowids' => self::nextTwoFiveZero_rowids($current),
            'nextCandidateRowids' => self::nextTwoFiveZero_rowids($next),
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
            'currentRawText' => self::nextTwoFiveZero_fieldByRowid($currentByRowid, 'likeText'),
            'nextRawText' => self::nextTwoFiveZero_fieldByRowid($nextByRowid, 'likeText'),
            'currentRawHex' => self::nextTwoFiveZero_fieldByRowid($currentByRowid, 'likeTextHex'),
            'nextRawHex' => self::nextTwoFiveZero_fieldByRowid($nextByRowid, 'likeTextHex'),
            'currentRtrimKeys' => self::nextTwoFiveZero_fieldByRowid($currentByRowid, 'rtrimKey'),
            'nextRtrimKeys' => self::nextTwoFiveZero_fieldByRowid($nextByRowid, 'rtrimKey'),
            'currentEncodings' => self::nextTwoFiveZero_fieldByRowid($currentByRowid, 'textEncoding'),
            'nextEncodings' => self::nextTwoFiveZero_fieldByRowid($nextByRowid, 'textEncoding'),
            'currentStorage' => self::nextTwoFiveZero_fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorage' => self::nextTwoFiveZero_fieldByRowid($nextByRowid, 'storageClass'),
            'currentTrace' => self::nextTwoFiveZero_traceByPosition($current),
            'nextTrace' => self::nextTwoFiveZero_traceByPosition($next),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-escape-tokenizer',
                'sqlite-rtrim-collation-key',
                'sqlite-like-residual-raw-text',
                'sqlite-mixed-utf-source-decoder',
                'sqlite-current-source-nexttwoFiveZero',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, RTRIM collation-key diagnostics, mixed UTF decoding, and current-source invalidation tracking',
            'non_overlap' => 'nextTwoFiveZero covers RTRIM collation peers that must still fail raw LIKE residuals when trailing spaces remain; avoids nextTwoFourSeven non-ASCII NOCASE prefixes, nextTwoFourSix dynamic ESCAPE affinity, nextTwoFourOne embedded-NUL/malformed-byte LIKE, numeric key_value LIKE nextTwoFourZero, Unicode GLOB ranges, UTF-16 malformed guards, and SQL/JSON/WAL/VFS/B-tree clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function nextTwoFiveZero_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $scanned = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name', $row) && !array_key_exists('key_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFiveZero rows require key_name or key_name_bytes');
            }
            $coerced = self::nextTwoFiveZero_coerceLikeText($row, $index);
            if ($coerced === null) {
                continue;
            }
            $likeText = $coerced['likeText'];
            $scanned[] = [
                'rowid' => is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1,
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
    private static function nextTwoFiveZero_coerceLikeText(array $row, int $index): ?array
    {
        if (array_key_exists('key_name_bytes', $row)) {
            if (!is_string($row['key_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFiveZero byte rows require key_name_bytes and integer text_encoding');
            }

            return [
                'likeText' => SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']),
                'textEncoding' => self::nextTwoFiveZero_encodingName($row['text_encoding']),
                'storageClass' => 'text',
            ];
        }

        $value = $row['key_name'];
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value)) {
            return ['likeText' => (string) $value, 'textEncoding' => 'UTF-8', 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            return ['likeText' => self::nextTwoFiveZero_formatReal($value), 'textEncoding' => 'UTF-8', 'storageClass' => 'real'];
        }
        if (is_bool($value)) {
            return ['likeText' => $value ? '1' : '0', 'textEncoding' => 'UTF-8', 'storageClass' => 'integer'];
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFiveZero string key_name must be well-formed UTF-8');
            }

            return ['likeText' => $value, 'textEncoding' => 'UTF-8', 'storageClass' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFiveZero key_name must be scalar text-affinity input');
    }

    private static function nextTwoFiveZero_formatReal(float $value): string
    {
        $formatted = sprintf('%.15G', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    private static function nextTwoFiveZero_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoFiveZero text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFiveZero_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFiveZero_rtrimPeerRowids(array $rows): array
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
    private static function nextTwoFiveZero_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFiveZero_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFiveZero_traceByPosition(array $rows): array
    {
        $trace = [];
        foreach ($rows as $position => $row) {
            $trace[$position] = $row;
        }

        return $trace;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationPreparedPatternAffinityPlan(
        array $currentRows,
        array $nextRows,
        mixed $currentPattern,
        mixed $nextPattern,
        mixed $currentEscape = null,
        mixed $nextEscape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@250',
        string $nextSource = 'main.app_settings@251',
        int $currentSchemaCookie = 250,
        int $nextSchemaCookie = 251,
    ): array {
        $currentPatternText = self::nextTwoFiveOne_coerceLikeText($currentPattern, 'pattern');
        $nextPatternText = self::nextTwoFiveOne_coerceLikeText($nextPattern, 'pattern');
        $currentEscapeText = self::nextTwoFiveOne_coerceEscapeText($currentEscape, 'current');
        $nextEscapeText = self::nextTwoFiveOne_coerceEscapeText($nextEscape, 'next');
        $patternPlan = SQLiteDatabase::likePatternPlan($currentPatternText['text'], $currentEscapeText['text']);
        $current = self::nextTwoFiveOne_scanRows($currentRows, $currentPatternText['text'], $currentEscapeText['text'], $caseSensitiveLike);
        $next = self::nextTwoFiveOne_scanRows($nextRows, $nextPatternText['text'], $nextEscapeText['text'], $caseSensitiveLike);

        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $currentByRowid = self::nextTwoFiveOne_rowsByRowid($current);
        $nextByRowid = self::nextTwoFiveOne_rowsByRowid($next);
        $changedValueText = [];
        $changedValueStorage = [];

        foreach ($retained as $rowid) {
            if (($currentByRowid[$rowid]['valueText'] ?? null) !== ($nextByRowid[$rowid]['valueText'] ?? null)) {
                $changedValueText[] = $rowid;
            }
            if (($currentByRowid[$rowid]['valueStorage'] ?? null) !== ($nextByRowid[$rowid]['valueStorage'] ?? null)) {
                $changedValueStorage[] = $rowid;
            }
        }

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentPatternText['text'] !== $nextPatternText['text']) {
            $reasons[] = 'pattern-text';
        }
        if ($currentPatternText['storageClass'] !== $nextPatternText['storageClass']) {
            $reasons[] = 'pattern-storage-class';
        }
        if ($currentPatternText['hex'] !== $nextPatternText['hex']) {
            $reasons[] = 'pattern-bytes';
        }
        if (($currentEscapeText['text'] ?? null) !== ($nextEscapeText['text'] ?? null)) {
            $reasons[] = 'escape-text';
        }
        if (($currentEscapeText['storageClass'] ?? null) !== ($nextEscapeText['storageClass'] ?? null)) {
            $reasons[] = 'escape-storage-class';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedValueText !== []) {
            $reasons[] = 'value-text';
        }
        if ($changedValueStorage !== []) {
            $reasons[] = 'value-storage-class';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFiveOne',
            'operator' => 'LIKE',
            'expression' => 'key_value LIKE ? ESCAPE ? /* prepared pattern affinity current-source fence */',
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'currentPatternText' => $currentPatternText['text'],
            'nextPatternText' => $nextPatternText['text'],
            'currentPatternHex' => $currentPatternText['hex'],
            'nextPatternHex' => $nextPatternText['hex'],
            'currentPatternStorageClass' => $currentPatternText['storageClass'],
            'nextPatternStorageClass' => $nextPatternText['storageClass'],
            'currentEscapeText' => $currentEscapeText['text'],
            'nextEscapeText' => $nextEscapeText['text'],
            'currentEscapeHex' => $currentEscapeText['hex'],
            'nextEscapeHex' => $nextEscapeText['hex'],
            'currentEscapeStorageClass' => $currentEscapeText['storageClass'],
            'nextEscapeStorageClass' => $nextEscapeText['storageClass'],
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
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
            'changedValueTextRowids' => $changedValueText,
            'changedValueStorageClassRowids' => $changedValueStorage,
            'currentValueText' => self::nextTwoFiveOne_fieldByRowid($currentByRowid, 'valueText'),
            'nextValueText' => self::nextTwoFiveOne_fieldByRowid($nextByRowid, 'valueText'),
            'currentValueStorageClasses' => self::nextTwoFiveOne_fieldByRowid($currentByRowid, 'valueStorage'),
            'nextValueStorageClasses' => self::nextTwoFiveOne_fieldByRowid($nextByRowid, 'valueStorage'),
            'currentKeyNames' => self::nextTwoFiveOne_fieldByRowid($currentByRowid, 'keyName'),
            'nextKeyNames' => self::nextTwoFiveOne_fieldByRowid($nextByRowid, 'keyName'),
            'patternStorageClassChangeInvalidatesEvenWhenTextMatches' => true,
            'escapeStorageClassChangeInvalidatesEvenWhenTextMatches' => true,
            'blobPatternAndBlobEscapeDoNotEnterLikeMatcher' => true,
            'numericAndBooleanPatternsUseTextAffinity' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-escape-tokenizer',
                'sqlite-pattern-text-affinity',
                'sqlite-current-source-nexttwoFiveOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, SQLite scalar storage classification, numeric/boolean text affinity, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFiveOne covers prepared LIKE pattern and ESCAPE affinity/storage transitions for key_value scans; avoids accepted numeric value LIKE nextTwoFourZero, embedded-NUL key_name nextTwoFourOne, UTF-16 mixed-source nextTwoFourFour, escaped key_name nextTwoThreeSix, Unicode GLOB ranges, malformed UTF guards, and storage/planner clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function nextTwoFiveOne_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite LIKE nextTwoFiveOne row requires key_value');
            }
            $value = self::nextTwoFiveOne_coerceLikeText($row['key_value'], 'key_value');
            if (!SQLiteDatabase::likeMatches($value['text'], $pattern, $escape, $caseSensitiveLike)) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1,
                'keyName' => self::nextTwoFiveOne_keyName($row, $index),
                'valueText' => $value['text'],
                'valueHex' => $value['hex'],
                'valueStorage' => $value['storageClass'],
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['valueText'], $right['valueText']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    /** @return array{text:string,hex:string,storageClass:string} */
    private static function nextTwoFiveOne_coerceLikeText(mixed $value, string $label): array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            throw new \InvalidArgumentException("SQLite LIKE nextTwoFiveOne {$label} must not be NULL or BLOB");
        }
        if (is_string($value)) {
            $text = $value;
            $storage = 'text';
        } elseif (is_int($value)) {
            $text = (string) $value;
            $storage = 'integer';
        } elseif (is_float($value)) {
            $text = self::nextTwoFiveOne_formatReal($value);
            $storage = 'real';
        } elseif (is_bool($value)) {
            $text = $value ? '1' : '0';
            $storage = 'integer';
        } else {
            throw new \InvalidArgumentException("SQLite LIKE nextTwoFiveOne {$label} must be scalar text-affinity input");
        }

        return ['text' => $text, 'hex' => bin2hex($text), 'storageClass' => $storage];
    }

    /** @return array{text:?string,hex:?string,storageClass:?string} */
    private static function nextTwoFiveOne_coerceEscapeText(mixed $value, string $source): array
    {
        if ($value === null) {
            return ['text' => null, 'hex' => null, 'storageClass' => null];
        }

        $escape = self::nextTwoFiveOne_coerceLikeText($value, $source . ' ESCAPE');
        if (SQLiteDatabase::likeMatches('', '', $escape['text']) !== true) {
            throw new \LogicException('unreachable LIKE ESCAPE validation guard');
        }

        return $escape;
    }

    private static function nextTwoFiveOne_formatReal(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }
        if ($value === INF) {
            return 'Inf';
        }
        if ($value === -INF) {
            return '-Inf';
        }

        $formatted = sprintf('%.15G', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoFiveOne_keyName(array $row, int $index): string
    {
        $name = $row['key_name'] ?? 'setting_' . ($index + 1);

        return is_scalar($name) ? (string) $name : 'setting_' . ($index + 1);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFiveOne_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFiveOne_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationLoadPolicyValuePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'yes%',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@252',
        string $nextSource = 'main.app_settings@253',
        int $currentSchemaCookie = 252,
        int $nextSchemaCookie = 253,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::nextTwoFiveThree_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::nextTwoFiveThree_scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::nextTwoFiveThree_changes($current['decoded'], $next['decoded']);
        $residualChanged = self::nextTwoFiveThree_residualChanges($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'text-affinity' => $changes['textAffinityChangedRowids'],
            'storage-class' => $changes['storageChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'encoding' => $changes['encodingChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'residual-result' => $residualChanged,
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if (self::nextTwoFiveThree_rowids($current['matched']) !== self::nextTwoFiveThree_rowids($next['matched'])) {
            $reasons[] = 'matched-rowset';
        }
        if (self::nextTwoFiveThree_rowids($current['candidate']) !== self::nextTwoFiveThree_rowids($next['candidate'])) {
            $reasons[] = 'candidate-rowset';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFiveThree',
            'operator' => 'LIKE',
            'expression' => 'key_value COLLATE NOCASE LIKE ? /* TEXT affinity cursor */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'affinity' => 'TEXT',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'rejectedReason' => $like['rejectedReason'],
            'currentCandidateRowids' => self::nextTwoFiveThree_rowids($current['candidate']),
            'nextCandidateRowids' => self::nextTwoFiveThree_rowids($next['candidate']),
            'currentMatchedRowids' => self::nextTwoFiveThree_rowids($current['matched']),
            'nextMatchedRowids' => self::nextTwoFiveThree_rowids($next['matched']),
            'matchedRetainedRowids' => self::nextTwoFiveThree_intersectSorted(self::nextTwoFiveThree_rowids($current['matched']), self::nextTwoFiveThree_rowids($next['matched'])),
            'matchedExitedRowids' => self::nextTwoFiveThree_diffSorted(self::nextTwoFiveThree_rowids($current['matched']), self::nextTwoFiveThree_rowids($next['matched'])),
            'matchedEnteredRowids' => self::nextTwoFiveThree_diffSorted(self::nextTwoFiveThree_rowids($next['matched']), self::nextTwoFiveThree_rowids($current['matched'])),
            'currentFalsePositiveRowids' => self::nextTwoFiveThree_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::nextTwoFiveThree_rowids($next['falsePositive']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentStorageClasses' => self::nextTwoFiveThree_map($current['decoded'], 'storageClass'),
            'nextStorageClasses' => self::nextTwoFiveThree_map($next['decoded'], 'storageClass'),
            'currentTextValues' => self::nextTwoFiveThree_map($current['decoded'], 'textValue'),
            'nextTextValues' => self::nextTwoFiveThree_map($next['decoded'], 'textValue'),
            'currentNocaseKeys' => self::nextTwoFiveThree_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::nextTwoFiveThree_map($next['decoded'], 'nocaseKey'),
            'currentEncodingNames' => self::nextTwoFiveThree_map($current['decoded'], 'encodingName'),
            'nextEncodingNames' => self::nextTwoFiveThree_map($next['decoded'], 'encodingName'),
            'currentByteHex' => self::nextTwoFiveThree_map($current['decoded'], 'byteHex'),
            'nextByteHex' => self::nextTwoFiveThree_map($next['decoded'], 'byteHex'),
            'currentResidualMatches' => self::nextTwoFiveThree_map($current['decoded'], 'residualMatch'),
            'nextResidualMatches' => self::nextTwoFiveThree_map($next['decoded'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedTextAffinityRowids' => $changes['textAffinityChangedRowids'],
            'changedStorageRowids' => $changes['storageChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedResidualRowids' => $residualChanged,
            'textAffinityAppliedBeforeLike' => true,
            'blobValuesDoNotMatchTextLike' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-text-affinity',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-nexttwoFiveThree',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, TEXT affinity coercion, ASCII NOCASE LIKE matching, and current-source rowset diagnostics',
            'non_overlap' => 'nextTwoFiveThree covers key_value TEXT-affinity LIKE over mixed UTF-8/UTF-16/scalar storage; avoids accepted key_name UTF-16 RTRIM/NOCASE LIKE current-source, Unicode GLOB, malformed insert guards, VFS/WAL/B-tree/JSON/planner clusters, and suite nextTwoFiveThree evidence',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decoded:list<array<string,mixed>>,candidate:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function nextTwoFiveThree_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::nextTwoFiveThree_assertRow($row);
            try {
                $value = self::nextTwoFiveThree_textAffinityValue($row);
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'storageClass' => self::nextTwoFiveThree_storageClass($row),
                    'textValue' => $value,
                    'nocaseKey' => strtolower($value),
                    'encodingName' => self::nextTwoFiveThree_encodingName($row['value_encoding'] ?? null),
                    'byteHex' => isset($row['key_value_bytes']) && is_string($row['key_value_bytes']) ? bin2hex($row['key_value_bytes']) : null,
                    'residualMatch' => SQLiteDatabase::likeMatches($value, $pattern, $escape, false),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }

        usort($decoded, static fn (array $left, array $right): int => strcmp($left['nocaseKey'], $right['nocaseKey']) ?: $left['rowid'] <=> $right['rowid']);
        sort($malformed);
        ksort($errors);

        $candidate = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::nextTwoFiveThree_inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $candidate[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }

        return [
            'decoded' => $decoded,
            'candidate' => $candidate,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoFiveThree_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoFiveThree_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveThree rows require integer setting_id');
        }
        if (!array_key_exists('storage', $row) || !is_string($row['storage'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveThree rows require storage');
        }
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoFiveThree_textAffinityValue(array $row): string
    {
        return match (strtolower($row['storage'])) {
            'text' => self::nextTwoFiveThree_decodeTextRow($row),
            'integer', 'real' => (string) $row['key_value'],
            'null' => '',
            'blob' => throw new \InvalidArgumentException('SQLite TEXT affinity LIKE does not coerce BLOB key_value bytes'),
            default => throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveThree unsupported storage class'),
        };
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoFiveThree_decodeTextRow(array $row): string
    {
        if (!array_key_exists('key_value_bytes', $row) || !is_string($row['key_value_bytes'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveThree text rows require key_value_bytes');
        }
        if (!array_key_exists('value_encoding', $row) || !is_int($row['value_encoding'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveThree text rows require integer value_encoding');
        }

        return SQLiteEncodingCollationSourceCursor::decodeText($row['key_value_bytes'], $row['value_encoding']);
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoFiveThree_storageClass(array $row): string
    {
        return strtolower($row['storage']);
    }

    private static function nextTwoFiveThree_encodingName(mixed $encoding): ?string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            null => null,
            default => 'unknown',
        };
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFiveThree_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function nextTwoFiveThree_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return array<string,list<int>> */
    private static function nextTwoFiveThree_changes(array $left, array $right): array
    {
        $leftById = self::nextTwoFiveThree_byRowid($left);
        $rightById = self::nextTwoFiveThree_byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changes = [
            'textChangedRowids' => [],
            'textAffinityChangedRowids' => [],
            'storageChangedRowids' => [],
            'bytesChangedRowids' => [],
            'encodingChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
        ];
        foreach ($rowids as $rowid) {
            $leftRow = $leftById[$rowid] ?? null;
            $rightRow = $rightById[$rowid] ?? null;
            foreach ([
                'textChangedRowids' => 'textValue',
                'textAffinityChangedRowids' => 'textValue',
                'storageChangedRowids' => 'storageClass',
                'bytesChangedRowids' => 'byteHex',
                'encodingChangedRowids' => 'encodingName',
                'nocaseKeyChangedRowids' => 'nocaseKey',
            ] as $bucket => $key) {
                if (($leftRow[$key] ?? null) !== ($rightRow[$key] ?? null)) {
                    $changes[$bucket][] = (int) $rowid;
                }
            }
        }

        return $changes;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFiveThree_byRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }
        ksort($indexed);

        return $indexed;
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return list<int> */
    private static function nextTwoFiveThree_residualChanges(array $left, array $right): array
    {
        $leftById = self::nextTwoFiveThree_byRowid($left);
        $rightById = self::nextTwoFiveThree_byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($leftById[$rowid]['residualMatch'] ?? null) !== ($rightById[$rowid]['residualMatch'] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoFiveThree_intersectSorted(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoFiveThree_diffSorted(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationNullableEscapeLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'module!_%',
        mixed $currentEscape = null,
        bool $currentEscapeIsExplicit = true,
        mixed $nextEscape = '!',
        bool $nextEscapeIsExplicit = true,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@253',
        string $nextSource = 'main.app_settings@254',
        int $currentSchemaCookie = 253,
        int $nextSchemaCookie = 254,
    ): array {
        $currentEscapeValue = self::nextTwoFiveFour_coerceEscape($currentEscape, $currentEscapeIsExplicit, 'current');
        $nextEscapeValue = self::nextTwoFiveFour_coerceEscape($nextEscape, $nextEscapeIsExplicit, 'next');
        $prefixPlan = SQLiteDatabase::likePatternPlan($pattern, $nextEscapeValue['likeEscape']);
        $current = self::nextTwoFiveFour_scanRows($currentRows, $pattern, $currentEscapeValue, $caseSensitiveLike);
        $next = self::nextTwoFiveFour_scanRows($nextRows, $pattern, $nextEscapeValue, $caseSensitiveLike);
        $currentMatched = self::nextTwoFiveFour_rowids($current['matchedRows']);
        $nextMatched = self::nextTwoFiveFour_rowids($next['matchedRows']);
        $retained = array_values(array_intersect($currentMatched, $nextMatched));
        $exited = array_values(array_diff($currentMatched, $nextMatched));
        $entered = array_values(array_diff($nextMatched, $currentMatched));
        $currentByRowid = self::nextTwoFiveFour_rowsByRowid($current['decisions']);
        $nextByRowid = self::nextTwoFiveFour_rowsByRowid($next['decisions']);
        $changedTruth = [];
        $changedText = [];
        $changedStorage = [];

        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['predicateResult'] !== $nextByRowid[$rowid]['predicateResult']) {
                $changedTruth[] = $rowid;
            }
            if ($currentByRowid[$rowid]['text'] !== $nextByRowid[$rowid]['text']) {
                $changedText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storageClass'] !== $nextByRowid[$rowid]['storageClass']) {
                $changedStorage[] = $rowid;
            }
        }
        sort($changedTruth);
        sort($changedText);
        sort($changedStorage);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentEscapeValue['sqlNullEscape'] !== $nextEscapeValue['sqlNullEscape']) {
            $reasons[] = 'escape-nullability';
        }
        if ($currentEscapeValue['text'] !== $nextEscapeValue['text']) {
            $reasons[] = 'escape-text';
        }
        if ($currentEscapeValue['storageClass'] !== $nextEscapeValue['storageClass']) {
            $reasons[] = 'escape-storage-class';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedTruth !== []) {
            $reasons[] = 'predicate-truth';
        }
        if ($changedText !== []) {
            $reasons[] = 'value-text';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'value-storage-class';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFiveFour',
            'operator' => 'LIKE',
            'expression' => 'key_value LIKE ? ESCAPE ? /* explicit SQL NULL ESCAPE is UNKNOWN, not omitted ESCAPE */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'currentEscapeText' => $currentEscapeValue['text'],
            'nextEscapeText' => $nextEscapeValue['text'],
            'currentEscapeHex' => $currentEscapeValue['hex'],
            'nextEscapeHex' => $nextEscapeValue['hex'],
            'currentEscapeStorageClass' => $currentEscapeValue['storageClass'],
            'nextEscapeStorageClass' => $nextEscapeValue['storageClass'],
            'currentEscapeWasExplicit' => $currentEscapeValue['explicit'],
            'nextEscapeWasExplicit' => $nextEscapeValue['explicit'],
            'currentEscapeIsSqlNull' => $currentEscapeValue['sqlNullEscape'],
            'nextEscapeIsSqlNull' => $nextEscapeValue['sqlNullEscape'],
            'omittedEscapeStillUsesLikeDefault' => true,
            'explicitNullEscapeForcesUnknownPredicate' => true,
            'notLikeWouldAlsoRemainUnknown' => true,
            'prefix' => $prefixPlan['prefix'],
            'prefixHex' => bin2hex($prefixPlan['prefix']),
            'prefixCharacters' => $prefixPlan['prefixCharacters'],
            'binaryRange' => $prefixPlan['binaryRange'],
            'noCaseRange' => $prefixPlan['noCaseRange'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'retainedMatchedRowids' => $retained,
            'exitedMatchedRowids' => $exited,
            'enteredMatchedRowids' => $entered,
            'changedPredicateTruthRowids' => $changedTruth,
            'changedValueTextRowids' => $changedText,
            'changedStorageClassRowids' => $changedStorage,
            'currentPredicateResults' => self::nextTwoFiveFour_fieldByRowid($currentByRowid, 'predicateResult'),
            'nextPredicateResults' => self::nextTwoFiveFour_fieldByRowid($nextByRowid, 'predicateResult'),
            'currentValueText' => self::nextTwoFiveFour_fieldByRowid($currentByRowid, 'text'),
            'nextValueText' => self::nextTwoFiveFour_fieldByRowid($nextByRowid, 'text'),
            'currentValueHex' => self::nextTwoFiveFour_fieldByRowid($currentByRowid, 'textHex'),
            'nextValueHex' => self::nextTwoFiveFour_fieldByRowid($nextByRowid, 'textHex'),
            'currentStorage' => self::nextTwoFiveFour_fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorage' => self::nextTwoFiveFour_fieldByRowid($nextByRowid, 'storageClass'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-escape-nullability',
                'sqlite-like-escape-tokenizer',
                'sqlite-text-affinity',
                'sqlite-current-source-nexttwoFiveFour',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, scalar text affinity, explicit SQL NULL handling, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFiveFour covers explicit SQL NULL ESCAPE versus omitted ESCAPE for LIKE predicates; avoids nextTwoFiveOne prepared pattern storage transitions, nextTwoFiveZero RTRIM residual peers, nextTwoThreeEight real text-affinity LIKE, nextTwoThreeFive malformed-byte NOT LIKE complement, Unicode GLOB ranges, and UTF-16 NOCASE/RTRIM cursor handoffs',
        ];
    }

    /**
     * @param array{text:?string,hex:?string,storageClass:?string,explicit:bool,sqlNullEscape:bool,likeEscape:?string} $escape
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>,matchedRows:list<array<string,mixed>>,unknownRowids:list<int>}
     */
    private static function nextTwoFiveFour_scanRows(array $rows, string $pattern, array $escape, bool $caseSensitiveLike): array
    {
        $decisions = [];
        $matched = [];
        $unknown = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite nullable ESCAPE LIKE nextTwoFiveFour rows require key_value');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            $value = self::nextTwoFiveFour_coerceText($row['key_value']);
            if ($value === null || $escape['sqlNullEscape']) {
                $unknown[] = $rowid;
                if ($value !== null) {
                    $decisions[] = [
                        'rowid' => $rowid,
                        'text' => $value['text'],
                        'textHex' => $value['hex'],
                        'storageClass' => $value['storageClass'],
                        'predicateResult' => null,
                    ];
                }
                continue;
            }

            $result = SQLiteDatabase::likeMatches($value['text'], $pattern, $escape['likeEscape'], $caseSensitiveLike);
            $decision = [
                'rowid' => $rowid,
                'text' => $value['text'],
                'textHex' => $value['hex'],
                'storageClass' => $value['storageClass'],
                'predicateResult' => $result,
            ];
            $decisions[] = $decision;
            if ($result) {
                $matched[] = $decision;
            }
        }

        usort($decisions, static fn (array $left, array $right): int => strcmp($left['text'], $right['text']) ?: $left['rowid'] <=> $right['rowid']);
        usort($matched, static fn (array $left, array $right): int => strcmp($left['text'], $right['text']) ?: $left['rowid'] <=> $right['rowid']);
        sort($unknown);

        return ['decisions' => $decisions, 'matchedRows' => $matched, 'unknownRowids' => $unknown];
    }

    /** @return array{text:?string,hex:?string,storageClass:?string,explicit:bool,sqlNullEscape:bool,likeEscape:?string} */
    private static function nextTwoFiveFour_coerceEscape(mixed $value, bool $explicit, string $label): array
    {
        if ($value === null) {
            return [
                'text' => null,
                'hex' => null,
                'storageClass' => null,
                'explicit' => $explicit,
                'sqlNullEscape' => $explicit,
                'likeEscape' => null,
            ];
        }

        $text = self::nextTwoFiveFour_coerceText($value);
        if ($text === null) {
            throw new \InvalidArgumentException("SQLite nullable ESCAPE LIKE nextTwoFiveFour {$label} ESCAPE must not be BLOB");
        }
        SQLiteDatabase::likePatternPlan('', $text['text']);

        return [
            'text' => $text['text'],
            'hex' => $text['hex'],
            'storageClass' => $text['storageClass'],
            'explicit' => $explicit,
            'sqlNullEscape' => false,
            'likeEscape' => $text['text'],
        ];
    }

    /** @return null|array{text:string,hex:string,storageClass:string} */
    private static function nextTwoFiveFour_coerceText(mixed $value): ?array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_string($value)) {
            $text = $value;
            $storage = 'text';
        } elseif (is_int($value)) {
            $text = (string) $value;
            $storage = 'integer';
        } elseif (is_float($value)) {
            $text = self::nextTwoFiveFour_formatReal($value);
            $storage = 'real';
        } elseif (is_bool($value)) {
            $text = $value ? '1' : '0';
            $storage = 'integer';
        } else {
            throw new \InvalidArgumentException('SQLite nullable ESCAPE LIKE nextTwoFiveFour value must be scalar text-affinity input');
        }

        return ['text' => $text, 'hex' => bin2hex($text), 'storageClass' => $storage];
    }

    private static function nextTwoFiveFour_formatReal(float $value): string
    {
        if (!is_finite($value)) {
            return (string) $value;
        }
        $formatted = sprintf('%.15G', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFiveFour_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFiveFour_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFiveFour_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationGlobClassFallbackPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $currentSource = 'main.app_settings@254',
        string $nextSource = 'main.app_settings@255',
        int $currentSchemaCookie = 254,
        int $nextSchemaCookie = 255,
    ): array {
        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $current = self::nextTwoFiveFive_scanRows($currentRows, $pattern);
        $next = self::nextTwoFiveFive_scanRows($nextRows, $pattern);
        $currentRowids = self::nextTwoFiveFive_rowids($current);
        $nextRowids = self::nextTwoFiveFive_rowids($next);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $currentByRowid = self::nextTwoFiveFive_rowsByRowid($current);
        $nextByRowid = self::nextTwoFiveFive_rowsByRowid($next);
        $changedText = [];
        $changedBytes = [];
        $changedEncoding = [];
        $changedStorage = [];
        $changedResidual = [];

        foreach (array_values(array_intersect(array_keys($currentByRowid), array_keys($nextByRowid))) as $rowid) {
            if ($currentByRowid[$rowid]['text'] !== $nextByRowid[$rowid]['text']) {
                $changedText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['bytesHex'] !== $nextByRowid[$rowid]['bytesHex']) {
                $changedBytes[] = $rowid;
            }
            if ($currentByRowid[$rowid]['textEncoding'] !== $nextByRowid[$rowid]['textEncoding']) {
                $changedEncoding[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storageClass'] !== $nextByRowid[$rowid]['storageClass']) {
                $changedStorage[] = $rowid;
            }
            if ($currentByRowid[$rowid]['residualMatch'] !== $nextByRowid[$rowid]['residualMatch']) {
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
        if ($range === null) {
            $reasons[] = 'glob-class-full-scan';
        }
        foreach ([
            'text-value' => $changedText,
            'text-bytes' => $changedBytes,
            'text-encoding' => $changedEncoding,
            'storage-class' => $changedStorage,
            'residual-truth' => $changedResidual,
            'matched-rowset' => ($entered !== [] || $exited !== []) ? array_values(array_unique(array_merge($entered, $exited))) : [],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFiveFive',
            'operator' => 'GLOB',
            'expression' => 'key_name GLOB ? /* bracket class has no prefix range */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'range' => $range,
            'rangeUsable' => $range !== null,
            'fullScanResidualRequired' => $range === null,
            'globCharacterClassPattern' => str_starts_with($pattern, '[') || str_starts_with($pattern, '[^'),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'enteredRowids' => $entered,
            'exitedRowids' => $exited,
            'changedTextRowids' => $changedText,
            'changedBytesRowids' => $changedBytes,
            'changedEncodingRowids' => $changedEncoding,
            'changedStorageClassRowids' => $changedStorage,
            'changedResidualTruthRowids' => $changedResidual,
            'currentText' => self::nextTwoFiveFive_fieldByRowid($currentByRowid, 'text'),
            'nextText' => self::nextTwoFiveFive_fieldByRowid($nextByRowid, 'text'),
            'currentBytesHex' => self::nextTwoFiveFive_fieldByRowid($currentByRowid, 'bytesHex'),
            'nextBytesHex' => self::nextTwoFiveFive_fieldByRowid($nextByRowid, 'bytesHex'),
            'currentTextEncodings' => self::nextTwoFiveFive_fieldByRowid($currentByRowid, 'textEncoding'),
            'nextTextEncodings' => self::nextTwoFiveFive_fieldByRowid($nextByRowid, 'textEncoding'),
            'currentStorageClasses' => self::nextTwoFiveFive_fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorageClasses' => self::nextTwoFiveFive_fieldByRowid($nextByRowid, 'storageClass'),
            'currentKeyValues' => self::nextTwoFiveFive_fieldByRowid($currentByRowid, 'keyValue'),
            'nextKeyValues' => self::nextTwoFiveFive_fieldByRowid($nextByRowid, 'keyValue'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-glob-character-class',
                'sqlite-mixed-utf-source-decoder',
                'sqlite-text-affinity',
                'sqlite-current-source-nexttwoFiveFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-8/UTF-16 decode, scalar text-affinity coercion, and GLOB bracket-class residual matching',
            'non_overlap' => 'nextTwoFiveFive covers GLOB bracket-class residual fallback when no fixed prefix range exists; avoids nextTwoFiveTwo numeric prefix cursor, nextTwoFiveOne prepared LIKE pattern affinity, nextTwoFiveZero RTRIM LIKE residual peers, accepted Unicode GLOB prefix ranges, malformed UTF guards, JSON, WAL, VFS, B-tree, and SQL planner clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function nextTwoFiveFive_scanRows(array $rows, string $pattern): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name', $row) && !array_key_exists('key_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite GLOB class nextTwoFiveFive rows require key_name or key_name_bytes');
            }
            $coerced = self::nextTwoFiveFive_coerceText($row);
            if ($coerced === null) {
                continue;
            }
            $residual = SQLiteDatabase::globMatches($coerced['text'], $pattern);
            if (!$residual) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1,
                'text' => $coerced['text'],
                'bytesHex' => bin2hex($coerced['bytes']),
                'textEncoding' => $coerced['textEncoding'],
                'storageClass' => $coerced['storageClass'],
                'keyValue' => $row['key_value'] ?? null,
                'residualMatch' => true,
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['text'], $right['text']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    /** @param array<string,mixed> $row @return array{text:string,bytes:string,textEncoding:string,storageClass:string}|null */
    private static function nextTwoFiveFive_coerceText(array $row): ?array
    {
        if (array_key_exists('key_name_bytes', $row)) {
            if (!is_string($row['key_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite GLOB class nextTwoFiveFive byte rows require key_name_bytes and integer text_encoding');
            }
            $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);

            return [
                'text' => $text,
                'bytes' => $row['key_name_bytes'],
                'textEncoding' => self::nextTwoFiveFive_encodingName($row['text_encoding']),
                'storageClass' => 'text',
            ];
        }

        $value = $row['key_name'];
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value) || is_bool($value)) {
            $text = (string) (int) $value;
            return ['text' => $text, 'bytes' => $text, 'textEncoding' => 'UTF-8', 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            $text = self::nextTwoFiveFive_formatReal($value);
            return ['text' => $text, 'bytes' => $text, 'textEncoding' => 'UTF-8', 'storageClass' => 'real'];
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite GLOB class nextTwoFiveFive string key_name must be well-formed UTF-8');
            }

            return ['text' => $value, 'bytes' => $value, 'textEncoding' => 'UTF-8', 'storageClass' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite GLOB class nextTwoFiveFive key_name must be scalar text-affinity input');
    }

    private static function nextTwoFiveFive_formatReal(float $value): string
    {
        $formatted = sprintf('%.15G', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    private static function nextTwoFiveFive_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite GLOB class nextTwoFiveFive text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFiveFive_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFiveFive_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFiveFive_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationPatternAffinityPlan(
        array $currentRows,
        array $nextRows,
        mixed $currentPattern,
        mixed $nextPattern,
        ?string $escape = null,
        string $collation = 'NOCASE',
        string $currentSource = 'main.app_settings@255',
        string $nextSource = 'main.app_settings@256',
        int $currentSchemaCookie = 255,
        int $nextSchemaCookie = 256,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite pattern-affinity LIKE nextTwoFiveSix collation must be BINARY, NOCASE, or RTRIM');
        }
        if ($escape !== null && self::nextTwoFiveSix_sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite pattern-affinity LIKE nextTwoFiveSix ESCAPE must be one SQLite character');
        }

        $currentPatternPlan = self::nextTwoFiveSix_patternPlan($currentPattern, $escape, $collation, 'current');
        $nextPatternPlan = self::nextTwoFiveSix_patternPlan($nextPattern, $escape, $collation, 'next');
        $current = self::nextTwoFiveSix_scan($currentRows, $currentPatternPlan['patternText'], $escape, $currentPatternPlan['range'], $collation);
        $next = self::nextTwoFiveSix_scan($nextRows, $nextPatternPlan['patternText'], $escape, $nextPatternPlan['range'], $collation);
        $changes = self::nextTwoFiveSix_changes($current['trace'], $next['trace']);
        $currentMatched = self::nextTwoFiveSix_rowids($current['matched']);
        $nextMatched = self::nextTwoFiveSix_rowids($next['matched']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentPatternPlan['storage'] !== $nextPatternPlan['storage']) {
            $reasons[] = 'pattern-storage';
        }
        if ($currentPatternPlan['patternText'] !== $nextPatternPlan['patternText']) {
            $reasons[] = 'pattern-text';
        }
        if ($currentPatternPlan['patternKey'] !== $nextPatternPlan['patternKey']) {
            $reasons[] = 'pattern-collation-key';
        }
        if ($currentPatternPlan['error'] !== null || $nextPatternPlan['error'] !== null) {
            $reasons[] = 'pattern-malformed';
        }
        foreach ([
            'storage-class' => $changes['storageRowids'],
            'like-text' => $changes['likeTextRowids'],
            'collation-key' => $changes['collationKeyRowids'],
            'candidate-rowset' => self::nextTwoFiveSix_rowids($current['candidate']) === self::nextTwoFiveSix_rowids($next['candidate']) ? [] : self::nextTwoFiveSix_uniqueSortedInts(array_merge(self::nextTwoFiveSix_rowids($current['candidate']), self::nextTwoFiveSix_rowids($next['candidate']))),
            'residual-result' => $changes['residualRowids'],
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::nextTwoFiveSix_uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['unknownRowids'] !== [] || $next['unknownRowids'] !== []) {
            $reasons[] = 'unknown-like';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFiveSix',
            'operator' => 'LIKE',
            'expression' => 'key_value COLLATE ' . $collation . ' LIKE dynamic_pattern /* pattern TEXT affinity current-source fence */',
            'escape' => $escape,
            'collation' => $collation,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPattern' => $currentPatternPlan,
            'nextPattern' => $nextPatternPlan,
            'currentCandidateRowids' => self::nextTwoFiveSix_rowids($current['candidate']),
            'nextCandidateRowids' => self::nextTwoFiveSix_rowids($next['candidate']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => self::nextTwoFiveSix_intersectSorted($currentMatched, $nextMatched),
            'enteredRowids' => self::nextTwoFiveSix_diffSorted($nextMatched, $currentMatched),
            'exitedRowids' => self::nextTwoFiveSix_diffSorted($currentMatched, $nextMatched),
            'currentFalsePositiveRowids' => self::nextTwoFiveSix_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::nextTwoFiveSix_rowids($next['falsePositive']),
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedLikeTextRowids' => $changes['likeTextRowids'],
            'changedCollationKeyRowids' => $changes['collationKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'patternUsesTextAffinity' => true,
            'nullPatternMakesLikeUnknown' => true,
            'blobPatternIsRejected' => true,
            'blobValuesDoNotMatchTextLike' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-pattern-text-affinity',
                'sqlite-like-prefix-range',
                'sqlite-nocase-rtrim-collation',
                'sqlite-current-source-nexttwoFiveSix',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE matching, SQLite scalar text-affinity conversion, ASCII NOCASE/RTRIM collation keys, and current-source diagnostics',
            'non_overlap' => 'nextTwoFiveSix covers the LIKE pattern operand TEXT-affinity current-source fence; avoids nextTwoFiveThree fixed-pattern value affinity, nextTwoFourSix dynamic ESCAPE affinity, UTF-16 NOCASE/RTRIM cursor work, Unicode GLOB ranges, JSON/VFS/WAL/B-tree/planner clusters, and suite evidence slices',
        ];
    }

    /** @return array<string,mixed> */
    private static function nextTwoFiveSix_patternPlan(mixed $pattern, ?string $escape, string $collation, string $label): array
    {
        $storage = SQLiteAffinityComparison::storageClass($pattern);
        if ($pattern === null) {
            return self::nextTwoFiveSix_emptyPatternPlan($storage, null, null);
        }
        if ($pattern instanceof SQLiteBlobValue) {
            return self::nextTwoFiveSix_emptyPatternPlan($storage, null, "SQLite pattern-affinity LIKE nextTwoFiveSix {$label} pattern is BLOB, not text");
        }

        $text = self::nextTwoFiveSix_textAffinity($pattern, "SQLite pattern-affinity LIKE nextTwoFiveSix {$label} pattern");
        if (preg_match('//u', $text) !== 1) {
            return self::nextTwoFiveSix_emptyPatternPlan($storage, $text, "SQLite pattern-affinity LIKE nextTwoFiveSix {$label} pattern text is malformed UTF-8");
        }

        $like = SQLiteLikeCollationPlan::plan($text, $collation, $escape, false);

        return [
            'storage' => $storage,
            'patternText' => $text,
            'patternHex' => strtoupper(bin2hex($text)),
            'patternKey' => self::nextTwoFiveSix_collationKey($text, $collation),
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'rejectedReason' => $like['rejectedReason'],
            'error' => null,
            'unknown' => false,
        ];
    }

    /** @return array<string,mixed> */
    private static function nextTwoFiveSix_emptyPatternPlan(string $storage, ?string $text, ?string $error): array
    {
        return [
            'storage' => $storage,
            'patternText' => $text,
            'patternHex' => $text === null ? null : strtoupper(bin2hex($text)),
            'patternKey' => null,
            'prefix' => '',
            'range' => null,
            'indexUsable' => false,
            'rejectedReason' => $error === null ? 'pattern_is_null' : 'pattern_is_not_text',
            'error' => $error,
            'unknown' => true,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidate:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function nextTwoFiveSix_scan(array $rows, ?string $pattern, ?string $escape, ?array $range, string $collation): array
    {
        $trace = [];
        $unknown = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite pattern-affinity LIKE nextTwoFiveSix row requires key_value');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            if ($pattern === null) {
                $unknown[] = $rowid;
                continue;
            }
            try {
                $likeText = self::nextTwoFiveSix_textAffinity($row['key_value'], 'SQLite pattern-affinity LIKE nextTwoFiveSix key_value');
                if (preg_match('//u', $likeText) !== 1) {
                    throw new \InvalidArgumentException('SQLite pattern-affinity LIKE nextTwoFiveSix key_value text is malformed UTF-8');
                }
                $entry = [
                    'rowid' => $rowid,
                    'keyName' => (string) ($row['key_name'] ?? ''),
                    'storage' => SQLiteAffinityComparison::storageClass($row['key_value']),
                    'likeText' => $likeText,
                    'likeTextHex' => strtoupper(bin2hex($likeText)),
                    'collationKey' => self::nextTwoFiveSix_collationKey($likeText, $collation),
                    'residualMatch' => SQLiteDatabase::likeMatches($likeText, $pattern, $escape, false),
                ];
                $trace[] = $entry;
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, self::nextTwoFiveSix_sortTrace(...));
        sort($unknown);
        sort($malformed);
        ksort($errors);

        $candidate = [];
        $matched = [];
        $falsePositive = [];
        foreach ($trace as $entry) {
            if (!self::nextTwoFiveSix_inRange($entry['collationKey'], $range)) {
                continue;
            }
            $candidate[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }

        return [
            'trace' => $trace,
            'candidate' => $candidate,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'unknownRowids' => $unknown,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoFiveSix_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    private static function nextTwoFiveSix_textAffinity(mixed $value, string $label): string
    {
        if ($value instanceof SQLiteBlobValue) {
            throw new \InvalidArgumentException($label . ' is BLOB, not text');
        }
        if ($value === null) {
            throw new \InvalidArgumentException($label . ' is NULL');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $text = sprintf('%.15g', $value);
            return str_contains($text, '.') || stripos($text, 'e') !== false ? $text : $text . '.0';
        }
        if (is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException($label . ' must be scalar text-affinity input');
    }

    private static function nextTwoFiveSix_collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'NOCASE' => strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
            'RTRIM' => rtrim($text, ' '),
            default => $text,
        };
    }

    private static function nextTwoFiveSix_sortTrace(array $left, array $right): int
    {
        return strcmp($left['collationKey'], $right['collationKey']) ?: $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFiveSix_rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{storageRowids:list<int>,likeTextRowids:list<int>,collationKeyRowids:list<int>,residualRowids:list<int>}
     */
    private static function nextTwoFiveSix_changes(array $current, array $next): array
    {
        $currentByRowid = self::nextTwoFiveSix_rowsByRowid($current);
        $nextByRowid = self::nextTwoFiveSix_rowsByRowid($next);
        $rowids = self::nextTwoFiveSix_uniqueSortedInts(array_merge(array_keys($currentByRowid), array_keys($nextByRowid)));
        $storage = [];
        $text = [];
        $key = [];
        $residual = [];
        foreach ($rowids as $rowid) {
            $left = $currentByRowid[$rowid] ?? null;
            $right = $nextByRowid[$rowid] ?? null;
            if ($left === null || $right === null) {
                $storage[] = $rowid;
                $text[] = $rowid;
                $key[] = $rowid;
                $residual[] = $rowid;
                continue;
            }
            if ($left['storage'] !== $right['storage']) {
                $storage[] = $rowid;
            }
            if ($left['likeText'] !== $right['likeText']) {
                $text[] = $rowid;
            }
            if ($left['collationKey'] !== $right['collationKey']) {
                $key[] = $rowid;
            }
            if ($left['residualMatch'] !== $right['residualMatch']) {
                $residual[] = $rowid;
            }
        }

        return [
            'storageRowids' => self::nextTwoFiveSix_uniqueSortedInts($storage),
            'likeTextRowids' => self::nextTwoFiveSix_uniqueSortedInts($text),
            'collationKeyRowids' => self::nextTwoFiveSix_uniqueSortedInts($key),
            'residualRowids' => self::nextTwoFiveSix_uniqueSortedInts($residual),
        ];
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFiveSix_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param list<int> $values @return list<int> */
    private static function nextTwoFiveSix_uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoFiveSix_intersectSorted(array $left, array $right): array
    {
        return self::nextTwoFiveSix_uniqueSortedInts(array_values(array_intersect($left, $right)));
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoFiveSix_diffSorted(array $left, array $right): array
    {
        return self::nextTwoFiveSix_uniqueSortedInts(array_values(array_diff($left, $right)));
    }

    private static function nextTwoFiveSix_sqliteTextLength(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        if (preg_match_all('/./us', $text, $matches) === false || implode('', $matches[0]) !== $text) {
            return strlen($text);
        }

        return count($matches[0]);
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyNumericAffinityLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = '2024%',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@256',
        string $nextSource = 'main.app_settings@257',
        int $currentSchemaCookie = 256,
        int $nextSchemaCookie = 257,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::nextTwoFiveSeven_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::nextTwoFiveSeven_scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::nextTwoFiveSeven_changes($current['decoded'], $next['decoded']);
        $residualChanges = self::nextTwoFiveSeven_residualChanges($current['decoded'], $next['decoded']);
        $currentCandidates = self::nextTwoFiveSeven_rowids($current['candidate']);
        $nextCandidates = self::nextTwoFiveSeven_rowids($next['candidate']);
        $currentMatched = self::nextTwoFiveSeven_rowids($current['matched']);
        $nextMatched = self::nextTwoFiveSeven_rowids($next['matched']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'name-affinity' => $changes['textChangedRowids'],
            'storage-class' => $changes['storageChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'encoding' => $changes['encodingChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'residual-result' => $residualChanges,
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFiveSeven',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE NOCASE LIKE ? /* NUMERIC storage coerced through TEXT affinity */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'affinity' => 'TEXT-for-LIKE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'rejectedReason' => $like['rejectedReason'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => self::nextTwoFiveSeven_intersectSorted($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::nextTwoFiveSeven_diffSorted($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::nextTwoFiveSeven_diffSorted($nextMatched, $currentMatched),
            'currentFalsePositiveRowids' => self::nextTwoFiveSeven_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::nextTwoFiveSeven_rowids($next['falsePositive']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentStorageClasses' => self::nextTwoFiveSeven_map($current['decoded'], 'storageClass'),
            'nextStorageClasses' => self::nextTwoFiveSeven_map($next['decoded'], 'storageClass'),
            'currentTextValues' => self::nextTwoFiveSeven_map($current['decoded'], 'textValue'),
            'nextTextValues' => self::nextTwoFiveSeven_map($next['decoded'], 'textValue'),
            'currentNocaseKeys' => self::nextTwoFiveSeven_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::nextTwoFiveSeven_map($next['decoded'], 'nocaseKey'),
            'currentEncodingNames' => self::nextTwoFiveSeven_map($current['decoded'], 'encodingName'),
            'nextEncodingNames' => self::nextTwoFiveSeven_map($next['decoded'], 'encodingName'),
            'currentByteHex' => self::nextTwoFiveSeven_map($current['decoded'], 'byteHex'),
            'nextByteHex' => self::nextTwoFiveSeven_map($next['decoded'], 'byteHex'),
            'currentResidualMatches' => self::nextTwoFiveSeven_map($current['decoded'], 'residualMatch'),
            'nextResidualMatches' => self::nextTwoFiveSeven_map($next['decoded'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedStorageRowids' => $changes['storageChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedResidualRowids' => $residualChanges,
            'numericStorageCoercedBeforeLike' => true,
            'blobAndNullRemainOutsideLikeCursor' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-nocase-prefix-range',
                'sqlite-text-affinity',
                'sqlite-utf16-decode',
                'sqlite-current-source-nexttwoFiveSeven',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE prefix planning, TEXT affinity coercion for numeric storage, UTF-16 decode, and current-source rowset invalidation diagnostics',
            'non_overlap' => 'nextTwoFiveSeven covers key_name numeric-storage TEXT coercion entering/leaving a NOCASE LIKE cursor; avoids accepted nextTwoFiveThree key_value TEXT-affinity LIKE, nextTwoFourFive dangling ESCAPE, Unicode GLOB ranges, UTF-16 malformed insert guards, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidate:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function nextTwoFiveSeven_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::nextTwoFiveSeven_assertRow($row);
            try {
                $text = self::nextTwoFiveSeven_textAffinityName($row);
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'storageClass' => strtolower($row['storage']),
                    'textValue' => $text,
                    'nocaseKey' => self::nextTwoFiveSeven_asciiLower($text),
                    'encodingName' => self::nextTwoFiveSeven_encodingName($row['name_encoding'] ?? null),
                    'byteHex' => isset($row['key_name_bytes']) && is_string($row['key_name_bytes']) ? bin2hex($row['key_name_bytes']) : null,
                    'residualMatch' => SQLiteDatabase::likeMatches($text, $pattern, $escape, false),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }
        usort($decoded, static fn (array $left, array $right): int => strcmp($left['nocaseKey'], $right['nocaseKey']) ?: $left['rowid'] <=> $right['rowid']);
        sort($malformed);
        ksort($errors);

        $candidate = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::nextTwoFiveSeven_inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $candidate[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }

        return [
            'decoded' => $decoded,
            'candidate' => $candidate,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoFiveSeven_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveSeven rows require integer setting_id');
        }
        if (!array_key_exists('storage', $row) || !is_string($row['storage'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveSeven rows require storage');
        }
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoFiveSeven_textAffinityName(array $row): string
    {
        return match (strtolower($row['storage'])) {
            'text' => self::nextTwoFiveSeven_decodeTextName($row),
            'integer', 'real' => (string) $row['key_name'],
            'blob' => throw new \InvalidArgumentException('SQLite TEXT affinity LIKE does not coerce BLOB key_name bytes'),
            'null' => throw new \InvalidArgumentException('SQLite LIKE over NULL key_name remains unknown'),
            default => throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveSeven unsupported storage class'),
        };
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoFiveSeven_decodeTextName(array $row): string
    {
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveSeven text rows require key_name_bytes');
        }
        if (!array_key_exists('name_encoding', $row) || !is_int($row['name_encoding'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE nextTwoFiveSeven text rows require integer name_encoding');
        }

        return SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['name_encoding']);
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoFiveSeven_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    private static function nextTwoFiveSeven_encodingName(mixed $encoding): ?string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            null => null,
            default => 'unknown',
        };
    }

    private static function nextTwoFiveSeven_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFiveSeven_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function nextTwoFiveSeven_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFiveSeven_byRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }
        ksort($indexed);

        return $indexed;
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return array<string,list<int>> */
    private static function nextTwoFiveSeven_changes(array $left, array $right): array
    {
        $leftById = self::nextTwoFiveSeven_byRowid($left);
        $rightById = self::nextTwoFiveSeven_byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changes = [
            'textChangedRowids' => [],
            'storageChangedRowids' => [],
            'bytesChangedRowids' => [],
            'encodingChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
        ];
        foreach ($rowids as $rowid) {
            $leftRow = $leftById[$rowid] ?? null;
            $rightRow = $rightById[$rowid] ?? null;
            foreach ([
                'textChangedRowids' => 'textValue',
                'storageChangedRowids' => 'storageClass',
                'bytesChangedRowids' => 'byteHex',
                'encodingChangedRowids' => 'encodingName',
                'nocaseKeyChangedRowids' => 'nocaseKey',
            ] as $bucket => $key) {
                if (($leftRow[$key] ?? null) !== ($rightRow[$key] ?? null)) {
                    $changes[$bucket][] = (int) $rowid;
                }
            }
        }

        return $changes;
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return list<int> */
    private static function nextTwoFiveSeven_residualChanges(array $left, array $right): array
    {
        $leftById = self::nextTwoFiveSeven_byRowid($left);
        $rightById = self::nextTwoFiveSeven_byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($leftById[$rowid]['residualMatch'] ?? null) !== ($rightById[$rowid]['residualMatch'] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoFiveSeven_intersectSorted(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoFiveSeven_diffSorted(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationCaseSensitiveLikeTransitionPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'MODULE!_%',
        ?string $escape = '!',
        bool $currentCaseSensitiveLike = false,
        bool $nextCaseSensitiveLike = true,
        string $currentSource = 'main.app_settings@257',
        string $nextSource = 'main.app_settings@258',
        int $currentSchemaCookie = 257,
        int $nextSchemaCookie = 258,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $globProbe = $patternPlan['prefix'] === '' ? '*' : $patternPlan['prefix'] . '*';
        $current = self::nextTwoFiveEight_scanRows($currentRows, $pattern, $escape, $currentCaseSensitiveLike, $globProbe);
        $next = self::nextTwoFiveEight_scanRows($nextRows, $pattern, $escape, $nextCaseSensitiveLike, $globProbe);
        $currentMatched = self::nextTwoFiveEight_rowids($current['matched']);
        $nextMatched = self::nextTwoFiveEight_rowids($next['matched']);
        $retained = array_values(array_intersect($currentMatched, $nextMatched));
        $exited = array_values(array_diff($currentMatched, $nextMatched));
        $entered = array_values(array_diff($nextMatched, $currentMatched));
        $currentByRowid = self::nextTwoFiveEight_rowsByRowid($current['decisions']);
        $nextByRowid = self::nextTwoFiveEight_rowsByRowid($next['decisions']);
        $changedTruth = [];
        $changedText = [];
        $changedStorage = [];

        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['predicateResult'] !== $nextByRowid[$rowid]['predicateResult']) {
                $changedTruth[] = $rowid;
            }
            if ($currentByRowid[$rowid]['text'] !== $nextByRowid[$rowid]['text']) {
                $changedText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storageClass'] !== $nextByRowid[$rowid]['storageClass']) {
                $changedStorage[] = $rowid;
            }
        }
        sort($changedTruth);
        sort($changedText);
        sort($changedStorage);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentCaseSensitiveLike !== $nextCaseSensitiveLike) {
            $reasons[] = 'case-sensitive-like';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedTruth !== []) {
            $reasons[] = 'predicate-truth';
        }
        if ($changedText !== []) {
            $reasons[] = 'value-text';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'value-storage-class';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFiveEight',
            'operator' => 'LIKE',
            'expression' => 'key_name LIKE ? ESCAPE ? /* case_sensitive_like current-source fence */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'binaryRange' => $patternPlan['binaryRange'],
            'noCaseRange' => $patternPlan['noCaseRange'],
            'currentCaseSensitiveLike' => $currentCaseSensitiveLike,
            'nextCaseSensitiveLike' => $nextCaseSensitiveLike,
            'currentCollation' => $currentCaseSensitiveLike ? 'BINARY' : 'NOCASE',
            'nextCollation' => $nextCaseSensitiveLike ? 'BINARY' : 'NOCASE',
            'caseSensitiveLikeChangesFunctionSemantics' => true,
            'caseSensitiveLikeDoesNotChangePatternTokens' => true,
            'caseSensitiveLikeInvalidatesPreparedLikeCursor' => true,
            'asciiNoCaseFoldsOnlyWhenPragmaIsOff' => true,
            'escapedUnderscoreRemainsLiteralInBothModes' => true,
            'globSemanticsUnaffectedByCaseSensitiveLike' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::nextTwoFiveEight_rowids($current['decisions']),
            'nextCandidateRowids' => self::nextTwoFiveEight_rowids($next['decisions']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedMatchedRowids' => $retained,
            'exitedMatchedRowids' => $exited,
            'enteredMatchedRowids' => $entered,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'changedPredicateTruthRowids' => $changedTruth,
            'changedValueTextRowids' => $changedText,
            'changedStorageClassRowids' => $changedStorage,
            'currentPredicateResults' => self::nextTwoFiveEight_fieldByRowid($currentByRowid, 'predicateResult'),
            'nextPredicateResults' => self::nextTwoFiveEight_fieldByRowid($nextByRowid, 'predicateResult'),
            'currentValueText' => self::nextTwoFiveEight_fieldByRowid($currentByRowid, 'text'),
            'nextValueText' => self::nextTwoFiveEight_fieldByRowid($nextByRowid, 'text'),
            'currentValueHex' => self::nextTwoFiveEight_fieldByRowid($currentByRowid, 'hex'),
            'nextValueHex' => self::nextTwoFiveEight_fieldByRowid($nextByRowid, 'hex'),
            'currentStorageClasses' => self::nextTwoFiveEight_fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorageClasses' => self::nextTwoFiveEight_fieldByRowid($nextByRowid, 'storageClass'),
            'currentSortKeys' => self::nextTwoFiveEight_fieldByRowid($currentByRowid, 'sortKey'),
            'nextSortKeys' => self::nextTwoFiveEight_fieldByRowid($nextByRowid, 'sortKey'),
            'currentGlobProbeRowids' => self::nextTwoFiveEight_rowids($current['globMatched']),
            'nextGlobProbeRowids' => self::nextTwoFiveEight_rowids($next['globMatched']),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-case-sensitive-pragma',
                'sqlite-like-escape-tokenizer',
                'sqlite-text-affinity',
                'sqlite-current-source-nexttwoFiveEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, text-affinity coercion, ASCII NOCASE matching, BINARY matching, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoFiveEight covers PRAGMA case_sensitive_like transitions for escaped Application key_name LIKE cursors; avoids accepted Unicode GLOB ranges, explicit SQL NULL ESCAPE nextTwoFiveFour, prepared pattern storage nextTwoFiveOne, non-ASCII NOCASE prefix nextTwoFourSeven, UTF-16 malformed guards, and SQL/JSON/WAL/VFS/B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>,matched:list<array<string,mixed>>,globMatched:list<array<string,mixed>>,unknownRowids:list<int>}
     */
    private static function nextTwoFiveEight_scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, string $globProbe): array
    {
        $decisions = [];
        $matched = [];
        $globMatched = [];
        $unknown = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name', $row)) {
                throw new \InvalidArgumentException('SQLite case-sensitive LIKE nextTwoFiveEight rows require key_name');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            $value = self::nextTwoFiveEight_coerceText($row['key_name']);
            if ($value === null) {
                $unknown[] = $rowid;
                continue;
            }
            $like = SQLiteDatabase::likeMatches($value['text'], $pattern, $escape, $caseSensitiveLike);
            $decision = [
                'rowid' => $rowid,
                'text' => $value['text'],
                'hex' => bin2hex($value['text']),
                'storageClass' => $value['storageClass'],
                'predicateResult' => $like,
                'sortKey' => $caseSensitiveLike ? $value['text'] : self::nextTwoFiveEight_asciiLower($value['text']),
            ];
            $decisions[] = $decision;
            if ($like) {
                $matched[] = $decision;
            }
            if (SQLiteDatabase::globMatches($value['text'], $globProbe)) {
                $globMatched[] = $decision;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp($left['sortKey'], $right['sortKey']) ?: $left['rowid'] <=> $right['rowid'];
        usort($decisions, $sort);
        usort($matched, $sort);
        usort($globMatched, $sort);
        sort($unknown);

        return ['decisions' => $decisions, 'matched' => $matched, 'globMatched' => $globMatched, 'unknownRowids' => $unknown];
    }

    /** @return null|array{text:string,storageClass:string} */
    private static function nextTwoFiveEight_coerceText(mixed $value): ?array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_string($value)) {
            return ['text' => $value, 'storageClass' => 'text'];
        }
        if (is_int($value)) {
            return ['text' => (string) $value, 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            return ['text' => self::nextTwoFiveEight_formatReal($value), 'storageClass' => 'real'];
        }
        if (is_bool($value)) {
            return ['text' => $value ? '1' : '0', 'storageClass' => 'integer'];
        }

        throw new \InvalidArgumentException('SQLite case-sensitive LIKE nextTwoFiveEight key_name must be scalar text-affinity input');
    }

    private static function nextTwoFiveEight_formatReal(float $value): string
    {
        if (!is_finite($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
    }

    private static function nextTwoFiveEight_asciiLower(string $text): string
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFiveEight_rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFiveEight_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFiveEight_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        ksort($values);

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationBinaryCollationDefaultLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'Module%',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@258',
        string $nextSource = 'main.app_settings@259',
        int $currentSchemaCookie = 258,
        int $nextSchemaCookie = 259,
    ): array {
        if ($escape !== null && self::nextTwoFiveNine_sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite BINARY LIKE nextTwoFiveNine ESCAPE must be one SQLite character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $patternPlan['binaryRange'];
        $binaryRangeUsable = $caseSensitiveLike && $range['lowerInclusive'] !== '';
        $current = self::nextTwoFiveNine_scan($currentRows, $pattern, $escape, $caseSensitiveLike, $binaryRangeUsable ? $range : null);
        $next = self::nextTwoFiveNine_scan($nextRows, $pattern, $escape, $caseSensitiveLike, $binaryRangeUsable ? $range : null);
        $currentMatched = self::nextTwoFiveNine_rowids($current['matched']);
        $nextMatched = self::nextTwoFiveNine_rowids($next['matched']);
        $changes = self::nextTwoFiveNine_changes($current['trace'], $next['trace']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$binaryRangeUsable) {
            $reasons[] = 'binary-prefix-range-unsafe';
        }
        foreach ([
            'text-value' => $changes['textRowids'],
            'text-bytes' => $changes['bytesRowids'],
            'text-encoding' => $changes['encodingRowids'],
            'storage-class' => $changes['storageRowids'],
            'binary-key' => $changes['binaryKeyRowids'],
            'residual-result' => $changes['residualRowids'],
            'candidate-rowset' => self::nextTwoFiveNine_rowids($current['candidate']) === self::nextTwoFiveNine_rowids($next['candidate']) ? [] : self::nextTwoFiveNine_uniqueSortedInts(array_merge(self::nextTwoFiveNine_rowids($current['candidate']), self::nextTwoFiveNine_rowids($next['candidate']))),
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::nextTwoFiveNine_uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoFiveNine',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE BINARY LIKE ? /* default LIKE ignores BINARY collation for ASCII folding */',
            'pattern' => $pattern,
            'patternHex' => strtoupper(bin2hex($pattern)),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : strtoupper(bin2hex($escape)),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => 'BINARY',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => strtoupper(bin2hex($patternPlan['prefix'])),
            'binaryRange' => $range,
            'binaryRangeUsable' => $binaryRangeUsable,
            'fullScanResidualRequired' => !$binaryRangeUsable,
            'defaultLikeIgnoresBinaryCollationForAsciiFold' => !$caseSensitiveLike,
            'caseSensitiveLikeRestoresBinaryRangeSafety' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::nextTwoFiveNine_rowids($current['candidate']),
            'nextCandidateRowids' => self::nextTwoFiveNine_rowids($next['candidate']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => self::nextTwoFiveNine_intersectSorted($currentMatched, $nextMatched),
            'enteredRowids' => self::nextTwoFiveNine_diffSorted($nextMatched, $currentMatched),
            'exitedRowids' => self::nextTwoFiveNine_diffSorted($currentMatched, $nextMatched),
            'currentFalsePositiveRowids' => self::nextTwoFiveNine_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::nextTwoFiveNine_rowids($next['falsePositive']),
            'changedTextRowids' => $changes['textRowids'],
            'changedBytesRowids' => $changes['bytesRowids'],
            'changedEncodingRowids' => $changes['encodingRowids'],
            'changedStorageClassRowids' => $changes['storageRowids'],
            'changedBinaryKeyRowids' => $changes['binaryKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentText' => self::nextTwoFiveNine_fieldByRowid($current['trace'], 'text'),
            'nextText' => self::nextTwoFiveNine_fieldByRowid($next['trace'], 'text'),
            'currentTextHex' => self::nextTwoFiveNine_fieldByRowid($current['trace'], 'textHex'),
            'nextTextHex' => self::nextTwoFiveNine_fieldByRowid($next['trace'], 'textHex'),
            'currentBinaryKeys' => self::nextTwoFiveNine_fieldByRowid($current['trace'], 'binaryKey'),
            'nextBinaryKeys' => self::nextTwoFiveNine_fieldByRowid($next['trace'], 'binaryKey'),
            'currentEncodings' => self::nextTwoFiveNine_fieldByRowid($current['trace'], 'encoding'),
            'nextEncodings' => self::nextTwoFiveNine_fieldByRowid($next['trace'], 'encoding'),
            'currentStorage' => self::nextTwoFiveNine_fieldByRowid($current['trace'], 'storage'),
            'nextStorage' => self::nextTwoFiveNine_fieldByRowid($next['trace'], 'storage'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-default-ascii-fold',
                'sqlite-binary-collation-key',
                'sqlite-mixed-utf-source-decoder',
                'sqlite-current-source-nexttwoFiveNine',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE matching, BINARY collation byte keys, mixed UTF decoding, scalar text-affinity, and current-source diagnostics',
            'non_overlap' => 'nextTwoFiveNine covers default LIKE ASCII folding over a BINARY-collated key_name expression where a BINARY prefix cursor is unsafe until case_sensitive_like is enabled; avoids accepted nextTwoFiveFive GLOB bracket-class fallback, nextTwoFiveSix dynamic pattern affinity, Unicode GLOB ranges, UTF-16 malformed guards, JSON/VFS/WAL/B-tree/SQL planner clusters, and suite evidence slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidate:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function nextTwoFiveNine_scan(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, ?array $range): array
    {
        $trace = [];
        $unknown = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name', $row) && !array_key_exists('key_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite BINARY LIKE nextTwoFiveNine rows require key_name or key_name_bytes');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            try {
                $coerced = self::nextTwoFiveNine_coerceText($row);
                if ($coerced === null) {
                    $unknown[] = $rowid;
                    continue;
                }
                if (preg_match('//u', $coerced['text']) !== 1) {
                    throw new \InvalidArgumentException('SQLite BINARY LIKE nextTwoFiveNine key_name text is malformed UTF-8');
                }
                $trace[] = [
                    'rowid' => $rowid,
                    'text' => $coerced['text'],
                    'textHex' => strtoupper(bin2hex($coerced['text'])),
                    'binaryKey' => $coerced['text'],
                    'encoding' => $coerced['encoding'],
                    'storage' => $coerced['storage'],
                    'residualMatch' => SQLiteDatabase::likeMatches($coerced['text'], $pattern, $escape, $caseSensitiveLike),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, static fn (array $left, array $right): int => strcmp($left['binaryKey'], $right['binaryKey']) ?: $left['rowid'] <=> $right['rowid']);
        sort($unknown);
        sort($malformed);
        ksort($errors);

        $candidate = [];
        $matched = [];
        $falsePositive = [];
        foreach ($trace as $entry) {
            if (!self::nextTwoFiveNine_inRange($entry['binaryKey'], $range)) {
                continue;
            }
            $candidate[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }

        return [
            'trace' => $trace,
            'candidate' => $candidate,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'unknownRowids' => $unknown,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoFiveNine_inRange(string $key, ?array $range): bool
    {
        if ($range === null) {
            return true;
        }
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array<string,mixed> $row @return ?array{text:string,encoding:string,storage:string} */
    private static function nextTwoFiveNine_coerceText(array $row): ?array
    {
        if (array_key_exists('key_name_bytes', $row)) {
            if (!is_string($row['key_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite BINARY LIKE nextTwoFiveNine byte rows require key_name_bytes and integer text_encoding');
            }

            return [
                'text' => SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']),
                'encoding' => self::nextTwoFiveNine_encodingName($row['text_encoding']),
                'storage' => 'text',
            ];
        }

        $value = $row['key_name'];
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value) || is_bool($value)) {
            return ['text' => (string) (int) $value, 'encoding' => 'UTF-8', 'storage' => 'integer'];
        }
        if (is_float($value)) {
            $text = sprintf('%.15G', $value);
            if (str_contains($text, '.')) {
                $text = rtrim(rtrim($text, '0'), '.');
            }

            return ['text' => $text === '-0' ? '0' : $text, 'encoding' => 'UTF-8', 'storage' => 'real'];
        }
        if (is_string($value)) {
            return ['text' => $value, 'encoding' => 'UTF-8', 'storage' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite BINARY LIKE nextTwoFiveNine key_name must be scalar text-affinity input');
    }

    private static function nextTwoFiveNine_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite BINARY LIKE nextTwoFiveNine text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function nextTwoFiveNine_sqliteTextLength(string $text): int
    {
        preg_match_all('/./us', $text, $matches);

        return count($matches[0]);
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{textRowids:list<int>,bytesRowids:list<int>,encodingRowids:list<int>,storageRowids:list<int>,binaryKeyRowids:list<int>,residualRowids:list<int>}
     */
    private static function nextTwoFiveNine_changes(array $current, array $next): array
    {
        $currentByRowid = self::nextTwoFiveNine_rowsByRowid($current);
        $nextByRowid = self::nextTwoFiveNine_rowsByRowid($next);
        $rowids = array_values(array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)));
        sort($rowids);
        $changes = [
            'textRowids' => [],
            'bytesRowids' => [],
            'encodingRowids' => [],
            'storageRowids' => [],
            'binaryKeyRowids' => [],
            'residualRowids' => [],
        ];

        foreach ($rowids as $rowid) {
            foreach ([
                'text' => 'textRowids',
                'textHex' => 'bytesRowids',
                'encoding' => 'encodingRowids',
                'storage' => 'storageRowids',
                'binaryKey' => 'binaryKeyRowids',
                'residualMatch' => 'residualRowids',
            ] as $field => $bucket) {
                if ($currentByRowid[$rowid][$field] !== $nextByRowid[$rowid][$field]) {
                    $changes[$bucket][] = $rowid;
                }
            }
        }

        return $changes;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoFiveNine_rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoFiveNine_intersectSorted(array $left, array $right): array
    {
        $result = array_values(array_intersect($left, $right));
        sort($result);

        return $result;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoFiveNine_diffSorted(array $left, array $right): array
    {
        $result = array_values(array_diff($left, $right));
        sort($result);

        return $result;
    }

    /** @param list<int> $values @return list<int> */
    private static function nextTwoFiveNine_uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoFiveNine_rowsByRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            $byRowid[$row['rowid']] = $row;
        }
        ksort($byRowid);

        return $byRowid;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoFiveNine_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[$row['rowid']] = $row[$field];
        }
        ksort($values);

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationRtrimCollationLikeResidualPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'module_cache',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@259',
        string $nextSource = 'main.app_settings@260',
        int $currentSchemaCookie = 259,
        int $nextSchemaCookie = 260,
    ): array {
        if ($escape !== null && self::nextTwoSixZero_sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoSixZero ESCAPE must be one SQLite character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $patternPlan['binaryRange'];
        $current = self::nextTwoSixZero_scan($currentRows, $pattern, $escape, $range);
        $next = self::nextTwoSixZero_scan($nextRows, $pattern, $escape, $range);
        $currentCandidates = self::nextTwoSixZero_rowids($current['candidates']);
        $nextCandidates = self::nextTwoSixZero_rowids($next['candidates']);
        $currentMatched = self::nextTwoSixZero_rowids($current['matched']);
        $nextMatched = self::nextTwoSixZero_rowids($next['matched']);
        $currentRejected = self::nextTwoSixZero_rowids($current['rejected']);
        $nextRejected = self::nextTwoSixZero_rowids($next['rejected']);
        $currentTrace = self::nextTwoSixZero_rowsByRowid($current['trace']);
        $nextTrace = self::nextTwoSixZero_rowsByRowid($next['trace']);
        $retainedCandidates = self::nextTwoSixZero_intersectSorted($currentCandidates, $nextCandidates);
        $enteredCandidates = self::nextTwoSixZero_diffSorted($nextCandidates, $currentCandidates);
        $exitedCandidates = self::nextTwoSixZero_diffSorted($currentCandidates, $nextCandidates);

        $changedText = [];
        $changedBytes = [];
        $changedEncoding = [];
        $changedRtrimKey = [];
        $changedResidual = [];
        foreach (self::nextTwoSixZero_intersectSorted(array_keys($currentTrace), array_keys($nextTrace)) as $rowid) {
            if ($currentTrace[$rowid]['text'] !== $nextTrace[$rowid]['text']) {
                $changedText[] = $rowid;
            }
            if ($currentTrace[$rowid]['bytesHex'] !== $nextTrace[$rowid]['bytesHex']) {
                $changedBytes[] = $rowid;
            }
            if ($currentTrace[$rowid]['encoding'] !== $nextTrace[$rowid]['encoding']) {
                $changedEncoding[] = $rowid;
            }
            if ($currentTrace[$rowid]['rtrimKey'] !== $nextTrace[$rowid]['rtrimKey']) {
                $changedRtrimKey[] = $rowid;
            }
            if ($currentTrace[$rowid]['residualMatch'] !== $nextTrace[$rowid]['residualMatch']) {
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
        foreach ([
            'candidate-rowset' => array_merge($enteredCandidates, $exitedCandidates),
            'matched-rowset' => $currentMatched === $nextMatched ? [] : array_values(array_unique(array_merge($currentMatched, $nextMatched))),
            'residual-result' => $changedResidual,
            'text-value' => $changedText,
            'text-bytes' => $changedBytes,
            'text-encoding' => $changedEncoding,
            'rtrim-collation-key' => $changedRtrimKey,
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['unknownRowids'] !== [] || $next['unknownRowids'] !== []) {
            $reasons[] = 'unknown-like';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoSixZero',
            'operator' => 'LIKE',
            'expression' => 'key_name COLLATE RTRIM LIKE ? /* RTRIM index candidates still require raw LIKE residual */',
            'pattern' => $pattern,
            'patternHex' => strtoupper(bin2hex($pattern)),
            'escape' => $escape,
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => strtoupper(bin2hex($patternPlan['prefix'])),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'rangeLowerInclusive' => $range['lowerInclusive'],
            'rangeUpperBound' => $range['upperBound'],
            'collation' => 'RTRIM',
            'rtrimCollationCanShareEqualityKey' => true,
            'likeResidualDoesNotTrimTrailingSpaces' => true,
            'rangeMayAdmitTrailingSpaceFalsePositives' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'retainedCandidateRowids' => $retainedCandidates,
            'enteredCandidateRowids' => $enteredCandidates,
            'exitedCandidateRowids' => $exitedCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentResidualRejectedRowids' => $currentRejected,
            'nextResidualRejectedRowids' => $nextRejected,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentText' => self::nextTwoSixZero_fieldByRowid($currentTrace, 'text'),
            'nextText' => self::nextTwoSixZero_fieldByRowid($nextTrace, 'text'),
            'currentBytesHex' => self::nextTwoSixZero_fieldByRowid($currentTrace, 'bytesHex'),
            'nextBytesHex' => self::nextTwoSixZero_fieldByRowid($nextTrace, 'bytesHex'),
            'currentEncodings' => self::nextTwoSixZero_fieldByRowid($currentTrace, 'encoding'),
            'nextEncodings' => self::nextTwoSixZero_fieldByRowid($nextTrace, 'encoding'),
            'currentRtrimKeys' => self::nextTwoSixZero_fieldByRowid($currentTrace, 'rtrimKey'),
            'nextRtrimKeys' => self::nextTwoSixZero_fieldByRowid($nextTrace, 'rtrimKey'),
            'changedTextRowids' => $changedText,
            'changedBytesRowids' => $changedBytes,
            'changedEncodingRowids' => $changedEncoding,
            'changedRtrimKeyRowids' => $changedRtrimKey,
            'changedResidualRowids' => $changedResidual,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-rtrim-collation-key',
                'sqlite-like-raw-residual',
                'sqlite-mixed-utf-source-decoder',
                'sqlite-current-source-nexttwoSixZero',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-8/UTF-16 decode, LIKE residual matching, scalar text-affinity coercion, and RTRIM collation-key diagnostics',
            'non_overlap' => 'nextTwoSixZero covers RTRIM-collated LIKE candidate false positives caused by trailing spaces; avoids accepted nextTwoFiveFive GLOB bracket fallback, nextTwoFiveSix dynamic pattern affinity, Unicode GLOB ranges, UTF-16 malformed guards, JSON, WAL, VFS, B-tree, and SQL planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function nextTwoSixZero_scan(array $rows, string $pattern, ?string $escape, array $range): array
    {
        $trace = [];
        $unknown = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('key_name', $row) && !array_key_exists('key_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoSixZero rows require key_name or key_name_bytes');
            }
            $rowid = is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1;
            try {
                $coerced = self::nextTwoSixZero_coerceText($row);
                if ($coerced === null) {
                    $unknown[] = $rowid;
                    continue;
                }
                $entry = [
                    'rowid' => $rowid,
                    'text' => $coerced['text'],
                    'bytesHex' => strtoupper(bin2hex($coerced['bytes'])),
                    'encoding' => $coerced['encoding'],
                    'storage' => $coerced['storage'],
                    'rtrimKey' => rtrim($coerced['text'], ' '),
                    'residualMatch' => SQLiteDatabase::likeMatches($coerced['text'], $pattern, $escape, true),
                ];
                $trace[] = $entry;
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, static fn (array $left, array $right): int => strcmp($left['rtrimKey'], $right['rtrimKey']) ?: strcmp($left['text'], $right['text']) ?: $left['rowid'] <=> $right['rowid']);
        sort($unknown);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $rejected = [];
        foreach ($trace as $entry) {
            if (!self::nextTwoSixZero_inRange($entry['rtrimKey'], $range)) {
                continue;
            }
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        return [
            'trace' => $trace,
            'candidates' => $candidates,
            'matched' => $matched,
            'rejected' => $rejected,
            'unknownRowids' => $unknown,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row @return array{text:string,bytes:string,encoding:string,storage:string}|null */
    private static function nextTwoSixZero_coerceText(array $row): ?array
    {
        if (array_key_exists('key_name_bytes', $row)) {
            if (!is_string($row['key_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoSixZero byte rows require key_name_bytes and integer text_encoding');
            }
            $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);

            return [
                'text' => $text,
                'bytes' => $row['key_name_bytes'],
                'encoding' => self::nextTwoSixZero_encodingName($row['text_encoding']),
                'storage' => 'text',
            ];
        }

        $value = $row['key_name'];
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value) || is_bool($value)) {
            $text = (string) (int) $value;
            return ['text' => $text, 'bytes' => $text, 'encoding' => 'UTF-8', 'storage' => 'integer'];
        }
        if (is_float($value)) {
            $text = self::nextTwoSixZero_formatReal($value);
            return ['text' => $text, 'bytes' => $text, 'encoding' => 'UTF-8', 'storage' => 'real'];
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoSixZero string key_name must be well-formed UTF-8');
            }

            return ['text' => $value, 'bytes' => $value, 'encoding' => 'UTF-8', 'storage' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoSixZero key_name must be scalar text-affinity input');
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function nextTwoSixZero_inRange(string $key, array $range): bool
    {
        return strcmp($key, $range['lowerInclusive']) >= 0 && ($range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0);
    }

    private static function nextTwoSixZero_formatReal(float $value): string
    {
        $formatted = sprintf('%.15G', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    private static function nextTwoSixZero_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite RTRIM LIKE nextTwoSixZero text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function nextTwoSixZero_sqliteTextLength(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return strlen($text);
        }

        return count($characters);
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoSixZero_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoSixZero_intersectSorted(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function nextTwoSixZero_diffSorted(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoSixZero_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function nextTwoSixZero_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function applicationUtf16NameAndValueLikePlan(
        array $currentRows,
        array $nextRows,
        string $namePatternBytes,
        int|string $namePatternEncoding,
        string $valuePattern = 'enabled:%',
        ?string $nameEscape = '!',
        ?string $valueEscape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings@260',
        string $nextSource = 'main.app_settings@261',
        int $currentSchemaCookie = 260,
        int $nextSchemaCookie = 261,
    ): array {
        $namePattern = SQLiteEncodingCollationSourceCursor::decodeText($namePatternBytes, self::nextTwoSixOne_encodingCode($namePatternEncoding));
        $namePatternPlan = SQLiteDatabase::likePatternPlan($namePattern, $nameEscape);
        $valuePatternPlan = SQLiteDatabase::likePatternPlan($valuePattern, $valueEscape);
        $current = self::nextTwoSixOne_scanRows($currentRows, $namePattern, $valuePattern, $nameEscape, $valueEscape, $caseSensitiveLike);
        $next = self::nextTwoSixOne_scanRows($nextRows, $namePattern, $valuePattern, $nameEscape, $valueEscape, $caseSensitiveLike);
        $currentMatched = self::nextTwoSixOne_rowids($current['matched']);
        $nextMatched = self::nextTwoSixOne_rowids($next['matched']);
        $retained = array_values(array_intersect($currentMatched, $nextMatched));
        $exited = array_values(array_diff($currentMatched, $nextMatched));
        $entered = array_values(array_diff($nextMatched, $currentMatched));
        $currentByRowid = self::nextTwoSixOne_rowsByRowid($current['decisions']);
        $nextByRowid = self::nextTwoSixOne_rowsByRowid($next['decisions']);
        $changedNameText = [];
        $changedValueText = [];
        $changedValueStorage = [];
        $changedCompositeTruth = [];

        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['nameText'] !== $nextByRowid[$rowid]['nameText']) {
                $changedNameText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['valueText'] !== $nextByRowid[$rowid]['valueText']) {
                $changedValueText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['valueStorageClass'] !== $nextByRowid[$rowid]['valueStorageClass']) {
                $changedValueStorage[] = $rowid;
            }
            if ($currentByRowid[$rowid]['compositeMatch'] !== $nextByRowid[$rowid]['compositeMatch']) {
                $changedCompositeTruth[] = $rowid;
            }
        }

        sort($changedNameText);
        sort($changedValueText);
        sort($changedValueStorage);
        sort($changedCompositeTruth);

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
        if ($changedCompositeTruth !== []) {
            $reasons[] = 'composite-predicate-truth';
        }
        if ($changedNameText !== []) {
            $reasons[] = 'decoded-name-text';
        }
        if ($changedValueText !== []) {
            $reasons[] = 'value-affinity-text';
        }
        if ($changedValueStorage !== []) {
            $reasons[] = 'value-storage-class';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-nexttwoSixOne',
            'operator' => 'LIKE',
            'expression' => 'key_name LIKE utf16(?) ESCAPE ? AND key_value LIKE ? /* text affinity current-source fence */',
            'namePattern' => $namePattern,
            'namePatternHex' => bin2hex($namePattern),
            'namePatternBytesHex' => bin2hex($namePatternBytes),
            'namePatternEncoding' => SQLiteEncodingCollationSourceCursor::encodingNameForCode(self::nextTwoSixOne_encodingCode($namePatternEncoding)),
            'nameEscape' => $nameEscape,
            'nameEscapeHex' => $nameEscape === null ? null : bin2hex($nameEscape),
            'namePrefix' => $namePatternPlan['prefix'],
            'namePrefixHex' => bin2hex($namePatternPlan['prefix']),
            'nameBinaryRange' => $namePatternPlan['binaryRange'],
            'nameNoCaseRange' => $namePatternPlan['noCaseRange'],
            'valuePattern' => $valuePattern,
            'valuePatternHex' => bin2hex($valuePattern),
            'valueEscape' => $valueEscape,
            'valuePrefix' => $valuePatternPlan['prefix'],
            'valueBinaryRange' => $valuePatternPlan['binaryRange'],
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::nextTwoSixOne_rowids($current['candidates']),
            'nextCandidateRowids' => self::nextTwoSixOne_rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedMatchedRowids' => $retained,
            'exitedMatchedRowids' => $exited,
            'enteredMatchedRowids' => $entered,
            'currentUnknownValueRowids' => $current['unknownValueRowids'],
            'nextUnknownValueRowids' => $next['unknownValueRowids'],
            'changedNameTextRowids' => $changedNameText,
            'changedValueTextRowids' => $changedValueText,
            'changedValueStorageClassRowids' => $changedValueStorage,
            'changedCompositeTruthRowids' => $changedCompositeTruth,
            'currentNameText' => self::nextTwoSixOne_fieldByRowid($currentByRowid, 'nameText'),
            'nextNameText' => self::nextTwoSixOne_fieldByRowid($nextByRowid, 'nameText'),
            'currentNameTextHex' => self::nextTwoSixOne_fieldByRowid($currentByRowid, 'nameHex'),
            'nextNameTextHex' => self::nextTwoSixOne_fieldByRowid($nextByRowid, 'nameHex'),
            'currentNameEncoding' => self::nextTwoSixOne_fieldByRowid($currentByRowid, 'nameEncoding'),
            'nextNameEncoding' => self::nextTwoSixOne_fieldByRowid($nextByRowid, 'nameEncoding'),
            'currentValueText' => self::nextTwoSixOne_fieldByRowid($currentByRowid, 'valueText'),
            'nextValueText' => self::nextTwoSixOne_fieldByRowid($nextByRowid, 'valueText'),
            'currentValueHex' => self::nextTwoSixOne_fieldByRowid($currentByRowid, 'valueHex'),
            'nextValueHex' => self::nextTwoSixOne_fieldByRowid($nextByRowid, 'valueHex'),
            'currentValueStorageClasses' => self::nextTwoSixOne_fieldByRowid($currentByRowid, 'valueStorageClass'),
            'nextValueStorageClasses' => self::nextTwoSixOne_fieldByRowid($nextByRowid, 'valueStorageClass'),
            'currentCompositeMatches' => self::nextTwoSixOne_fieldByRowid($currentByRowid, 'compositeMatch'),
            'nextCompositeMatches' => self::nextTwoSixOne_fieldByRowid($nextByRowid, 'compositeMatch'),
            'currentNameResidualMatches' => self::nextTwoSixOne_fieldByRowid($currentByRowid, 'nameMatch'),
            'currentValueResidualMatches' => self::nextTwoSixOne_fieldByRowid($currentByRowid, 'valueMatch'),
            'nextNameResidualMatches' => self::nextTwoSixOne_fieldByRowid($nextByRowid, 'nameMatch'),
            'nextValueResidualMatches' => self::nextTwoSixOne_fieldByRowid($nextByRowid, 'valueMatch'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'utf16PatternDecodedBeforeLikeTokenization' => true,
            'nameLikeUsesAsciiNoCaseOnly' => !$caseSensitiveLike,
            'valueLikeAppliesTextAffinityBeforeResidual' => true,
            'blobAndNullValuesRemainUnknownForLike' => true,
            'escapedUnderscoreInUtf16PatternIsLiteral' => true,
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-like-escape-tokenizer',
                'sqlite-text-affinity',
                'sqlite-current-source-nexttwoSixOne',
            ],
            'dependency_closure' => 'no new support component needed; nextTwoSixOne reuses native UTF-16 decode, LIKE tokenization, ASCII NOCASE matching, and scalar text-affinity coercion',
            'non_overlap' => 'nextTwoSixOne covers a composite UTF-16 bound key_name LIKE plus key_value text-affinity LIKE current-source fence; it avoids accepted nextTwoFourZero numeric-only LIKE, nextTwoFiveEight case_sensitive_like binary transition, Unicode GLOB range nextOneZeroTwo/nextTwoFiveNine, UTF-16 malformed guard, and storage/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,unknownValueRowids:list<int>}
     */
    private static function nextTwoSixOne_scanRows(array $rows, string $namePattern, string $valuePattern, ?string $nameEscape, ?string $valueEscape, bool $caseSensitiveLike): array
    {
        $decisions = [];
        $candidates = [];
        $matched = [];
        $unknown = [];

        foreach ($rows as $index => $row) {
            $rowid = self::nextTwoSixOne_rowid($row, $index);
            $name = self::nextTwoSixOne_decodeKeyName($row);
            $value = self::nextTwoSixOne_coerceLikeText($row['key_value'] ?? null);
            $nameMatch = SQLiteDatabase::likeMatches($name['text'], $namePattern, $nameEscape, $caseSensitiveLike);
            $valueMatch = $value !== null && SQLiteDatabase::likeMatches($value['text'], $valuePattern, $valueEscape, $caseSensitiveLike);
            if ($value === null) {
                $unknown[] = $rowid;
            }

            $decision = [
                'rowid' => $rowid,
                'nameText' => $name['text'],
                'nameHex' => bin2hex($name['text']),
                'nameEncoding' => $name['encoding'],
                'valueText' => $value['text'] ?? null,
                'valueHex' => $value === null ? null : bin2hex($value['text']),
                'valueStorageClass' => $value['storageClass'] ?? 'unknown',
                'nameMatch' => $nameMatch,
                'valueMatch' => $valueMatch,
                'compositeMatch' => $nameMatch && $valueMatch,
                'sortKey' => $caseSensitiveLike ? $name['text'] : self::nextTwoSixOne_asciiLower($name['text']),
            ];
            $decisions[] = $decision;
            if ($nameMatch) {
                $candidates[] = $decision;
            }
            if ($decision['compositeMatch']) {
                $matched[] = $decision;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp($left['sortKey'], $right['sortKey']) ?: $left['rowid'] <=> $right['rowid'];
        usort($decisions, $sort);
        usort($candidates, $sort);
        usort($matched, $sort);
        sort($unknown);

        return ['decisions' => $decisions, 'candidates' => $candidates, 'matched' => $matched, 'unknownValueRowids' => $unknown];
    }

    /** @param array<string,mixed> $row */
    private static function nextTwoSixOne_rowid(array $row, int $index): int
    {
        if (!isset($row['setting_id']) || !is_int($row['setting_id'])) {
            return $index + 1;
        }

        return $row['setting_id'];
    }

    /** @param array<string,mixed> $row @return array{text:string,encoding:string} */
    private static function nextTwoSixOne_decodeKeyName(array $row): array
    {
        if (isset($row['key_name_bytes'])) {
            if (!is_string($row['key_name_bytes']) || !isset($row['name_text_encoding'])) {
                throw new \InvalidArgumentException('SQLite nextTwoSixOne key_name_bytes rows require name_text_encoding');
            }
            $encoding = self::nextTwoSixOne_encodingCode($row['name_text_encoding']);

            return [
                'text' => SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $encoding),
                'encoding' => SQLiteEncodingCollationSourceCursor::encodingNameForCode($encoding),
            ];
        }
        if (!array_key_exists('key_name', $row) || !is_string($row['key_name'])) {
            throw new \InvalidArgumentException('SQLite nextTwoSixOne rows require key_name text or encoded key_name_bytes');
        }

        return ['text' => $row['key_name'], 'encoding' => 'UTF-8'];
    }

    /** @return null|array{text:string,storageClass:string} */
    private static function nextTwoSixOne_coerceLikeText(mixed $value): ?array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_string($value)) {
            return ['text' => $value, 'storageClass' => 'text'];
        }
        if (is_int($value)) {
            return ['text' => (string) $value, 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            return ['text' => self::nextTwoSixOne_formatReal($value), 'storageClass' => 'real'];
        }
        if (is_bool($value)) {
            return ['text' => $value ? '1' : '0', 'storageClass' => 'integer'];
        }

        throw new \InvalidArgumentException('SQLite nextTwoSixOne LIKE affinity value must be scalar or SQLiteBlobValue');
    }

    private static function nextTwoSixOne_formatReal(float $value): string
    {
        if (!is_finite($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
    }

    private static function nextTwoSixOne_encodingCode(int|string $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite nextTwoSixOne text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite nextTwoSixOne text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function nextTwoSixOne_asciiLower(string $text): string
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function nextTwoSixOne_rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function nextTwoSixOne_rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private static function nextTwoSixOne_fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }
        ksort($values);

        return $values;
    }
}
