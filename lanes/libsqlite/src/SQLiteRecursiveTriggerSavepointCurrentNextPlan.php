<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecursiveTriggerSavepointCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $savepointRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $currentTriggers
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $nextTriggers
     * @param list<string> $uniqueColumns
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,int,array<string,mixed>):mixed> $returning
     * @param array{recursive_triggers?:bool,max_depth?:int} $currentOptions
     * @param array{recursive_triggers?:bool,max_depth?:int} $nextOptions
     * @return array{savepoint:string,status:string,savepoint_rows:list<array<string,mixed>>,current_attempt_rows:list<array<string,mixed>>,next_rows:list<array<string,mixed>>,current_returning_rows:list<array<string,mixed>>,current_attempted_yields:list<array<string,mixed>>,next_returning_rows:list<array<string,mixed>>,next_attempted_yields:list<array<string,mixed>>,current_effects:list<array<string,mixed>>,next_effects:list<array<string,mixed>>,discarded_current_rows:list<array<string,mixed>>,current_changes:int,next_changes:int,total_changes:int,current_rolled_back:bool,next_rolled_back:bool,savepoint_preserved_after_current:bool,next_started_from_savepoint:bool,current_rollback_reason:?string,next_rollback_reason:?string,next_rowid_after_current:int,next_rowid_after_next:int,dependencies:list<string>}
     */
    public static function retryAfterRollback(
        string $savepoint,
        array $savepointRows,
        array $currentRows,
        array $currentTriggers,
        array $nextRows,
        array $nextTriggers,
        array $uniqueColumns,
        array $returning,
        string $currentConflictAction = 'rollback',
        string $nextConflictAction = 'abort',
        array $currentOptions = [],
        array $nextOptions = [],
    ): array {
        self::identifier($savepoint, 'savepoint');
        if ($currentRows === []) {
            throw new \InvalidArgumentException('SQLite recursive trigger current statement rows cannot be empty');
        }
        if ($nextRows === []) {
            throw new \InvalidArgumentException('SQLite recursive trigger next statement rows cannot be empty');
        }

        $current = SQLiteRecursiveTriggerReturningSavepointPlan::insertRows(
            $savepoint,
            $savepointRows,
            $currentRows,
            $currentTriggers,
            $uniqueColumns,
            $currentConflictAction,
            $returning,
            $currentOptions
        );

        $nextBaseRows = $current['rolled_back'] ? $savepointRows : $current['rows'];
        $next = SQLiteRecursiveTriggerReturningSavepointPlan::insertRows(
            $savepoint,
            $nextBaseRows,
            $nextRows,
            $nextTriggers,
            $uniqueColumns,
            $nextConflictAction,
            $returning,
            $nextOptions
        );

        return [
            'savepoint' => $savepoint,
            'status' => $current['rolled_back'] && !$next['rolled_back'] ? 'rolled-back-then-next-applied' : ($next['rolled_back'] ? 'next-rolled-back' : 'current-and-next-applied'),
            'savepoint_rows' => array_values($savepointRows),
            'current_attempt_rows' => $current['attempted_rows'],
            'next_rows' => $next['rows'],
            'current_returning_rows' => $current['returning_rows'],
            'current_attempted_yields' => $current['attempted_yields'],
            'next_returning_rows' => $next['returning_rows'],
            'next_attempted_yields' => $next['attempted_yields'],
            'current_effects' => $current['effects'],
            'next_effects' => $next['effects'],
            'discarded_current_rows' => $current['discarded'],
            'current_changes' => $current['changes'],
            'next_changes' => $next['changes'],
            'total_changes' => $current['changes'] + $next['changes'],
            'current_rolled_back' => $current['rolled_back'],
            'next_rolled_back' => $next['rolled_back'],
            'savepoint_preserved_after_current' => $current['savepoint_preserved'],
            'next_started_from_savepoint' => self::startsWithRows($next['rows'], $savepointRows),
            'current_rollback_reason' => $current['rollback_reason'],
            'next_rollback_reason' => $next['rollback_reason'],
            'next_rowid_after_current' => $current['next_rowid'],
            'next_rowid_after_next' => $next['next_rowid'],
            'dependencies' => [
                'sqlite-recursive-trigger-savepoint-current-next71',
                'sqlite-recursive-trigger-conflict',
                'sqlite-savepoint-current-rollback',
                'sqlite-returning-recursive-yield-current-next50',
            ],
        ];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite recursive trigger {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $prefix
     */
    private static function startsWithRows(array $rows, array $prefix): bool
    {
        if (count($rows) < count($prefix)) {
            return false;
        }

        return array_slice($rows, 0, count($prefix)) === array_values($prefix);
    }
}
