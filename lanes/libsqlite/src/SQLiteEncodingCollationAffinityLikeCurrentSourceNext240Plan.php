<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext240Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionValueNumericLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@239',
        string $nextSource = 'main.wp_options@240',
        int $currentSchemaCookie = 239,
        int $nextSchemaCookie = 240,
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
            'status' => 'encoding-collation-affinity-like-current-source-next240',
            'operator' => 'LIKE',
            'expression' => 'CAST(option_value AS NUMERIC) LIKE ? ESCAPE ? /* numeric affinity current-source fence */',
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
            'currentFormatted' => self::fieldByRowid($currentByRowid, 'formatted'),
            'nextFormatted' => self::fieldByRowid($nextByRowid, 'formatted'),
            'currentFormattedHex' => self::fieldByRowid($currentByRowid, 'formattedHex'),
            'nextFormattedHex' => self::fieldByRowid($nextByRowid, 'formattedHex'),
            'currentStorageClasses' => self::fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorageClasses' => self::fieldByRowid($nextByRowid, 'storageClass'),
            'currentOptionNames' => self::fieldByRowid($currentByRowid, 'optionName'),
            'nextOptionNames' => self::fieldByRowid($nextByRowid, 'optionName'),
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
                'sqlite-current-source-next240',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization and lane-local SQLite numeric/text-affinity formatting diagnostics',
            'non_overlap' => 'next240 covers NUMERIC-affinity LIKE current-source invalidation over option_value formatting and storage classes; avoids next236 escaped option_name LIKE, UTF-16 RTRIM/NOCASE cursors, Unicode GLOB ranges, malformed text guards, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite numeric LIKE next240 row requires option_value');
            }
            $coerced = self::coerceLikeText($row['option_value']);
            if ($coerced === null) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($coerced['formatted'], $pattern, $escape, $caseSensitiveLike)) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'optionName' => self::optionName($row, $index),
                'formatted' => $coerced['formatted'],
                'formattedHex' => bin2hex($coerced['formatted']),
                'storageClass' => $coerced['storageClass'],
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['formatted'], $right['formatted']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    /** @return array{formatted:string,storageClass:string}|null */
    private static function coerceLikeText(mixed $value): ?array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value)) {
            return ['formatted' => (string) $value, 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            return ['formatted' => self::formatReal($value), 'storageClass' => 'real'];
        }
        if (is_bool($value)) {
            return ['formatted' => $value ? '1' : '0', 'storageClass' => 'integer'];
        }
        if (is_string($value)) {
            return ['formatted' => $value, 'storageClass' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite numeric LIKE next240 option_value must be scalar or SQLiteBlobValue');
    }

    private static function formatReal(float $value): string
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
    private static function optionName(array $row, int $index): string
    {
        $name = $row['option_name'] ?? 'option_' . ($index + 1);

        return is_scalar($name) ? (string) $name : 'option_' . ($index + 1);
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
