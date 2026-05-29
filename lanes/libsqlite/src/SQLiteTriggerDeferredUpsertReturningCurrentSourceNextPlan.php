<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>|null,string,int):mixed> $returning
     * @param list<array<string,mixed>> $parentRows
     * @param array{child_key:string,parent_key:string,deferred?:bool,parent_table?:string,child_table?:string} $foreignKey
     * @param array{transaction?:string,current_source?:string,next_source?:string,rollback_transaction?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeDeferredCommit(
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $returning,
        array $parentRows,
        array $foreignKey,
        array $options = [],
        ?callable $where = null,
    ): array {
        $transaction = self::identifier((string) ($options['transaction'] ?? 'wp_import_txn'), 'transaction');
        $currentSource = (string) ($options['current_source'] ?? 'current-upsert-returning-source');
        $nextSource = (string) ($options['next_source'] ?? 'next-deferred-fk-commit-source');
        $rollbackTransaction = (bool) ($options['rollback_transaction'] ?? true);

        $childKey = self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child foreign key');
        $parentKey = self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent foreign key');
        $parentTable = (string) ($foreignKey['parent_table'] ?? 'parent');
        $childTable = (string) ($foreignKey['child_table'] ?? 'child');
        $deferred = (bool) ($foreignKey['deferred'] ?? true);

        $statement = SQLiteTriggerUpsertSavepointReturningCurrentSourceNext132Plan::executeWithinSavepoint(
            $transaction,
            $rows,
            $incomingRows,
            $uniqueColumns,
            $assignments,
            $triggers,
            $returning,
            $where,
        );
        if ($statement['rolled_back_to_savepoint'] === true) {
            throw new \InvalidArgumentException('SQLite deferred UPSERT RETURNING current-source next135 requires a statement that reaches deferred commit validation');
        }

        $afterStatementRows = $statement['after_savepoint'];
        $violations = $deferred ? self::deferredViolations($afterStatementRows, $parentRows, $childKey, $parentKey, $childTable, $parentTable) : [];
        $commitBlocked = $violations !== [];
        $afterRollbackRows = $commitBlocked && $rollbackTransaction ? array_values($rows) : $afterStatementRows;
        $yielded = self::markYielded($statement['yield_stream'], $transaction, $commitBlocked);

        return [
            'status' => 'trigger-deferred-upsert-returning-current-source-next135-ready',
            'transaction' => $transaction,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'deferred' => $deferred,
            'commit_status' => $commitBlocked ? 'deferred-foreign-key-failed' : 'ok',
            'commit_blocked' => $commitBlocked,
            'rollback_transaction' => $commitBlocked && $rollbackTransaction,
            'before' => array_values($rows),
            'parent_rows' => array_values($parentRows),
            'after_statement' => $afterStatementRows,
            'after_failed_commit' => $afterStatementRows,
            'after_transaction_rollback' => $afterRollbackRows,
            'current_returning' => $statement['current_returning'],
            'next_returning' => $commitBlocked ? [] : $statement['next_returning'],
            'yield_stream' => $yielded,
            'inserted_rows' => $commitBlocked && $rollbackTransaction ? [] : $statement['inserted_rows'],
            'updated_rows' => $commitBlocked && $rollbackTransaction ? [] : $statement['updated_rows'],
            'skipped_rows' => $statement['skipped_rows'],
            'trigger_effects_before_commit' => $statement['trigger_effects_before_rollback'],
            'deferred_violations' => $violations,
            'discarded_next_returning_count' => $commitBlocked ? count($statement['current_returning']) : 0,
            'changes' => $commitBlocked && $rollbackTransaction ? 0 : $statement['changes'],
            'statement_changes_before_commit' => $statement['changes'],
            'restored_child_keys' => self::rowKeys($afterRollbackRows, $childKey),
            'parent_keys' => self::rowKeys($parentRows, $parentKey),
            'dependencies' => array_values(array_unique(array_merge(
                $statement['dependencies'],
                [
                    'sqlite-trigger-deferred-upsert-returning-current-source-next135',
                    'sqlite-returning-yield-before-deferred-fk-commit',
                    'sqlite-deferred-fk-blocks-next-source-after-upsert-returning',
                    'sqlite-transaction-rollback-restores-current-source-after-deferred-fk',
                ],
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $parentRows
     * @return list<array<string,mixed>>
     */
    private static function deferredViolations(array $childRows, array $parentRows, string $childKey, string $parentKey, string $childTable, string $parentTable): array
    {
        $parentKeys = [];
        foreach ($parentRows as $parent) {
            if (!array_key_exists($parentKey, $parent)) {
                throw new \InvalidArgumentException("SQLite deferred UPSERT parent key {$parentKey} is missing");
            }
            if ($parent[$parentKey] !== null) {
                $parentKeys[] = $parent[$parentKey];
            }
        }

        $violations = [];
        foreach ($childRows as $rowid => $child) {
            if (!array_key_exists($childKey, $child)) {
                throw new \InvalidArgumentException("SQLite deferred UPSERT child key {$childKey} is missing");
            }
            $value = $child[$childKey];
            if ($value === null || in_array($value, $parentKeys, false)) {
                continue;
            }
            $violations[] = [
                'table' => $childTable,
                'rowid' => $child['rowid'] ?? $child['id'] ?? $child['meta_id'] ?? $rowid + 1,
                'parent' => $parentTable,
                'child_key' => $childKey,
                'parent_key' => $parentKey,
                'value' => $value,
                'deferred_until' => 'commit',
            ];
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function markYielded(array $yielded, string $transaction, bool $commitBlocked): array
    {
        foreach ($yielded as &$row) {
            $row['transaction'] = $transaction;
            $row['commit_blocked_after_yield'] = $commitBlocked;
        }
        unset($row);

        return $yielded;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function rowKeys(array $rows, string $column): array
    {
        $keys = [];
        foreach ($rows as $row) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite deferred UPSERT row key {$column} is missing");
            }
            $keys[] = $row[$column];
        }

        return $keys;
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("SQLite deferred UPSERT RETURNING {$label} is malformed");
        }

        return $identifier;
    }
}
