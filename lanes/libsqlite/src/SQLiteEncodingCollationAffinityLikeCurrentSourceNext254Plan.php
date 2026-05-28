<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext254Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressNullableEscapeLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_%',
        mixed $currentEscape = null,
        bool $currentEscapeIsExplicit = true,
        mixed $nextEscape = '!',
        bool $nextEscapeIsExplicit = true,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@253',
        string $nextSource = 'main.wp_options@254',
        int $currentSchemaCookie = 253,
        int $nextSchemaCookie = 254,
    ): array {
        $currentEscapeValue = self::coerceEscape($currentEscape, $currentEscapeIsExplicit, 'current');
        $nextEscapeValue = self::coerceEscape($nextEscape, $nextEscapeIsExplicit, 'next');
        $prefixPlan = SQLiteDatabase::likePatternPlan($pattern, $nextEscapeValue['likeEscape']);
        $current = self::scanRows($currentRows, $pattern, $currentEscapeValue, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $nextEscapeValue, $caseSensitiveLike);
        $currentMatched = self::rowids($current['matchedRows']);
        $nextMatched = self::rowids($next['matchedRows']);
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
            'status' => 'encoding-collation-affinity-like-current-source-next254',
            'operator' => 'LIKE',
            'expression' => 'option_value LIKE ? ESCAPE ? /* explicit SQL NULL ESCAPE is UNKNOWN, not omitted ESCAPE */',
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
            'currentPredicateResults' => self::fieldByRowid($currentByRowid, 'predicateResult'),
            'nextPredicateResults' => self::fieldByRowid($nextByRowid, 'predicateResult'),
            'currentValueText' => self::fieldByRowid($currentByRowid, 'text'),
            'nextValueText' => self::fieldByRowid($nextByRowid, 'text'),
            'currentValueHex' => self::fieldByRowid($currentByRowid, 'textHex'),
            'nextValueHex' => self::fieldByRowid($nextByRowid, 'textHex'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storageClass'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-escape-nullability',
                'sqlite-like-escape-tokenizer',
                'sqlite-text-affinity',
                'sqlite-current-source-next254',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, scalar text affinity, explicit SQL NULL handling, and current-source invalidation diagnostics',
            'non_overlap' => 'next254 covers explicit SQL NULL ESCAPE versus omitted ESCAPE for LIKE predicates; avoids next251 prepared pattern storage transitions, next250 RTRIM residual peers, next238 real text-affinity LIKE, next235 malformed-byte NOT LIKE complement, Unicode GLOB ranges, and UTF-16 NOCASE/RTRIM cursor handoffs',
        ];
    }

    /**
     * @param array{text:?string,hex:?string,storageClass:?string,explicit:bool,sqlNullEscape:bool,likeEscape:?string} $escape
     * @param list<array<string,mixed>> $rows
     * @return array{decisions:list<array<string,mixed>>,matchedRows:list<array<string,mixed>>,unknownRowids:list<int>}
     */
    private static function scanRows(array $rows, string $pattern, array $escape, bool $caseSensitiveLike): array
    {
        $decisions = [];
        $matched = [];
        $unknown = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite nullable ESCAPE LIKE next254 rows require option_value');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            $value = self::coerceText($row['option_value']);
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
    private static function coerceEscape(mixed $value, bool $explicit, string $label): array
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

        $text = self::coerceText($value);
        if ($text === null) {
            throw new \InvalidArgumentException("SQLite nullable ESCAPE LIKE next254 {$label} ESCAPE must not be BLOB");
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
    private static function coerceText(mixed $value): ?array
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
            $text = self::formatReal($value);
            $storage = 'real';
        } elseif (is_bool($value)) {
            $text = $value ? '1' : '0';
            $storage = 'integer';
        } else {
            throw new \InvalidArgumentException('SQLite nullable ESCAPE LIKE next254 value must be scalar text-affinity input');
        }

        return ['text' => $text, 'hex' => bin2hex($text), 'storageClass' => $storage];
    }

    private static function formatReal(float $value): string
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
