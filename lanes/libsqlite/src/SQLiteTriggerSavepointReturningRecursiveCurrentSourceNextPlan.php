<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param array{savepoint?:string,rollback_to?:bool,recursive_triggers?:bool,max_depth?:int,conflict_action?:string,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,?string):mixed>} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentRows,
        array $nextRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $options = [],
    ): array {
        $savepoint = self::identifier((string) ($options['savepoint'] ?? 'wp_import_trigger_batch'), 'savepoint');
        $rollbackTo = (bool) ($options['rollback_to'] ?? true);
        $beforeRows = array_values($rows);

        $attempt = SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan::execute(
            $beforeRows,
            $currentRows,
            $nextRows,
            $uniqueColumns,
            $assignments,
            $triggers,
            $options,
        );

        $afterRows = $rollbackTo ? $beforeRows : array_values($attempt['rows']);
        $currentReturning = array_values($attempt['current_returning']);
        $nextReturning = array_values($attempt['next_returning']);

        return [
            'savepoint' => $savepoint,
            'rolled_back' => $rollbackTo,
            'status' => $rollbackTo ? 'rolled-back-to-savepoint-after-returning-yield' : 'released-after-returning-yield',
            'before_rows' => $beforeRows,
            'attempted_rows' => array_values($attempt['rows']),
            'rows' => $afterRows,
            'current_rows' => $afterRows,
            'current_source_rows' => array_values($attempt['current_source_rows']),
            'next_source_rows' => array_values($attempt['next_source_rows']),
            'current_attempt' => $attempt['current'],
            'next_attempt' => $attempt['next'],
            'yield_stream' => self::markYielded(
                array_merge(array_values($attempt['current_yield_edges']), array_values($attempt['next_yield_edges'])),
                $savepoint,
                $rollbackTo,
            ),
            'current_returning_rows' => $rollbackTo ? [] : $currentReturning,
            'next_returning_rows' => $rollbackTo ? [] : $nextReturning,
            'attempted_current_returning_rows' => $currentReturning,
            'attempted_next_returning_rows' => $nextReturning,
            'returning_rows' => $rollbackTo ? [] : array_merge($currentReturning, $nextReturning),
            'attempted_returning_rows' => array_merge($currentReturning, $nextReturning),
            'discarded_returning_count' => $rollbackTo ? count($currentReturning) + count($nextReturning) : 0,
            'discarded_rows' => $rollbackTo ? self::discardedRows($beforeRows, array_values($attempt['rows'])) : [],
            'changes' => $rollbackTo ? 0 : (int) $attempt['changes'],
            'attempted_changes' => (int) $attempt['changes'],
            'savepoint_preserved' => $afterRows == $beforeRows,
            'current_source_count' => count($attempt['current_source_rows']),
            'next_source_count' => count($attempt['next_source_rows']),
            'dependencies' => array_values(array_unique(array_merge(
                $attempt['dependencies'],
                [
                    'sqlite-trigger-savepoint-returning-recursive-current-source-next122',
                    'sqlite-returning-yield-before-savepoint-rollback-recursive-trigger',
                    'sqlite-current-source-next-source-restored-by-rollback-to',
                ],
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function markYielded(array $yielded, string $savepoint, bool $rolledBack): array
    {
        foreach ($yielded as &$row) {
            $row['savepoint'] = $savepoint;
            $row['rolled_back_after_yield'] = $rolledBack;
        }
        unset($row);

        return array_values($yielded);
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $after
     * @return list<array<string,mixed>>
     */
    private static function discardedRows(array $before, array $after): array
    {
        $discarded = [];
        foreach ($after as $index => $row) {
            if (!array_key_exists($index, $before) || $before[$index] != $row) {
                $discarded[] = [
                    'row_index' => $index,
                    'attempted_row' => $row,
                    'savepoint_row' => $before[$index] ?? null,
                ];
            }
        }

        return $discarded;
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger savepoint RETURNING recursive current-source {$label} is malformed");
        }

        return $identifier;
    }
}
