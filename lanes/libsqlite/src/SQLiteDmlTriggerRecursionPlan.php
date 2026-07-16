<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDmlTriggerRecursionPlan
{
    /**
     * @param list<array<string,mixed>> $initialRows
     * @param list<array<string,mixed>> $inputRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param array{recursive_triggers?:bool,max_depth?:int} $options
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,changes:int,max_depth:int,recursive_triggers:bool}
     */
    public static function insertRows(
        array $initialRows,
        array $inputRows,
        array $triggers,
        array $uniqueColumns,
        string $conflictAction = 'abort',
        array $options = [],
    ): array {
        self::validateUniqueColumns($uniqueColumns);
        $conflictAction = self::normalizeConflictAction($conflictAction, 'statement');
        $recursiveTriggers = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = (int) ($options['max_depth'] ?? 1000);
        if ($maxDepth < 0) {
            throw new \InvalidArgumentException('SQLite trigger recursion max_depth cannot be negative');
        }

        $rows = array_values($initialRows);
        $inserted = [];
        $ignored = [];
        $effects = [];

        foreach ($inputRows as $row) {
            $result = self::insertOne($rows, $row, $triggers, $uniqueColumns, $conflictAction, $recursiveTriggers, $maxDepth, 0);
            $rows = $result['rows'];
            $inserted = array_merge($inserted, $result['inserted']);
            $ignored = array_merge($ignored, $result['ignored']);
            $effects = array_merge($effects, $result['effects']);
        }

        return [
            'rows' => array_values($rows),
            'inserted' => $inserted,
            'ignored' => $ignored,
            'effects' => $effects,
            'changes' => count($inserted),
            'max_depth' => $maxDepth,
            'recursive_triggers' => $recursiveTriggers,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>}
     */
    private static function insertOne(
        array $rows,
        array $row,
        array $triggers,
        array $uniqueColumns,
        string $conflictAction,
        bool $recursiveTriggers,
        int $maxDepth,
        int $depth,
    ): array {
        if ($depth > $maxDepth) {
            throw new \RuntimeException('SQLite trigger recursion limit exceeded');
        }

        $inserted = [];
        $ignored = [];
        $effects = [];

        $before = self::applyTriggers('before', $rows, $row, $triggers, $uniqueColumns, $conflictAction, $recursiveTriggers, $maxDepth, $depth);
        $rows = $before['rows'];
        $inserted = array_merge($inserted, $before['inserted']);
        $ignored = array_merge($ignored, $before['ignored']);
        $effects = array_merge($effects, $before['effects']);
        if ($before['skip_outer']) {
            $ignored[] = $row;
            return ['rows' => $rows, 'inserted' => $inserted, 'ignored' => $ignored, 'effects' => $effects];
        }

        $conflictIndex = self::findConflictIndex($rows, $row, $uniqueColumns);
        if ($conflictIndex !== null) {
            if ($conflictAction === 'ignore') {
                $ignored[] = $row;
                $effects[] = self::effect('insert', 'ignored-conflict', $row, $depth, $conflictAction);
                return ['rows' => array_values($rows), 'inserted' => $inserted, 'ignored' => $ignored, 'effects' => $effects];
            }
            if ($conflictAction === 'replace') {
                $rows[$conflictIndex] = $row;
                $inserted[] = $row;
                $effects[] = self::effect('insert', 'replaced-conflict', $row, $depth, $conflictAction);
            } else {
                throw new \InvalidArgumentException('SQLite recursive trigger unique constraint conflict');
            }
        } else {
            $rows[] = $row;
            $inserted[] = $row;
            $effects[] = self::effect('insert', 'inserted', $row, $depth, $conflictAction);
        }

        $after = self::applyTriggers('after', $rows, $row, $triggers, $uniqueColumns, $conflictAction, $recursiveTriggers, $maxDepth, $depth);
        $rows = $after['rows'];
        $inserted = array_merge($inserted, $after['inserted']);
        $ignored = array_merge($ignored, $after['ignored']);
        $effects = array_merge($effects, $after['effects']);

        return ['rows' => array_values($rows), 'inserted' => $inserted, 'ignored' => $ignored, 'effects' => $effects];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,skip_outer:bool}
     */
    private static function applyTriggers(
        string $timing,
        array $rows,
        array $newRow,
        array $triggers,
        array $uniqueColumns,
        string $statementConflictAction,
        bool $recursiveTriggers,
        int $maxDepth,
        int $depth,
    ): array {
        $inserted = [];
        $ignored = [];
        $effects = [];

        foreach ($triggers as $trigger) {
            self::validateTrigger($trigger);
            if (strtolower((string) $trigger['timing']) !== $timing || strtolower((string) $trigger['event']) !== 'insert') {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? null, $newRow)) {
                $effects[] = self::effect('trigger', 'when-skipped', $newRow, $depth, $statementConflictAction, $timing);
                continue;
            }

            $childRow = self::triggerRow((array) ($trigger['insert_row'] ?? []), $newRow);
            $triggerConflictAction = isset($trigger['conflict_action'])
                ? self::normalizeConflictAction((string) $trigger['conflict_action'], 'trigger')
                : $statementConflictAction;
            $childConflictAction = $statementConflictAction === 'abort' ? $triggerConflictAction : $statementConflictAction;
            $effects[] = self::effect('trigger', 'fired', $childRow, $depth, $childConflictAction, $timing);

            if (!$recursiveTriggers && $depth > 0) {
                $effects[] = self::effect('trigger', 'recursive-trigger-suppressed', $childRow, $depth, $childConflictAction, $timing);
                continue;
            }

            if ($childConflictAction === 'fail' && self::findConflictIndex($rows, $childRow, $uniqueColumns) !== null) {
                $effects[] = self::effect('trigger', 'failed-conflict', $childRow, $depth + 1, $childConflictAction, $timing);
                return ['rows' => array_values($rows), 'inserted' => $inserted, 'ignored' => $ignored, 'effects' => $effects, 'skip_outer' => true];
            }

            $child = self::insertOne($rows, $childRow, $triggers, $uniqueColumns, $childConflictAction, $recursiveTriggers, $maxDepth, $depth + 1);
            $rows = $child['rows'];
            $inserted = array_merge($inserted, $child['inserted']);
            $ignored = array_merge($ignored, $child['ignored']);
            $effects = array_merge($effects, $child['effects']);
        }

        return ['rows' => array_values($rows), 'inserted' => $inserted, 'ignored' => $ignored, 'effects' => $effects, 'skip_outer' => false];
    }

    /**
     * @param array<string,mixed> $trigger
     */
    private static function validateTrigger(array $trigger): void
    {
        if (!in_array(strtolower((string) ($trigger['timing'] ?? '')), ['before', 'after'], true)) {
            throw new \InvalidArgumentException('SQLite recursive trigger timing is unsupported');
        }
        if (strtolower((string) ($trigger['event'] ?? '')) !== 'insert') {
            throw new \InvalidArgumentException('SQLite recursive trigger corpus supports INSERT triggers only');
        }
        if (($trigger['table'] ?? null) !== 'target') {
            throw new \InvalidArgumentException('SQLite recursive trigger corpus supports target-table trigger actions only');
        }
        if (($trigger['action'] ?? null) !== 'insert') {
            throw new \InvalidArgumentException('SQLite recursive trigger corpus supports INSERT trigger actions only');
        }
        if (!isset($trigger['insert_row']) || !is_array($trigger['insert_row'])) {
            throw new \InvalidArgumentException('SQLite recursive trigger insert_row is required');
        }
    }

    /**
     * @param array<string,mixed> $template
     * @param array<string,mixed> $newRow
     * @return array<string,mixed>
     */
    private static function triggerRow(array $template, array $newRow): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            if (is_string($value) && str_starts_with($value, 'new.')) {
                $sourceColumn = substr($value, 4);
                if (!array_key_exists($sourceColumn, $newRow)) {
                    throw new \InvalidArgumentException("SQLite recursive trigger NEW column {$sourceColumn} is missing");
                }
                $row[$column] = $newRow[$sourceColumn];
                continue;
            }
            if (is_string($value) && str_starts_with($value, 'new_increment.')) {
                $sourceColumn = substr($value, 14);
                if (!array_key_exists($sourceColumn, $newRow) || !is_int($newRow[$sourceColumn])) {
                    throw new \InvalidArgumentException("SQLite recursive trigger NEW integer column {$sourceColumn} is missing");
                }
                $row[$column] = $newRow[$sourceColumn] + 1;
                continue;
            }
            if (is_string($value) && str_starts_with($value, 'concat:new.')) {
                [$sourceColumn, $suffix] = array_pad(explode(':', substr($value, 11), 2), 2, '');
                if (!array_key_exists($sourceColumn, $newRow)) {
                    throw new \InvalidArgumentException("SQLite recursive trigger NEW column {$sourceColumn} is missing");
                }
                $row[$column] = (string) $newRow[$sourceColumn] . $suffix;
                continue;
            }

            $row[$column] = $value;
        }

        return $row;
    }

    /**
     * @param mixed $when
     * @param array<string,mixed> $row
     */
    private static function whenMatches(mixed $when, array $row): bool
    {
        if ($when === null) {
            return true;
        }
        if (!is_array($when)) {
            throw new \InvalidArgumentException('SQLite recursive trigger WHEN clause is malformed');
        }
        $column = (string) ($when['column'] ?? '');
        if ($column === '' || !array_key_exists($column, $row)) {
            throw new \InvalidArgumentException('SQLite recursive trigger WHEN column is missing');
        }
        $operator = strtolower((string) ($when['operator'] ?? '<'));
        $value = $when['value'] ?? null;
        return match ($operator) {
            '<' => $row[$column] < $value,
            '<=' => $row[$column] <= $value,
            '=' => $row[$column] === $value,
            '!=' => $row[$column] !== $value,
            'in' => is_array($value) && in_array($row[$column], $value, true),
            default => throw new \InvalidArgumentException('SQLite recursive trigger WHEN operator is unsupported'),
        };
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function effect(string $action, string $result, array $row, int $depth, string $conflictAction, ?string $timing = null): array
    {
        return array_filter([
            'timing' => $timing,
            'action' => $action,
            'result' => $result,
            'depth' => $depth,
            'effective_conflict_action' => $conflictAction,
            'row' => $row,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function findConflictIndex(array $rows, array $row, array $columns): ?int
    {
        foreach ($rows as $index => $candidate) {
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $candidate) || $row[$column] !== $candidate[$column]) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }

    /**
     * @param list<string> $columns
     */
    private static function validateUniqueColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite recursive trigger unique column list cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new \InvalidArgumentException('SQLite recursive trigger unique column is malformed');
            }
        }
    }

    private static function normalizeConflictAction(string $action, string $label): string
    {
        $action = strtolower(trim($action));
        if (!in_array($action, ['abort', 'fail', 'ignore', 'replace', 'rollback'], true)) {
            throw new \InvalidArgumentException("SQLite {$label} conflict action is unsupported");
        }

        return $action;
    }
}
