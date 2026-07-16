<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecursiveTriggerDepthPlan
{
    /**
     * @param list<array<string,mixed>> $initialRows
     * @param list<array<string,mixed>> $inputRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param array{recursive_triggers?:bool,max_depth?:int,on_limit?:string,rollback_rows?:list<array<string,mixed>>} $options
     * @return array{rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,depth_checks:list<array<string,mixed>>,changes:int,recursive_triggers:bool,max_depth:int,max_observed_depth:int,limit_hit:bool,limit_reason:?string,rolled_back:bool,rollback_scope:string}
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
        $conflictAction = self::normalizeConflictAction($conflictAction);
        $recursiveTriggers = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = (int) ($options['max_depth'] ?? 1000);
        if ($maxDepth < 0) {
            throw new \InvalidArgumentException('SQLite recursive trigger depth limit cannot be negative');
        }
        $onLimit = strtolower((string) ($options['on_limit'] ?? 'abort'));
        if (!in_array($onLimit, ['abort', 'rollback', 'ignore'], true)) {
            throw new \InvalidArgumentException('SQLite recursive trigger depth on_limit action is unsupported');
        }

        $statementRows = array_values($initialRows);
        $rollbackRows = array_values($options['rollback_rows'] ?? $statementRows);
        $state = [
            'rows' => $statementRows,
            'inserted' => [],
            'ignored' => [],
            'effects' => [],
            'depth_checks' => [],
            'max_observed_depth' => 0,
            'limit_hit' => false,
            'limit_reason' => null,
        ];

        foreach ($inputRows as $row) {
            $state = self::insertOne($state, $row, $triggers, $uniqueColumns, $conflictAction, $recursiveTriggers, $maxDepth, $onLimit, 0);
            if ($state['limit_hit'] && $onLimit !== 'ignore') {
                break;
            }
        }

        $rolledBack = false;
        $rollbackScope = 'none';
        if ($state['limit_hit'] && $onLimit === 'rollback') {
            $state['rows'] = $rollbackRows;
            $state['inserted'] = [];
            $state['ignored'] = [];
            $rolledBack = true;
            $rollbackScope = 'transaction';
        } elseif ($state['limit_hit'] && $onLimit === 'abort') {
            $state['rows'] = $statementRows;
            $state['inserted'] = [];
            $state['ignored'] = [];
            $rollbackScope = 'statement';
        }

        return [
            'rows' => array_values($state['rows']),
            'inserted' => array_values($state['inserted']),
            'ignored' => array_values($state['ignored']),
            'effects' => array_values($state['effects']),
            'depth_checks' => array_values($state['depth_checks']),
            'changes' => count($state['inserted']),
            'recursive_triggers' => $recursiveTriggers,
            'max_depth' => $maxDepth,
            'max_observed_depth' => (int) $state['max_observed_depth'],
            'limit_hit' => (bool) $state['limit_hit'],
            'limit_reason' => $state['limit_reason'],
            'rolled_back' => $rolledBack,
            'rollback_scope' => $rollbackScope,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @return array<string,mixed>
     */
    private static function insertOne(
        array $state,
        array $row,
        array $triggers,
        array $uniqueColumns,
        string $conflictAction,
        bool $recursiveTriggers,
        int $maxDepth,
        string $onLimit,
        int $currentDepth,
    ): array {
        $state['max_observed_depth'] = max((int) $state['max_observed_depth'], $currentDepth);
        $conflictIndex = self::findConflictIndex($state['rows'], $row, $uniqueColumns);
        if ($conflictIndex !== null) {
            if ($conflictAction === 'ignore') {
                $state['ignored'][] = $row;
                $state['effects'][] = self::effect('insert', 'ignored-conflict', $row, $currentDepth, $currentDepth, $conflictAction);
                return $state;
            }
            if ($conflictAction !== 'replace') {
                throw new \InvalidArgumentException('SQLite recursive trigger depth unique constraint conflict');
            }
            $state['rows'][$conflictIndex] = $row;
            $state['effects'][] = self::effect('insert', 'replaced-conflict', $row, $currentDepth, $currentDepth, $conflictAction);
        } else {
            $state['rows'][] = $row;
            $state['effects'][] = self::effect('insert', 'inserted', $row, $currentDepth, $currentDepth, $conflictAction);
        }
        $state['inserted'][] = $row;

        foreach ($triggers as $trigger) {
            self::validateTrigger($trigger);
            if (strtolower((string) $trigger['timing']) !== 'after' || strtolower((string) $trigger['event']) !== 'insert') {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? null, $row)) {
                $state['effects'][] = self::effect('trigger', 'when-skipped', $row, $currentDepth, $currentDepth + 1, $conflictAction);
                continue;
            }

            $childRow = self::triggerRow((array) $trigger['insert_row'], $row);
            $nextDepth = $currentDepth + 1;
            $state['depth_checks'][] = [
                'current_depth' => $currentDepth,
                'next_depth' => $nextDepth,
                'max_depth' => $maxDepth,
                'allowed' => $nextDepth <= $maxDepth,
                'row' => $childRow,
            ];

            if (!$recursiveTriggers && $currentDepth > 0) {
                $state['effects'][] = self::effect('trigger', 'recursive-trigger-suppressed', $childRow, $currentDepth, $nextDepth, $conflictAction);
                continue;
            }
            if ($nextDepth > $maxDepth) {
                $state['limit_hit'] = true;
                $state['limit_reason'] = 'trigger-recursion-depth-exceeded';
                $state['effects'][] = self::effect('trigger', 'depth-limit-blocked', $childRow, $currentDepth, $nextDepth, $conflictAction);
                if ($onLimit === 'ignore') {
                    $state['ignored'][] = $childRow;
                    continue;
                }
                return $state;
            }

            $childConflictAction = isset($trigger['conflict_action'])
                ? self::normalizeConflictAction((string) $trigger['conflict_action'])
                : $conflictAction;
            $state['effects'][] = self::effect('trigger', 'fired', $childRow, $currentDepth, $nextDepth, $childConflictAction);
            $state = self::insertOne($state, $childRow, $triggers, $uniqueColumns, $childConflictAction, $recursiveTriggers, $maxDepth, $onLimit, $nextDepth);
            if ($state['limit_hit'] && $onLimit !== 'ignore') {
                return $state;
            }
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $trigger
     */
    private static function validateTrigger(array $trigger): void
    {
        if (!in_array(strtolower((string) ($trigger['timing'] ?? '')), ['before', 'after'], true)) {
            throw new \InvalidArgumentException('SQLite recursive trigger depth timing is unsupported');
        }
        if (strtolower((string) ($trigger['event'] ?? '')) !== 'insert') {
            throw new \InvalidArgumentException('SQLite recursive trigger depth supports INSERT triggers only');
        }
        if (($trigger['table'] ?? null) !== 'target') {
            throw new \InvalidArgumentException('SQLite recursive trigger depth supports target table only');
        }
        if (($trigger['action'] ?? null) !== 'insert') {
            throw new \InvalidArgumentException('SQLite recursive trigger depth supports INSERT trigger actions only');
        }
        if (!isset($trigger['insert_row']) || !is_array($trigger['insert_row'])) {
            throw new \InvalidArgumentException('SQLite recursive trigger depth insert_row is required');
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
                    throw new \InvalidArgumentException("SQLite recursive trigger depth NEW column {$sourceColumn} is missing");
                }
                $row[$column] = $newRow[$sourceColumn];
                continue;
            }
            if (is_string($value) && str_starts_with($value, 'new_increment.')) {
                $sourceColumn = substr($value, 14);
                if (!array_key_exists($sourceColumn, $newRow) || !is_int($newRow[$sourceColumn])) {
                    throw new \InvalidArgumentException("SQLite recursive trigger depth NEW integer column {$sourceColumn} is missing");
                }
                $row[$column] = $newRow[$sourceColumn] + 1;
                continue;
            }
            if (is_string($value) && str_starts_with($value, 'concat:new.')) {
                [$sourceColumn, $suffix] = array_pad(explode(':', substr($value, 11), 2), 2, '');
                if (!array_key_exists($sourceColumn, $newRow)) {
                    throw new \InvalidArgumentException("SQLite recursive trigger depth NEW column {$sourceColumn} is missing");
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
            throw new \InvalidArgumentException('SQLite recursive trigger depth WHEN clause is malformed');
        }
        $column = (string) ($when['column'] ?? '');
        if ($column === '' || !array_key_exists($column, $row)) {
            throw new \InvalidArgumentException('SQLite recursive trigger depth WHEN column is missing');
        }
        $operator = strtolower((string) ($when['operator'] ?? '<'));
        $value = $when['value'] ?? null;

        return match ($operator) {
            '<' => $row[$column] < $value,
            '<=' => $row[$column] <= $value,
            '=' => $row[$column] === $value,
            '!=' => $row[$column] !== $value,
            'in' => is_array($value) && in_array($row[$column], $value, true),
            default => throw new \InvalidArgumentException('SQLite recursive trigger depth WHEN operator is unsupported'),
        };
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function effect(string $action, string $result, array $row, int $currentDepth, int $nextDepth, string $conflictAction): array
    {
        return [
            'action' => $action,
            'result' => $result,
            'current_depth' => $currentDepth,
            'next_depth' => $nextDepth,
            'effective_conflict_action' => $conflictAction,
            'row' => $row,
        ];
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
            throw new \InvalidArgumentException('SQLite recursive trigger depth unique column list cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new \InvalidArgumentException('SQLite recursive trigger depth unique column is malformed');
            }
        }
    }

    private static function normalizeConflictAction(string $action): string
    {
        $action = strtolower(trim($action));
        if (!in_array($action, ['abort', 'ignore', 'replace'], true)) {
            throw new \InvalidArgumentException('SQLite recursive trigger depth conflict action is unsupported');
        }

        return $action;
    }
}
