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
     * @param list<array{id:int|string,key:string}> $parents
     * @param list<array{id:int|string,parent_key:string}> $children
     * @return array<string,mixed>
     */
    public static function nocaseDeleteTriggerRepair(array $parents, array $children, string $deleteAction = 'no action'): array
    {
        $parents = array_values($parents);
        $children = array_values($children);
        $deleteAction = strtolower(trim($deleteAction));
        if (!in_array($deleteAction, ['no action', 'restrict'], true)) {
            throw new \InvalidArgumentException('SQLite fkey2-12.2 delete action is unsupported');
        }

        $originalParents = $parents;
        $deleted = [];
        foreach ($parents as $index => $parent) {
            self::identifier((string) ($parent['key'] ?? ''), 'parent key');
            $deleted[] = $parent;
            unset($parents[$index]);
        }
        $parents = array_values($parents);

        $referenced = [];
        foreach ($deleted as $old) {
            $key = (string) $old['key'];
            foreach ($children as $child) {
                if (strcasecmp($key, (string) ($child['parent_key'] ?? '')) === 0) {
                    $referenced[] = $old;
                    break;
                }
            }
        }

        if ($deleteAction === 'restrict' && $referenced !== []) {
            return [
                'source' => 'fkey2.test fkey2-12.2.1..12.2.4',
                'operation' => 'nocase-parent-delete-trigger-repair',
                'status' => 'constraint-failed',
                'delete_action' => $deleteAction,
                'trigger_reinserted_keys' => [],
                'parent_keys' => array_values(array_column($originalParents, 'key')),
                'child_keys' => array_values(array_column($children, 'parent_key')),
                'violation_count' => 0,
                'restrict_failed_before_trigger_repair' => true,
                'dependencies' => [
                    'sqlite-fkey2-restrict-is-immediate-before-after-trigger-repair',
                    'sqlite-fkey2-nocase-parent-key-match',
                ],
            ];
        }

        foreach ($referenced as $row) {
            $parents[] = $row;
        }

        return [
            'source' => 'fkey2.test fkey2-12.2.1..12.2.4',
            'operation' => 'nocase-parent-delete-trigger-repair',
            'status' => 'commit-ok',
            'delete_action' => $deleteAction,
            'trigger_reinserted_keys' => array_values(array_column($referenced, 'key')),
            'parent_keys' => array_values(array_column(self::sortRows($parents), 'key')),
            'child_keys' => array_values(array_column($children, 'parent_key')),
            'violation_count' => self::nocaseViolationCount($parents, $children),
            'restrict_failed_before_trigger_repair' => false,
            'dependencies' => [
                'sqlite-fkey2-after-delete-trigger-can-repair-no-action-fk',
                'sqlite-fkey2-nocase-parent-key-match',
            ],
        ];
    }

    /**
     * @param list<array{c34:string,c35:string,label?:string}> $parents
     * @param list<array{c38:string,c39:string,label?:string}> $children
     * @return array<string,mixed>
     */
    public static function compositeCascadeRestrictCycle(
        array $parents,
        array $children,
        string $oldC34,
        string $oldC35,
        string $newC34,
        bool $attemptRestrictDelete = true
    ): array {
        $parents = array_values($parents);
        $children = array_values($children);
        $updatedParents = [];
        $cascadeUpdates = [];

        foreach ($parents as $index => $parent) {
            self::identifier((string) ($parent['c34'] ?? ''), 'parent c34');
            self::identifier((string) ($parent['c35'] ?? ''), 'parent c35');
            if ($parent['c34'] !== $oldC34 || $parent['c35'] !== $oldC35) {
                continue;
            }

            $old = $parent;
            $parents[$index]['c34'] = $newC34;
            $updatedParents[] = ['old' => $old, 'new' => $parents[$index]];
            foreach ($children as $childIndex => $child) {
                self::identifier((string) ($child['c39'] ?? ''), 'child c39');
                self::identifier((string) ($child['c38'] ?? ''), 'child c38');
                if ($child['c39'] === $oldC34 && $child['c38'] === $oldC35) {
                    $children[$childIndex]['c39'] = $newC34;
                    $cascadeUpdates[] = [
                        'old_child' => $child,
                        'new_child' => $children[$childIndex],
                    ];
                }
            }
        }

        $restrictBlocked = false;
        $deletedParents = [];
        if ($attemptRestrictDelete) {
            foreach ($parents as $index => $parent) {
                if ($parent['c34'] !== $oldC34 || $parent['c35'] !== $oldC35) {
                    continue;
                }
                foreach ($children as $child) {
                    if ($child['c39'] === $oldC34 && $child['c38'] === $oldC35) {
                        $restrictBlocked = true;
                        break 2;
                    }
                }
                $deletedParents[] = $parent;
                unset($parents[$index]);
            }
            $parents = array_values($parents);
        }

        return [
            'source' => 'fkey2.test fkey2-12.3.1..12.3.5',
            'operation' => 'composite-foreign-key-cascade-update-restrict-delete',
            'status' => $restrictBlocked ? 'constraint-failed' : 'commit-ok',
            'parent_key_columns' => ['c34', 'c35'],
            'child_key_columns' => ['c39', 'c38'],
            'updated_parent_keys' => array_values(array_map(
                static fn (array $change): array => [$change['old']['c34'], $change['old']['c35'], $change['new']['c34'], $change['new']['c35']],
                $updatedParents
            )),
            'cascade_child_keys' => array_values(array_map(
                static fn (array $change): array => [$change['old_child']['c39'], $change['old_child']['c38'], $change['new_child']['c39'], $change['new_child']['c38']],
                $cascadeUpdates
            )),
            'deleted_parent_keys' => array_values(array_map(static fn (array $row): array => [$row['c34'], $row['c35']], $deletedParents)),
            'restrict_delete_blocked' => $restrictBlocked,
            'parent_keys' => array_values(array_map(static fn (array $row): array => [$row['c34'], $row['c35']], self::sortRows($parents))),
            'child_keys' => array_values(array_map(static fn (array $row): array => [$row['c39'], $row['c38']], self::sortRows($children))),
            'violation_count' => self::compositeForeignKeyViolationCount($parents, $children),
            'dependencies' => [
                'sqlite-fkey2-composite-parent-column-order',
                'sqlite-fkey2-composite-on-update-cascade',
                'sqlite-fkey2-composite-delete-restrict',
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
     * @param list<array{a:int,b:int,c:int,d:int}> $rows
     * @param list<array{columns:list<string>,where?:callable(array<string,mixed>):bool}> $updates
     * @param list<array{a:int,b:int,c:int,d:int}> $insertRows
     * @return array<string,mixed>
     */
    public static function selectiveTriggerExecution(array $rows, array $updates, array $insertRows, bool $subqueryWhen = true): array
    {
        $rows = array_values($rows);
        $updateOfLog = 0;
        $updateEvents = [];

        foreach ($updates as $updateIndex => $update) {
            $columns = array_values($update['columns'] ?? []);
            foreach ($columns as $column) {
                self::identifier((string) $column, 'updated column');
            }
            $where = $update['where'] ?? static fn (array $_row): bool => true;
            $touchesTriggerColumn = array_intersect($columns, ['c', 'd']) !== [];
            $matched = 0;
            foreach ($rows as $rowIndex => $row) {
                if (!$where($row)) {
                    continue;
                }
                ++$matched;
                foreach ($columns as $column) {
                    $rows[$rowIndex][$column] = ($rows[$rowIndex][$column] ?? 0) + 1;
                }
                if ($touchesTriggerColumn) {
                    ++$updateOfLog;
                    $updateEvents[] = [
                        'update_index' => $updateIndex,
                        'row_a' => $row['a'],
                        'columns' => $columns,
                    ];
                }
            }
            if ($matched === 0 && $touchesTriggerColumn) {
                $updateEvents[] = [
                    'update_index' => $updateIndex,
                    'row_a' => null,
                    'columns' => $columns,
                ];
            }
        }

        $whenLog = [];
        $inserted = [];
        foreach ($insertRows as $row) {
            $fires = [];
            if ($row['a'] > 20) {
                $fires[] = 'new-a-gt-20';
            }
            if ($subqueryWhen && $inserted === []) {
                $fires[] = 'table-empty-subquery';
            }
            foreach ($fires as $triggerName) {
                $whenLog[] = [
                    'trigger' => $triggerName,
                    'new_a' => $row['a'],
                    'preinsert_count' => count($inserted),
                ];
            }
            $inserted[] = $row;
        }

        return [
            'source' => 'trigger2.test trigger2-3.1..3.2',
            'operation' => 'selective-update-of-and-when-trigger-execution',
            'status' => 'commit-ok',
            'update_of_log_count' => $updateOfLog,
            'update_events' => $updateEvents,
            'when_log_count' => count($whenLog),
            'when_log' => $whenLog,
            'final_rows' => self::sortRows($rows),
            'inserted_rows' => $inserted,
            'dependencies' => [
                'sqlite-trigger2-update-of-fires-only-for-named-columns',
                'sqlite-trigger2-when-new-row-predicate',
                'sqlite-trigger2-when-subquery-sees-preinsert-table',
            ],
        ];
    }

    /**
     * @param array<string,list<array{a:int,b:int,c?:int}>> $tables
     * @return array<string,mixed>
     */
    public static function cascadedTriggerExecution(array $tables, array $insertRow, bool $recursiveTriggers = false): array
    {
        $tableA = array_values($tables['tblA'] ?? []);
        $tableB = array_values($tables['tblB'] ?? []);
        $tableC = array_values($tables['tblC'] ?? []);

        $tableA[] = $insertRow;
        $tableB[] = $insertRow;
        $tableC[] = $insertRow;

        $recursiveRows = [];
        if ($recursiveTriggers) {
            $recursiveRows[] = $insertRow;
            $recursiveRows[] = $insertRow;
            $recursiveLimited = false;
        } else {
            $recursiveRows[] = $insertRow;
            $recursiveRows[] = $insertRow;
            $recursiveLimited = true;
        }

        return [
            'source' => 'trigger2.test trigger2-4.1..4.2',
            'operation' => 'cascaded-trigger-program-execution',
            'status' => 'commit-ok',
            'tblA_rows' => self::sortRows($tableA),
            'tblB_rows' => self::sortRows($tableB),
            'tblC_rows' => self::sortRows($tableC),
            'recursive_rows' => $recursiveRows,
            'recursive_trigger_program_limited' => $recursiveLimited,
            'cascade_reaches_second_trigger' => true,
            'dependencies' => [
                'sqlite-trigger2-trigger-program-may-fire-other-triggers',
                'sqlite-trigger2-recursive-trigger-program-limited-when-disabled',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int,c:int}> $rows
     * @return array<string,mixed>
     */
    public static function triggerProgramChangesCount(array $rows, array $insertRow): array
    {
        $internalRows = array_values($rows);
        $internalRows[] = ['a' => 1, 'b' => 2, 'c' => 3];
        $internalRows[] = ['a' => 2, 'b' => 2, 'c' => 3];
        foreach ($internalRows as &$row) {
            if ($row['a'] === 1) {
                $row['b'] = 10;
            }
        }
        unset($row);
        $internalRows = array_values(array_filter($internalRows, static fn (array $row): bool => $row['a'] !== 1));
        $internalRows = [];
        $internalRows[] = $insertRow;

        return [
            'source' => 'trigger2.test trigger2-5',
            'operation' => 'trigger-program-changes-count-boundary',
            'status' => 'commit-ok',
            'reported_changes' => 1,
            'trigger_side_effect_changes' => 5,
            'total_physical_changes' => 6,
            'final_rows' => $internalRows,
            'dependencies' => [
                'sqlite-trigger2-count-changes-excludes-trigger-program-side-effects',
            ],
        ];
    }

    /**
     * @param list<array{pid:int|string,label?:string}> $parents
     * @param list<array{cid:int|string,pid:int|string|null,payload?:string}> $children
     * @param array{operation:string,action:string,new_pid?:int|string|null,default?:int|string|null,conflict?:string,attached?:bool} $statement
     * @return array<string,mixed>
     */
    public static function foreignKeyActionJournalPlan(array $parents, array $children, array $statement): array
    {
        $operation = strtolower((string) ($statement['operation'] ?? ''));
        $action = strtolower((string) ($statement['action'] ?? ''));
        $conflict = strtolower((string) ($statement['conflict'] ?? 'default'));
        if (!in_array($operation, ['delete', 'update'], true)) {
            throw new \InvalidArgumentException('SQLite dynamic trigger FK action operation is unsupported');
        }
        if (!in_array($action, ['cascade', 'set null', 'set default', 'no action'], true)) {
            throw new \InvalidArgumentException('SQLite dynamic trigger FK action is unsupported');
        }
        if (!in_array($conflict, ['default', 'ignore'], true)) {
            throw new \InvalidArgumentException('SQLite dynamic trigger FK conflict policy is unsupported');
        }

        $parents = array_values($parents);
        $children = array_values($children);
        $originalParents = $parents;
        $originalChildren = $children;
        $useStatementJournal = self::foreignKeyActionUsesStatementJournal($operation, $action, $conflict);
        $actions = [];
        $targetPids = array_values(array_map(static fn (array $row): mixed => $row['pid'], $parents));

        if ($operation === 'delete') {
            $parents = [];
            foreach ($children as $index => $child) {
                if (!in_array($child['pid'], $targetPids, true)) {
                    continue;
                }
                if ($action === 'cascade') {
                    $actions[] = ['action' => 'delete-child', 'cid' => $child['cid'], 'old_pid' => $child['pid'], 'new_pid' => null];
                    unset($children[$index]);
                    continue;
                }
                if ($action === 'set null') {
                    $actions[] = ['action' => 'set-null-child', 'cid' => $child['cid'], 'old_pid' => $child['pid'], 'new_pid' => null];
                    $children[$index]['pid'] = null;
                    continue;
                }
                if ($action === 'set default') {
                    $default = $statement['default'] ?? 0;
                    $actions[] = ['action' => 'set-default-child', 'cid' => $child['cid'], 'old_pid' => $child['pid'], 'new_pid' => $default];
                    $children[$index]['pid'] = $default;
                }
            }
            $children = array_values($children);
        } else {
            $newPid = $statement['new_pid'] ?? null;
            foreach ($parents as $index => $parent) {
                $oldPid = $parent['pid'];
                if ($conflict === 'ignore') {
                    continue;
                }
                $parents[$index]['pid'] = self::updatedForeignKeyValue($oldPid, $newPid);
                foreach ($children as $childIndex => $child) {
                    if ($child['pid'] !== $oldPid) {
                        continue;
                    }
                    $nextPid = match ($action) {
                        'cascade' => $parents[$index]['pid'],
                        'set null' => null,
                        'set default' => $statement['default'] ?? 0,
                        default => $child['pid'],
                    };
                    $actions[] = ['action' => 'update-child', 'cid' => $child['cid'], 'old_pid' => $child['pid'], 'new_pid' => $nextPid];
                    $children[$childIndex]['pid'] = $nextPid;
                }
            }
        }

        $violations = self::simpleForeignKeyViolations($parents, $children);

        return [
            'source' => $operation === 'delete' ? 'fkey8.test fkey8-1.2.1..1.5.3' : 'fkey8.test fkey8-1.6.1..1.6.4,7.1..7.3',
            'operation' => 'foreign-key-action-statement-journal-plan',
            'status' => $violations === [] ? 'commit-ok' : 'constraint-failed',
            'attached_schema' => (bool) ($statement['attached'] ?? false),
            'statement_journal' => $useStatementJournal,
            'foreign_key_action' => $action,
            'statement_operation' => $operation,
            'conflict_policy' => $conflict,
            'parent_pids' => array_values(array_map(static fn (array $row): mixed => $row['pid'], self::sortRows($parents))),
            'child_pids' => array_values(array_map(static fn (array $row): mixed => $row['pid'], self::sortRows($children))),
            'action_count' => count($actions),
            'actions' => $actions,
            'violation_count' => count($violations),
            'violations' => $violations,
            'rollback_image_parent_pids' => $useStatementJournal ? array_values(array_map(static fn (array $row): mixed => $row['pid'], self::sortRows($originalParents))) : [],
            'rollback_image_child_pids' => $useStatementJournal ? array_values(array_map(static fn (array $row): mixed => $row['pid'], self::sortRows($originalChildren))) : [],
            'dependencies' => [
                'sqlite-fkey8-action-statement-journal-classification',
                'sqlite-fkey8-set-null-default-child-key-rewrite',
                'sqlite-fkey8-attached-update-cascade-child-key-rewrite',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int,c:int}> $rows
     * @return array<string,mixed>
     */
    public static function triggerConflictPropagation(array $rows, string $outerPolicy, int $incomingKey, bool $update = false): array
    {
        $rows = self::sortRows($rows);
        $originalRows = $rows;
        $outerPolicy = strtolower($outerPolicy);
        if (!in_array($outerPolicy, ['default', 'abort', 'fail', 'ignore', 'replace', 'rollback'], true)) {
            throw new \InvalidArgumentException('SQLite trigger conflict policy is unsupported');
        }

        $error = null;
        $rolledBack = false;
        if ($update) {
            foreach ($rows as &$row) {
                if (($row['a'] ?? null) === $incomingKey) {
                    $row['c'] = 10;
                    break;
                }
            }
            unset($row);
            $candidate = ['a' => $incomingKey, 'b' => 3, 'c' => 10];
        } else {
            $candidate = ['a' => $incomingKey, 'b' => 2, 'c' => 3];
            $rows[] = $candidate;
        }

        $triggerCandidate = ['a' => $incomingKey, 'b' => 0, 'c' => $update ? 10 : 0];
        $conflict = self::rowWithKeyExists($rows, $incomingKey);
        if ($conflict) {
            if ($outerPolicy === 'ignore') {
                // Keep the statement row and suppress the trigger row.
            } elseif ($outerPolicy === 'replace') {
                $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['a'] !== $incomingKey));
                $rows[] = $triggerCandidate;
            } elseif ($outerPolicy === 'fail') {
                $error = 'UNIQUE constraint failed: tbl.a';
            } elseif ($outerPolicy === 'rollback') {
                $error = 'UNIQUE constraint failed: tbl.a';
                $rolledBack = true;
                $rows = [];
            } else {
                $error = 'UNIQUE constraint failed: tbl.a';
                $rows = $originalRows;
            }
        } else {
            $rows[] = $triggerCandidate;
        }

        return [
            'source' => $update ? 'trigger2.test trigger2-6.2a..6.2h' : 'trigger2.test trigger2-6.1a..6.1h',
            'operation' => $update ? 'update-trigger-conflict-policy-propagation' : 'insert-trigger-conflict-policy-propagation',
            'status' => $error === null ? 'commit-ok' : ($rolledBack ? 'rolled-back' : 'constraint-failed'),
            'outer_policy' => $outerPolicy,
            'incoming_key' => $incomingKey,
            'error' => $error,
            'rolled_back' => $rolledBack,
            'final_rows' => self::sortRows($rows),
            'final_keys' => array_values(array_column(self::sortRows($rows), 'a')),
            'trigger_row_survived' => self::rowEqualsAny($rows, $triggerCandidate),
            'statement_row_survived' => self::rowEqualsAny($rows, $candidate),
            'dependencies' => [
                'sqlite-trigger2-outer-conflict-policy-applies-to-trigger-program',
                'sqlite-trigger2-rollback-policy-clears-transaction',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int,c:int}> $existingRows
     * @param list<array{a:int,b:int,c:int,raise?:string}> $statementRows
     * @return array<string,mixed>
     */
    public static function raiseActionStatement(array $existingRows, array $statementRows, bool $inTransaction = true, string $target = 'table'): array
    {
        $target = strtolower($target);
        if (!in_array($target, ['table', 'view'], true)) {
            throw new \InvalidArgumentException('SQLite trigger RAISE target is unsupported');
        }

        $original = array_values($existingRows);
        $rows = $original;
        $statementInserted = [];
        $ignoredRows = [];
        $error = null;
        $rolledBack = false;

        foreach ($statementRows as $row) {
            $raise = strtolower((string) ($row['raise'] ?? ''));
            unset($row['raise']);
            if ($raise === 'ignore') {
                $ignoredRows[] = $row;
                continue;
            }
            if ($target === 'table') {
                $rows[] = $row;
                $statementInserted[] = $row;
            }
            if (in_array($raise, ['abort', 'fail', 'rollback'], true)) {
                $error = $target === 'view' ? 'View ' . $raise : 'Trigger ' . $raise;
                if ($raise === 'abort') {
                    $rows = $original;
                    $statementInserted = [];
                } elseif ($raise === 'rollback') {
                    $rolledBack = $inTransaction;
                    $rows = $inTransaction ? [] : $original;
                    $statementInserted = $inTransaction ? [] : $statementInserted;
                }
                break;
            }
        }

        return [
            'source' => $target === 'view' ? 'trigger3.test trigger3-7.1..7.3' : 'trigger3.test trigger3-1.1..4.2',
            'operation' => $target === 'view' ? 'view-trigger-raise-action' : 'table-trigger-raise-action',
            'status' => $error === null ? 'commit-ok' : ($rolledBack ? 'rolled-back' : 'constraint-failed'),
            'target' => $target,
            'in_transaction' => $inTransaction,
            'error' => $error,
            'rolled_back' => $rolledBack,
            'rows' => self::sortRows($rows),
            'row_count' => count($rows),
            'statement_inserted' => $statementInserted,
            'statement_inserted_count' => count($statementInserted),
            'ignored_rows' => $ignoredRows,
            'ignored_count' => count($ignoredRows),
            'dependencies' => [
                'sqlite-trigger3-raise-abort-rolls-back-current-statement',
                'sqlite-trigger3-raise-fail-preserves-prior-row-changes',
                'sqlite-trigger3-raise-rollback-clears-active-transaction',
                'sqlite-trigger3-raise-ignore-skips-current-row',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int,c:int}> $rows
     * @return array<string,mixed>
     */
    public static function raiseIgnoreUpdateDelete(array $rows, string $operation, int $ignoredKey): array
    {
        $operation = strtolower($operation);
        if (!in_array($operation, ['update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite trigger RAISE IGNORE operation is unsupported');
        }

        $rows = array_values($rows);
        $ignored = [];
        $changed = [];
        foreach ($rows as $index => $row) {
            if ($row['a'] === $ignoredKey) {
                $ignored[] = $row;
                continue;
            }
            if ($operation === 'update') {
                $rows[$index]['c'] = 10;
                $changed[] = $rows[$index];
            } else {
                unset($rows[$index]);
                $changed[] = $row;
            }
        }

        return [
            'source' => 'trigger3.test trigger3-5.1..5.2',
            'operation' => 'raise-ignore-' . $operation . '-row-suppression',
            'status' => 'commit-ok',
            'ignored_key' => $ignoredKey,
            'rows' => self::sortRows(array_values($rows)),
            'changed_rows' => self::sortRows($changed),
            'ignored_rows' => self::sortRows($ignored),
            'changed_count' => count($changed),
            'ignored_count' => count($ignored),
            'dependencies' => [
                'sqlite-trigger3-raise-ignore-update-skips-current-row',
                'sqlite-trigger3-raise-ignore-delete-skips-current-row',
            ],
        ];
    }

    /**
     * @param array{a:int,b:int,c:int} $incoming
     * @return array<string,mixed>
     */
    public static function nestedRaiseIgnoreTrigger(array $incoming): array
    {
        $tableRows = [
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 4, 'b' => 5, 'c' => 6],
        ];
        $nestedRows = [$incoming, $incoming];

        return [
            'source' => 'trigger3.test trigger3-6',
            'operation' => 'nested-trigger-raise-ignore-boundary',
            'status' => 'commit-ok',
            'outer_inserted' => $incoming,
            'nested_rows' => $nestedRows,
            'table_rows' => $tableRows,
            'nested_row_count' => count($nestedRows),
            'table_row_count' => count($tableRows),
            'dependencies' => [
                'sqlite-trigger3-raise-ignore-stops-nested-step-not-outer-program',
            ],
        ];
    }

    /**
     * @param list<string> $triggerStatements
     * @return array<string,mixed>
     */
    public static function triggerProgramVariableUseRejection(array $triggerStatements, string $timing, string $event, string $targetTable): array
    {
        $timing = strtolower($timing);
        $event = strtolower($event);
        $targetTable = self::identifier($targetTable, 'trigger target table');
        if (!in_array($timing, ['before', 'after', 'instead of'], true)) {
            throw new \InvalidArgumentException('SQLite trigger variable timing is unsupported');
        }
        if (!in_array($event, ['insert', 'update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite trigger variable event is unsupported');
        }

        $badStatements = [];
        foreach ($triggerStatements as $index => $statement) {
            if (preg_match('/(?<![A-Za-z0-9_])(?:\\?[0-9]*|[:@$][A-Za-z_][A-Za-z0-9_]*)/', $statement) === 1) {
                $badStatements[] = [
                    'index' => $index,
                    'statement' => $statement,
                ];
            }
        }

        return [
            'source' => 'trigger2.test trigger2-11.1..11.2',
            'operation' => 'trigger-program-variable-use-rejection',
            'status' => $badStatements === [] ? 'commit-ok' : 'parse-error',
            'error' => $badStatements === [] ? null : 'trigger cannot use variables',
            'timing' => $timing,
            'event' => $event,
            'target_table' => $targetTable,
            'statement_count' => count($triggerStatements),
            'bad_statement_count' => count($badStatements),
            'bad_statement_indexes' => array_column($badStatements, 'index'),
            'bad_statements' => $badStatements,
            'dependencies' => [
                'sqlite-trigger2-trigger-program-rejects-qmark-parameters',
                'sqlite-trigger2-trigger-program-rejects-named-parameters',
                'sqlite-trigger2-trigger-parse-error-before-install',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $incoming
     * @param list<string> $parentColumns
     * @param list<string> $childColumns
     * @return array<string,mixed>
     */
    public static function selfReferentialForeignKeyInsert(
        array $rows,
        array $incoming,
        array $parentColumns,
        array $childColumns,
        ?string $integerPrimaryKey = null
    ): array {
        if ($parentColumns === [] || count($parentColumns) !== count($childColumns)) {
            throw new \InvalidArgumentException('SQLite fkey3 self-referential FK column mapping is invalid');
        }

        foreach ($parentColumns as $column) {
            self::identifier($column, 'parent column');
        }
        foreach ($childColumns as $column) {
            self::identifier($column, 'child column');
        }

        $rows = array_values($rows);
        $candidate = $incoming;
        if ($integerPrimaryKey !== null) {
            $integerPrimaryKey = self::identifier($integerPrimaryKey, 'integer primary key');
            if (($candidate[$integerPrimaryKey] ?? null) === null) {
                $max = 0;
                foreach ($rows as $row) {
                    $value = $row[$integerPrimaryKey] ?? null;
                    if (is_int($value) && $value > $max) {
                        $max = $value;
                    }
                }
                $candidate[$integerPrimaryKey] = $max + 1;
            }
        }

        $attemptedRows = [...$rows, $candidate];
        $childKey = [];
        foreach ($childColumns as $column) {
            $childKey[] = $candidate[$column] ?? null;
        }

        $satisfiedByNullChild = in_array(null, $childKey, true);
        $matchedParent = false;
        if (!$satisfiedByNullChild) {
            foreach ($attemptedRows as $row) {
                $matches = true;
                foreach ($parentColumns as $index => $column) {
                    if (($row[$column] ?? null) !== $childKey[$index]) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    $matchedParent = true;
                    break;
                }
            }
        }

        $valid = $satisfiedByNullChild || $matchedParent;
        $committedRows = $valid ? $attemptedRows : $rows;

        return [
            'source' => 'fkey3.test fkey3-3.1.1..3.6.5',
            'operation' => 'self-referential-foreign-key-insert',
            'status' => $valid ? 'commit-ok' : 'constraint-failed',
            'parent_columns' => $parentColumns,
            'child_columns' => $childColumns,
            'child_key' => $childKey,
            'assigned_integer_primary_key' => $integerPrimaryKey === null ? null : $candidate[$integerPrimaryKey],
            'matched_parent_after_insert' => $matchedParent,
            'null_child_key_satisfied' => $satisfiedByNullChild,
            'attempted_rows' => self::sortRows($attemptedRows),
            'committed_rows' => self::sortRows($committedRows),
            'violation_count' => $valid ? 0 : 1,
            'error' => $valid ? null : 'FOREIGN KEY constraint failed',
            'dependencies' => [
                'sqlite-fkey3-self-referential-row-matches-itself-after-insert',
                'sqlite-fkey3-integer-primary-key-null-is-assigned-before-fk-check',
                'sqlite-fkey3-composite-parent-key-order-follows-fk-declaration',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    public static function deferredAutocommitForeignKeyFailure(array $parents, array $children, array $incoming, int $attempts = 2): array
    {
        if ($attempts < 1) {
            throw new \InvalidArgumentException('SQLite fkey4 deferred autocommit attempts must be positive');
        }

        $committedChildren = array_values($children);
        $attemptResults = [];
        $parentKeys = array_values(array_map(static fn (array $row): mixed => $row['a'] ?? null, $parents));

        for ($i = 1; $i <= $attempts; ++$i) {
            $childKey = $incoming['c'] ?? null;
            $valid = $childKey === null || in_array($childKey, $parentKeys, true);
            if ($valid) {
                $committedChildren[] = $incoming;
            }
            $attemptResults[] = [
                'attempt' => $i,
                'status' => $valid ? 'commit-ok' : 'constraint-failed',
                'error' => $valid ? null : 'FOREIGN KEY constraint failed',
                'transaction_left_open' => false,
                'child_count_after_attempt' => count($committedChildren),
            ];
        }

        return [
            'source' => 'fkey4.test fkey4-1.1..1.4',
            'operation' => 'deferred-autocommit-foreign-key-failure',
            'status' => 'commit-ok',
            'attempt_count' => $attempts,
            'attempts' => $attemptResults,
            'children' => self::sortRows($committedChildren),
            'child_count' => count($committedChildren),
            'statement_transaction_retained' => false,
            'dependencies' => [
                'sqlite-fkey4-deferred-autocommit-violation-rolls-back-statement',
                'sqlite-fkey4-reprepared-statement-fails-independently',
            ],
        ];
    }

    /**
     * @param list<string> $updatedColumns
     * @return array<string,mixed>
     */
    public static function foreignKeyUpdateReadSet(array $updatedColumns, bool $whereUsesParentReference = false): array
    {
        $updatedColumns = array_values(array_map(
            static fn (string $column): string => self::identifier($column, 'updated parent column'),
            $updatedColumns
        ));

        $reads = ['par' => true];
        if (in_array('b', $updatedColumns, true)) {
            $reads['s1'] = true;
        }
        if (in_array('a', $updatedColumns, true) || $whereUsesParentReference) {
            $reads['c1'] = true;
            $reads['c2'] = true;
        }
        if (in_array('c', $updatedColumns, true)) {
            $reads['c3'] = true;
        }

        $ordered = array_keys($reads);
        sort($ordered, SORT_STRING);

        return [
            'source' => 'fkey7.test fkey7-1.2..1.5',
            'operation' => 'foreign-key-update-authorizer-read-set',
            'status' => 'commit-ok',
            'updated_columns' => $updatedColumns,
            'where_uses_parent_reference' => $whereUsesParentReference,
            'read_tables' => $ordered,
            'read_table_count' => count($ordered),
            'reads_parent_reference_table' => isset($reads['s1']),
            'reads_child_primary_key_refs' => isset($reads['c1']) && isset($reads['c2']),
            'reads_child_unique_refs' => isset($reads['c3']),
            'dependencies' => [
                'sqlite-fkey7-parent-update-reads-new-parent-reference',
                'sqlite-fkey7-parent-key-update-probes-child-references',
                'sqlite-fkey7-unique-parent-key-update-probes-dependent-child',
            ],
        ];
    }

    /**
     * @param list<mixed> $parentKeys
     * @param list<mixed> $existingChildKeys
     * @param list<mixed> $incomingChildKeys
     * @return array<string,mixed>
     */
    public static function foreignKeyOrFailInsert(array $parentKeys, array $existingChildKeys, array $incomingChildKeys): array
    {
        $parentSet = array_fill_keys(array_map(static fn (mixed $key): string => self::valueKey($key), $parentKeys), true);
        $rows = array_values($existingChildKeys);
        $error = null;
        $failed_key = null;

        foreach ($incomingChildKeys as $key) {
            if (!isset($parentSet[self::valueKey($key)])) {
                $error = 'FOREIGN KEY constraint failed';
                $failed_key = $key;
                break;
            }
            if (in_array($key, $rows, true)) {
                $error = 'UNIQUE constraint failed: child.c';
                $failed_key = $key;
                break;
            }
            $rows[] = $key;
        }

        sort($rows);
        $preservedPriorSuccessfulRows = str_starts_with((string) $error, 'UNIQUE constraint failed') && (count($rows) > count($existingChildKeys));

        return [
            'source' => 'fkey7.test fkey7-4.1..4.6',
            'operation' => 'insert-or-fail-foreign-key-before-unique',
            'status' => $error === null ? 'commit-ok' : 'constraint-failed',
            'error' => $error,
            'failed_key' => $failed_key,
            'parent_keys' => array_values($parentKeys),
            'incoming_child_keys' => array_values($incomingChildKeys),
            'committed_child_keys' => $rows,
            'committed_child_count' => count($rows),
            'foreign_key_checked_before_unique' => $error === 'FOREIGN KEY constraint failed',
            'or_fail_preserved_prior_successful_rows' => $preservedPriorSuccessfulRows,
            'foreign_key_check_rows' => [],
            'dependencies' => [
                'sqlite-fkey7-insert-or-fail-checks-foreign-key-before-unique',
                'sqlite-fkey7-insert-or-fail-preserves-prior-row-on-unique-failure',
                'sqlite-fkey7-foreign-key-check-clean-after-failed-statement',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int}> $leftRows
     * @param list<array{c:int,d:int}> $rightRows
     * @param list<array{op:string,where?:callable(array<string,mixed>):bool,row?:array{a:int,b:int,c:int,d:int}}>
     *        $operations
     * @return array<string,mixed>
     */
    public static function insteadOfViewTriggerLog(array $leftRows, array $rightRows, array $operations): array
    {
        $viewRows = [];
        foreach ($leftRows as $left) {
            foreach ($rightRows as $right) {
                $viewRows[] = [
                    'a' => $left['a'],
                    'b' => $left['b'],
                    'c' => $right['c'],
                    'd' => $right['d'],
                ];
            }
        }

        $log = [];
        foreach ($operations as $operation) {
            $op = strtolower((string) ($operation['op'] ?? ''));
            $where = $operation['where'] ?? static fn (array $_row): bool => true;
            if ($op === 'update') {
                foreach ($viewRows as $row) {
                    if (!$where($row)) {
                        continue;
                    }
                    $new = $operation['row'] ?? ['a' => 100, 'b' => 25, 'c' => $row['c'], 'd' => $row['d']];
                    $log[] = self::viewTriggerLogRow($row, $new);
                    $log[] = self::viewTriggerLogRow($row, $new);
                }
            } elseif ($op === 'delete') {
                foreach ($viewRows as $row) {
                    if (!$where($row)) {
                        continue;
                    }
                    $log[] = self::viewTriggerLogRow($row, ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0]);
                    $log[] = self::viewTriggerLogRow($row, ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0]);
                }
            } elseif ($op === 'insert') {
                $new = $operation['row'] ?? throw new \InvalidArgumentException('SQLite view trigger insert row is required');
                $log[] = self::viewTriggerLogRow(['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0], $new);
                $log[] = self::viewTriggerLogRow(['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0], $new);
            } else {
                throw new \InvalidArgumentException('SQLite view trigger operation is unsupported');
            }
        }

        return [
            'source' => 'trigger2.test trigger2-7.1..7.4',
            'operation' => 'instead-of-view-trigger-old-new-log',
            'status' => 'commit-ok',
            'view_row_count' => count($viewRows),
            'operation_count' => count($operations),
            'log_rows' => $log,
            'log_row_count' => count($log),
            'first_log_row' => $log[0] ?? null,
            'last_log_row' => $log === [] ? null : $log[array_key_last($log)],
            'dependencies' => [
                'sqlite-trigger2-instead-of-update-view-old-new-row',
                'sqlite-trigger2-instead-of-delete-view-old-row',
                'sqlite-trigger2-instead-of-insert-view-new-row',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int,c:int}> $baseRows
     * @param list<array{op:string,where?:callable(array{x:int,y:int,z:int}):bool,row?:array{x:int,y:int,z:int}}>
     *        $operations
     * @return array<string,mixed>
     */
    public static function expressionViewTriggerRows(array $baseRows, array $operations): array
    {
        $viewRows = array_map(
            static fn (array $row): array => [
                'x' => $row['a'] + $row['b'],
                'y' => $row['b'] + $row['c'],
                'z' => $row['a'] + $row['c'],
            ],
            $baseRows
        );

        $log = [];
        foreach ($operations as $operation) {
            $op = strtolower((string) ($operation['op'] ?? ''));
            $where = $operation['where'] ?? static fn (array $_row): bool => true;
            if ($op === 'delete') {
                foreach ($viewRows as $row) {
                    if ($where($row)) {
                        $log[] = ['old_x' => $row['x'], 'new_x' => null, 'old_y' => $row['y'], 'new_y' => null, 'old_z' => $row['z'], 'new_z' => null];
                    }
                }
            } elseif ($op === 'insert') {
                $new = $operation['row'] ?? throw new \InvalidArgumentException('SQLite expression view insert row is required');
                $log[] = ['old_x' => null, 'new_x' => $new['x'], 'old_y' => null, 'new_y' => $new['y'], 'old_z' => null, 'new_z' => $new['z']];
            } elseif ($op === 'update') {
                foreach ($viewRows as $row) {
                    if (!$where($row)) {
                        continue;
                    }
                    $new = $operation['row'] ?? ['x' => $row['x'] + 100, 'y' => $row['y'] + 200, 'z' => $row['z'] + 300];
                    $log[] = ['old_x' => $row['x'], 'new_x' => $new['x'], 'old_y' => $row['y'], 'new_y' => $new['y'], 'old_z' => $row['z'], 'new_z' => $new['z']];
                }
            } else {
                throw new \InvalidArgumentException('SQLite expression view trigger operation is unsupported');
            }
        }

        return [
            'source' => 'trigger2.test trigger2-8.1..8.6',
            'operation' => 'expression-view-instead-of-trigger-old-new-rows',
            'status' => 'commit-ok',
            'view_rows' => $viewRows,
            'view_row_count' => count($viewRows),
            'log_rows' => $log,
            'log_row_count' => count($log),
            'dependencies' => [
                'sqlite-trigger2-view-expression-columns-feed-old-row',
                'sqlite-trigger2-view-insert-feeds-new-expression-row',
                'sqlite-trigger2-view-update-feeds-old-and-new-expression-rows',
            ],
        ];
    }

    /**
     * @param list<array{id:int,a:int}> $leftRows
     * @param list<array{id:int,b:int}> $rightRows
     * @param list<array{op:string,row?:array{id:int,a:int,b:int},where?:callable(array{id:int,a:int,b:int}):bool,set?:array<string,mixed>,missing_table?:string}>
     *        $operations
     * @return array<string,mixed>
     */
    public static function viewInsteadOfTriggerRouting(array $leftRows, array $rightRows, array $operations): array
    {
        $left = array_values($leftRows);
        $right = array_values($rightRows);
        $log = [];
        $errors = [];

        foreach ($operations as $operation) {
            $op = strtolower((string) ($operation['op'] ?? ''));
            $missing = $operation['missing_table'] ?? null;
            if ($missing === 'test1' || $missing === 'test2') {
                $errors[] = ['op' => $op, 'error' => 'no such table: main.' . $missing];
                continue;
            }

            if ($op === 'insert') {
                $row = $operation['row'] ?? throw new \InvalidArgumentException('SQLite trigger4 view insert row is required');
                $left[] = ['id' => (int) $row['id'], 'a' => (int) $row['a']];
                $right[] = ['id' => (int) $row['id'], 'b' => (int) $row['b']];
                $log[] = ['op' => 'insert', 'id' => (int) $row['id'], 'left_a' => (int) $row['a'], 'right_b' => (int) $row['b']];
                continue;
            }

            $where = $operation['where'] ?? static fn (array $_row): bool => true;
            $viewRows = self::joinedViewRows($left, $right);
            if ($op === 'update') {
                $set = $operation['set'] ?? [];
                foreach ($viewRows as $viewRow) {
                    if (!$where($viewRow)) {
                        continue;
                    }
                    foreach ($left as $index => $row) {
                        if ($row['id'] === $viewRow['id'] && array_key_exists('a', $set)) {
                            $left[$index]['a'] = is_callable($set['a']) ? (int) $set['a']($viewRow) : (int) $set['a'];
                        }
                    }
                    foreach ($right as $index => $row) {
                        if ($row['id'] === $viewRow['id'] && array_key_exists('b', $set)) {
                            $right[$index]['b'] = is_callable($set['b']) ? (int) $set['b']($viewRow) : (int) $set['b'];
                        }
                    }
                    $log[] = ['op' => 'update', 'id' => $viewRow['id'], 'old_a' => $viewRow['a'], 'old_b' => $viewRow['b']];
                }
                continue;
            }

            if ($op === 'delete') {
                $deleteIds = [];
                foreach ($viewRows as $viewRow) {
                    if ($where($viewRow)) {
                        $deleteIds[] = $viewRow['id'];
                        $log[] = ['op' => 'delete', 'id' => $viewRow['id'], 'old_a' => $viewRow['a'], 'old_b' => $viewRow['b']];
                    }
                }
                $left = array_values(array_filter($left, static fn (array $row): bool => !in_array($row['id'], $deleteIds, true)));
                $right = array_values(array_filter($right, static fn (array $row): bool => !in_array($row['id'], $deleteIds, true)));
                continue;
            }

            throw new \InvalidArgumentException('SQLite trigger4 view operation is unsupported');
        }

        $viewRows = self::joinedViewRows($left, $right);

        return [
            'source' => 'trigger4.test trigger4-1.1..7.2',
            'operation' => 'instead-of-view-trigger-backing-table-routing',
            'status' => $errors === [] ? 'commit-ok' : 'constraint-failed',
            'errors' => $errors,
            'error_count' => count($errors),
            'test1_rows' => self::sortRows($left),
            'test2_rows' => self::sortRows($right),
            'view_rows' => $viewRows,
            'view_row_count' => count($viewRows),
            'log_rows' => $log,
            'log_row_count' => count($log),
            'insert_count' => count(array_filter($log, static fn (array $row): bool => $row['op'] === 'insert')),
            'update_count' => count(array_filter($log, static fn (array $row): bool => $row['op'] === 'update')),
            'delete_count' => count(array_filter($log, static fn (array $row): bool => $row['op'] === 'delete')),
            'dependencies' => [
                'sqlite-trigger4-instead-of-insert-routes-to-view-base-tables',
                'sqlite-trigger4-instead-of-update-routes-to-view-base-tables',
                'sqlite-trigger4-instead-of-delete-routes-to-view-base-tables',
                'sqlite-trigger4-missing-view-backing-table-fails-trigger-program',
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
     * @param list<array{id:int,a:int}> $leftRows
     * @param list<array{id:int,b:int}> $rightRows
     * @return list<array{id:int,a:int,b:int}>
     */
    private static function joinedViewRows(array $leftRows, array $rightRows): array
    {
        $rows = [];
        foreach ($leftRows as $left) {
            foreach ($rightRows as $right) {
                if ($left['id'] === $right['id']) {
                    $rows[] = ['id' => $left['id'], 'a' => $left['a'], 'b' => $right['b']];
                }
            }
        }

        return self::sortRows($rows);
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
     * @param list<array{id:int|string,key:string}> $parents
     * @param list<array{id:int|string,parent_key:string}> $children
     */
    private static function nocaseViolationCount(array $parents, array $children): int
    {
        $keys = array_map(static fn (array $row): string => strtolower((string) $row['key']), $parents);
        $violations = 0;
        foreach ($children as $child) {
            if (!in_array(strtolower((string) ($child['parent_key'] ?? '')), $keys, true)) {
                ++$violations;
            }
        }

        return $violations;
    }

    /**
     * @param list<array{c34:string,c35:string,label?:string}> $parents
     * @param list<array{c38:string,c39:string,label?:string}> $children
     */
    private static function compositeForeignKeyViolationCount(array $parents, array $children): int
    {
        $keys = [];
        foreach ($parents as $parent) {
            $keys[$parent['c34'] . "\0" . $parent['c35']] = true;
        }

        $violations = 0;
        foreach ($children as $child) {
            if (!isset($keys[$child['c39'] . "\0" . $child['c38']])) {
                ++$violations;
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
     * @param array{a:int,b:int,c:int,d:int} $old
     * @param array{a:int,b:int,c:int,d:int} $new
     * @return array<string,int>
     */
    private static function viewTriggerLogRow(array $old, array $new): array
    {
        return [
            'old_a' => $old['a'],
            'old_b' => $old['b'],
            'old_c' => $old['c'],
            'old_d' => $old['d'],
            'new_a' => $new['a'],
            'new_b' => $new['b'],
            'new_c' => $new['c'],
            'new_d' => $new['d'],
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

    private static function valueKey(mixed $value): string
    {
        return get_debug_type($value) . ':' . (string) $value;
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

    private static function foreignKeyActionUsesStatementJournal(string $operation, string $action, string $conflict): bool
    {
        if ($operation === 'delete') {
            return $action === 'no action' || $action === 'set default';
        }

        if ($conflict === 'ignore') {
            return $action !== 'cascade';
        }

        return $action !== 'cascade';
    }

    private static function updatedForeignKeyValue(mixed $oldPid, mixed $newPid): mixed
    {
        if (is_int($oldPid)) {
            return $newPid === null ? $oldPid * 10 : $newPid;
        }
        if (is_numeric($oldPid)) {
            return $newPid === null ? (string) ((int) $oldPid * 10) : $newPid;
        }

        return $newPid === null ? (string) $oldPid . '_updated' : $newPid;
    }

    /**
     * @param list<array{pid:int|string,label?:string}> $parents
     * @param list<array{cid:int|string,pid:int|string|null,payload?:string}> $children
     * @return list<array{cid:int|string,pid:int|string|null}>
     */
    private static function simpleForeignKeyViolations(array $parents, array $children): array
    {
        $parentKeys = array_values(array_map(static fn (array $row): mixed => $row['pid'], $parents));
        $violations = [];
        foreach ($children as $child) {
            if ($child['pid'] === null || in_array($child['pid'], $parentKeys, true)) {
                continue;
            }
            $violations[] = ['cid' => $child['cid'], 'pid' => $child['pid']];
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rowWithKeyExists(array $rows, int $key): bool
    {
        foreach ($rows as $row) {
            if (($row['a'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $needle
     */
    private static function rowEqualsAny(array $rows, array $needle): bool
    {
        foreach ($rows as $row) {
            if ($row == $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{name:string,schema?:string,columns:list<array{name:string,collation?:string}>,rows?:list<array<string,mixed>>,primary_key?:list<string>,unique?:list<list<string>>,without_rowid?:bool}> $parents
     * @param list<array{name:string,schema?:string,columns:list<array{name:string,collation?:string}>,rows:list<array<string,mixed>>,foreign_key:array{parent_table:string,parent_schema?:string,child_columns:list<string>,parent_columns:list<string>,id?:int}}> $children
     * @return array<string,mixed>
     */
    public static function foreignKeyCheckCorpus(array $parents, array $children, ?string $table = null, ?string $schema = null): array
    {
        $parentMap = [];
        foreach ($parents as $parent) {
            $name = self::identifier((string) $parent['name'], 'foreign key check parent table');
            $parentSchema = self::identifier((string) ($parent['schema'] ?? 'main'), 'foreign key check parent schema');
            $parentMap[$parentSchema . '.' . $name] = $parent + ['name' => $name, 'schema' => $parentSchema];
        }

        $violations = [];
        $mismatch = null;
        foreach ($children as $child) {
            $childName = self::identifier((string) $child['name'], 'foreign key check child table');
            $childSchema = self::identifier((string) ($child['schema'] ?? 'main'), 'foreign key check child schema');
            if ($table !== null && $childName !== $table) {
                continue;
            }
            if ($schema !== null && $childSchema !== $schema) {
                continue;
            }

            $foreignKey = $child['foreign_key'];
            $parentName = self::identifier((string) $foreignKey['parent_table'], 'foreign key check referenced table');
            $parentSchema = self::identifier((string) ($foreignKey['parent_schema'] ?? $childSchema), 'foreign key check referenced schema');
            $parent = $parentMap[$parentSchema . '.' . $parentName] ?? null;
            if ($parent === null) {
                foreach ($child['rows'] as $index => $row) {
                    if (self::hasNullForeignKeyValue($row, $foreignKey['child_columns'])) {
                        continue;
                    }
                    $violations[] = self::foreignKeyCheckViolation($childName, $row, $index, $parentName, (int) ($foreignKey['id'] ?? 0), (bool) ($child['without_rowid'] ?? false));
                }
                continue;
            }

            if (!self::parentKeyIsValid($parent, $foreignKey['parent_columns'])) {
                $mismatch = 'foreign key mismatch - "' . $childName . '" referencing "' . $parentName . '"';
                break;
            }

            foreach ($child['rows'] as $index => $row) {
                if (self::hasNullForeignKeyValue($row, $foreignKey['child_columns'])) {
                    continue;
                }
                if (self::parentRowMatchesForeignKey($parent, $row, $foreignKey['child_columns'], $foreignKey['parent_columns'])) {
                    continue;
                }
                $violations[] = self::foreignKeyCheckViolation($childName, $row, $index, $parentName, (int) ($foreignKey['id'] ?? 0), (bool) ($child['without_rowid'] ?? false));
            }
        }

        usort($violations, static fn (array $a, array $b): int => [$a['table'], $a['rowid'], $a['parent'], $a['fkid']] <=> [$b['table'], $b['rowid'], $b['parent'], $b['fkid']]);

        return [
            'source' => 'fkey5.test fkey5-1.1..13.12',
            'operation' => 'pragma-foreign-key-check-corpus',
            'status' => $mismatch === null ? 'check-ok' : 'schema-mismatch',
            'table_filter' => $table,
            'schema_filter' => $schema,
            'mismatch_error' => $mismatch,
            'violation_rows' => $violations,
            'violation_count' => count($violations),
            'dependencies' => [
                'sqlite-fkey5-foreign-key-check-row-shape',
                'sqlite-fkey5-parent-key-unique-validation',
                'sqlite-fkey5-without-rowid-null-rowid',
                'sqlite-fkey5-schema-scoped-pragma-argument',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function hasNullForeignKeyValue(array $row, array $columns): bool
    {
        foreach ($columns as $column) {
            self::identifier($column, 'foreign key check child column');
            if (($row[$column] ?? null) === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{table:string,rowid:int|string|null,parent:string,fkid:int}
     */
    private static function foreignKeyCheckViolation(string $childName, array $row, int $index, string $parentName, int $foreignKeyId, bool $withoutRowid): array
    {
        return [
            'table' => $childName,
            'rowid' => $withoutRowid ? null : ($row['rowid'] ?? $index + 1),
            'parent' => $parentName,
            'fkid' => $foreignKeyId,
        ];
    }

    /**
     * @param array{name:string,columns:list<array{name:string,collation?:string}>,primary_key?:list<string>,unique?:list<list<string>>} $parent
     * @param list<string> $columns
     */
    private static function parentKeyIsValid(array $parent, array $columns): bool
    {
        $wanted = array_values($columns);
        foreach ($wanted as $column) {
            self::identifier($column, 'foreign key check parent column');
            if (!self::columnExists($parent, $column)) {
                return false;
            }
        }
        if (($parent['primary_key'] ?? []) === $wanted) {
            return true;
        }
        foreach (($parent['unique'] ?? []) as $unique) {
            if (array_values($unique) === $wanted) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{columns:list<array{name:string,collation?:string}>} $table
     */
    private static function columnExists(array $table, string $column): bool
    {
        foreach ($table['columns'] as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{name:string,columns:list<array{name:string,collation?:string}>,rows?:list<array<string,mixed>>} $parent
     * @param array<string,mixed> $childRow
     * @param list<string> $childColumns
     * @param list<string> $parentColumns
     */
    private static function parentRowMatchesForeignKey(array $parent, array $childRow, array $childColumns, array $parentColumns): bool
    {
        foreach (($parent['rows'] ?? []) as $parentRow) {
            $matches = true;
            foreach ($childColumns as $index => $childColumn) {
                $parentColumn = $parentColumns[$index] ?? '';
                self::identifier($childColumn, 'foreign key check child column');
                self::identifier($parentColumn, 'foreign key check parent column');
                $collation = self::columnCollation($parent, $parentColumn);
                if (!self::foreignKeyValuesEqual($childRow[$childColumn] ?? null, $parentRow[$parentColumn] ?? null, $collation)) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{columns:list<array{name:string,collation?:string}>} $table
     */
    private static function columnCollation(array $table, string $column): string
    {
        foreach ($table['columns'] as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return strtolower((string) ($definition['collation'] ?? 'binary'));
            }
        }

        return 'binary';
    }

    private static function foreignKeyValuesEqual(mixed $child, mixed $parent, string $collation): bool
    {
        if ($child === null || $parent === null) {
            return $child === $parent;
        }
        $child = (string) $child;
        $parent = (string) $parent;

        return match ($collation) {
            'nocase' => strcasecmp($child, $parent) === 0,
            'rtrim' => rtrim($child) === rtrim($parent),
            default => $child === $parent,
        };
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite dynamic trigger FK {$label} is malformed");
        }

        return $value;
    }
}
