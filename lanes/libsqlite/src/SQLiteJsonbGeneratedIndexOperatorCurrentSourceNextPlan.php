<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan
{
    /**
     * @param list<array{name?:string,sql:string,rootPage?:int,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function plan(
        array $indexDefinitions,
        array $currentRows,
        array $nextRows,
        string $rowidColumn = 'setting_id',
    ): array {
        $indexes = self::operatorIndexes($indexDefinitions);
        $currentByRowid = self::rowsByRowid($currentRows, $rowidColumn, 'current');
        $nextByRowid = self::rowsByRowid($nextRows, $rowidColumn, 'next');
        $rowids = array_values(array_unique(array_merge(array_keys($currentByRowid), array_keys($nextByRowid))));
        usort($rowids, static fn (int|string $left, int|string $right): int => $left <=> $right);

        $deleteEntries = [];
        $insertEntries = [];
        $unchangedEntries = [];
        $rowTransitions = [];

        foreach ($rowids as $rowid) {
            $current = $currentByRowid[$rowid] ?? null;
            $next = $nextByRowid[$rowid] ?? null;
            $rowChanges = [];

            foreach ($indexes as $index) {
                $currentActive = $current !== null && self::partialRowMatches($current, $index['partialPredicate']);
                $nextActive = $next !== null && self::partialRowMatches($next, $index['partialPredicate']);
                $currentValue = $currentActive ? self::operatorValue($current, $index) : null;
                $nextValue = $nextActive ? self::operatorValue($next, $index) : null;
                $changed = $currentActive !== $nextActive || self::valueKey($currentValue) !== self::valueKey($nextValue);
                $base = [
                    'index' => $index['name'],
                    'rootPage' => $index['rootPage'],
                    'rowid' => $rowid,
                    'operator' => $index['operator'],
                    'sourceColumn' => $index['sourceColumn'],
                    'path' => $index['path'],
                    'collation' => $index['collation'],
                    'descending' => $index['descending'],
                    'partial' => $index['partialPredicate'] !== null,
                ];

                if (!$changed) {
                    $unchangedEntries[] = $base + [
                        'currentActive' => $currentActive,
                        'nextActive' => $nextActive,
                        'value' => $currentValue,
                    ];
                    continue;
                }

                if ($currentActive) {
                    $deleteEntries[] = $base + [
                        'operation' => 'delete-current',
                        'value' => $currentValue,
                    ];
                }
                if ($nextActive) {
                    $insertEntries[] = $base + [
                        'operation' => 'insert-next',
                        'value' => $nextValue,
                    ];
                }
                $rowChanges[] = $base + [
                    'currentActive' => $currentActive,
                    'nextActive' => $nextActive,
                    'currentValue' => $currentValue,
                    'nextValue' => $nextValue,
                ];
            }

            $rowTransitions[] = [
                'rowid' => $rowid,
                'state' => $current === null ? 'inserted' : ($next === null ? 'deleted' : 'updated'),
                'current' => $current,
                'next' => $next,
                'index_changes' => $rowChanges,
            ];
        }

        return [
            'action' => 'jsonb-generated-index-operator-current-source-next107',
            'indexes' => $indexes,
            'row_transitions' => $rowTransitions,
            'delete_entries' => $deleteEntries,
            'insert_entries' => $insertEntries,
            'unchanged_entries' => $unchangedEntries,
            'changed_entry_count' => count($deleteEntries) + count($insertEntries),
        ];
    }

    /**
     * @param list<array{name?:string,sql:string,rootPage?:int,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @return list<array{name:?string,rootPage:?int,operator:string,sourceColumn:string,path:string,collation:string,descending:bool,partialPredicate:?SQLiteIndexPredicate}>
     */
    private static function operatorIndexes(array $indexDefinitions): array
    {
        $indexes = [];
        foreach ($indexDefinitions as $definition) {
            foreach (['->>' => SQLiteCreateIndex::firstJsonTextOperatorExpression($definition['sql']), '->' => SQLiteCreateIndex::firstJsonValueOperatorExpression($definition['sql'])] as $operator => $expression) {
                if (!$expression instanceof SQLiteJsonExtractIndexExpression) {
                    continue;
                }
                $indexes[] = [
                    'name' => $definition['name'] ?? null,
                    'rootPage' => isset($definition['rootPage']) ? (int) $definition['rootPage'] : null,
                    'operator' => $operator,
                    'sourceColumn' => $expression->columnName,
                    'path' => $expression->path,
                    'collation' => $expression->collation,
                    'descending' => $expression->descending,
                    'partialPredicate' => $expression->partialPredicate,
                ];
                break;
            }
        }

        return $indexes;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int|string,array<string,mixed>>
     */
    private static function rowsByRowid(array $rows, string $rowidColumn, string $label): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowidColumn, $row)) {
                throw new \InvalidArgumentException("SQLite JSONB operator generated-index {$label} row is missing {$rowidColumn}");
            }
            $rowid = $row[$rowidColumn];
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException("SQLite JSONB operator generated-index {$label} {$rowidColumn} must be integer or text");
            }
            if (isset($mapped[$rowid])) {
                throw new \InvalidArgumentException("SQLite JSONB operator generated-index {$label} rowid is duplicated");
            }
            $mapped[$rowid] = $row;
        }

        return $mapped;
    }

    /**
     * @param array<string,mixed> $row
     * @param array{operator:string,sourceColumn:string,path:string} $index
     */
    private static function operatorValue(array $row, array $index): mixed
    {
        if (!array_key_exists($index['sourceColumn'], $row)) {
            throw new \InvalidArgumentException("SQLite JSONB operator generated-index row is missing source column {$index['sourceColumn']}");
        }

        $source = $row[$index['sourceColumn']];
        if ($source === null) {
            return null;
        }
        if (!$source instanceof SQLiteBlobValue && !$source instanceof SQLiteJsonSubtypeValue && !is_string($source)) {
            throw new \InvalidArgumentException("SQLite JSONB operator generated-index source column {$index['sourceColumn']} must be JSON text, JSONB, subtype, or NULL");
        }

        $located = SQLiteJsonInspection::locatePath($source, $index['path']);
        if (!$located['found']) {
            return null;
        }

        $value = $located['value'];
        if ($index['operator'] === '->') {
            return SQLiteJsonCanonical::encodeDecodedJson($value);
        }
        if ($value === true) {
            return 1;
        }
        if ($value === false) {
            return 0;
        }
        if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        return SQLiteJsonCanonical::encodeDecodedJson($value);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function partialRowMatches(array $row, ?SQLiteIndexPredicate $predicate): bool
    {
        if ($predicate === null) {
            return true;
        }
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            if (!is_array($predicate->value)) {
                return false;
            }
            foreach ($predicate->value as $child) {
                if (!$child instanceof SQLiteIndexPredicate) {
                    return false;
                }
                if (!self::partialRowMatches($row, $child)) {
                    return false;
                }
            }

            return true;
        }
        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }
            foreach ($predicate->value as $child) {
                if (!$child instanceof SQLiteIndexPredicate) {
                    continue;
                }
                if (self::partialRowMatches($row, $child)) {
                    return true;
                }
            }

            return false;
        }
        if ($predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL) {
            foreach ($row as $column => $value) {
                if (strcasecmp((string) $column, $predicate->columnName) === 0) {
                    return $value !== null;
                }
            }

            return false;
        }
        if ($predicate->operator !== SQLiteIndexPredicate::EQUALS) {
            return false;
        }
        foreach ($row as $column => $value) {
            if (strcasecmp((string) $column, $predicate->columnName) === 0) {
                return $value === $predicate->value;
            }
        }

        return false;
    }

    private static function valueKey(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . bin2hex($value->bytes);
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return 'json:' . $value->json;
        }

        return get_debug_type($value) . ':' . json_encode($value, JSON_UNESCAPED_SLASHES);
    }
}
