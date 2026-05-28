<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext258Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressCaseSensitiveLikeTransitionPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'PLUGIN!_%',
        ?string $escape = '!',
        bool $currentCaseSensitiveLike = false,
        bool $nextCaseSensitiveLike = true,
        string $currentSource = 'main.wp_options@257',
        string $nextSource = 'main.wp_options@258',
        int $currentSchemaCookie = 257,
        int $nextSchemaCookie = 258,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $current = self::scanRows($currentRows, $pattern, $escape, $currentCaseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $escape, $nextCaseSensitiveLike);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $retained = array_values(array_intersect($currentMatched, $nextMatched));
        $exited = array_values(array_diff($currentMatched, $nextMatched));
        $entered = array_values(array_diff($nextMatched, $currentMatched));
        $currentByRowid = self::rowsByRowid($current['decisions']);
        $nextByRowid = self::rowsByRowid($next['decisions']);
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
            'status' => 'encoding-collation-affinity-like-current-source-next258',
            'operator' => 'LIKE',
            'expression' => 'option_name LIKE ? ESCAPE ? /* case_sensitive_like current-source fence */',
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
            'currentCandidateRowids' => self::rowids($current['decisions']),
            'nextCandidateRowids' => self::rowids($next['decisions']),
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
            'currentPredicateResults' => self::fieldByRowid($currentByRowid, 'predicateResult'),
            'nextPredicateResults' => self::fieldByRowid($nextByRowid, 'predicateResult'),
            'currentValueText' => self::fieldByRowid($currentByRowid, 'text'),
            'nextValueText' => self::fieldByRowid($nextByRowid, 'text'),
            'currentValueHex' => self::fieldByRowid($currentByRowid, 'hex'),
            'nextValueHex' => self::fieldByRowid($nextByRowid, 'hex'),
            'currentStorageClasses' => self::fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorageClasses' => self::fieldByRowid($nextByRowid, 'storageClass'),
            'currentSortKeys' => self::fieldByRowid($currentByRowid, 'sortKey'),
            'nextSortKeys' => self::fieldByRowid($nextByRowid, 'sortKey'),
            'currentGlobProbeRowids' => self::rowids($current['globMatched']),
            'nextGlobProbeRowids' => self::rowids($next['globMatched']),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-case-sensitive-pragma',
                'sqlite-like-escape-tokenizer',
                'sqlite-text-affinity',
                'sqlite-current-source-next258',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, text-affinity coercion, ASCII NOCASE matching, BINARY matching, and current-source invalidation diagnostics',
            'non_overlap' => 'next258 covers PRAGMA case_sensitive_like transitions for escaped WordPress option_name LIKE cursors; avoids accepted Unicode GLOB ranges, explicit SQL NULL ESCAPE next254, prepared pattern storage next251, non-ASCII NOCASE prefix next247, UTF-16 malformed guards, and SQL/JSON/WAL/VFS/B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>,matched:list<array<string,mixed>>,globMatched:list<array<string,mixed>>,unknownRowids:list<int>}
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $decisions = [];
        $matched = [];
        $globMatched = [];
        $unknown = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name', $row)) {
                throw new \InvalidArgumentException('SQLite case-sensitive LIKE next258 rows require option_name');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            $value = self::coerceText($row['option_name']);
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
                'sortKey' => $caseSensitiveLike ? $value['text'] : self::asciiLower($value['text']),
            ];
            $decisions[] = $decision;
            if ($like) {
                $matched[] = $decision;
            }
            if (SQLiteDatabase::globMatches($value['text'], 'PLUGIN_*')) {
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
    private static function coerceText(mixed $value): ?array
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
            return ['text' => self::formatReal($value), 'storageClass' => 'real'];
        }
        if (is_bool($value)) {
            return ['text' => $value ? '1' : '0', 'storageClass' => 'integer'];
        }

        throw new \InvalidArgumentException('SQLite case-sensitive LIKE next258 option_name must be scalar text-affinity input');
    }

    private static function formatReal(float $value): string
    {
        if (!is_finite($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
    }

    private static function asciiLower(string $text): string
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
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

        ksort($values);

        return $values;
    }
}
