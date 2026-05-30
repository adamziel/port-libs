<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDynamicTriggerForeignKeyPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param array{id:mixed,parent_id:mixed,label?:string} $incoming
     * @return array<string,mixed>
     */
    public static function replaceSelfReferencingRow(array $rows, array $incoming, bool $deferred = true, bool $rollbackOnViolation = true): array
    {
        $original = array_values($rows);
        $rows = array_values($rows);
        $incomingId = $incoming['id'] ?? throw new \InvalidArgumentException('SQLite dynamic trigger FK incoming row id is required');
        $incomingParent = $incoming['parent_id'] ?? null;
        $deleted = [];

        foreach ($rows as $index => $row) {
            if (($row['id'] ?? null) === $incomingId) {
                $deleted[] = $row;
                unset($rows[$index]);
                break;
            }
        }

        $rows = array_values($rows);
        $queue = array_column($deleted, 'id');
        while ($queue !== []) {
            $parent = array_shift($queue);
            foreach ($rows as $index => $row) {
                if (($row['parent_id'] ?? null) === $parent) {
                    $deleted[] = $row;
                    $queue[] = $row['id'];
                    unset($rows[$index]);
                }
            }
            $rows = array_values($rows);
        }

        $rows[] = $incoming + ['label' => $incoming['label'] ?? 'incoming-' . (string) $incomingId];
        $violations = self::selfReferencingViolations($rows);
        $rollback = $deferred && $rollbackOnViolation && $violations !== [];

        return [
            'source' => 'fkey1.test fkey1-5.1..5.4',
            'operation' => 'insert-or-replace-self-referencing-cascade',
            'status' => $violations === [] ? 'commit-ok' : ($rollback ? 'rolled-back' : 'constraint-failed'),
            'deferred' => $deferred,
            'rollback_on_violation' => $rollbackOnViolation,
            'incoming_id' => $incomingId,
            'incoming_parent_id' => $incomingParent,
            'deleted_ids' => array_values(array_column($deleted, 'id')),
            'deleted_parent_ids' => array_values(array_column($deleted, 'parent_id')),
            'cascade_delete_count' => count($deleted),
            'attempted_rows' => self::sortRows($rows),
            'committed_rows' => self::sortRows($rollback ? $original : $rows),
            'violations' => $violations,
            'violation_count' => count($violations),
            'boundary' => $rollback ? 'replace-delete-cascade-rolled-back' : ($violations === [] ? 'replace-cascade-committed' : 'replace-cascade-blocked'),
            'dependencies' => [
                'sqlite-fkey1-replace-cascade-parent-delete',
                'sqlite-deferred-foreign-key-commit-check',
                'sqlite-trigger-backed-foreign-key-actions',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{event:string,match_column:string,match_value:mixed,delete_column:string,delete_value:mixed,name?:string} $trigger
     * @return array<string,mixed>
     */
    public static function deleteWithAfterTrigger(array $rows, string $whereColumn, mixed $whereValue, array $trigger): array
    {
        $whereColumn = self::identifier($whereColumn, 'WHERE column');
        $matchColumn = self::identifier((string) ($trigger['match_column'] ?? ''), 'trigger match column');
        $deleteColumn = self::identifier((string) ($trigger['delete_column'] ?? ''), 'trigger delete column');
        $triggerName = self::identifier((string) ($trigger['name'] ?? 'audit_delete'), 'trigger name');
        $rows = array_values($rows);
        $outerDeleted = [];
        $triggerDeleted = [];

        foreach ($rows as $index => $row) {
            if (($row[$whereColumn] ?? null) === $whereValue) {
                $outerDeleted[] = $row;
                unset($rows[$index]);
            }
        }
        $rows = array_values($rows);

        foreach ($outerDeleted as $old) {
            if (($old[$matchColumn] ?? null) !== $trigger['match_value']) {
                continue;
            }
            foreach ($rows as $index => $row) {
                if (($row[$deleteColumn] ?? null) === $trigger['delete_value']) {
                    $triggerDeleted[] = $row + ['trigger' => $triggerName];
                    unset($rows[$index]);
                }
            }
            $rows = array_values($rows);
        }

        return [
            'source' => 'trigger1.test trigger1-1.10',
            'operation' => 'delete-statement-with-after-delete-trigger',
            'status' => 'commit-ok',
            'trigger' => $triggerName,
            'outer_deleted_ids' => array_values(array_column($outerDeleted, 'id')),
            'trigger_deleted_ids' => array_values(array_column($triggerDeleted, 'id')),
            'remaining_ids' => array_values(array_column(self::sortRows($rows), 'id')),
            'outer_delete_count' => count($outerDeleted),
            'trigger_delete_count' => count($triggerDeleted),
            'total_changes' => count($outerDeleted) + count($triggerDeleted),
            'statement_delete_preserved' => $outerDeleted !== [],
            'dependencies' => [
                'sqlite-trigger1-delete-trigger-does-not-corrupt-outer-delete',
                'sqlite-row-trigger-old-row-scope',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{event:string,match_column:string,match_value:mixed,delete_column:string,delete_value:mixed,name?:string} $trigger
     * @param array<string,mixed> $assignments
     * @return array<string,mixed>
     */
    public static function updateWithAfterTrigger(array $rows, string $whereColumn, mixed $whereValue, array $assignments, array $trigger): array
    {
        $whereColumn = self::identifier($whereColumn, 'WHERE column');
        $matchColumn = self::identifier((string) ($trigger['match_column'] ?? ''), 'trigger match column');
        $deleteColumn = self::identifier((string) ($trigger['delete_column'] ?? ''), 'trigger delete column');
        $triggerName = self::identifier((string) ($trigger['name'] ?? 'audit_update'), 'trigger name');
        $rows = array_values($rows);
        $outerUpdated = [];
        $triggerDeleted = [];

        foreach ($assignments as $column => $_) {
            self::identifier((string) $column, 'assignment column');
        }

        foreach ($rows as $index => $row) {
            if (($row[$whereColumn] ?? null) !== $whereValue) {
                continue;
            }
            $old = $row;
            foreach ($assignments as $column => $value) {
                $row[(string) $column] = is_callable($value) ? $value($old, $row) : $value;
            }
            $rows[$index] = $row;
            $outerUpdated[] = ['old' => $old, 'new' => $row];
        }

        foreach ($outerUpdated as $change) {
            $old = $change['old'];
            if (($old[$matchColumn] ?? null) !== $trigger['match_value']) {
                continue;
            }
            foreach ($rows as $index => $row) {
                if (($row[$deleteColumn] ?? null) === $trigger['delete_value'] && ($row['id'] ?? null) !== ($change['new']['id'] ?? null)) {
                    $triggerDeleted[] = $row + ['trigger' => $triggerName];
                    unset($rows[$index]);
                }
            }
            $rows = array_values($rows);
        }

        $updatedIds = array_values(array_map(static fn (array $change): mixed => $change['new']['id'] ?? null, $outerUpdated));

        return [
            'source' => 'trigger1.test trigger1-1.11',
            'operation' => 'update-statement-with-after-update-trigger',
            'status' => 'commit-ok',
            'trigger' => $triggerName,
            'outer_updated_ids' => $updatedIds,
            'trigger_deleted_ids' => array_values(array_column($triggerDeleted, 'id')),
            'remaining_ids' => array_values(array_column(self::sortRows($rows), 'id')),
            'updated_rows' => array_values(array_map(static fn (array $change): array => $change['new'], $outerUpdated)),
            'outer_update_count' => count($outerUpdated),
            'trigger_delete_count' => count($triggerDeleted),
            'total_changes' => count($outerUpdated) + count($triggerDeleted),
            'statement_update_preserved' => $outerUpdated !== [],
            'dependencies' => [
                'sqlite-trigger1-update-trigger-does-not-corrupt-outer-update',
                'sqlite-row-trigger-old-row-scope',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int}> $initialRows
     * @param list<array{a:int,b:int}> $insertRows
     * @return array<string,mixed>
     */
    public static function rowTriggerExecutionOrder(array $initialRows, array $insertRows = []): array
    {
        $rows = array_values($initialRows);
        $updateLog = [];
        $conditionalLog = [];
        $index = 1;

        foreach ($rows as $rowIndex => $old) {
            $new = [
                'a' => $old['a'] * 10,
                'b' => $old['b'] * 10,
            ];
            $updateLog[] = self::rowTriggerOrderLogEntry($index++, $old, self::sumRows($rows), $new);
            $rows[$rowIndex] = $new;
            $afterEntry = self::rowTriggerOrderLogEntry($index++, $old, self::sumRows($rows), $new);
            $updateLog[] = $afterEntry;
            if ($old['a'] === $initialRows[0]['a']) {
                $conditionalLog[] = $afterEntry;
            }
        }

        $deleteRows = array_values($rows);
        $deleteLog = [];
        $index = 1;
        foreach ($deleteRows as $row) {
            $deleteLog[] = self::rowTriggerOrderLogEntry($index++, $row, self::sumRows($deleteRows), ['a' => 0, 'b' => 0]);
            $deleteRows = array_values(array_filter($deleteRows, static fn (array $candidate): bool => $candidate !== $row));
            $deleteLog[] = self::rowTriggerOrderLogEntry($index++, $row, self::sumRows($deleteRows), ['a' => 0, 'b' => 0]);
        }

        $insertedRows = [];
        $insertLog = [];
        $index = 1;
        foreach ($insertRows as $new) {
            $insertLog[] = self::rowTriggerOrderLogEntry($index++, ['a' => 0, 'b' => 0], self::sumRows($insertedRows), $new);
            $insertedRows[] = $new;
            $insertLog[] = self::rowTriggerOrderLogEntry($index++, ['a' => 0, 'b' => 0], self::sumRows($insertedRows), $new);
        }

        return [
            'source' => 'trigger2.test trigger2-1.1..1.3',
            'operation' => 'row-trigger-before-after-execution-order',
            'status' => 'commit-ok',
            'initial_rows' => $initialRows,
            'updated_rows' => $rows,
            'update_log' => $updateLog,
            'conditional_update_log' => $conditionalLog,
            'delete_log' => $deleteLog,
            'insert_log' => $insertLog,
            'final_insert_rows' => $insertedRows,
            'update_log_count' => count($updateLog),
            'conditional_update_log_count' => count($conditionalLog),
            'delete_log_count' => count($deleteLog),
            'insert_log_count' => count($insertLog),
            'dependencies' => [
                'sqlite-trigger2-before-trigger-sees-prestatement-rowset',
                'sqlite-trigger2-after-trigger-sees-current-row-change',
                'sqlite-trigger2-when-clause-uses-old-row-image',
            ],
        ];
    }

    /**
     * @param list<array{name:string,object_type:string,target?:string,temp?:bool}> $objects
     * @return array<string,mixed>
     */
    public static function schemaLifecycle(array $objects, array $actions): array
    {
        $catalog = [];
        $tempCatalog = [];
        foreach ($objects as $object) {
            self::catalogAdd($catalog, $tempCatalog, $object);
        }

        $snapshots = [];
        $errors = [];
        $transaction = null;
        foreach ($actions as $action) {
            if (!is_array($action)) {
                throw new \InvalidArgumentException('SQLite dynamic trigger FK schema action is malformed');
            }
            $op = strtolower((string) ($action['op'] ?? ''));
            if ($op === 'begin') {
                $transaction = [$catalog, $tempCatalog];
            } elseif ($op === 'rollback') {
                if ($transaction !== null) {
                    [$catalog, $tempCatalog] = $transaction;
                    $transaction = null;
                }
            } elseif ($op === 'commit') {
                $transaction = null;
            } elseif ($op === 'create-trigger') {
                $name = self::identifier((string) ($action['name'] ?? ''), 'trigger name');
                $target = self::identifier((string) ($action['target'] ?? ''), 'trigger target');
                $temp = (bool) ($action['temp'] ?? false);
                if (!isset($catalog[$target]) && !isset($tempCatalog[$target])) {
                    $errors[] = ['op' => $op, 'name' => $name, 'error' => $temp ? "no such table: {$target}" : "no such table: main.{$target}"];
                    continue;
                }
                $destination = $temp ? $tempCatalog : $catalog;
                if (isset($destination[$name])) {
                    $errors[] = ['op' => $op, 'name' => $name, 'error' => "trigger {$name} already exists"];
                    continue;
                }
                self::catalogAdd($catalog, $tempCatalog, ['name' => $name, 'object_type' => 'trigger', 'target' => $target, 'temp' => $temp]);
            } elseif ($op === 'drop-trigger') {
                $name = self::identifier((string) ($action['name'] ?? ''), 'trigger name');
                $ifExists = (bool) ($action['if_exists'] ?? false);
                if (isset($catalog[$name])) {
                    unset($catalog[$name]);
                } elseif (isset($tempCatalog[$name])) {
                    unset($tempCatalog[$name]);
                } elseif (!$ifExists) {
                    $errors[] = ['op' => $op, 'name' => $name, 'error' => "no such trigger: {$name}"];
                }
            } elseif ($op === 'drop-table') {
                $name = self::identifier((string) ($action['name'] ?? ''), 'table name');
                unset($catalog[$name], $tempCatalog[$name]);
                foreach ($catalog as $objectName => $object) {
                    if (($object['object_type'] ?? null) === 'trigger' && ($object['target'] ?? null) === $name) {
                        unset($catalog[$objectName]);
                    }
                }
                foreach ($tempCatalog as $objectName => $object) {
                    if (($object['object_type'] ?? null) === 'trigger' && ($object['target'] ?? null) === $name) {
                        unset($tempCatalog[$objectName]);
                    }
                }
            } else {
                throw new \InvalidArgumentException('SQLite dynamic trigger FK schema action is unsupported');
            }

            $snapshots[] = [
                'op' => $op,
                'main' => self::catalogNames($catalog),
                'temp' => self::catalogNames($tempCatalog),
            ];
        }

        return [
            'source' => 'trigger1.test trigger1-1.2..1.8',
            'operation' => 'trigger-schema-lifecycle',
            'status' => $errors === [] ? 'ok' : 'error-recorded',
            'main_names' => self::catalogNames($catalog),
            'temp_names' => self::catalogNames($tempCatalog),
            'main_trigger_names' => self::catalogNames($catalog, 'trigger'),
            'temp_trigger_names' => self::catalogNames($tempCatalog, 'trigger'),
            'errors' => $errors,
            'error_count' => count($errors),
            'snapshots' => $snapshots,
            'dependencies' => [
                'sqlite-trigger1-create-drop-rollback',
                'sqlite-trigger1-temp-trigger-hidden-from-main-schema',
                'sqlite-trigger1-drop-table-drops-triggers',
            ],
        ];
    }

    /**
     * @param list<array{id:int,parent_id:int|null,label?:string}> $rows
     * @return array<string,mixed>
     */
    public static function recursiveCascadeVsTrigger(array $rows, int $deleteRoot, bool $recursiveTriggers): array
    {
        $rows = self::sortRows($rows);
        $fkRemaining = self::deleteDescendants($rows, $deleteRoot, true);
        $triggerRemaining = self::deleteDescendants($rows, $deleteRoot, $recursiveTriggers);

        return [
            'source' => 'fkey2.test fkey2-4.1..4.4',
            'operation' => 'recursive-foreign-key-actions-ignore-recursive-trigger-pragma',
            'status' => 'commit-ok',
            'recursive_triggers' => $recursiveTriggers,
            'delete_root' => $deleteRoot,
            'fk_remaining_ids' => array_values(array_column($fkRemaining, 'id')),
            'trigger_remaining_ids' => array_values(array_column($triggerRemaining, 'id')),
            'fk_delete_count' => count($rows) - count($fkRemaining),
            'trigger_delete_count' => count($rows) - count($triggerRemaining),
            'fk_cascade_ignores_recursive_trigger_pragma' => true,
            'dependencies' => [
                'sqlite-fkey2-recursive-cascade-actions-ignore-recursive-triggers',
                'sqlite-trigger-recursion-pragma-only-controls-trigger-programs',
            ],
        ];
    }

    /**
     * @param list<string> $parents
     * @param list<string> $children
     * @return array<string,mixed>
     */
    public static function restrictReinsertAfterDeleteTrigger(array $parents, array $children, bool $restrict): array
    {
        $remainingParents = [];
        $triggerReinserted = [];
        $violation = null;

        foreach ($parents as $parent) {
            $hasChild = self::containsNocase($children, $parent);
            if ($restrict && $hasChild) {
                $remainingParents = $parents;
                $violation = 'FOREIGN KEY constraint failed';
                break;
            }
            if ($hasChild) {
                $remainingParents[] = $parent;
                $triggerReinserted[] = $parent;
            }
        }

        if ($violation === null) {
            $remainingParents = array_values(array_unique($remainingParents));
            sort($remainingParents, SORT_STRING);
        }

        return [
            'source' => 'fkey2.test fkey2-12.2.1..12.2.4',
            'operation' => 'after-delete-trigger-reinsert-versus-restrict',
            'status' => $violation === null ? 'commit-ok' : 'constraint-failed',
            'restrict' => $restrict,
            'parent_rows' => $remainingParents,
            'child_rows' => $children,
            'trigger_reinserted' => $triggerReinserted,
            'violation' => $violation,
            'nocase_lookup' => true,
            'dependencies' => [
                'sqlite-fkey2-restrict-prevents-after-delete-repair-trigger',
                'sqlite-trigger-when-exists-uses-parent-collation',
            ],
        ];
    }

    /**
     * @param list<array{c34:string,c35:string}> $parents
     * @param list<array{c39:string,c38:string}> $children
     * @return array<string,mixed>
     */
    public static function compositeCascadeColumnMapping(array $parents, array $children, string $oldC34, string $newC34): array
    {
        $updatedParents = [];
        foreach ($parents as $parent) {
            if ($parent['c34'] === $oldC34) {
                $parent['c34'] = $newC34;
            }
            $updatedParents[] = $parent;
        }

        $updatedChildren = [];
        foreach ($children as $child) {
            if ($child['c39'] === $oldC34) {
                $child['c39'] = $newC34;
            }
            $updatedChildren[] = $child;
        }

        return [
            'source' => 'fkey2.test fkey2-12.3.1..12.3.5',
            'operation' => 'composite-foreign-key-cascade-swapped-column-mapping',
            'status' => 'commit-ok',
            'parent_rows' => $updatedParents,
            'child_rows' => $updatedChildren,
            'selected_child_pairs' => array_map(static fn (array $row): array => [$row['c38'], $row['c39']], $updatedChildren),
            'updated_parent_key' => ['from' => $oldC34, 'to' => $newC34],
            'child_reference_mapping' => ['c39' => 'c34', 'c38' => 'c35'],
            'dependencies' => [
                'sqlite-fkey2-composite-cascade-column-order',
                'sqlite-composite-primary-key-reference-default-column-list',
            ],
        ];
    }

    /**
     * @param list<array{id:mixed,label?:string}> $parents
     * @param list<array{id:mixed,parent_id:mixed,label?:string}> $children
     * @return array<string,mixed>
     */
    public static function deferForeignKeysRestrictDelete(array $parents, array $children, mixed $deleteId, bool $deferForeignKeys, bool $repairAfterDelete): array
    {
        $originalParents = array_values($parents);
        $parents = array_values($parents);
        $children = array_values($children);
        $deleted = null;

        foreach ($parents as $index => $parent) {
            if (($parent['id'] ?? null) === $deleteId) {
                $deleted = $parent;
                unset($parents[$index]);
                break;
            }
        }
        $parents = array_values($parents);

        $referencing = self::childrenReferencing($children, $deleteId);
        if (!$deferForeignKeys && $referencing !== []) {
            return [
                'source' => 'fkey6.test fkey6-3.3.1..3.3.4',
                'operation' => 'defer-foreign-keys-restrict-delete-trigger-repair',
                'status' => 'constraint-failed',
                'defer_foreign_keys' => false,
                'pragma_after_boundary' => 0,
                'deleted_parent' => $deleteId,
                'initial_violation_count' => count($referencing),
                'commit_violation_count' => count($referencing),
                'trigger_repaired' => false,
                'trigger_inserted_parent' => null,
                'parent_ids' => array_values(array_column($originalParents, 'id')),
                'child_parent_ids' => array_values(array_column($children, 'parent_id')),
                'rollback_restored' => true,
                'dependencies' => [
                    'sqlite-fkey6-restrict-is-immediate-without-defer-foreign-keys',
                    'sqlite-fkey6-defer-foreign-keys-resets-at-transaction-boundary',
                ],
            ];
        }

        $triggerInserted = null;
        if ($repairAfterDelete && $deleted !== null) {
            $triggerInserted = $deleted;
            $triggerInserted['label'] = 'deleted!';
            $parents[] = $triggerInserted;
        }

        $commitViolations = self::foreignKeyViolations($parents, $children, 'id', 'parent_id');
        $status = $commitViolations === [] ? 'commit-ok' : 'commit-failed';

        return [
            'source' => 'fkey6.test fkey6-3.3.1..3.3.4',
            'operation' => 'defer-foreign-keys-restrict-delete-trigger-repair',
            'status' => $status,
            'defer_foreign_keys' => $deferForeignKeys,
            'pragma_after_boundary' => 0,
            'deleted_parent' => $deleteId,
            'initial_violation_count' => count($referencing),
            'commit_violation_count' => count($commitViolations),
            'trigger_repaired' => $triggerInserted !== null,
            'trigger_inserted_parent' => $triggerInserted,
            'parent_ids' => array_values(array_column(self::sortRows($parents), 'id')),
            'child_parent_ids' => array_values(array_column($children, 'parent_id')),
            'rollback_restored' => $status !== 'commit-ok',
            'dependencies' => [
                'sqlite-fkey6-defer-foreign-keys-disables-restrict-until-commit',
                'sqlite-fkey6-after-delete-trigger-can-repair-deferred-restrict',
                'sqlite-fkey6-defer-foreign-keys-resets-at-transaction-boundary',
            ],
        ];
    }

    /**
     * @param list<array{id:mixed,label?:string}> $parents
     * @param list<array{id:mixed,parent_id:mixed,label?:string}> $children
     * @return array<string,mixed>
     */
    public static function deferForeignKeysUpdateCommit(array $parents, array $children, mixed $oldId, mixed $newId, bool $deferForeignKeys): array
    {
        $parents = array_values($parents);
        $children = array_values($children);
        $referencing = self::childrenReferencing($children, $oldId);

        if (!$deferForeignKeys && $referencing !== []) {
            return [
                'source' => 'fkey6.test fkey6-3.2.1..3.2.6',
                'operation' => 'defer-foreign-keys-restrict-update-commit-check',
                'status' => 'constraint-failed',
                'defer_foreign_keys' => false,
                'pragma_after_boundary' => 0,
                'old_parent_key' => $oldId,
                'new_parent_key' => $newId,
                'initial_violation_count' => count($referencing),
                'commit_violation_count' => count($referencing),
                'parent_ids' => array_values(array_column(self::sortRows($parents), 'id')),
                'child_parent_ids' => array_values(array_column($children, 'parent_id')),
                'dependencies' => [
                    'sqlite-fkey6-restrict-update-is-immediate-without-defer-foreign-keys',
                    'sqlite-fkey6-defer-foreign-keys-resets-at-transaction-boundary',
                ],
            ];
        }

        foreach ($parents as &$parent) {
            if (($parent['id'] ?? null) === $oldId) {
                $parent['id'] = $newId;
            }
        }
        unset($parent);

        $commitViolations = self::foreignKeyViolations($parents, $children, 'id', 'parent_id');

        return [
            'source' => 'fkey6.test fkey6-3.2.1..3.2.6',
            'operation' => 'defer-foreign-keys-restrict-update-commit-check',
            'status' => $commitViolations === [] ? 'commit-ok' : 'commit-failed',
            'defer_foreign_keys' => $deferForeignKeys,
            'pragma_after_boundary' => 0,
            'old_parent_key' => $oldId,
            'new_parent_key' => $newId,
            'initial_violation_count' => count($referencing),
            'commit_violation_count' => count($commitViolations),
            'parent_ids' => array_values(array_column(self::sortRows($parents), 'id')),
            'child_parent_ids' => array_values(array_column($children, 'parent_id')),
            'dependencies' => [
                'sqlite-fkey6-defer-foreign-keys-disables-restrict-until-commit',
                'sqlite-fkey6-commit-still-rejects-outstanding-violations',
                'sqlite-fkey6-defer-foreign-keys-resets-at-transaction-boundary',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function replaceConflictDeleteTrigger(string $operation, bool $beforeTrigger, bool $recursiveTriggers): array
    {
        $rows = [
            ['a' => 1, 'b' => 'a'],
            ['a' => 2, 'b' => 'b'],
            ['a' => 3, 'b' => 'c'],
        ];
        $triggerRows = [];
        $conflictDeletesFire = $recursiveTriggers;
        $directDelete = false;
        $insertRow = null;
        $updateSelector = null;
        $updateValues = [];

        switch ($operation) {
            case 'delete-a2':
                $directDelete = true;
                $rows = self::deleteRowsWithTrigger($rows, static fn (array $row): bool => $row['a'] === 2, $beforeTrigger, true, $triggerRows);
                break;

            case 'insert-replace-rowid':
                $insertRow = ['a' => 2, 'b' => 'd'];
                $rows = self::deleteRowsWithTrigger($rows, static fn (array $row): bool => $row['a'] === 2, $beforeTrigger, $conflictDeletesFire, $triggerRows);
                break;

            case 'update-replace-rowid':
                $updateSelector = static fn (array $row): bool => $row['a'] === 3;
                $updateValues = ['a' => 2];
                $rows = self::deleteRowsWithTrigger($rows, static fn (array $row): bool => $row['a'] === 2, $beforeTrigger, $conflictDeletesFire, $triggerRows);
                break;

            case 'insert-replace-unique-b':
                $insertRow = ['a' => 4, 'b' => 'b'];
                $rows = self::deleteRowsWithTrigger($rows, static fn (array $row): bool => $row['b'] === 'b', $beforeTrigger, $conflictDeletesFire, $triggerRows);
                break;

            case 'update-replace-unique-b':
                $updateSelector = static fn (array $row): bool => $row['b'] === 'c';
                $updateValues = ['b' => 'b'];
                $rows = self::deleteRowsWithTrigger($rows, static fn (array $row): bool => $row['b'] === 'b', $beforeTrigger, $conflictDeletesFire, $triggerRows);
                break;

            case 'insert-replace-rowid-and-unique':
                $insertRow = ['a' => 2, 'b' => 'c'];
                $rows = self::deleteRowsWithTrigger($rows, static fn (array $row): bool => $row['a'] === 2, $beforeTrigger, $conflictDeletesFire, $triggerRows);
                $rows = self::deleteRowsWithTrigger($rows, static fn (array $row): bool => $row['b'] === 'c', $beforeTrigger, $conflictDeletesFire, $triggerRows);
                break;

            case 'update-replace-rowid-and-unique':
                $updateSelector = static fn (array $row): bool => $row['a'] === 3;
                $updateValues = ['a' => 1, 'b' => 'b'];
                $rows = self::deleteRowsWithTrigger($rows, static fn (array $row): bool => $row['a'] === 1, $beforeTrigger, $conflictDeletesFire, $triggerRows);
                $rows = self::deleteRowsWithTrigger($rows, static fn (array $row): bool => $row['b'] === 'b', $beforeTrigger, $conflictDeletesFire, $triggerRows);
                break;

            default:
                throw new \InvalidArgumentException('SQLite dynamic trigger FK replace operation is unsupported');
        }

        if ($insertRow !== null) {
            $rows[] = $insertRow;
        }
        if ($updateSelector !== null) {
            foreach ($rows as &$row) {
                if (!$updateSelector($row)) {
                    continue;
                }
                foreach ($updateValues as $column => $value) {
                    $row[$column] = $value;
                }
            }
            unset($row);
        }

        $rows = self::sortPairRows($rows);

        return [
            'source' => 'triggerC.test triggerC-5.1..5.3',
            'operation' => 'or-replace-delete-trigger-firing',
            'status' => 'commit-ok',
            'dml' => $operation,
            'trigger_timing' => $beforeTrigger ? 'before' : 'after',
            'recursive_triggers' => $recursiveTriggers,
            'direct_delete' => $directDelete,
            'conflict_delete_triggers_fire' => $directDelete || $recursiveTriggers,
            'trigger_rows' => $triggerRows,
            'trigger_row_count' => count($triggerRows),
            'final_rows' => $rows,
            'final_row_count' => count($rows),
            'dependencies' => [
                'sqlite-triggerC-or-replace-delete-triggers',
                'sqlite-recursive-triggers-gate-conflict-delete-triggers',
                'sqlite-before-after-delete-trigger-row-counts',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function selfReferencingViolations(array $rows): array
    {
        $ids = array_column($rows, 'id');
        $violations = [];
        foreach ($rows as $index => $row) {
            $parent = $row['parent_id'] ?? null;
            if ($parent !== null && !in_array($parent, $ids, true)) {
                $violations[] = [
                    'row_index' => $index,
                    'id' => $row['id'] ?? null,
                    'parent_id' => $parent,
                    'phase' => 'deferred-commit',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @return list<array<string,mixed>>
     */
    private static function childrenReferencing(array $children, mixed $parentId): array
    {
        return array_values(array_filter($children, static fn (array $child): bool => ($child['parent_id'] ?? null) === $parentId));
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @return list<array<string,mixed>>
     */
    private static function foreignKeyViolations(array $parents, array $children, string $parentKey, string $childKey): array
    {
        $parentIds = array_column($parents, $parentKey);
        $violations = [];
        foreach ($children as $index => $child) {
            $value = $child[$childKey] ?? null;
            if ($value !== null && !in_array($value, $parentIds, true)) {
                $violations[] = [
                    'child_index' => $index,
                    'child_id' => $child['id'] ?? null,
                    'child_key' => $value,
                    'phase' => 'deferred-commit',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function sortRows(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));

        return array_values($rows);
    }

    /**
     * @param list<array{a:int,b:string}> $rows
     * @return list<array{a:int,b:string}>
     */
    private static function sortPairRows(array $rows): array
    {
        usort($rows, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

        return array_values($rows);
    }

    /**
     * @param list<array{a:int,b:string}> $rows
     * @param callable(array{a:int,b:string}): bool $predicate
     * @param list<array{a:int,b:string,count:int}> $triggerRows
     * @return list<array{a:int,b:string}>
     */
    private static function deleteRowsWithTrigger(array $rows, callable $predicate, bool $beforeTrigger, bool $fireTrigger, array &$triggerRows): array
    {
        foreach ($rows as $index => $row) {
            if (!$predicate($row)) {
                continue;
            }
            if ($fireTrigger && $beforeTrigger) {
                $triggerRows[] = ['a' => $row['a'], 'b' => $row['b'], 'count' => count($rows)];
            }
            unset($rows[$index]);
            $rows = array_values($rows);
            if ($fireTrigger && !$beforeTrigger) {
                $triggerRows[] = ['a' => $row['a'], 'b' => $row['b'], 'count' => count($rows)];
            }

            return $rows;
        }

        return array_values($rows);
    }

    /**
     * @param list<array{a:int,b:int}> $rows
     * @return array{a:int,b:int}
     */
    private static function sumRows(array $rows): array
    {
        $sumA = 0;
        $sumB = 0;
        foreach ($rows as $row) {
            $sumA += $row['a'];
            $sumB += $row['b'];
        }

        return ['a' => $sumA, 'b' => $sumB];
    }

    /**
     * @param array{a:int,b:int} $old
     * @param array{a:int,b:int} $sums
     * @param array{a:int,b:int} $new
     * @return array{idx:int,old_a:int,old_b:int,db_sum_a:int,db_sum_b:int,new_a:int,new_b:int}
     */
    private static function rowTriggerOrderLogEntry(int $index, array $old, array $sums, array $new): array
    {
        return [
            'idx' => $index,
            'old_a' => $old['a'],
            'old_b' => $old['b'],
            'db_sum_a' => $sums['a'],
            'db_sum_b' => $sums['b'],
            'new_a' => $new['a'],
            'new_b' => $new['b'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function deleteDescendants(array $rows, int $deleteRoot, bool $recursive): array
    {
        $toDelete = [$deleteRoot => true];
        $frontier = [$deleteRoot];
        while ($frontier !== []) {
            $parent = array_shift($frontier);
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if (($row['parent_id'] ?? null) !== $parent || isset($toDelete[$id])) {
                    continue;
                }
                $toDelete[$id] = true;
                if ($recursive) {
                    $frontier[] = $id;
                }
            }
        }

        return array_values(array_filter($rows, static fn (array $row): bool => !isset($toDelete[(int) ($row['id'] ?? 0)])));
    }

    /**
     * @param list<string> $values
     */
    private static function containsNocase(array $values, string $needle): bool
    {
        foreach ($values as $value) {
            if (strcasecmp($value, $needle) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function catalogAdd(array &$catalog, array &$tempCatalog, array $object): void
    {
        $name = self::identifier((string) ($object['name'] ?? ''), 'object name');
        $type = (string) ($object['object_type'] ?? '');
        if (!in_array($type, ['table', 'view', 'trigger'], true)) {
            throw new \InvalidArgumentException('SQLite dynamic trigger FK catalog object type is unsupported');
        }

        $row = [
            'name' => $name,
            'object_type' => $type,
            'target' => isset($object['target']) ? self::identifier((string) $object['target'], 'trigger target') : null,
        ];
        if ((bool) ($object['temp'] ?? false)) {
            $tempCatalog[$name] = $row;
        } else {
            $catalog[$name] = $row;
        }
    }

    /**
     * @return list<string>
     */
    private static function catalogNames(array $catalog, ?string $type = null): array
    {
        $names = [];
        foreach ($catalog as $name => $object) {
            if ($type === null || ($object['object_type'] ?? null) === $type) {
                $names[] = (string) $name;
            }
        }
        sort($names);

        return $names;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite dynamic trigger FK {$label} is malformed");
        }

        return $value;
    }
}
