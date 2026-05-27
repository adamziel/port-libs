<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecursiveTriggerConflictRollbackPlan
{
    /**
     * @param list<array<string,mixed>> $transactionRows
     * @param list<array<string,mixed>> $inputRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param array{recursive_triggers?:bool,max_depth?:int,rollback_rows?:list<array<string,mixed>>} $options
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,changes:int,conflict_action:string,recursive_triggers:bool,max_depth:int,rolled_back:bool,aborted:bool,rollback_scope:string,rollback_reason:?string}
     */
    public static function insertRows(
        array $transactionRows,
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
            throw new \InvalidArgumentException('SQLite recursive trigger rollback max_depth cannot be negative');
        }

        $statementStartRows = array_values($transactionRows);
        $rollbackRows = array_values($options['rollback_rows'] ?? $statementStartRows);
        $rows = $statementStartRows;
        $inserted = [];
        $ignored = [];
        $effects = [];

        foreach ($inputRows as $row) {
            $result = self::insertOne($rows, $row, $triggers, $uniqueColumns, $conflictAction, $recursiveTriggers, $maxDepth, 0);
            $effects = array_merge($effects, $result['effects']);

            if ($result['rollback']) {
                return self::finish(
                    $rollbackRows,
                    [],
                    [],
                    $effects,
                    $conflictAction,
                    $recursiveTriggers,
                    $maxDepth,
                    true,
                    true,
                    'transaction',
                    $result['reason']
                );
            }
            if ($result['abort']) {
                return self::finish(
                    $statementStartRows,
                    [],
                    [],
                    $effects,
                    $conflictAction,
                    $recursiveTriggers,
                    $maxDepth,
                    false,
                    true,
                    'statement',
                    $result['reason']
                );
            }

            $rows = $result['rows'];
            $inserted = array_merge($inserted, $result['inserted']);
            $ignored = array_merge($ignored, $result['ignored']);
        }

        return self::finish($rows, $inserted, $ignored, $effects, $conflictAction, $recursiveTriggers, $maxDepth, false, false, 'none', null);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,rollback:bool,abort:bool,reason:?string}
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
            throw new \RuntimeException('SQLite recursive trigger rollback limit exceeded');
        }

        $inserted = [];
        $ignored = [];
        $effects = [];

        $conflict = self::applyConflict($rows, $row, $uniqueColumns, $conflictAction, $depth);
        $effects[] = $conflict['effect'];
        if ($conflict['rollback'] || $conflict['abort']) {
            return self::stepResult($rows, $inserted, $ignored, $effects, $conflict['rollback'], $conflict['abort'], $conflict['reason']);
        }
        if ($conflict['ignored']) {
            $ignored[] = $row;
            return self::stepResult($rows, $inserted, $ignored, $effects, false, false, null);
        }

        $rows = $conflict['rows'];
        $inserted[] = $row;

        foreach ($triggers as $trigger) {
            self::validateTrigger($trigger);
            if (strtolower((string) $trigger['timing']) !== 'after' || strtolower((string) $trigger['event']) !== 'insert') {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? null, $row)) {
                $effects[] = self::effect('trigger', 'when-skipped', $row, $depth, $conflictAction, 'after');
                continue;
            }

            $childRow = self::triggerRow((array) $trigger['insert_row'], $row);
            $childConflictAction = isset($trigger['conflict_action'])
                ? self::normalizeConflictAction((string) $trigger['conflict_action'], 'trigger')
                : $conflictAction;
            $effects[] = self::effect('trigger', 'fired', $childRow, $depth, $childConflictAction, 'after');

            if (!$recursiveTriggers && $depth > 0) {
                $effects[] = self::effect('trigger', 'recursive-trigger-suppressed', $childRow, $depth, $childConflictAction, 'after');
                continue;
            }

            $child = self::insertOne($rows, $childRow, $triggers, $uniqueColumns, $childConflictAction, $recursiveTriggers, $maxDepth, $depth + 1);
            $effects = array_merge($effects, $child['effects']);
            if ($child['rollback'] || $child['abort']) {
                return self::stepResult($rows, $inserted, $ignored, $effects, $child['rollback'], $child['abort'], $child['reason']);
            }
            $rows = $child['rows'];
            $inserted = array_merge($inserted, $child['inserted']);
            $ignored = array_merge($ignored, $child['ignored']);
        }

        return self::stepResult(array_values($rows), $inserted, $ignored, $effects, false, false, null);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     * @return array{rows:list<array<string,mixed>>,effect:array<string,mixed>,ignored:bool,rollback:bool,abort:bool,reason:?string}
     */
    private static function applyConflict(array $rows, array $row, array $uniqueColumns, string $conflictAction, int $depth): array
    {
        $conflictIndex = self::findConflictIndex($rows, $row, $uniqueColumns);
        if ($conflictIndex === null) {
            $rows[] = $row;
            return [
                'rows' => array_values($rows),
                'effect' => self::effect('insert', 'inserted', $row, $depth, $conflictAction),
                'ignored' => false,
                'rollback' => false,
                'abort' => false,
                'reason' => null,
            ];
        }

        if ($conflictAction === 'ignore') {
            return [
                'rows' => array_values($rows),
                'effect' => self::effect('insert', 'ignored-conflict', $row, $depth, $conflictAction),
                'ignored' => true,
                'rollback' => false,
                'abort' => false,
                'reason' => null,
            ];
        }
        if ($conflictAction === 'replace') {
            $rows[$conflictIndex] = $row;
            return [
                'rows' => array_values($rows),
                'effect' => self::effect('insert', 'replaced-conflict', $row, $depth, $conflictAction),
                'ignored' => false,
                'rollback' => false,
                'abort' => false,
                'reason' => null,
            ];
        }
        if ($conflictAction === 'fail') {
            return [
                'rows' => array_values($rows),
                'effect' => self::effect('insert', 'failed-conflict', $row, $depth, $conflictAction),
                'ignored' => true,
                'rollback' => false,
                'abort' => false,
                'reason' => 'unique-conflict-fail',
            ];
        }
        if ($conflictAction === 'rollback') {
            return [
                'rows' => array_values($rows),
                'effect' => self::effect('insert', 'rolled-back-conflict', $row, $depth, $conflictAction),
                'ignored' => false,
                'rollback' => true,
                'abort' => false,
                'reason' => 'unique-conflict-rollback',
            ];
        }

        return [
            'rows' => array_values($rows),
            'effect' => self::effect('insert', 'aborted-conflict', $row, $depth, $conflictAction),
            'ignored' => false,
            'rollback' => false,
            'abort' => true,
            'reason' => 'unique-conflict-abort',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $inserted
     * @param list<array<string,mixed>> $ignored
     * @param list<array<string,mixed>> $effects
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,changes:int,conflict_action:string,recursive_triggers:bool,max_depth:int,rolled_back:bool,aborted:bool,rollback_scope:string,rollback_reason:?string}
     */
    private static function finish(
        array $rows,
        array $inserted,
        array $ignored,
        array $effects,
        string $conflictAction,
        bool $recursiveTriggers,
        int $maxDepth,
        bool $rolledBack,
        bool $aborted,
        string $rollbackScope,
        ?string $rollbackReason,
    ): array {
        return [
            'rows' => array_values($rows),
            'inserted' => $inserted,
            'ignored' => $ignored,
            'effects' => $effects,
            'changes' => count($inserted),
            'conflict_action' => $conflictAction,
            'recursive_triggers' => $recursiveTriggers,
            'max_depth' => $maxDepth,
            'rolled_back' => $rolledBack,
            'aborted' => $aborted,
            'rollback_scope' => $rollbackScope,
            'rollback_reason' => $rollbackReason,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $inserted
     * @param list<array<string,mixed>> $ignored
     * @param list<array<string,mixed>> $effects
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,rollback:bool,abort:bool,reason:?string}
     */
    private static function stepResult(array $rows, array $inserted, array $ignored, array $effects, bool $rollback, bool $abort, ?string $reason): array
    {
        return [
            'rows' => array_values($rows),
            'inserted' => $inserted,
            'ignored' => $ignored,
            'effects' => $effects,
            'rollback' => $rollback,
            'abort' => $abort,
            'reason' => $reason,
        ];
    }

    /**
     * @param array<string,mixed> $trigger
     */
    private static function validateTrigger(array $trigger): void
    {
        if (!in_array(strtolower((string) ($trigger['timing'] ?? '')), ['after'], true)) {
            throw new \InvalidArgumentException('SQLite recursive trigger rollback corpus supports AFTER triggers only');
        }
        if (strtolower((string) ($trigger['event'] ?? '')) !== 'insert') {
            throw new \InvalidArgumentException('SQLite recursive trigger rollback corpus supports INSERT triggers only');
        }
        if (($trigger['table'] ?? null) !== 'target') {
            throw new \InvalidArgumentException('SQLite recursive trigger rollback corpus supports target-table trigger actions only');
        }
        if (($trigger['action'] ?? null) !== 'insert') {
            throw new \InvalidArgumentException('SQLite recursive trigger rollback corpus supports INSERT trigger actions only');
        }
        if (!isset($trigger['insert_row']) || !is_array($trigger['insert_row'])) {
            throw new \InvalidArgumentException('SQLite recursive trigger rollback insert_row is required');
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
                    throw new \InvalidArgumentException("SQLite recursive trigger rollback NEW column {$sourceColumn} is missing");
                }
                $row[$column] = $newRow[$sourceColumn];
                continue;
            }
            if (is_string($value) && str_starts_with($value, 'new_increment.')) {
                $sourceColumn = substr($value, 14);
                if (!array_key_exists($sourceColumn, $newRow) || !is_int($newRow[$sourceColumn])) {
                    throw new \InvalidArgumentException("SQLite recursive trigger rollback NEW integer column {$sourceColumn} is missing");
                }
                $row[$column] = $newRow[$sourceColumn] + 1;
                continue;
            }
            if (is_string($value) && str_starts_with($value, 'concat:new.')) {
                [$sourceColumn, $suffix] = array_pad(explode(':', substr($value, 11), 2), 2, '');
                if (!array_key_exists($sourceColumn, $newRow)) {
                    throw new \InvalidArgumentException("SQLite recursive trigger rollback NEW column {$sourceColumn} is missing");
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
            throw new \InvalidArgumentException('SQLite recursive trigger rollback WHEN clause is malformed');
        }
        $column = (string) ($when['column'] ?? '');
        if ($column === '' || !array_key_exists($column, $row)) {
            throw new \InvalidArgumentException('SQLite recursive trigger rollback WHEN column is missing');
        }
        $operator = strtolower((string) ($when['operator'] ?? '<'));
        $value = $when['value'] ?? null;

        return match ($operator) {
            '<' => $row[$column] < $value,
            '<=' => $row[$column] <= $value,
            '=' => $row[$column] === $value,
            '!=' => $row[$column] !== $value,
            default => throw new \InvalidArgumentException('SQLite recursive trigger rollback WHEN operator is unsupported'),
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
            throw new \InvalidArgumentException('SQLite recursive trigger rollback unique column list cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new \InvalidArgumentException('SQLite recursive trigger rollback unique column is malformed');
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
