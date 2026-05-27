<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecursiveTriggerSavepointPlan
{
    /**
     * @param list<array<string,mixed>> $savepointRows
     * @param list<array<string,mixed>> $inputRows
     * @param list<array<string,mixed>> $triggers
     * @param list<string> $uniqueColumns
     * @param array{recursive_triggers?:bool,max_depth?:int} $options
     * @return array{savepoint:string,rows:list<array<string,mixed>>,current_rows:list<array<string,mixed>>,attempted_rows:list<array<string,mixed>>,inserted:list<array<string,mixed>>,ignored:list<array<string,mixed>>,effects:list<array<string,mixed>>,changes:int,conflict_action:string,recursive_triggers:bool,max_depth:int,rolled_back:bool,aborted:bool,rollback_scope:string,rollback_reason:?string,savepoint_preserved:bool,discarded:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function insertRows(
        string $savepoint,
        array $savepointRows,
        array $inputRows,
        array $triggers,
        array $uniqueColumns,
        string $conflictAction = 'rollback',
        array $options = [],
    ): array {
        $savepoint = trim($savepoint);
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite recursive trigger savepoint name cannot be empty');
        }

        $result = SQLiteRecursiveTriggerConflictRollbackPlan::insertRows(
            $savepointRows,
            $inputRows,
            $triggers,
            $uniqueColumns,
            $conflictAction,
            $options + ['rollback_rows' => array_values($savepointRows)]
        );

        $rolledBackToSavepoint = $result['rolled_back'] && $result['rollback_scope'] === 'transaction';
        $rows = $rolledBackToSavepoint ? array_values($savepointRows) : $result['rows'];
        $effects = $result['effects'];
        $discarded = $rolledBackToSavepoint ? self::discardedRows($effects) : [];
        if ($rolledBackToSavepoint) {
            $effects[] = [
                'action' => 'savepoint',
                'result' => 'rollback-to-current-savepoint',
                'savepoint' => $savepoint,
                'discarded_count' => count($discarded),
                'reason' => $result['rollback_reason'],
            ];
        }

        return [
            'savepoint' => $savepoint,
            'rows' => $rows,
            'current_rows' => $rows,
            'attempted_rows' => $result['rows'],
            'inserted' => $rolledBackToSavepoint ? [] : $result['inserted'],
            'ignored' => $rolledBackToSavepoint ? [] : $result['ignored'],
            'effects' => $effects,
            'changes' => $rolledBackToSavepoint ? 0 : $result['changes'],
            'conflict_action' => $result['conflict_action'],
            'recursive_triggers' => $result['recursive_triggers'],
            'max_depth' => $result['max_depth'],
            'rolled_back' => $rolledBackToSavepoint,
            'aborted' => $result['aborted'],
            'rollback_scope' => $rolledBackToSavepoint ? 'savepoint' : $result['rollback_scope'],
            'rollback_reason' => $result['rollback_reason'],
            'savepoint_preserved' => self::rowsEqual($rows, $savepointRows),
            'discarded' => $discarded,
            'dependencies' => [
                'sqlite-recursive-trigger-conflict',
                'sqlite-savepoint-current-rollback',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $left
     * @param list<array<string,mixed>> $right
     */
    private static function rowsEqual(array $left, array $right): bool
    {
        return json_encode(array_values($left), JSON_THROW_ON_ERROR) === json_encode(array_values($right), JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string,mixed>> $effects
     * @return list<array<string,mixed>>
     */
    private static function discardedRows(array $effects): array
    {
        $discarded = [];
        foreach ($effects as $effect) {
            if (($effect['action'] ?? null) !== 'insert') {
                continue;
            }
            if (!in_array($effect['result'] ?? null, ['inserted', 'replaced-conflict'], true)) {
                continue;
            }
            if (isset($effect['row']) && is_array($effect['row'])) {
                $discarded[] = $effect['row'];
            }
        }

        return $discarded;
    }
}
