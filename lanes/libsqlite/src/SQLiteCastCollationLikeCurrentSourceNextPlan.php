<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCastCollationLikeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionValueCastScan(
        array $currentRows,
        array $nextRows,
        string $castTarget,
        string $pattern,
        string $operator = 'LIKE',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = true,
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite CAST current-source plan operator must be LIKE or GLOB');
        }
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("SQLite CAST current-source plan collation {$collation} is unsupported");
        }
        if ($operator === 'GLOB' && $escape !== null) {
            throw new \InvalidArgumentException('SQLite CAST current-source GLOB plan does not accept ESCAPE');
        }
        if ($escape !== null && strlen($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite CAST current-source LIKE ESCAPE must be one byte');
        }
        if ($castTarget === '') {
            throw new \InvalidArgumentException('SQLite CAST current-source plan needs a target affinity');
        }

        $currentTrace = self::traceSource($currentRows, $castTarget, $pattern, $operator, $collation, $escape, $caseSensitiveLike);
        $nextTrace = self::traceSource($nextRows, $castTarget, $pattern, $operator, $collation, $escape, $caseSensitiveLike);
        $currentByRowid = self::byRowid($currentTrace);
        $nextByRowid = self::byRowid($nextTrace);

        $currentMatched = self::matchedRowids($currentTrace);
        $nextMatched = self::matchedRowids($nextTrace);
        $changedCastRowids = [];
        $changedMatchRowids = [];
        foreach (array_unique(array_merge(array_keys($currentByRowid), array_keys($nextByRowid))) as $rowid) {
            $current = $currentByRowid[$rowid] ?? null;
            $next = $nextByRowid[$rowid] ?? null;
            if ($current === null || $next === null || $current['castKey'] !== $next['castKey']) {
                $changedCastRowids[] = $rowid;
            }
            if ($current === null || $next === null || $current['matched'] !== $next['matched']) {
                $changedMatchRowids[] = $rowid;
            }
        }
        sort($changedCastRowids);
        sort($changedMatchRowids);

        $reusable = $currentSchemaCookie === $nextSchemaCookie
            && $changedCastRowids === []
            && $changedMatchRowids === [];
        $invalidationReasons = [];
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $invalidationReasons[] = 'schema-cookie';
        }
        if ($changedCastRowids !== []) {
            $invalidationReasons[] = 'cast-result';
        }
        if ($changedMatchRowids !== []) {
            $invalidationReasons[] = 'residual-match';
        }
        if (self::matchedTextChanged($currentByRowid, $nextByRowid, $currentMatched, $nextMatched)) {
            $invalidationReasons[] = 'matched-text';
        }

        return [
            'operator' => $operator,
            'castTarget' => $castTarget,
            'collation' => $collation,
            'pattern' => $pattern,
            'escape' => $escape,
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $currentTrace,
            'nextTrace' => $nextTrace,
            'currentRowids' => $currentMatched,
            'nextRowids' => $nextMatched,
            'enteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'changedCastRowids' => $changedCastRowids,
            'changedMatchRowids' => $changedMatchRowids,
            'reusable' => $reusable,
            'invalidationReasons' => $invalidationReasons,
            'dependencies' => [
                'sqlite-select-cast-expression',
                'sqlite-like-glob-residual',
                'sqlite-collation-comparison',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function traceSource(
        array $rows,
        string $castTarget,
        string $pattern,
        string $operator,
        string $collation,
        ?string $escape,
        bool $caseSensitiveLike,
    ): array {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite CAST current-source rows must be arrays');
            }
            foreach (['option_id', 'option_value'] as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite CAST current-source row is missing {$column}");
                }
            }
            if (!is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite CAST current-source option_id must be an integer');
            }
        }

        $quotedTarget = self::castTargetSql($castTarget);
        $castRows = SQLiteSelectSql::execute(
            "SELECT option_id, option_value, CAST(option_value AS {$quotedTarget}) AS cast_value FROM wp_options ORDER BY option_id",
            ['wp_options' => $rows],
        );

        $trace = [];
        foreach ($castRows as $row) {
            $castValue = $row['cast_value'];
            $text = self::textValue($castValue);
            $matched = $operator === 'LIKE'
                ? SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)
                : SQLiteDatabase::globMatches($text, $pattern);
            $collationKey = self::collationKey($text, $collation);
            $trace[] = [
                'rowid' => $row['option_id'],
                'originalStorage' => self::storageClass($row['option_value']),
                'castStorage' => self::storageClass($castValue),
                'castValue' => $castValue instanceof SQLiteBlobValue ? $castValue->bytes : $castValue,
                'castText' => $text,
                'castTextHex' => strtoupper(bin2hex($text)),
                'collationKey' => $collationKey,
                'collationKeyHex' => strtoupper(bin2hex($collationKey)),
                'castKey' => self::storageClass($castValue) . ':' . $text,
                'matched' => $matched,
            ];
        }

        return $trace;
    }

    private static function castTargetSql(string $target): string
    {
        $target = trim($target);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_ ]*(?:\([0-9 ,]+\))?$/', $target) !== 1) {
            throw new \InvalidArgumentException('SQLite CAST current-source target affinity is malformed');
        }

        return $target;
    }

    private static function textValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('SQLite CAST current-source values must be scalar, BLOB, or NULL');
    }

    private static function storageClass(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value instanceof SQLiteBlobValue => 'blob',
            is_int($value), is_bool($value) => 'integer',
            is_float($value) => 'real',
            is_string($value) => 'text',
            default => throw new \InvalidArgumentException('SQLite CAST current-source values must be scalar, BLOB, or NULL'),
        };
    }

    private static function collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'BINARY' => $text,
            'NOCASE' => strtolower($text),
            'RTRIM' => rtrim($text, ' '),
            default => throw new \InvalidArgumentException("SQLite CAST current-source collation {$collation} is unsupported"),
        };
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return array<int,array<string,mixed>>
     */
    private static function byRowid(array $trace): array
    {
        $indexed = [];
        foreach ($trace as $entry) {
            $indexed[$entry['rowid']] = $entry;
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return list<int>
     */
    private static function matchedRowids(array $trace): array
    {
        return array_values(array_map(
            static fn (array $entry): int => $entry['rowid'],
            array_filter($trace, static fn (array $entry): bool => $entry['matched'] === true),
        ));
    }

    /**
     * @param array<int,array<string,mixed>> $currentByRowid
     * @param array<int,array<string,mixed>> $nextByRowid
     * @param list<int> $currentMatched
     * @param list<int> $nextMatched
     */
    private static function matchedTextChanged(array $currentByRowid, array $nextByRowid, array $currentMatched, array $nextMatched): bool
    {
        foreach (array_unique(array_merge($currentMatched, $nextMatched)) as $rowid) {
            if (!isset($currentByRowid[$rowid], $nextByRowid[$rowid])) {
                return true;
            }
            if ($currentByRowid[$rowid]['castText'] !== $nextByRowid[$rowid]['castText']) {
                return true;
            }
        }

        return false;
    }
}
