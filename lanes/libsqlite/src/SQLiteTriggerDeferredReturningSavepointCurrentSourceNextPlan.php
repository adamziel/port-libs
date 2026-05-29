<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan
{

    /* Variant consolidated from SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $updates
     * @param array{parent_key:string,child_key:string,deferred?:bool,on_update?:string} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int,int):mixed> $returning
     * @param array{savepoint?:string,rollback_to?:bool,recursive_triggers?:bool,max_depth?:int,current_source?:string,next_source?:string} $options
     * @return array<string,mixed>
     */
    public static function updateParentsWithinSavepointNext119(
        array $parents,
        array $children,
        array $updates,
        array $foreignKey,
        array $triggers = [],
        array $returning = ['*'],
        array $options = [],
    ): array {
        $savepoint = self::identifierNext119((string) ($options['savepoint'] ?? 'wp_import_batch'), 'savepoint');
        $rollbackTo = (bool) ($options['rollback_to'] ?? true);
        $currentSource = (string) ($options['current_source'] ?? 'current-savepoint-source');
        $nextSource = (string) ($options['next_source'] ?? 'next-after-rollback-to');

        $beforeParents = array_values($parents);
        $beforeChildren = array_values($children);
        $statement = SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNext114Plan::updateParents(
            $beforeParents,
            $beforeChildren,
            $updates,
            $foreignKey,
            $triggers,
            $returning,
            [
                'recursive_triggers' => (bool) ($options['recursive_triggers'] ?? true),
                'max_depth' => (int) ($options['max_depth'] ?? 32),
                'current_source' => $currentSource,
                'next_source' => $nextSource,
            ],
        );

        $afterParents = $rollbackTo ? $beforeParents : $statement['parent'];
        $afterChildren = $rollbackTo ? $beforeChildren : $statement['child'];
        $afterViolations = $rollbackTo ? [] : $statement['deferred_violations'];
        $afterCommit = $rollbackTo ? 'ok-after-rollback-to-savepoint' : $statement['commit_status'];

        return [
            'savepoint' => $savepoint,
            'rolled_back' => $rollbackTo,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'before' => [
                'parent' => $beforeParents,
                'child' => $beforeChildren,
            ],
            'after_statement' => [
                'parent' => $statement['parent'],
                'child' => $statement['child'],
                'deferred_violations' => $statement['deferred_violations'],
                'commit_status' => $statement['commit_status'],
            ],
            'after_savepoint' => [
                'parent' => $afterParents,
                'child' => $afterChildren,
                'deferred_violations' => $afterViolations,
                'commit_status' => $afterCommit,
            ],
            'returning_rows' => $statement['returning_rows'],
            'yielded' => self::markYieldedRowsNext119($statement['yielded'], $savepoint, $rollbackTo),
            'trigger_effects_before_rollback' => $statement['trigger_effects'],
            'foreign_key_actions_before_rollback' => $statement['foreign_key_actions'],
            'deferred_before_rollback' => $statement['deferred_violations'],
            'discarded_returning_count' => $rollbackTo ? count($statement['returning_rows']) : 0,
            'restored_parent_keys' => self::rowKeysNext119($afterParents, (string) $foreignKey['parent_key']),
            'restored_child_keys' => self::rowKeysNext119($afterChildren, (string) $foreignKey['child_key']),
            'dependencies' => array_values(array_unique(array_merge(
                $statement['dependencies'],
                [
                    'sqlite-trigger-deferred-returning-savepoint-current-source-next119',
                    'sqlite-returning-yield-before-rollback-to-savepoint',
                    'sqlite-deferred-fk-queue-cleared-by-savepoint-rollback',
                ],
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function markYieldedRowsNext119(array $yielded, string $savepoint, bool $rolledBack): array
    {
        foreach ($yielded as &$row) {
            $row['savepoint'] = $savepoint;
            $row['rolled_back_after_yield'] = $rolledBack;
        }
        unset($row);

        return $yielded;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function rowKeysNext119(array $rows, string $column): array
    {
        $column = self::identifierNext119($column, 'row key column');
        $keys = [];
        foreach ($rows as $row) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite trigger deferred RETURNING savepoint row key {$column} is missing");
            }
            $keys[] = $row[$column];
        }

        return $keys;
    }

    private static function identifierNext119(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("SQLite trigger deferred RETURNING savepoint {$label} is malformed");
        }

        return $identifier;
    }


    /* Variant consolidated from SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php. */
/**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $updates
     * @param array{parent_key:string,child_key:string,deferred?:bool,on_update?:string} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int,int):mixed> $returning
     * @param array{savepoint?:string,rollback_to?:bool,retry_after_rollback?:bool,recursive_triggers?:bool,max_depth?:int,current_source?:string,next_source?:string,retry_source?:string} $options
     * @return array<string,mixed>
     */
    public static function commitBarrierRetryNext141(
        array $parents,
        array $children,
        array $updates,
        array $foreignKey,
        array $triggers = [],
        array $returning = ['*'],
        array $options = [],
    ): array {
        $savepoint = self::identifierNext141((string) ($options['savepoint'] ?? 'wp_import_batch'), 'savepoint');
        $rollbackTo = (bool) ($options['rollback_to'] ?? true);
        $retryAfterRollback = (bool) ($options['retry_after_rollback'] ?? true);
        $currentSource = (string) ($options['current_source'] ?? 'current-trigger-returning-source');
        $nextSource = (string) ($options['next_source'] ?? 'next-deferred-commit-source');
        $retrySource = (string) ($options['retry_source'] ?? 'next-retry-after-rollback-source');

        $attempt = SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::updateParentsWithinSavepointNext119(
            $parents,
            $children,
            $updates,
            $foreignKey,
            $triggers,
            $returning,
            [
                'savepoint' => $savepoint,
                'rollback_to' => $rollbackTo,
                'recursive_triggers' => (bool) ($options['recursive_triggers'] ?? true),
                'max_depth' => (int) ($options['max_depth'] ?? 32),
                'current_source' => $currentSource,
                'next_source' => $nextSource,
            ],
        );

        $blocked = ($attempt['after_statement']['commit_status'] ?? null) === 'deferred-constraint-failed';
        $commitBarrier = $blocked
            ? 'deferred-fk-blocks-commit-after-returning-yield'
            : 'commit-admits-returning-source';
        $durableRows = ($blocked && $rollbackTo) ? [] : $attempt['returning_rows'];
        $invalidatedRows = ($blocked && $rollbackTo) ? self::invalidatedReturningRowsNext141($attempt['returning_rows'], $savepoint, $currentSource, $nextSource) : [];

        $retry = null;
        if ($retryAfterRollback && $rollbackTo) {
            $retry = self::retryPlanNext141($attempt, $foreignKey, $updates, $retrySource, $blocked);
        }

        return [
            'savepoint' => $savepoint,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'retry_source' => $retrySource,
            'commit_barrier' => $commitBarrier,
            'commit_blocked' => $blocked,
            'rolled_back' => $rollbackTo,
            'attempt' => $attempt,
            'yielded_returning_rows' => $attempt['returning_rows'],
            'durable_returning_rows' => $durableRows,
            'invalidated_returning_rows' => $invalidatedRows,
            'invalidated_returning_count' => count($invalidatedRows),
            'deferred_queue_at_commit' => $attempt['after_statement']['deferred_violations'],
            'deferred_queue_after_rollback' => $attempt['after_savepoint']['deferred_violations'],
            'source_transition' => [
                'statement' => $currentSource,
                'commit_attempt' => $nextSource,
                'retry' => $retrySource,
                'next_visible' => ($blocked && $rollbackTo) ? $retrySource : $nextSource,
            ],
            'retry' => $retry,
            'dependencies' => array_values(array_unique(array_merge(
                $attempt['dependencies'],
                [
                    'sqlite-trigger-deferred-returning-savepoint-current-source-next141',
                    'sqlite-deferred-fk-commit-barrier-after-returning',
                    'sqlite-savepoint-current-source-retry-after-deferred-commit-failure',
                ],
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $returningRows
     * @return list<array<string,mixed>>
     */
    private static function invalidatedReturningRowsNext141(array $returningRows, string $savepoint, string $currentSource, string $nextSource): array
    {
        $invalidated = [];
        foreach ($returningRows as $index => $row) {
            $invalidated[] = [
                'ordinal' => $index,
                'savepoint' => $savepoint,
                'yield_source' => $currentSource,
                'blocked_source' => $nextSource,
                'durable' => false,
                'row' => $row,
            ];
        }

        return $invalidated;
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array{parent_key:string,child_key:string,deferred?:bool,on_update?:string} $foreignKey
     * @param list<array<string,mixed>> $updates
     * @return array<string,mixed>
     */
    private static function retryPlanNext141(array $attempt, array $foreignKey, array $updates, string $retrySource, bool $blocked): array
    {
        $parentKey = self::identifierNext141((string) $foreignKey['parent_key'], 'parent key');
        $childKey = self::identifierNext141((string) $foreignKey['child_key'], 'child key');
        $parents = $attempt['after_savepoint']['parent'];
        $children = $attempt['after_savepoint']['child'];

        return [
            'source' => $retrySource,
            'admitted' => $blocked,
            'parent_keys' => self::rowKeysNext141($parents, $parentKey),
            'child_keys' => self::rowKeysNext141($children, $childKey),
            'pending_updates' => self::pendingUpdateKeysNext141($updates),
            'deferred_queue' => $attempt['after_savepoint']['deferred_violations'],
            'returning_rows' => [],
            'status' => $blocked ? 'retry-from-restored-savepoint-image' : 'retry-not-needed',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function rowKeysNext141(array $rows, string $column): array
    {
        $keys = [];
        foreach ($rows as $row) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite deferred RETURNING retry row key {$column} is missing");
            }
            $keys[] = $row[$column];
        }

        return $keys;
    }

    /**
     * @param list<array<string,mixed>> $updates
     * @return list<mixed>
     */
    private static function pendingUpdateKeysNext141(array $updates): array
    {
        $keys = [];
        foreach ($updates as $update) {
            $keys[] = $update['match'] ?? null;
        }

        return $keys;
    }

    private static function identifierNext141(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("SQLite deferred RETURNING retry {$label} is malformed");
        }

        return $identifier;
    }
}
