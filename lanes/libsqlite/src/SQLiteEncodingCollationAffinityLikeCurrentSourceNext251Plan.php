<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext251Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressPreparedPatternAffinityPlan(
        array $currentRows,
        array $nextRows,
        mixed $currentPattern,
        mixed $nextPattern,
        mixed $currentEscape = null,
        mixed $nextEscape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@250',
        string $nextSource = 'main.wp_options@251',
        int $currentSchemaCookie = 250,
        int $nextSchemaCookie = 251,
    ): array {
        $currentPatternText = self::coerceLikeText($currentPattern, 'pattern');
        $nextPatternText = self::coerceLikeText($nextPattern, 'pattern');
        $currentEscapeText = self::coerceEscapeText($currentEscape, 'current');
        $nextEscapeText = self::coerceEscapeText($nextEscape, 'next');
        $patternPlan = SQLiteDatabase::likePatternPlan($currentPatternText['text'], $currentEscapeText['text']);
        $current = self::scanRows($currentRows, $currentPatternText['text'], $currentEscapeText['text'], $caseSensitiveLike);
        $next = self::scanRows($nextRows, $nextPatternText['text'], $nextEscapeText['text'], $caseSensitiveLike);

        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
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
            'status' => 'encoding-collation-affinity-like-current-source-next251',
            'operator' => 'LIKE',
            'expression' => 'option_value LIKE ? ESCAPE ? /* prepared pattern affinity current-source fence */',
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
            'currentValueText' => self::fieldByRowid($currentByRowid, 'valueText'),
            'nextValueText' => self::fieldByRowid($nextByRowid, 'valueText'),
            'currentValueStorageClasses' => self::fieldByRowid($currentByRowid, 'valueStorage'),
            'nextValueStorageClasses' => self::fieldByRowid($nextByRowid, 'valueStorage'),
            'currentOptionNames' => self::fieldByRowid($currentByRowid, 'optionName'),
            'nextOptionNames' => self::fieldByRowid($nextByRowid, 'optionName'),
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
                'sqlite-current-source-next251',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, SQLite scalar storage classification, numeric/boolean text affinity, and current-source invalidation diagnostics',
            'non_overlap' => 'next251 covers prepared LIKE pattern and ESCAPE affinity/storage transitions for option_value scans; avoids accepted numeric value LIKE next240, embedded-NUL option_name next241, UTF-16 mixed-source next244, escaped option_name next236, Unicode GLOB ranges, malformed UTF guards, and storage/planner clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite LIKE next251 row requires option_value');
            }
            $value = self::coerceLikeText($row['option_value'], 'option_value');
            if (!SQLiteDatabase::likeMatches($value['text'], $pattern, $escape, $caseSensitiveLike)) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'optionName' => self::optionName($row, $index),
                'valueText' => $value['text'],
                'valueHex' => $value['hex'],
                'valueStorage' => $value['storageClass'],
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['valueText'], $right['valueText']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    /** @return array{text:string,hex:string,storageClass:string} */
    private static function coerceLikeText(mixed $value, string $label): array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            throw new \InvalidArgumentException("SQLite LIKE next251 {$label} must not be NULL or BLOB");
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
            throw new \InvalidArgumentException("SQLite LIKE next251 {$label} must be scalar text-affinity input");
        }

        return ['text' => $text, 'hex' => bin2hex($text), 'storageClass' => $storage];
    }

    /** @return array{text:?string,hex:?string,storageClass:?string} */
    private static function coerceEscapeText(mixed $value, string $source): array
    {
        if ($value === null) {
            return ['text' => null, 'hex' => null, 'storageClass' => null];
        }

        $escape = self::coerceLikeText($value, $source . ' ESCAPE');
        if (SQLiteDatabase::likeMatches('', '', $escape['text']) !== true) {
            throw new \LogicException('unreachable LIKE ESCAPE validation guard');
        }

        return $escape;
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
