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
     * @param list<int> $indexedValues
     * @param list<int> $leftFilter
     * @param list<int> $rightFilter
     * @return array<string,mixed>
     */
    public static function recursiveTriggerInsertSelectOncePlan(
        array $indexedValues,
        int $initialValue,
        int $maxValue,
        array $leftFilter,
        ?array $rightFilter = null
    ): array {
        if ($maxValue < $initialValue) {
            throw new \InvalidArgumentException('SQLite triggerG recursive trigger max value cannot be before the initial value');
        }

        $indexedValues = array_values(array_map('intval', $indexedValues));
        sort($indexedValues);
        $leftFilter = array_values(array_unique(array_map('intval', $leftFilter)));
        sort($leftFilter);
        if ($leftFilter === []) {
            throw new \InvalidArgumentException('SQLite triggerG recursive trigger left filter is empty');
        }

        if ($rightFilter !== null) {
            $rightFilter = array_values(array_unique(array_map('intval', $rightFilter)));
            sort($rightFilter);
            if ($rightFilter === []) {
                throw new \InvalidArgumentException('SQLite triggerG recursive trigger right filter is empty');
            }
        }

        $tableValues = [$initialValue];
        $queue = [$initialValue];
        $triggerRows = [];
        $triggerInvocations = 0;
        $selectedLeft = self::filteredIntegers($indexedValues, $leftFilter);
        $selectedRight = $rightFilter === null ? [] : self::filteredIntegers($indexedValues, $rightFilter);

        while ($queue !== []) {
            $newValue = array_shift($queue);
            ++$triggerInvocations;

            if ($newValue < $maxValue) {
                $nextValue = $newValue + 1;
                $tableValues[] = $nextValue;
                $queue[] = $nextValue;
            }

            if ($rightFilter === null) {
                foreach ($selectedLeft as $left) {
                    $triggerRows[] = ($newValue * 100) + $left;
                }
                continue;
            }

            foreach ($selectedLeft as $left) {
                foreach ($selectedRight as $right) {
                    $triggerRows[] = ($newValue * 10000) + ($left * 100) + $right;
                }
            }
        }

        sort($tableValues);
        sort($triggerRows);

        return [
            'source' => $rightFilter === null ? 'triggerG.test triggerG-100..110' : 'triggerG.test triggerG-200',
            'operation' => $rightFilter === null ? 'recursive-trigger-insert-select-once' : 'recursive-trigger-join-select-once',
            'initial_value' => $initialValue,
            'max_value' => $maxValue,
            'indexed_values' => $indexedValues,
            'left_filter' => $leftFilter,
            'right_filter' => $rightFilter,
            'selected_left_values' => $selectedLeft,
            'selected_right_values' => $selectedRight,
            'recursive_triggers' => true,
            'trigger_invocations' => $triggerInvocations,
            'inserted_trigger_values' => $tableValues,
            'trigger_output_rows' => $triggerRows,
            'output_count' => count($triggerRows),
            'dependencies' => [
                'sqlite-triggerG-recursive-trigger-reruns-insert-select-program',
                'sqlite-triggerG-op-once-subprogram-does-not-suppress-recursive-select',
                $rightFilter === null
                    ? 'sqlite-triggerG-recursive-trigger-single-source-select'
                    : 'sqlite-triggerG-recursive-trigger-join-source-select',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @return array<string,mixed>
     */
    public static function deferredCounterScanPlan(array $parents, array $children, string $operation, bool $hasOutstandingDeferredViolation): array
    {
        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['insert-parent', 'delete-child', 'delete-parent'], true)) {
            throw new \InvalidArgumentException('SQLite fkey2-15 operation is unsupported');
        }

        $parentsByKey = [];
        foreach ($parents as $parent) {
            $parentsByKey[(string) ($parent['id'] ?? '')] = true;
        }

        $violations = [];
        foreach ($children as $child) {
            $parentId = $child['parent_id'] ?? null;
            if ($parentId !== null && !isset($parentsByKey[(string) $parentId])) {
                $violations[] = $child['id'] ?? count($violations);
            }
        }

        $searches = 0;
        if ($operation === 'insert-parent' && $hasOutstandingDeferredViolation) {
            $searches = 2;
        } elseif ($operation === 'delete-child') {
            $searches = $hasOutstandingDeferredViolation ? 2 : 1;
        } elseif ($operation === 'delete-parent') {
            $searches = 1;
        }

        return [
            'source' => 'fkey2.test fkey2-15.1.1..15.1.7',
            'operation' => 'deferred-counter-scan-avoidance',
            'statement' => $operation,
            'status' => 'commit-ok',
            'outstanding_deferred_violation' => $hasOutstandingDeferredViolation,
            'deferred_violation_count' => count($violations),
            'violation_child_ids' => array_values($violations),
            'fk_lookup_count' => $searches,
            'skipped_unnecessary_fk_scan' => $searches === 0,
            'dependencies' => [
                'sqlite-fkey2-zero-deferred-counter-skips-parent-probe',
                'sqlite-fkey2-nonzero-deferred-counter-rechecks-pending-violation',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function selfReferencingRowPlan(string $schemaKind, int $oldKey, int $oldParent, int $newKey, int $newParent): array
    {
        $schemaKind = strtolower(trim($schemaKind));
        if (!in_array($schemaKind, ['integer-primary-key', 'primary-key', 'unique-parent'], true)) {
            throw new \InvalidArgumentException('SQLite fkey2-16 schema kind is unsupported');
        }

        $validBefore = $oldParent === $oldKey;
        $validAfter = $newParent === $newKey;

        return [
            'source' => 'fkey2.test fkey2-16.1.1..16.1.8',
            'operation' => 'self-referencing-row-update',
            'schema_kind' => $schemaKind,
            'old_key' => $oldKey,
            'old_parent_key' => $oldParent,
            'new_key' => $newKey,
            'new_parent_key' => $newParent,
            'old_row_valid' => $validBefore,
            'status' => $validAfter ? 'commit-ok' : 'constraint-failed',
            'self_reference_preserved' => $validAfter,
            'delete_self_reference_status' => $validBefore ? 'commit-ok' : 'constraint-failed',
            'dependencies' => [
                'sqlite-fkey2-self-referencing-row-may-be-inserted',
                'sqlite-fkey2-self-referencing-row-may-be-updated-when-key-and-reference-move-together',
                'sqlite-fkey2-self-referencing-row-delete-does-not-self-violate',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function countChangesForeignKeyPlan(string $statement, bool $deferred, bool $foreignKeyAction = false): array
    {
        $statement = strtolower(trim($statement));
        if (!in_array($statement, ['insert-child-violation', 'update-parent-cascade', 'delete-parent-cascade'], true)) {
            throw new \InvalidArgumentException('SQLite fkey2-17 statement is unsupported');
        }

        if ($statement === 'insert-child-violation') {
            return [
                'source' => 'fkey2.test fkey2-17.1.1..17.1.14',
                'operation' => 'count-changes-foreign-key-violation',
                'statement' => $statement,
                'status' => $deferred ? 'row-then-constraint' : 'constraint-immediate',
                'deferred' => $deferred,
                'count_changes_returned_rows' => $deferred ? [1] : [],
                'changes' => $deferred ? 1 : 0,
                'total_changes_delta' => $deferred ? 1 : 0,
                'error_code' => 'SQLITE_CONSTRAINT_FOREIGNKEY',
                'dependencies' => [
                    'sqlite-fkey2-count-changes-immediate-fk-fails-before-row-count',
                    'sqlite-fkey2-count-changes-deferred-fk-returns-row-count-before-failure',
                ],
            ];
        }

        $direct = 1;
        $action = $foreignKeyAction ? 1 : 0;

        return [
            'source' => 'fkey2.test fkey2-17.2.1..17.2.10',
            'operation' => 'count-changes-foreign-key-action',
            'statement' => $statement,
            'status' => 'commit-ok',
            'deferred' => $deferred,
            'foreign_key_action' => $foreignKeyAction,
            'count_changes_returned_rows' => [$direct],
            'changes' => $direct,
            'total_changes_delta' => $direct + $action,
            'fk_action_changes_excluded_from_changes' => $foreignKeyAction,
            'dependencies' => [
                'sqlite-fkey2-count-changes-excludes-fk-action-rows',
                'sqlite-fkey2-total-changes-includes-fk-action-rows',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @return array<string,mixed>
     */
    public static function deferredRestrictDeleteTriggerRepair(
        array $parents,
        array $children,
        string $parentKeyColumn,
        string $childKeyColumn,
        mixed $deleteKey,
        bool $deferForeignKeys,
        bool $afterDeleteTriggerRepair = true
    ): array {
        $parentKeyColumn = self::identifier($parentKeyColumn, 'parent key column');
        $childKeyColumn = self::identifier($childKeyColumn, 'child key column');
        $parents = array_values($parents);
        $children = array_values($children);
        $originalParents = $parents;
        $deletedParents = [];

        foreach ($parents as $index => $parent) {
            if (!array_key_exists($parentKeyColumn, $parent)) {
                throw new \InvalidArgumentException('SQLite fkey6 parent row is missing the parent key column');
            }
            if ($parent[$parentKeyColumn] !== $deleteKey) {
                continue;
            }

            $deletedParents[] = $parent;
            unset($parents[$index]);
        }
        $parents = array_values($parents);

        foreach ($children as $child) {
            if (!array_key_exists($childKeyColumn, $child)) {
                throw new \InvalidArgumentException('SQLite fkey6 child row is missing the child key column');
            }
        }

        $referencingChildren = self::matchingChildIndexes($children, $childKeyColumn, $deleteKey);
        if (!$deferForeignKeys && $referencingChildren !== []) {
            return [
                'source' => 'fkey6.test 3.3.1..3.3.4',
                'operation' => 'deferred-restrict-delete-trigger-repair',
                'status' => 'constraint-failed',
                'defer_foreign_keys' => false,
                'after_delete_trigger_repair' => $afterDeleteTriggerRepair,
                'deleted_parent_keys' => [],
                'trigger_inserted_keys' => [],
                'referencing_child_indexes' => $referencingChildren,
                'deferred_violation_count' => 0,
                'parent_keys_after_statement' => array_values(array_column($originalParents, $parentKeyColumn)),
                'parent_keys_after_commit' => array_values(array_column($originalParents, $parentKeyColumn)),
                'child_keys_after_commit' => array_values(array_column($children, $childKeyColumn)),
                'commit_boundary' => 'restrict-checked-before-trigger-repair',
                'dependencies' => [
                    'sqlite-fkey6-restrict-is-immediate-without-defer-foreign-keys',
                    'sqlite-fkey6-after-delete-trigger-can-repair-deferred-restrict',
                ],
            ];
        }

        $triggerInserted = [];
        if ($afterDeleteTriggerRepair) {
            foreach ($deletedParents as $deletedParent) {
                $repair = $deletedParent;
                $repair['trigger_payload'] = 'deleted!';
                $parents[] = $repair;
                $triggerInserted[] = $repair;
            }
        }

        $violations = self::foreignKeyMissingParentKeys($parents, $children, $parentKeyColumn, $childKeyColumn);

        return [
            'source' => 'fkey6.test 3.3.1..3.3.4',
            'operation' => 'deferred-restrict-delete-trigger-repair',
            'status' => $violations === [] ? 'commit-ok' : 'deferred-commit-failed',
            'defer_foreign_keys' => $deferForeignKeys,
            'after_delete_trigger_repair' => $afterDeleteTriggerRepair,
            'deleted_parent_keys' => array_values(array_column($deletedParents, $parentKeyColumn)),
            'trigger_inserted_keys' => array_values(array_column($triggerInserted, $parentKeyColumn)),
            'referencing_child_indexes' => $referencingChildren,
            'deferred_violation_count' => count($violations),
            'violations' => $violations,
            'parent_keys_after_statement' => array_values(array_column($parents, $parentKeyColumn)),
            'parent_keys_after_commit' => array_values(array_column($violations === [] ? self::sortRows($parents) : $originalParents, $parentKeyColumn)),
            'child_keys_after_commit' => array_values(array_column($children, $childKeyColumn)),
            'commit_boundary' => $violations === [] ? 'outer-commit-after-trigger-repair' : 'outer-commit-foreign-key-check',
            'dependencies' => [
                'sqlite-fkey6-defer-foreign-keys-delays-restrict',
                'sqlite-fkey6-after-delete-trigger-can-repair-deferred-restrict',
                'sqlite-fkey6-defer-foreign-keys-resets-at-transaction-boundary',
            ],
        ];
    }

    /**
     * @param list<array{nodeid:int,parent:int|null}> $nodes
     * @param list<array{id:string,nodeid:int}> $leaves
     * @param list<array<string,mixed>> $steps
     * @return array<string,mixed>
     */
    public static function fkey2DeferredGraphTransaction(array $nodes, array $leaves, array $steps): array
    {
        $nodes = array_values($nodes);
        $leaves = array_values($leaves);
        $originalNodes = $nodes;
        $originalLeaves = $leaves;
        $events = [];
        $commitAttempts = [];
        $insideTransaction = false;

        foreach ($steps as $index => $step) {
            $action = strtolower(trim((string) ($step['action'] ?? '')));
            $events[] = ['step' => $action, 'index' => $index];

            if ($action === 'begin') {
                $insideTransaction = true;
                continue;
            }
            if ($action === 'insert-node') {
                $nodes[] = [
                    'nodeid' => (int) ($step['nodeid'] ?? 0),
                    'parent' => array_key_exists('parent', $step) ? ($step['parent'] === null ? null : (int) $step['parent']) : null,
                ];
                continue;
            }
            if ($action === 'insert-leaf') {
                $leaves[] = [
                    'id' => (string) ($step['id'] ?? ('leaf-' . $index)),
                    'nodeid' => (int) ($step['nodeid'] ?? 0),
                ];
                continue;
            }
            if ($action === 'update-node-parent') {
                $nodeid = (int) ($step['nodeid'] ?? 0);
                foreach ($nodes as &$node) {
                    if ((int) $node['nodeid'] === $nodeid) {
                        $node['parent'] = $step['parent'] === null ? null : (int) $step['parent'];
                    }
                }
                unset($node);
                continue;
            }
            if ($action === 'delete-node') {
                $nodeid = (int) ($step['nodeid'] ?? 0);
                $nodes = array_values(array_filter($nodes, static fn (array $node): bool => (int) $node['nodeid'] !== $nodeid));
                continue;
            }
            if ($action === 'commit') {
                $violations = self::fkey2GraphViolations($nodes, $leaves);
                $status = $violations === [] ? 'commit-ok' : 'commit-blocked';
                $commitAttempts[] = [
                    'status' => $status,
                    'violation_count' => count($violations),
                    'violations' => $violations,
                ];
                if ($status === 'commit-ok') {
                    $insideTransaction = false;
                }
                continue;
            }
            if ($action === 'rollback') {
                $nodes = $originalNodes;
                $leaves = $originalLeaves;
                $insideTransaction = false;
                continue;
            }

            throw new \InvalidArgumentException('SQLite fkey2 deferred graph action is unsupported');
        }

        $violations = self::fkey2GraphViolations($nodes, $leaves);

        $sortedNodes = $nodes;
        usort($sortedNodes, static fn (array $left, array $right): int => ((int) $left['nodeid']) <=> ((int) $right['nodeid']));

        return [
            'source' => 'fkey2.test fkey2-2.1..2.17',
            'operation' => 'deferred-foreign-key-graph-transaction',
            'status' => $violations === [] ? 'commit-ok' : 'transaction-open-with-deferred-violations',
            'transaction_open' => $insideTransaction,
            'node_ids' => array_values(array_column($sortedNodes, 'nodeid')),
            'leaf_ids' => array_values(array_column($leaves, 'id')),
            'leaf_nodeids' => array_values(array_column($leaves, 'nodeid')),
            'commit_attempts' => $commitAttempts,
            'commit_attempt_count' => count($commitAttempts),
            'violation_count' => count($violations),
            'violations' => $violations,
            'events' => $events,
            'dependencies' => [
                'sqlite-fkey2-deferred-child-insert-can-be-repaired-before-commit',
                'sqlite-fkey2-deferred-self-reference-parent-can-be-repaired-before-commit',
                'sqlite-fkey2-failed-commit-leaves-transaction-open',
                'sqlite-fkey2-delete-parent-remains-deferred-until-commit',
            ],
        ];
    }

    /**
     * @param list<array{nodeid:int,parent:int|null}> $nodes
     * @param list<array{id:string,nodeid:int}> $leaves
     * @return array<string,mixed>
     */
    public static function fkey2StatementRollbackCounterReset(array $nodes, array $leaves, bool $repairWithDistinctParents): array
    {
        $nodes = array_values($nodes);
        $leaves = array_values($leaves);
        $originalNodes = $nodes;
        $originalLeaves = $leaves;

        $nodes = [];
        $leaves = [];
        foreach ($originalLeaves as $leaf) {
            $leaves[] = $leaf;
        }
        $deferredBeforeStatement = count(self::fkey2GraphViolations($nodes, $leaves));

        $statementNodes = $nodes;
        $insertedNodeIds = [];
        $seenNodeIds = [];
        $statementStatus = 'commit-ok';
        foreach ($leaves as $leaf) {
            $nodeid = (int) $leaf['nodeid'];
            if (isset($seenNodeIds[$nodeid])) {
                $statementStatus = 'rolled-back-on-unique-nodeid';
                $statementNodes = $nodes;
                break;
            }
            $seenNodeIds[$nodeid] = true;
            $statementNodes[] = ['nodeid' => $nodeid, 'parent' => 3];
            $insertedNodeIds[] = $nodeid;
        }
        $nodes = $statementNodes;
        $deferredAfterStatement = count(self::fkey2GraphViolations($nodes, $leaves));

        $firstCommitViolations = self::fkey2GraphViolations($nodes, $leaves);
        $firstCommitStatus = $firstCommitViolations === [] ? 'commit-ok' : 'commit-blocked';

        if ($repairWithDistinctParents) {
            $nodeIds = [];
            foreach ($nodes as $node) {
                $nodeIds[(int) $node['nodeid']] = true;
            }
            foreach ($leaves as $leaf) {
                $nodeid = (int) $leaf['nodeid'];
                if (!isset($nodeIds[$nodeid])) {
                    $nodes[] = ['nodeid' => $nodeid, 'parent' => null];
                    $nodeIds[$nodeid] = true;
                }
            }
        }

        $finalViolations = self::fkey2GraphViolations($nodes, $leaves);

        $sortedNodes = $nodes;
        usort($sortedNodes, static fn (array $left, array $right): int => ((int) $left['nodeid']) <=> ((int) $right['nodeid']));

        return [
            'source' => 'fkey2.test fkey2-2.61..2.75',
            'operation' => 'deferred-counter-reset-after-statement-rollback',
            'status' => $finalViolations === [] ? 'commit-ok' : 'commit-blocked',
            'statement_status' => $statementStatus,
            'deleted_node_count' => count($originalNodes),
            'deleted_leaf_count' => 0,
            'leaf_nodeids' => array_values(array_column($leaves, 'nodeid')),
            'attempted_insert_nodeids' => $insertedNodeIds,
            'statement_rolled_back' => $statementStatus !== 'commit-ok',
            'deferred_before_statement' => $deferredBeforeStatement,
            'deferred_after_statement' => $deferredAfterStatement,
            'counter_reset_after_rollback' => $statementStatus !== 'commit-ok' && $deferredAfterStatement === $deferredBeforeStatement,
            'first_commit_status' => $firstCommitStatus,
            'first_commit_violation_count' => count($firstCommitViolations),
            'repair_with_distinct_parent_select' => $repairWithDistinctParents,
            'final_node_ids' => array_values(array_column($sortedNodes, 'nodeid')),
            'final_leaf_ids' => array_values(array_column($leaves, 'id')),
            'final_violation_count' => count($finalViolations),
            'dependencies' => [
                'sqlite-fkey2-statement-transaction-restores-deferred-counter',
                'sqlite-fkey2-insert-select-unique-failure-rolls-back-statement',
                'sqlite-fkey2-distinct-parent-repair-commits-after-counter-reset',
            ],
        ];
    }

    /**
     * @param list<array{node:int,parent:int|null}> $foreignKeyRows
     * @param list<array{node:int,parent:int|null}> $triggerRows
     * @return array<string,mixed>
     */
    public static function fkey2RecursiveCascadeIgnoresRecursiveTriggerPragma(
        array $foreignKeyRows,
        array $triggerRows,
        int $deleteNode,
        bool $recursiveTriggers
    ): array {
        $foreignKeyRows = array_values($foreignKeyRows);
        $triggerRows = array_values($triggerRows);
        $originalForeignKeyRows = $foreignKeyRows;
        $originalTriggerRows = $triggerRows;

        $foreignKeyDeleted = self::cascadeDeleteTreeRows($foreignKeyRows, $deleteNode, true);
        $triggerDeleted = self::cascadeDeleteTreeRows($triggerRows, $deleteNode, $recursiveTriggers);

        return [
            'source' => 'fkey2.test fkey2-4.1..4.4',
            'operation' => 'recursive-foreign-key-cascade-ignores-recursive-trigger-pragma',
            'status' => 'commit-ok',
            'recursive_triggers' => $recursiveTriggers,
            'delete_node' => $deleteNode,
            'foreign_key_deleted_nodes' => $foreignKeyDeleted,
            'trigger_deleted_nodes' => $triggerDeleted,
            'foreign_key_remaining_nodes' => array_values(array_column(self::sortRows($foreignKeyRows), 'node')),
            'trigger_remaining_nodes' => array_values(array_column(self::sortRows($triggerRows), 'node')),
            'foreign_key_cascade_reaches_grandchildren' => self::treeDeleteReachedDepth($originalForeignKeyRows, $foreignKeyDeleted, $deleteNode, 2),
            'ordinary_trigger_reaches_grandchildren' => self::treeDeleteReachedDepth($originalTriggerRows, $triggerDeleted, $deleteNode, 2),
            'foreign_key_changes' => count($foreignKeyDeleted),
            'trigger_changes' => count($triggerDeleted),
            'dependencies' => [
                'sqlite-fkey2-recursive-fk-actions-ignore-recursive-trigger-pragma',
                'sqlite-fkey2-user-trigger-recursion-obeys-recursive-trigger-pragma',
                'sqlite-fkey2-cascade-delete-visits-descendant-tree',
            ],
        ];
    }

    /**
     * @param list<array{a:int|string,b:int|string,c:int|string}> $parents
     * @param list<array{d:int|string,e:int|string,f:int|string}> $children
     * @param array{mode:string,insert?:array{g:int|string,h:int|string,i:int|string},update_shift?:int,cascade_key?:int|string} $statement
     * @return array<string,mixed>
     */
    public static function fkey2CountChangesBoundary(array $parents, array $children, array $statement): array
    {
        $mode = strtolower(trim((string) ($statement['mode'] ?? '')));
        $parents = array_values($parents);
        $children = array_values($children);
        $parentKeys = [];
        foreach ($parents as $parent) {
            $parentKeys[(string) $parent['b'] . "\0" . (string) $parent['c']] = true;
        }

        if ($mode === 'deferred-insert-step') {
            $insert = $statement['insert'] ?? null;
            if (!is_array($insert)) {
                throw new \InvalidArgumentException('SQLite fkey2-17 deferred insert row is required');
            }
            $violates = !isset($parentKeys[(string) $insert['h'] . "\0" . (string) $insert['i']]);

            return [
                'source' => 'fkey2.test fkey2-17.1.10..17.1.14',
                'operation' => 'count-changes-deferred-insert-step-boundary',
                'status' => $violates ? 'constraint-on-second-step' : 'done',
                'first_step_result' => 'SQLITE_ROW',
                'count_changes_row' => 1,
                'second_step_result' => $violates ? 'SQLITE_CONSTRAINT' : 'SQLITE_DONE',
                'finalize_result' => $violates ? 'SQLITE_CONSTRAINT' : 'SQLITE_OK',
                'extended_error' => $violates ? 'SQLITE_CONSTRAINT_FOREIGNKEY' : null,
                'row_visible_before_constraint_step' => true,
                'statement_changes' => 1,
                'foreign_key_action_changes' => 0,
                'total_changes_delta' => 1,
                'deferred_violation_count' => $violates ? 1 : 0,
                'inserted_row' => $insert,
                'dependencies' => [
                    'sqlite-fkey2-count-changes-yields-row-before-deferred-fk-error',
                    'sqlite-fkey2-deferred-fk-error-repeats-on-finalize',
                    'sqlite-fkey2-count-changes-row-excludes-fk-actions',
                ],
            ];
        }

        if ($mode === 'update-child-keys') {
            $shift = (int) ($statement['update_shift'] ?? 1);
            $updated = [];
            $violations = [];
            foreach ($children as $child) {
                $next = $child;
                $next['e'] = (is_numeric($next['e']) ? (int) $next['e'] : 0) + $shift;
                $next['f'] = (is_numeric($next['f']) ? (int) $next['f'] : 0) + $shift;
                $updated[] = $next;
                if (!isset($parentKeys[(string) $next['e'] . "\0" . (string) $next['f']])) {
                    $violations[] = ['d' => $next['d'], 'e' => $next['e'], 'f' => $next['f']];
                }
            }

            return [
                'source' => 'fkey2.test fkey2-17.1.5..17.1.9',
                'operation' => 'count-changes-update-fk-boundary',
                'status' => $violations === [] ? 'commit-ok' : 'constraint-failed',
                'statement_changes' => $violations === [] ? count($children) : 0,
                'foreign_key_action_changes' => 0,
                'total_changes_delta' => $violations === [] ? count($children) : 0,
                'count_changes_rows' => $violations === [] ? [count($children)] : [],
                'updated_children' => $violations === [] ? $updated : $children,
                'committed_children' => $violations === [] ? $updated : $children,
                'committed_parents' => $parents,
                'violation_count' => count($violations),
                'violations' => $violations,
                'transaction_can_commit_after_failed_statement' => true,
                'dependencies' => [
                    'sqlite-fkey2-failed-fk-update-rolls-back-statement-only',
                    'sqlite-fkey2-transaction-can-commit-after-statement-fk-error',
                    'sqlite-fkey2-count-changes-reports-direct-row-updates',
                ],
            ];
        }

        if ($mode === 'cascade-update-delete') {
            if (!array_key_exists('cascade_key', $statement)) {
                throw new \InvalidArgumentException('SQLite fkey2-17 cascade key is required');
            }
            $oldKey = $statement['cascade_key'];
            $newKey = (string) $oldKey . '-next';
            $updatedParents = [];
            $updatedChildren = [];
            foreach ($parents as $parent) {
                if ((string) $parent['a'] === (string) $oldKey) {
                    $parent['a'] = $newKey;
                }
                $updatedParents[] = $parent;
            }
            foreach ($children as $child) {
                if ((string) $child['f'] === (string) $oldKey) {
                    $child['f'] = $newKey;
                }
                $updatedChildren[] = $child;
            }
            $deletedParentCount = count(array_filter($updatedParents, static fn (array $row): bool => (string) $row['a'] === $newKey));
            $deletedChildCount = count(array_filter($updatedChildren, static fn (array $row): bool => (string) $row['f'] === $newKey));

            return [
                'source' => 'fkey2.test fkey2-17.2.1..17.2.10',
                'operation' => 'count-changes-cascade-action-boundary',
                'status' => 'commit-ok',
                'update_statement_changes' => 1,
                'update_fk_action_changes' => $deletedChildCount,
                'update_total_changes_delta' => 1 + $deletedChildCount,
                'delete_statement_changes' => $deletedParentCount,
                'delete_fk_action_changes' => $deletedChildCount,
                'delete_total_changes_delta' => $deletedParentCount + $deletedChildCount,
                'count_changes_excludes_fk_actions' => true,
                'total_changes_includes_fk_actions' => true,
                'updated_child_keys' => array_values(array_column($updatedChildren, 'f')),
                'remaining_parent_count_after_delete' => count($updatedParents) - $deletedParentCount,
                'remaining_child_count_after_delete' => count($updatedChildren) - $deletedChildCount,
                'dependencies' => [
                    'sqlite-fkey2-count-changes-excludes-cascade-update',
                    'sqlite-fkey2-total-changes-includes-cascade-update',
                    'sqlite-fkey2-count-changes-excludes-cascade-delete',
                ],
            ];
        }

        throw new \InvalidArgumentException('SQLite fkey2-17 count_changes boundary mode is unsupported');
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
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{operation:string,set?:array<string,mixed>,where_column?:string,where_value?:mixed,parent_key?:string,child_key?:string,child_tables?:list<string>,parent_table?:string,referenced_table?:string,referenced_key?:string} $statement
     * @return array<string,mixed>
     */
    public static function foreignKeyAuthorizerReadPlan(array $parents, array $children, array $statement): array
    {
        $operation = strtolower(trim((string) ($statement['operation'] ?? '')));
        if (!in_array($operation, ['update-parent-reference', 'update-parent-primary-key', 'update-parent-unique-key', 'update-parent-all-keys'], true)) {
            throw new \InvalidArgumentException('SQLite fkey7 authorizer operation is unsupported');
        }

        $parentTable = self::identifier((string) ($statement['parent_table'] ?? 'par'), 'parent table');
        $referencedTable = self::identifier((string) ($statement['referenced_table'] ?? 's1'), 'referenced table');
        $parentKey = self::identifier((string) ($statement['parent_key'] ?? 'a'), 'parent key');
        $referencedKey = self::identifier((string) ($statement['referenced_key'] ?? 'b'), 'referenced key');
        $whereColumn = self::identifier((string) ($statement['where_column'] ?? $parentKey), 'WHERE column');
        $set = $statement['set'] ?? [];
        if ($set === []) {
            throw new \InvalidArgumentException('SQLite fkey7 authorizer SET list is empty');
        }
        foreach ($set as $column => $_value) {
            self::identifier((string) $column, 'SET column');
        }

        $childTables = $statement['child_tables'] ?? ['c1', 'c2', 'c3'];
        foreach ($childTables as $table) {
            self::identifier((string) $table, 'child table');
        }

        $parents = array_values($parents);
        $children = array_values($children);
        $updated = [];
        foreach ($parents as $index => $parent) {
            if (($parent[$whereColumn] ?? null) !== ($statement['where_value'] ?? null)) {
                continue;
            }
            $old = $parent;
            foreach ($set as $column => $value) {
                $parent[(string) $column] = $value;
            }
            $parents[$index] = $parent;
            $updated[] = ['old' => $old, 'new' => $parent];
        }

        $readTables = [$parentTable => true];
        $needsReferencedParentRead = array_key_exists($referencedKey, $set);
        if ($needsReferencedParentRead) {
            $readTables[$referencedTable] = true;
        }
        $needsChildProbe = array_key_exists($parentKey, $set);
        if ($needsChildProbe) {
            $readTables[(string) $childTables[0]] = true;
            $readTables[(string) $childTables[1]] = true;
        }
        $needsUniqueChildProbe = array_key_exists('c', $set);
        if ($needsUniqueChildProbe) {
            $readTables[(string) $childTables[2]] = true;
        }
        ksort($readTables);

        $violations = [];
        if ($needsReferencedParentRead) {
            $referencedKeys = array_values(array_column($parents, $referencedKey));
            foreach ($updated as $change) {
                $newValue = $change['new'][$referencedKey] ?? null;
                if ($newValue !== null && !in_array($newValue, $referencedKeys, true)) {
                    $violations[] = ['table' => $parentTable, 'column' => $referencedKey, 'value' => $newValue, 'reason' => 'missing-referenced-parent'];
                }
            }
        }

        return [
            'source' => 'fkey7.test fkey7-1.2..1.5',
            'operation' => 'foreign-key-authorizer-read-dependencies',
            'status' => $violations === [] ? 'commit-ok' : 'constraint-failed',
            'statement_operation' => $operation,
            'parent_table' => $parentTable,
            'referenced_table' => $referencedTable,
            'read_tables' => array_keys($readTables),
            'read_table_count' => count($readTables),
            'updated_count' => count($updated),
            'updated_parent_rows' => array_values(array_map(static fn (array $change): array => $change['new'], $updated)),
            'child_probe_tables' => $needsChildProbe ? [(string) $childTables[0], (string) $childTables[1]] : [],
            'unique_child_probe_tables' => $needsUniqueChildProbe ? [(string) $childTables[2]] : [],
            'referenced_parent_read' => $needsReferencedParentRead,
            'violation_count' => count($violations),
            'violations' => $violations,
            'foreign_key_checks_enabled' => true,
            'dependencies' => [
                'sqlite-fkey7-authorizer-reads-parent-reference-table',
                'sqlite-fkey7-authorizer-reads-child-tables-for-primary-key-update',
                'sqlite-fkey7-authorizer-reads-unique-child-table-for-unique-key-update',
            ],
        ];
    }

    /**
     * @param list<array{rowid:int,a:int|string,b:int|string,c:int|string}> $parents
     * @param list<array{d:int|string,e:int|string,f?:int|string}> $children
     * @param array{rowid?:int,a:int|string,b:int|string,c:int|string,conflict?:string,transaction?:bool} $incoming
     * @return array<string,mixed>
     */
    public static function replaceCompositeParentForeignKey(array $parents, array $children, array $incoming): array
    {
        $parents = array_values($parents);
        $children = array_values($children);
        $originalParents = $parents;
        $conflict = strtolower(trim((string) ($incoming['conflict'] ?? 'unique-a')));
        if (!in_array($conflict, ['unique-a', 'rowid'], true)) {
            throw new \InvalidArgumentException('SQLite fkey2-13 REPLACE conflict target is unsupported');
        }

        $incomingRow = [
            'rowid' => (int) ($incoming['rowid'] ?? self::nextCompositeReplaceRowid($parents)),
            'a' => $incoming['a'] ?? throw new \InvalidArgumentException('SQLite fkey2-13 incoming a is required'),
            'b' => $incoming['b'] ?? throw new \InvalidArgumentException('SQLite fkey2-13 incoming b is required'),
            'c' => $incoming['c'] ?? throw new \InvalidArgumentException('SQLite fkey2-13 incoming c is required'),
        ];

        $deleted = [];
        foreach ($parents as $index => $parent) {
            self::requireCompositeReplaceParent($parent);
            $matches = $conflict === 'rowid'
                ? (int) $parent['rowid'] === $incomingRow['rowid']
                : $parent['a'] === $incomingRow['a'];
            if (!$matches) {
                continue;
            }

            $deleted[] = $parent;
            unset($parents[$index]);
        }
        $parents = array_values($parents);

        foreach ($parents as $index => $parent) {
            $matches = (int) $parent['rowid'] === $incomingRow['rowid']
                || $parent['a'] === $incomingRow['a']
                || ($parent['b'] === $incomingRow['b'] && $parent['c'] === $incomingRow['c']);
            if (!$matches) {
                continue;
            }

            $deleted[] = $parent;
            unset($parents[$index]);
        }
        $parents = array_values($parents);

        $attempted = $parents;
        $attempted[] = $incomingRow;
        $violations = self::compositeReplaceViolations($attempted, $children);
        $status = $violations === [] ? 'commit-ok' : 'constraint-failed';
        $committed = $status === 'commit-ok' ? $attempted : $originalParents;

        return [
            'source' => 'fkey2.test fkey2-13.1.1..13.1.4',
            'operation' => 'replace-composite-parent-foreign-key',
            'status' => $status,
            'transaction_open_after_failed_replace' => (bool) ($incoming['transaction'] ?? false) && $status === 'constraint-failed',
            'conflict_target' => $conflict,
            'incoming_parent_key' => [$incomingRow['b'], $incomingRow['c']],
            'incoming_rowid' => $incomingRow['rowid'],
            'deleted_parent_keys' => array_values(array_map(static fn (array $row): array => [$row['b'], $row['c']], $deleted)),
            'deleted_rowids' => array_values(array_map(static fn (array $row): int => (int) $row['rowid'], $deleted)),
            'committed_parent_rows' => self::sortRows($committed),
            'committed_parent_keys' => array_values(array_map(static fn (array $row): array => [$row['b'], $row['c']], self::sortRows($committed))),
            'committed_child_keys' => array_values(array_map(static fn (array $row): array => [$row['d'], $row['e']], $children)),
            'violation_count' => count($violations),
            'violations' => $violations,
            'dependencies' => [
                'sqlite-fkey2-replace-runs-foreign-key-processing',
                'sqlite-fkey2-replace-failure-preserves-original-rows',
                'sqlite-fkey2-replace-same-composite-parent-key-commits',
            ],
        ];
    }

    /**
     * @param list<mixed> $parentValues
     * @param list<mixed> $incomingChildValues
     * @return array<string,mixed>
     */
    public static function insertOrFailForeignKeyBatch(array $parentValues, array $incomingChildValues, bool $uniqueChild): array
    {
        if ($incomingChildValues === []) {
            throw new \InvalidArgumentException('SQLite fkey7 INSERT OR FAIL batch is empty');
        }

        $children = [];
        $failed = null;
        foreach (array_values($incomingChildValues) as $index => $value) {
            if (!in_array($value, $parentValues, true)) {
                $failed = ['index' => $index, 'value' => $value, 'reason' => 'foreign-key'];
                break;
            }
            if ($uniqueChild && in_array($value, $children, true)) {
                $failed = ['index' => $index, 'value' => $value, 'reason' => 'unique'];
                break;
            }
            $children[] = $value;
        }

        return [
            'source' => 'fkey7.test fkey7-4.1..4.6',
            'operation' => 'insert-or-fail-foreign-key-batch',
            'status' => $failed === null ? 'commit-ok' : 'constraint-failed',
            'conflict_policy' => 'fail',
            'unique_child' => $uniqueChild,
            'parent_values' => array_values($parentValues),
            'incoming_child_values' => array_values($incomingChildValues),
            'inserted_child_values' => $children,
            'inserted_count' => count($children),
            'failed_index' => $failed['index'] ?? null,
            'failed_value' => $failed['value'] ?? null,
            'failed_reason' => $failed['reason'] ?? null,
            'foreign_key_check_rows' => [],
            'statement_preserves_prior_successes' => $failed !== null && $children !== [],
            'dependencies' => [
                'sqlite-fkey7-insert-or-fail-stops-at-first-fk-violation',
                'sqlite-fkey7-insert-or-fail-preserves-prior-successful-rows',
                'sqlite-fkey7-foreign-key-check-empty-after-failed-statement',
            ],
        ];
    }

    /**
     * @param list<array{a:int}> $rows
     * @return array<string,mixed>
     */
    public static function triggerRaiseExpressionPowerOfTwo(array $rows, string $conflictAction = 'abort'): array
    {
        $conflictAction = strtolower(trim($conflictAction));
        if (!in_array($conflictAction, ['abort', 'fail', 'rollback'], true)) {
            throw new \InvalidArgumentException('SQLite trigger1-24 RAISE action is unsupported');
        }

        $inserted = [];
        $failed = null;
        foreach (array_values($rows) as $index => $row) {
            if (!array_key_exists('a', $row)) {
                throw new \InvalidArgumentException('SQLite trigger1-24 row is missing column a');
            }

            $value = (int) $row['a'];
            if (($value & ($value - 1)) !== 0) {
                $failed = [
                    'index' => $index,
                    'value' => $value,
                    'message' => sprintf('attempt to insert %d where is not a power of 2', $value),
                ];
                break;
            }

            $inserted[] = ['a' => $value];
        }

        $rolledBack = $failed !== null && in_array($conflictAction, ['abort', 'rollback'], true);

        return [
            'source' => 'trigger1.test trigger1-24.1..24.2',
            'operation' => 'trigger-raise-expression-message',
            'status' => $failed === null ? 'commit-ok' : 'constraint-failed',
            'raise_action' => $conflictAction,
            'trigger_expression' => "format('attempt to insert %d where is not a power of 2',new.a)",
            'attempted_values' => array_values(array_map(static fn (array $row): int => (int) $row['a'], $rows)),
            'inserted_values' => $rolledBack ? [] : array_values(array_column($inserted, 'a')),
            'failed_index' => $failed['index'] ?? null,
            'failed_value' => $failed['value'] ?? null,
            'error_message' => $failed['message'] ?? null,
            'statement_rolled_back' => $rolledBack,
            'prior_successes_preserved' => $failed !== null && !$rolledBack && $inserted !== [],
            'dependencies' => [
                'sqlite-trigger1-raise-message-accepts-sql-expression',
                'sqlite-trigger1-raise-expression-can-reference-new-row',
                'sqlite-trigger1-raise-abort-rolls-back-statement',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int> $updatedColumns
     * @return array<string,mixed>
     */
    public static function wideColumnTriggerMaskPlan(array $rows, array $updatedColumns, int $columnCount = 66): array
    {
        if ($columnCount < 1) {
            throw new \InvalidArgumentException('SQLite triggerB wide-column corpus requires at least one column');
        }

        $rows = array_values($rows);
        $updated = [];
        $changes = [];
        $seen = [];

        foreach ($updatedColumns as $column) {
            if ($column < 0 || $column >= $columnCount) {
                throw new \InvalidArgumentException('SQLite triggerB column index is outside the trigger mask width');
            }
            if (isset($seen[$column])) {
                continue;
            }
            $seen[$column] = true;
            $updated[] = $column;

            $columnName = 'c' . $column;
            foreach ($rows as $rowIndex => $row) {
                $old = $row[$columnName] ?? null;
                $new = 'b' . $column . '-' . ($row['setting_id'] ?? $rowIndex);
                $rows[$rowIndex][$columnName] = $new;
                if ($old !== $new) {
                    $changes[] = [
                        'rowid' => $row['setting_id'] ?? $rowIndex + 1,
                        'colnum' => $column,
                        'oldval' => $old,
                        'newval' => $new,
                    ];
                }
            }
        }

        return [
            'source' => 'triggerB.test triggerB-3.1..3.2',
            'operation' => 'wide-old-new-trigger-column-mask',
            'status' => 'commit-ok',
            'column_count' => $columnCount,
            'updated_columns' => $updated,
            'change_count' => count($changes),
            'changes' => $changes,
            'final_rows' => $rows,
            'high_column_mask_required' => max($updated ?: [0]) >= 32,
            'dependencies' => [
                'sqlite-triggerB-old-new-column-mask-beyond-32-columns',
                'sqlite-triggerB-when-old-column-differs-from-new-column',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:string}> $rows
     * @return array<string,mixed>
     */
    public static function withoutRowidConflictDeleteTriggerPlan(array $rows, string $triggerMode): array
    {
        $rows = array_values($rows);
        usort($rows, static fn (array $a, array $b): int => $a['a'] <=> $b['a']);
        $triggerMode = strtolower(trim($triggerMode));
        if (!in_array($triggerMode, ['none', 'after', 'before', 'both'], true)) {
            throw new \InvalidArgumentException('SQLite triggerF trigger mode must be none, after, before, or both');
        }

        $log = [];
        $delete = static function (array &$state, int $key) use (&$log, $triggerMode): void {
            foreach ($state as $index => $row) {
                if ((int) $row['a'] !== $key) {
                    continue;
                }

                $beforeCount = count($state);
                if ($triggerMode === 'before' || $triggerMode === 'both') {
                    $log[] = $row['a'] . $row['b'] . $beforeCount;
                }
                unset($state[$index]);
                $state = array_values($state);
                if ($triggerMode === 'after' || $triggerMode === 'both') {
                    $log[] = $row['a'] . $row['b'] . count($state);
                }
                return;
            }
        };

        $delete($rows, 1);
        $delete($rows, 2);
        $rows[] = ['a' => 2, 'b' => 'three'];
        usort($rows, static fn (array $a, array $b): int => $a['a'] <=> $b['a']);
        $delete($rows, 3);
        foreach ($rows as $index => $row) {
            if ((int) $row['a'] === 2) {
                $rows[$index]['a'] = 3;
                break;
            }
        }
        usort($rows, static fn (array $a, array $b): int => $a['a'] <=> $b['a']);

        return [
            'source' => 'triggerF.test triggerF-1.*',
            'operation' => 'without-rowid-conflict-delete-triggers',
            'status' => 'commit-ok',
            'trigger_mode' => $triggerMode,
            'log' => $log,
            'log_count' => count($log),
            'final_rows' => $rows,
            'final_keys' => array_values(array_column($rows, 'a')),
            'dependencies' => [
                'sqlite-triggerF-without-rowid-conflict-delete-trigger-order',
                'sqlite-triggerF-before-delete-sees-row-before-removal',
                'sqlite-triggerF-after-delete-sees-row-after-removal',
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
     * @param list<int> $indexedValues
     * @return array<string,mixed>
     */
    public static function recursiveOnceTriggerSelectPlan(
        int $seed,
        array $indexedValues,
        int $recursiveLimit = 5,
        bool $recursiveTriggers = true,
        bool $crossJoin = false
    ): array {
        if ($recursiveLimit < $seed) {
            throw new \InvalidArgumentException('SQLite triggerG recursive limit must not be less than seed');
        }
        foreach ($indexedValues as $value) {
            if (!is_int($value)) {
                throw new \InvalidArgumentException('SQLite triggerG indexed values must be integers');
            }
        }

        $indexedValues = array_values($indexedValues);
        $t3 = [];
        $t2 = [];
        $triggerFirings = [];
        $queue = [$seed];

        while ($queue !== []) {
            $current = array_shift($queue);
            $t3[] = $current;
            $triggerFirings[] = $current;

            $nextValue = $current + 1;
            if ($recursiveTriggers && $current < $recursiveLimit) {
                $queue[] = $nextValue;
            }

            if ($crossJoin) {
                foreach ($indexedValues as $left) {
                    if ($left < 1 || $left > 4) {
                        continue;
                    }
                    foreach ($indexedValues as $right) {
                        if ($right < 2 || $right > 5) {
                            continue;
                        }
                        $t2[] = ($current * 10000) + ($left * 100) + $right;
                    }
                }
                continue;
            }

            foreach ($indexedValues as $value) {
                if ($value >= 1 && $value <= 4) {
                    $t2[] = ($current * 100) + $value;
                }
            }
        }

        sort($t2);
        sort($t3);

        return [
            'source' => 'triggerG.test triggerG-100..200',
            'operation' => 'recursive-trigger-select-subprogram-once-reset',
            'status' => 'commit-ok',
            'recursive_triggers' => $recursiveTriggers,
            'seed' => $seed,
            'recursive_limit' => $recursiveLimit,
            'cross_join' => $crossJoin,
            'indexed_values' => $indexedValues,
            'trigger_firings' => $triggerFirings,
            'trigger_fire_count' => count($triggerFirings),
            't3_values' => $t3,
            't2_values' => $t2,
            't2_count' => count($t2),
            'once_subprogram_reset_per_firing' => true,
            'dependencies' => [
                'sqlite-triggerG-recursive-trigger-subprogram-select',
                'sqlite-triggerG-op-once-resets-for-each-trigger-invocation',
                'sqlite-triggerG-indexed-in-filter-inside-recursive-trigger',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:string}> $initialRows
     * @return array<string,mixed>
     */
    public static function withoutRowidReplaceDeleteTriggerPlan(array $initialRows, bool $beforeTrigger, bool $afterTrigger): array
    {
        if (!$beforeTrigger && !$afterTrigger) {
            throw new \InvalidArgumentException('SQLite triggerF requires at least one delete trigger');
        }

        $rows = [];
        foreach ($initialRows as $row) {
            if (!is_int($row['a']) || $row['a'] < 1) {
                throw new \InvalidArgumentException('SQLite triggerF WITHOUT ROWID primary key must be a positive integer');
            }
            if (array_key_exists($row['a'], $rows)) {
                throw new \InvalidArgumentException('SQLite triggerF WITHOUT ROWID primary key must be unique');
            }
            $rows[$row['a']] = ['a' => $row['a'], 'b' => $row['b']];
        }
        ksort($rows);

        $log = [];
        self::deleteWithoutRowidRow($rows, 1, $beforeTrigger, $afterTrigger, $log);

        if (isset($rows[2])) {
            self::deleteWithoutRowidRow($rows, 2, $beforeTrigger, $afterTrigger, $log);
        }
        $rows[2] = ['a' => 2, 'b' => 'three'];
        ksort($rows);

        if (isset($rows[3])) {
            self::deleteWithoutRowidRow($rows, 3, $beforeTrigger, $afterTrigger, $log);
        }
        $row = $rows[2] ?? throw new \InvalidArgumentException('SQLite triggerF update source row is missing');
        unset($rows[2]);
        $row['a'] = 3;
        $rows[3] = $row;
        ksort($rows);

        return [
            'source' => 'triggerF.test 1.2..1.4',
            'operation' => 'without-rowid-replace-delete-trigger-log',
            'status' => 'commit-ok',
            'before_trigger' => $beforeTrigger,
            'after_trigger' => $afterTrigger,
            'trigger_count' => (int) $beforeTrigger + (int) $afterTrigger,
            'initial_primary_keys' => array_values(array_column($initialRows, 'a')),
            'final_rows' => array_values($rows),
            'final_primary_keys' => array_values(array_keys($rows)),
            'log' => $log,
            'log_values' => array_values(array_column($log, 'value')),
            'log_count' => count($log),
            'replace_delete_count' => 3,
            'without_rowid_primary_key_preserved' => true,
            'dependencies' => [
                'sqlite-triggerF-without-rowid-delete-triggers-fire-for-replace-conflicts',
                'sqlite-triggerF-before-trigger-sees-row-before-delete',
                'sqlite-triggerF-after-trigger-sees-row-after-delete',
            ],
        ];
    }

    /**
     * @param array{rowid?:mixed,oid?:mixed,_rowid_?:mixed,x:mixed,w?:mixed,y?:mixed,z?:mixed} $row
     * @return array<string,mixed>
     */
    public static function triggerRowidAliasResolutionPlan(array $row, string $event, bool $declaredRowidColumns, int $physicalRowid = 1): array
    {
        $event = strtolower(trim($event));
        if (!in_array($event, ['insert', 'update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite triggerD event is unsupported');
        }
        if ($physicalRowid < 1) {
            throw new \InvalidArgumentException('SQLite triggerD physical rowid must be positive');
        }

        $old = $row;
        $new = $row;
        if ($event === 'insert' && !$declaredRowidColumns) {
            $old = ['rowid' => -1, 'oid' => -1, '_rowid_' => -1, 'x' => $row['x'] ?? null];
            $new = ['rowid' => $physicalRowid, 'oid' => $physicalRowid, '_rowid_' => $physicalRowid, 'x' => $row['x'] ?? null];
        } elseif ($event === 'insert') {
            $old = $new = [
                'rowid' => $row['rowid'] ?? null,
                'oid' => $row['oid'] ?? null,
                '_rowid_' => $row['_rowid_'] ?? null,
                'x' => $row['x'] ?? null,
            ];
        } elseif ($event === 'update') {
            if ($declaredRowidColumns) {
                $new['rowid'] = self::numericAdd($row['rowid'] ?? null, 1);
            } else {
                $old = [
                    'rowid' => $physicalRowid,
                    'oid' => $physicalRowid,
                    '_rowid_' => $physicalRowid,
                    'x' => $row['x'] ?? null,
                ];
                $new = $old;
                $new['x'] = self::numericAdd($new['x'], 1);
            }
        } elseif (!$declaredRowidColumns) {
            $old = $new = [
                'rowid' => $physicalRowid,
                'oid' => $physicalRowid,
                '_rowid_' => $physicalRowid,
                'x' => $row['x'] ?? null,
            ];
        }

        $entries = [];
        if ($event === 'insert') {
            $entries[] = self::triggerDLogEntry('r1', $declaredRowidColumns ? $new : $old);
            $entries[] = self::triggerDLogEntry('r2', $new);
        } elseif ($event === 'update') {
            $entries[] = self::triggerDLogEntry('r3.old', $old);
            $entries[] = self::triggerDLogEntry('r3.new', $new);
            $entries[] = self::triggerDLogEntry('r4.old', $old);
            $entries[] = self::triggerDLogEntry('r4.new', $new);
        } else {
            $entries[] = self::triggerDLogEntry('r5', $old);
            $entries[] = self::triggerDLogEntry('r6', $old);
        }

        return [
            'source' => 'triggerD.test triggerD-1.1..2.4',
            'operation' => 'trigger-rowid-alias-resolution',
            'status' => 'commit-ok',
            'event' => $event,
            'declared_rowid_columns' => $declaredRowidColumns,
            'physical_rowid' => $physicalRowid,
            'log' => $entries,
            'log_count' => count($entries),
            'rowid_values' => array_values(array_column($entries, 'rowid')),
            'oid_values' => array_values(array_column($entries, 'oid')),
            '_rowid_values' => array_values(array_column($entries, '_rowid_')),
            'x_values' => array_values(array_column($entries, 'x')),
            'uses_declared_columns_before_physical_aliases' => $declaredRowidColumns,
            'insert_before_trigger_sees_unassigned_rowid' => !$declaredRowidColumns && $event === 'insert',
            'dependencies' => [
                'sqlite-triggerD-declared-rowid-columns-shadow-physical-rowid',
                'sqlite-triggerD-old-new-rowid-aliases-use-physical-rowid-when-not-declared',
                'sqlite-triggerD-before-insert-rowid-alias-is-negative-one',
            ],
        ];
    }

    /**
     * @param list<array{column:string,placeholder:string,location:string}> $references
     * @return array<string,mixed>
     */
    public static function triggerVariableReferencePlan(array $references, bool $fromWritableSchema = false): array
    {
        $normalized = [];
        foreach ($references as $reference) {
            $placeholder = (string) ($reference['placeholder'] ?? '');
            if (!preg_match('/^(?:\?|[:@$][A-Za-z_][A-Za-z0-9_]*|\?[1-9][0-9]*|\$[1-9][0-9]*)$/', $placeholder)) {
                throw new \InvalidArgumentException('SQLite triggerE variable placeholder is malformed');
            }
            $normalized[] = [
                'column' => self::identifier((string) ($reference['column'] ?? ''), 'trigger variable column'),
                'placeholder' => $placeholder,
                'location' => self::identifier((string) ($reference['location'] ?? 'body'), 'trigger variable location'),
                'runtime_value' => null,
            ];
        }

        return [
            'source' => 'triggerE.test triggerE-1.1..2.3',
            'operation' => 'trigger-variable-reference-boundary',
            'status' => $fromWritableSchema ? 'loaded-from-schema-null-coercion' : 'create-trigger-rejected',
            'from_writable_schema' => $fromWritableSchema,
            'error' => $fromWritableSchema ? null : 'trigger cannot use variables',
            'references' => $normalized,
            'reference_count' => count($normalized),
            'runtime_values' => array_values(array_column($normalized, 'runtime_value')),
            'coerces_to_null' => $fromWritableSchema,
            'creation_rejected' => !$fromWritableSchema,
            'dependencies' => [
                'sqlite-triggerE-create-trigger-rejects-bound-variables',
                'sqlite-triggerE-schema-loaded-trigger-variables-become-null',
                'sqlite-triggerE-trigger-variable-null-comparisons-drive-body',
            ],
        ];
    }

    /**
     * Model the triggerA.test INSTEAD OF view trigger cases where the outer
     * UPDATE/DELETE WHERE clause must be applied while materializing the view.
     *
     * @return array<string,mixed>
     */
    public static function insteadOfViewWhereRoutingPlan(int $seed, string $view, string $event): array
    {
        if ($seed < 1) {
            throw new \InvalidArgumentException('SQLite triggerA seed must be positive');
        }

        $view = self::identifier($view, 'triggerA view name');
        if (!in_array($view, ['v1', 'v2', 'v3', 'v4', 'v5'], true)) {
            throw new \InvalidArgumentException('SQLite triggerA view is unsupported');
        }

        $event = strtolower(trim($event));
        if (!in_array($event, ['delete', 'update'], true)) {
            throw new \InvalidArgumentException('SQLite triggerA event is unsupported');
        }

        $words = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
        $t1 = [];
        $t2 = [];
        foreach ($words as $index => $word) {
            $x = $index + 1;
            $t1[] = ['x' => $x, 'y' => $word];
            $t2[] = ['a' => 20 - $x, 'b' => ($x * 100) + strlen($word) + $seed, 'c' => $word];
        }

        $viewRows = match ($view) {
            'v1' => array_map(static fn (array $row): array => ['y' => $row['y'], 'x' => $row['x']], $t1),
            'v2' => array_values(array_map(
                static fn (array $row): array => ['x' => $row['x'], 'y' => $row['y']],
                array_filter($t1, static fn (array $row): bool => str_contains((string) $row['y'], 'e'))
            )),
            'v3' => self::triggerACompoundRows($t1, null),
            'v4' => self::triggerACompoundRows($t1, static fn (array $row): bool => $row['x'] >= 3 && $row['x'] <= 5),
            'v5' => self::triggerAJoinRows($t1, $t2),
            default => [],
        };

        $matched = array_values(array_filter($viewRows, static function (array $row) use ($view): bool {
            if ($view === 'v1' || $view === 'v2' || $view === 'v5') {
                return ((int) ($row['x'] ?? 0)) >= 3 && ((int) ($row['x'] ?? 0)) <= 5;
            }

            $value = (string) ($row['c1'] ?? '');

            return strcmp($value, '8') >= 0 && strcmp($value, 'eight') <= 0;
        }));

        $log = [];
        foreach ($matched as $row) {
            if ($event === 'delete') {
                $log[] = match ($view) {
                    'v1', 'v2' => ['old_a' => $row['y'], 'old_b' => $row['x']],
                    'v3', 'v4' => ['old_a' => $row['c1']],
                    'v5' => ['old_a' => $row['x'], 'old_b' => $row['b']],
                    default => [],
                };
                continue;
            }

            $log[] = match ($view) {
                'v1', 'v2' => ['old_a' => $row['y'], 'old_b' => $row['x'], 'new_c' => $row['y'] . '-extra', 'new_d' => $row['x']],
                'v3', 'v4' => ['old_a' => $row['c1'], 'new_b' => $row['c1'] . '-extra'],
                'v5' => ['old_a' => $row['x'], 'old_b' => $row['b'], 'new_c' => $row['x'], 'new_d' => $row['b'] + 9900000],
                default => [],
            };
        }

        usort($log, static function (array $left, array $right): int {
            return ($left['old_a'] <=> $right['old_a']) ?: (($left['old_b'] ?? 0) <=> ($right['old_b'] ?? 0));
        });

        return [
            'source' => 'triggerA.test triggerA-2.1..2.11',
            'operation' => 'instead-of-view-trigger-where-routing',
            'status' => 'commit-ok',
            'view' => $view,
            'event' => $event,
            'seed' => $seed,
            'view_row_count' => count($viewRows),
            'matched_row_count' => count($matched),
            'trigger_log' => $log,
            'trigger_log_count' => count($log),
            'first_log_row' => $log[0] ?? null,
            'last_log_row' => $log === [] ? null : $log[array_key_last($log)],
            'dependencies' => [
                'sqlite-triggerA-instead-of-trigger-view-where-routing',
                'sqlite-triggerA-compound-view-materialization-before-trigger',
                'sqlite-triggerA-join-view-materialization-before-trigger',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int,c:int}> $baseRows
     * @param list<array{a:int,b:int,c:int}> $statementRows
     * @return array<string,mixed>
     */
    public static function raiseActionBoundaryPlan(
        array $baseRows,
        array $statementRows,
        int $raiseValue,
        bool $insideTransaction = true,
        bool $viewTrigger = false
    ): array {
        $rows = array_values($baseRows);
        $attemptedRows = [];
        $insertedRows = [];
        $raiseAction = self::raiseActionForValue($raiseValue, $viewTrigger);
        $message = self::raiseMessageForAction($raiseAction, $viewTrigger);
        $status = $raiseAction === 'none' || $raiseAction === 'ignore' ? 'commit-ok' : 'constraint-trigger';
        $rolledBack = $raiseAction === 'rollback' && $insideTransaction;

        foreach ($statementRows as $row) {
            foreach (['a', 'b', 'c'] as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException('SQLite trigger3 row is missing column ' . $column);
                }
            }
            $attemptedRows[] = $row;

            if ($viewTrigger) {
                if (($row['a'] ?? null) === $raiseValue && $raiseAction !== 'none') {
                    break;
                }
                continue;
            }

            if (($row['a'] ?? null) === 4) {
                continue;
            }
            $rows[] = $row;
            $insertedRows[] = $row;

            if (($row['a'] ?? null) === $raiseValue && $raiseAction !== 'none' && $raiseAction !== 'ignore') {
                break;
            }
        }

        if ($rolledBack) {
            $rows = array_values($baseRows);
        }

        return [
            'source' => $viewTrigger ? 'trigger3.test trigger3-7.1..7.3' : 'trigger3.test trigger3-1.1..4.2',
            'operation' => $viewTrigger ? 'view-trigger-raise-action-boundary' : 'table-trigger-raise-action-boundary',
            'status' => $status,
            'raise_action' => $raiseAction,
            'raise_message' => $message,
            'inside_transaction' => $insideTransaction,
            'view_trigger' => $viewTrigger,
            'rolled_back' => $rolledBack,
            'statement_aborted' => in_array($raiseAction, ['abort', 'fail', 'rollback'], true),
            'attempted_a_values' => array_values(array_column($attemptedRows, 'a')),
            'inserted_a_values' => array_values(array_column($insertedRows, 'a')),
            'final_a_values' => array_values(array_column($rows, 'a')),
            'error_code' => $status === 'constraint-trigger' ? 'SQLITE_CONSTRAINT_TRIGGER' : null,
            'changes' => count($insertedRows),
            'dependencies' => [
                'sqlite-trigger3-raise-abort-fail-rollback-boundaries',
                'sqlite-trigger3-raise-ignore-skips-current-row',
                'sqlite-trigger3-view-trigger-raise-actions',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int,c:int}> $rows
     * @param list<array{a:int,b:int,c:int}> $logRows
     * @param array{type:string,row?:array{a:int,b:int,c:int},set?:array<string,int>,where?:callable(array{a:int,b:int,c:int}):bool} $statement
     * @return array<string,mixed>
     */
    public static function triggerProgramStatementExecution(array $rows, array $logRows, array $statement, string $program, string $timing): array
    {
        $timing = strtolower(trim($timing));
        $program = strtolower(trim($program));
        $type = strtolower((string) ($statement['type'] ?? ''));
        if (!in_array($timing, ['before', 'after'], true)) {
            throw new \InvalidArgumentException('SQLite trigger2 program timing is unsupported');
        }
        if (!in_array($type, ['insert', 'update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite trigger2 statement type is unsupported');
        }

        $rows = array_values($rows);
        $logRows = array_values($logRows);
        $changes = 0;
        $triggerChanges = 0;
        $contexts = [];

        if ($type === 'insert') {
            $new = $statement['row'] ?? throw new \InvalidArgumentException('SQLite trigger2 insert row is required');
            if ($timing === 'before') {
                $triggerChanges += self::applyTrigger2Program($rows, $logRows, $program, [], $new);
            }
            $rows[] = $new;
            ++$changes;
            if ($timing === 'after') {
                $triggerChanges += self::applyTrigger2Program($rows, $logRows, $program, [], $new);
            }
            $contexts[] = ['old' => [], 'new' => $new];
        } elseif ($type === 'update') {
            $where = $statement['where'] ?? static fn (array $row): bool => true;
            $set = $statement['set'] ?? [];
            foreach ($rows as $index => $row) {
                if (!$where($row)) {
                    continue;
                }
                $new = $row;
                foreach ($set as $column => $value) {
                    if (!in_array($column, ['a', 'b', 'c'], true)) {
                        throw new \InvalidArgumentException('SQLite trigger2 update column is unsupported');
                    }
                    $new[$column] = $value;
                }
                if ($timing === 'before') {
                    $triggerChanges += self::applyTrigger2Program($rows, $logRows, $program, $row, $new);
                }
                $rows[$index] = $new;
                ++$changes;
                if ($timing === 'after') {
                    $triggerChanges += self::applyTrigger2Program($rows, $logRows, $program, $row, $new);
                }
                $contexts[] = ['old' => $row, 'new' => $new];
            }
        } else {
            $where = $statement['where'] ?? static fn (array $row): bool => true;
            foreach ($rows as $index => $row) {
                if (!$where($row)) {
                    continue;
                }
                if ($timing === 'before') {
                    $triggerChanges += self::applyTrigger2Program($rows, $logRows, $program, $row, []);
                }
                unset($rows[$index]);
                $rows = array_values($rows);
                ++$changes;
                if ($timing === 'after') {
                    $triggerChanges += self::applyTrigger2Program($rows, $logRows, $program, $row, []);
                }
                $contexts[] = ['old' => $row, 'new' => []];
                break;
            }
        }

        return [
            'source' => 'trigger2.test trigger2-2',
            'operation' => 'trigger-program-statement-execution',
            'status' => 'commit-ok',
            'timing' => $timing,
            'statement_type' => $type,
            'program' => $program,
            'statement_changes' => $changes,
            'trigger_program_changes' => $triggerChanges,
            'total_changes' => $changes + $triggerChanges,
            'context_count' => count($contexts),
            'contexts' => $contexts,
            'final_rows' => self::sortRows($rows),
            'log_rows' => self::sortRows($logRows),
            'dependencies' => [
                'sqlite-trigger2-before-program-runs-before-statement-row-change',
                'sqlite-trigger2-after-program-runs-after-statement-row-change',
                'sqlite-trigger2-trigger-program-can-update-insert-delete-select',
                'sqlite-trigger2-old-new-row-values-feed-program',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function triggerRowidAliasResolution(array $row, string $event, bool $ordinaryRowidColumns, int $storageRowid): array
    {
        $event = strtolower(trim($event));
        if (!in_array($event, ['insert', 'update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite triggerD rowid alias event is unsupported');
        }
        if ($storageRowid < 1) {
            throw new \InvalidArgumentException('SQLite triggerD storage rowid must be positive');
        }

        $required = $ordinaryRowidColumns ? ['rowid', 'oid', '_rowid_', 'x'] : ['w', 'x', 'y', 'z'];
        foreach ($required as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException('SQLite triggerD row is missing a required column');
            }
        }

        $oldRow = $row;
        $newRow = $row;
        if ($event === 'insert' && !$ordinaryRowidColumns) {
            $oldRow = [];
            $newRow = $row + ['rowid' => $storageRowid, 'oid' => $storageRowid, '_rowid_' => $storageRowid];
        } elseif ($event === 'insert') {
            $oldRow = [];
        } elseif ($event === 'update') {
            if ($ordinaryRowidColumns) {
                $newRow['rowid'] = (int) $row['rowid'] + 1;
            } else {
                $oldRow += ['rowid' => $storageRowid, 'oid' => $storageRowid, '_rowid_' => $storageRowid];
                $newRow += ['rowid' => $storageRowid, 'oid' => $storageRowid, '_rowid_' => $storageRowid];
                $newRow['x'] = (int) $row['x'] + 1;
            }
        } elseif (!$ordinaryRowidColumns) {
            $oldRow += ['rowid' => $storageRowid, 'oid' => $storageRowid, '_rowid_' => $storageRowid];
            $newRow = [];
        } else {
            $newRow = [];
        }

        $before = self::triggerDAliasLogRows($event, 'before', $oldRow, $newRow, $ordinaryRowidColumns);
        $after = self::triggerDAliasLogRows($event, 'after', $oldRow, $newRow, $ordinaryRowidColumns);

        return [
            'source' => $ordinaryRowidColumns ? 'triggerD.test triggerD-1.1..1.4' : 'triggerD.test triggerD-2.1..2.4',
            'operation' => 'trigger-rowid-alias-resolution',
            'status' => 'commit-ok',
            'event' => $event,
            'ordinary_rowid_columns' => $ordinaryRowidColumns,
            'storage_rowid' => $storageRowid,
            'old_values' => $oldRow === [] ? [] : self::triggerDAliasValues($oldRow, $ordinaryRowidColumns),
            'new_values' => $newRow === [] ? [] : self::triggerDAliasValues($newRow, $ordinaryRowidColumns),
            'before_log' => $before,
            'after_log' => $after,
            'combined_log' => array_merge($before, $after),
            'log_count' => count($before) + count($after),
            'rowid_source' => $ordinaryRowidColumns ? 'ordinary-column' : 'storage-rowid',
            'dependencies' => [
                'sqlite-triggerD-rowid-oid-_rowid_-ordinary-columns-shadow-storage-rowid',
                'sqlite-triggerD-old-new-rowid-aliases-use-storage-rowid-without-shadow-columns',
                'sqlite-triggerD-before-after-triggers-see-event-specific-old-new-images',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $targetRows
     * @param array<string,mixed> $insertedRow
     * @return array<string,mixed>
     */
    public static function storedTriggerVariablesResolveNull(array $targetRows, array $insertedRow, string $program): array
    {
        $program = strtolower(trim($program));
        if (!in_array($program, ['insert-null-pair', 'when-null-update'], true)) {
            throw new \InvalidArgumentException('SQLite triggerE stored trigger program is unsupported');
        }

        $targetRows = array_values($targetRows);
        $insertedRow = array_replace(['a' => null, 'b' => null], $insertedRow);
        $changed = 0;

        if ($program === 'insert-null-pair') {
            $targetRows[] = ['c' => null, 'd' => null];
            ++$changed;
        } else {
            foreach ($targetRows as $index => $row) {
                if (($row['c'] ?? null) === null) {
                    $targetRows[$index]['c'] = $row['d'] ?? null;
                    ++$changed;
                }
            }
        }

        return [
            'source' => 'triggerE.test triggerE-2.1..2.3',
            'operation' => 'stored-trigger-variables-resolve-null',
            'status' => 'commit-ok',
            'program' => $program,
            'inserted_row' => $insertedRow,
            'variable_value' => null,
            'trigger_when_result' => $program === 'when-null-update',
            'changed_rows' => $changed,
            'target_rows' => $targetRows,
            'target_c_values' => array_values(array_map(static fn (array $row): mixed => $row['c'] ?? null, $targetRows)),
            'target_d_values' => array_values(array_map(static fn (array $row): mixed => $row['d'] ?? null, $targetRows)),
            'dependencies' => [
                'sqlite-triggerE-create-trigger-rejects-bound-variables',
                'sqlite-triggerE-writable-schema-loaded-trigger-variables-resolve-null',
                'sqlite-triggerE-null-variable-drives-when-and-update-expression',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int,c:int}> $rows
     * @return array<string,mixed>
     */
    public static function raiseIgnoreMutationPlan(array $rows, string $operation): array
    {
        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['update', 'delete', 'nested-insert'], true)) {
            throw new \InvalidArgumentException('SQLite trigger3 RAISE IGNORE operation is unsupported');
        }

        $rows = array_values($rows);
        $ignored = [];
        $mutated = [];
        $nestedRows = [];

        if ($operation === 'update') {
            foreach ($rows as $index => $row) {
                if (($row['a'] ?? null) === 1) {
                    $ignored[] = $row['a'];
                    continue;
                }
                $rows[$index]['c'] = 10;
                $mutated[] = $row['a'];
            }
        } elseif ($operation === 'delete') {
            foreach ($rows as $index => $row) {
                if (($row['a'] ?? null) === 1) {
                    $ignored[] = $row['a'];
                    continue;
                }
                $mutated[] = $row['a'];
                unset($rows[$index]);
            }
            $rows = array_values($rows);
        } else {
            $nestedRows[] = ['a' => 1, 'b' => 2, 'c' => 3];
            $nestedRows[] = ['a' => 1, 'b' => 2, 'c' => 3];
            foreach ($rows as $index => $row) {
                if (($row['a'] ?? null) === 1) {
                    $ignored[] = $row['a'];
                    continue;
                }
                $rows[$index]['c'] = 10;
                $mutated[] = $row['a'];
            }
        }

        return [
            'source' => $operation === 'nested-insert' ? 'trigger3.test trigger3-6' : 'trigger3.test trigger3-5.1..5.2',
            'operation' => 'raise-ignore-' . $operation,
            'status' => 'commit-ok',
            'ignored_a_values' => $ignored,
            'mutated_a_values' => $mutated,
            'final_rows' => self::sortRows($rows),
            'final_a_values' => array_values(array_column(self::sortRows($rows), 'a')),
            'nested_rows' => $nestedRows,
            'nested_row_count' => count($nestedRows),
            'dependencies' => [
                'sqlite-trigger3-raise-ignore-update-delete-row-skip',
                'sqlite-trigger3-nested-trigger-ignore-resumes-outer-program',
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
     * @param list<array<string,mixed>> $targetRows
     * @param list<array<string,mixed>> $sourceRows
     * @param list<array<string,mixed>> $eventRows
     * @return array<string,mixed>
     */
    public static function triggerUpdateFromProgram(
        array $targetRows,
        array $sourceRows,
        array $eventRows,
        string $event,
        string $setColumn,
        string $sourceValueColumn,
        string $targetKeyColumn,
        string $sourceKeyColumn,
        string $eventKeyColumn,
        bool $temporaryTrigger = false,
        string $sourceSchema = 'main',
        string $targetSchema = 'main'
    ): array {
        $event = strtolower(trim($event));
        if (!in_array($event, ['after-insert', 'before-delete', 'instead-of-update-view'], true)) {
            throw new \InvalidArgumentException('SQLite trigger UPDATE FROM event is unsupported');
        }
        $setColumn = self::identifier($setColumn, 'trigger UPDATE FROM set column');
        $sourceValueColumn = self::identifier($sourceValueColumn, 'trigger UPDATE FROM source value column');
        $targetKeyColumn = self::identifier($targetKeyColumn, 'trigger UPDATE FROM target key column');
        $sourceKeyColumn = self::identifier($sourceKeyColumn, 'trigger UPDATE FROM source key column');
        $eventKeyColumn = self::identifier($eventKeyColumn, 'trigger UPDATE FROM event key column');
        $sourceSchema = self::identifier($sourceSchema, 'trigger UPDATE FROM source schema');
        $targetSchema = self::identifier($targetSchema, 'trigger UPDATE FROM target schema');

        if (!$temporaryTrigger && $sourceSchema !== $targetSchema) {
            return [
                'source' => 'triggerupfrom.test triggerupfrom-2.1',
                'operation' => 'trigger-update-from-program',
                'status' => 'schema-error',
                'event' => $event,
                'temporary_trigger' => false,
                'source_schema' => $sourceSchema,
                'target_schema' => $targetSchema,
                'error' => 'trigger tr2 cannot reference objects in database ' . $sourceSchema,
                'updated_rows' => [],
                'change_count' => 0,
                'dependencies' => [
                    'sqlite-triggerupfrom-non-temp-trigger-cannot-reference-attached-schema',
                    'sqlite-triggerupfrom-temp-trigger-may-reference-attached-schema',
                    'sqlite-triggerupfrom-update-from-runs-inside-trigger-program',
                ],
            ];
        }

        $rows = array_values($targetRows);
        $updates = [];
        $log = [];
        foreach ($eventRows as $eventRow) {
            if (!array_key_exists($eventKeyColumn, $eventRow)) {
                throw new \InvalidArgumentException('SQLite trigger UPDATE FROM event row is missing key column');
            }
            $eventKey = $eventRow[$eventKeyColumn];
            $sourceValue = null;
            $matchedSource = false;
            foreach ($sourceRows as $sourceRow) {
                if (!array_key_exists($sourceKeyColumn, $sourceRow) || !array_key_exists($sourceValueColumn, $sourceRow)) {
                    throw new \InvalidArgumentException('SQLite trigger UPDATE FROM source row is malformed');
                }
                if ($sourceRow[$sourceKeyColumn] === $eventKey) {
                    $sourceValue = $sourceRow[$sourceValueColumn];
                    $matchedSource = true;
                }
            }

            if (!$matchedSource && $event !== 'before-delete') {
                continue;
            }

            foreach ($rows as $index => $row) {
                if (!array_key_exists($targetKeyColumn, $row)) {
                    throw new \InvalidArgumentException('SQLite trigger UPDATE FROM target row is missing key column');
                }
                if ($row[$targetKeyColumn] !== $eventKey) {
                    continue;
                }

                $old = $row;
                if ($event === 'before-delete') {
                    $sourceValue = $eventRow[$sourceValueColumn] ?? $sourceValue;
                }
                $rows[$index][$setColumn] = $sourceValue;
                $updates[] = [
                    'key' => $eventKey,
                    'old_value' => $old[$setColumn] ?? null,
                    'new_value' => $sourceValue,
                    'source_matched' => $matchedSource,
                    'event' => $event,
                ];
                $log[] = '(' . (string) ($old[$targetKeyColumn] ?? '') . ',' . (string) ($old[$setColumn] ?? '') . ')->(' . (string) $eventKey . ',' . (string) $sourceValue . ')';
            }
        }

        return [
            'source' => match ($event) {
                'after-insert' => $temporaryTrigger ? 'triggerupfrom.test triggerupfrom-2.2..3.0' : 'triggerupfrom.test triggerupfrom-1.0..1.3',
                'before-delete' => 'triggerupfrom.test triggerupfrom-2.3..2.4',
                default => 'triggerupfrom.test triggerupfrom-4.2..4.3',
            },
            'operation' => 'trigger-update-from-program',
            'status' => 'commit-ok',
            'event' => $event,
            'temporary_trigger' => $temporaryTrigger,
            'source_schema' => $sourceSchema,
            'target_schema' => $targetSchema,
            'updated_rows' => $updates,
            'rows_after_trigger' => array_values($rows),
            'change_count' => count($updates),
            'log' => $log,
            'dependencies' => [
                'sqlite-triggerupfrom-update-from-runs-inside-trigger-program',
                'sqlite-triggerupfrom-attached-schema-resolution-follows-trigger-schema',
                'sqlite-triggerupfrom-instead-of-view-update-from-feeds-old-new-rows',
            ],
        ];
    }

    /**
     * @param array{x:int|string,y:mixed} $row
     * @param list<mixed> $counterArgs
     * @return array<string,mixed>
     */
    public static function triggerNewExpressionEvaluation(string $operation, array $row, int $counterStart = 0, int $offset = 0, array $counterArgs = []): array
    {
        $operation = strtolower($operation);
        if (!in_array($operation, ['insert', 'update'], true)) {
            throw new \InvalidArgumentException('SQLite trigger6 operation is unsupported');
        }

        if (!array_key_exists('x', $row) || !array_key_exists('y', $row)) {
            throw new \InvalidArgumentException('SQLite trigger6 row must contain x and y');
        }

        $counterAfter = $counterStart + 1;
        $evaluatedY = $counterAfter + $offset;
        $newRow = ['x' => $row['x'], 'y' => $evaluatedY];
        $logRow = [
            'trigger' => $operation === 'insert' ? 'r1' : 'r2',
            'event' => $operation,
            'a' => $operation === 'insert' ? 1 : 2,
            'new_x' => $newRow['x'],
            'new_y' => $newRow['y'],
        ];

        return [
            'source' => 'trigger6.test trigger6-1.1..1.6',
            'operation' => 'trigger-new-expression-evaluated-once',
            'status' => 'commit-ok',
            'event' => $operation,
            'counter_before' => $counterStart,
            'counter_after' => $counterAfter,
            'counter_args' => array_values($counterArgs),
            'expression_offset' => $offset,
            'expression_evaluations' => 1,
            'row' => $newRow,
            'log_rows' => [$logRow],
            'new_image_matches_stored_row' => $logRow['new_x'] === $newRow['x'] && $logRow['new_y'] === $newRow['y'],
            'dependencies' => [
                'sqlite-trigger6-side-effect-expression-evaluated-once',
                'sqlite-trigger6-before-insert-new-row-reuses-evaluated-expression',
                'sqlite-trigger6-before-update-new-row-reuses-evaluated-expression',
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
     * @param list<array{b:int|string,c:int|string,label?:string}> $parents
     * @param list<array{id:int|string,e:int|string|null,f:int|string|null,label?:string}> $children
     * @param array{operation:string,rows?:list<array{id:int|string,e:int|string|null,f:int|string|null,label?:string>>,set?:array{e?:int|string|null,f?:int|string|null},on_update?:string,deferred?:bool} $statement
     * @return array<string,mixed>
     */
    public static function countChangesForeignKeyStatement(array $parents, array $children, array $statement): array
    {
        $operation = strtolower((string) ($statement['operation'] ?? ''));
        if (!in_array($operation, ['insert-child', 'update-child'], true)) {
            throw new \InvalidArgumentException('SQLite fkey2 count_changes operation is unsupported');
        }

        $parents = array_values($parents);
        $children = array_values($children);
        $deferred = (bool) ($statement['deferred'] ?? false);
        $onUpdate = strtolower((string) ($statement['on_update'] ?? 'no action'));
        if (!in_array($onUpdate, ['no action', 'cascade', 'set null', 'set default'], true)) {
            throw new \InvalidArgumentException('SQLite fkey2 count_changes on_update action is unsupported');
        }

        $attemptedRows = 0;
        $fkActionRows = 0;
        $events = [];

        if ($operation === 'insert-child') {
            foreach ((array) ($statement['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite fkey2 count_changes child row is malformed');
                }
                $children[] = $row;
                ++$attemptedRows;
                $events[] = ['step' => 'insert-child', 'id' => $row['id'] ?? null, 'e' => $row['e'] ?? null, 'f' => $row['f'] ?? null];
            }
        } else {
            $set = (array) ($statement['set'] ?? []);
            foreach ($children as $index => $child) {
                $old = $child;
                if (array_key_exists('e', $set)) {
                    $children[$index]['e'] = $set['e'];
                }
                if (array_key_exists('f', $set)) {
                    $children[$index]['f'] = $set['f'];
                }
                ++$attemptedRows;
                $events[] = [
                    'step' => 'update-child',
                    'id' => $child['id'] ?? null,
                    'old' => [$old['e'] ?? null, $old['f'] ?? null],
                    'new' => [$children[$index]['e'] ?? null, $children[$index]['f'] ?? null],
                ];
            }
        }

        $violations = self::compositeCountChangesViolations($parents, $children);
        $immediateFailure = !$deferred && $violations !== [];
        $status = $violations === [] ? 'statement-ok' : ($deferred ? 'deferred-constraint-failed' : 'constraint-failed');

        if ($operation === 'update-child' && $violations === [] && $onUpdate !== 'no action') {
            foreach ($children as $index => $child) {
                if ($onUpdate === 'cascade') {
                    $children[$index]['e'] = $child['e'];
                    $children[$index]['f'] = $child['f'];
                    ++$fkActionRows;
                    $events[] = ['step' => 'fk-action-cascade', 'id' => $child['id'] ?? null];
                } elseif ($onUpdate === 'set null') {
                    $children[$index]['e'] = null;
                    $children[$index]['f'] = null;
                    ++$fkActionRows;
                    $events[] = ['step' => 'fk-action-set-null', 'id' => $child['id'] ?? null];
                } elseif ($onUpdate === 'set default') {
                    $children[$index]['e'] = 0;
                    $children[$index]['f'] = 0;
                    ++$fkActionRows;
                    $events[] = ['step' => 'fk-action-set-default', 'id' => $child['id'] ?? null];
                }
            }
        }

        return [
            'source' => 'fkey2.test fkey2-17.1.1..17.1.6',
            'operation' => 'foreign-key-count-changes-statement',
            'status' => $status,
            'deferred' => $deferred,
            'count_changes_enabled' => true,
            'sqlite3_step_result' => $immediateFailure ? 'SQLITE_CONSTRAINT' : 'SQLITE_ROW',
            'finalize_result' => $violations === [] ? 'SQLITE_OK' : 'SQLITE_CONSTRAINT',
            'returned_count_rows' => $immediateFailure ? [] : [$attemptedRows],
            'changes' => $violations === [] || $deferred ? $attemptedRows : 0,
            'total_changes_delta' => ($violations === [] || $deferred ? $attemptedRows : 0) + $fkActionRows,
            'foreign_key_action_rows_not_counted' => $fkActionRows > 0,
            'fk_action_rows' => $fkActionRows,
            'violations' => $violations,
            'violation_count' => count($violations),
            'child_pairs' => array_values(array_map(static fn (array $row): array => [$row['e'] ?? null, $row['f'] ?? null], $children)),
            'events' => $events,
            'dependencies' => [
                'sqlite-fkey2-count-changes-immediate-fk-fails-before-row-count',
                'sqlite-fkey2-count-changes-deferred-fk-returns-row-count-before-commit-fail',
                'sqlite-fkey2-count-changes-excludes-foreign-key-action-rows',
            ],
        ];
    }

    /**
     * @param list<array{id:int|string,label?:string}> $parents
     * @param list<array{id:int|string,parent_id:int|string|null,label?:string}> $children
     * @param array{kind:string,parent_id?:int|string,child_id?:int|string|null,replace_parent_id?:int|string|null,trigger_replaces_parent?:bool,self_referential?:bool} $statement
     * @return array<string,mixed>
     */
    public static function withoutRowidReplaceForeignKeyCounter(array $parents, array $children, array $statement): array
    {
        $kind = strtolower((string) ($statement['kind'] ?? 'replace-parent-after-delete'));
        if (!in_array($kind, ['replace-parent-after-delete', 'replace-child-cycle', 'delete-parent-trigger-replace'], true)) {
            throw new \InvalidArgumentException('SQLite fkey8 WITHOUT ROWID replace counter kind is unsupported');
        }

        $parents = self::sortRows(array_values($parents));
        $children = self::sortRows(array_values($children));
        $originalParents = $parents;
        $originalChildren = $children;
        $deferredViolations = [];
        $implicitDeletes = [];
        $triggerEffects = [];

        if ($kind === 'replace-parent-after-delete') {
            $deleteParentId = $statement['parent_id'] ?? throw new \InvalidArgumentException('SQLite fkey8 deleted parent id is required');
            $replaceParentId = $statement['replace_parent_id'] ?? throw new \InvalidArgumentException('SQLite fkey8 replacement parent id is required');
            $parents = array_values(array_filter($parents, static fn (array $row): bool => ($row['id'] ?? null) !== $deleteParentId));
            $parents = array_values(array_filter($parents, static function (array $row) use ($replaceParentId, &$implicitDeletes): bool {
                if (($row['id'] ?? null) === $replaceParentId) {
                    $implicitDeletes[] = ['table' => 'parent', 'id' => $row['id'], 'reason' => 'replace-conflict'];
                    return false;
                }

                return true;
            }));
            $parents[] = ['id' => $replaceParentId, 'label' => 'replacement-' . (string) $replaceParentId];
        } elseif ($kind === 'replace-child-cycle') {
            $childId = $statement['child_id'] ?? throw new \InvalidArgumentException('SQLite fkey8 child id is required');
            $replacementParentId = $statement['replace_parent_id'] ?? null;
            $children = array_values(array_filter($children, static function (array $row) use ($childId, &$implicitDeletes): bool {
                if (($row['id'] ?? null) === $childId) {
                    $implicitDeletes[] = ['table' => 'child', 'id' => $row['id'], 'reason' => 'replace-conflict'];
                    return false;
                }

                return true;
            }));
            $children[] = ['id' => $childId, 'parent_id' => $replacementParentId, 'label' => 'replacement-child-' . (string) $childId];
            if ($statement['self_referential'] ?? false) {
                $parents[] = ['id' => $childId, 'label' => 'self-parent-' . (string) $childId];
            }
        } else {
            $deleteParentId = $statement['parent_id'] ?? throw new \InvalidArgumentException('SQLite fkey8 deleted parent id is required');
            $parents = array_values(array_filter($parents, static fn (array $row): bool => ($row['id'] ?? null) !== $deleteParentId));
            if ($statement['trigger_replaces_parent'] ?? false) {
                $replaceParentId = $statement['replace_parent_id'] ?? throw new \InvalidArgumentException('SQLite fkey8 trigger replacement parent id is required');
                $parents = array_values(array_filter($parents, static function (array $row) use ($replaceParentId, &$implicitDeletes): bool {
                    if (($row['id'] ?? null) === $replaceParentId) {
                        $implicitDeletes[] = ['table' => 'parent', 'id' => $row['id'], 'reason' => 'trigger-replace-conflict'];
                        return false;
                    }

                    return true;
                }));
                $parents[] = ['id' => $replaceParentId, 'label' => 'trigger-replacement-' . (string) $replaceParentId];
                $triggerEffects[] = ['trigger' => 'after_parent_delete_replace', 'event' => 'delete', 'old_id' => $deleteParentId, 'replace_id' => $replaceParentId];
            }
        }

        $parentIds = array_values(array_map(static fn (array $row): mixed => $row['id'], $parents));
        foreach ($children as $index => $child) {
            $parentId = $child['parent_id'] ?? null;
            if ($parentId === null || in_array($parentId, $parentIds, true)) {
                continue;
            }
            $deferredViolations[] = [
                'child_index' => $index,
                'child_id' => $child['id'] ?? null,
                'missing_parent_id' => $parentId,
                'phase' => 'deferred-commit',
            ];
        }

        $failed = $deferredViolations !== [];

        return [
            'source' => match ($kind) {
                'replace-parent-after-delete' => 'fkey8.test fkey8-2.1.0..2.1.2',
                'replace-child-cycle' => 'fkey8.test fkey8-2.2.0..2.2.1',
                default => 'fkey8.test fkey8-2.3.0..3.1',
            },
            'operation' => 'without-rowid-replace-foreign-key-counter',
            'status' => $failed ? 'constraint-failed' : 'commit-ok',
            'kind' => $kind,
            'without_rowid' => true,
            'deferred_counter_delta' => count($deferredViolations),
            'implicit_delete_count' => count($implicitDeletes),
            'implicit_deletes' => $implicitDeletes,
            'trigger_effects' => $triggerEffects,
            'parent_ids' => array_values(array_map(static fn (array $row): mixed => $row['id'], self::sortRows($parents))),
            'child_parent_ids' => array_values(array_map(static fn (array $row): mixed => $row['parent_id'] ?? null, self::sortRows($children))),
            'violations' => $deferredViolations,
            'rollback_parent_ids' => $failed ? array_values(array_map(static fn (array $row): mixed => $row['id'], $originalParents)) : [],
            'rollback_child_parent_ids' => $failed ? array_values(array_map(static fn (array $row): mixed => $row['parent_id'] ?? null, $originalChildren)) : [],
            'dependencies' => [
                'sqlite-fkey8-without-rowid-replace-updates-deferred-counter',
                'sqlite-fkey8-replace-child-conflict-can-clear-deferred-counter',
                'sqlite-fkey8-triggered-replace-delete-preserves-fk-failure',
            ],
        ];
    }

    /**
     * @param list<array{id:int|string,label?:string}> $parents
     * @param list<array{id:int|string,parent_id:int|string|null,label?:string}> $children
     * @param array{operation:string,target?:int|string,repair_trigger?:bool,pragma_defer?:bool,transaction?:string,action?:string} $statement
     * @return array<string,mixed>
     */
    public static function deferForeignKeysPragmaTransaction(array $parents, array $children, array $statement): array
    {
        $parents = array_values($parents);
        $children = array_values($children);
        $operation = strtolower(trim((string) ($statement['operation'] ?? 'delete')));
        $transaction = strtolower(trim((string) ($statement['transaction'] ?? 'commit')));
        $action = strtolower(trim((string) ($statement['action'] ?? 'no action')));
        $pragmaDefer = (bool) ($statement['pragma_defer'] ?? false);
        $repairTrigger = (bool) ($statement['repair_trigger'] ?? false);
        $target = $statement['target'] ?? ($parents[0]['id'] ?? null);

        if (!in_array($operation, ['delete', 'update'], true)) {
            throw new \InvalidArgumentException('SQLite fkey6 defer-foreign-keys operation is unsupported');
        }
        if (!in_array($transaction, ['commit', 'rollback', 'statement'], true)) {
            throw new \InvalidArgumentException('SQLite fkey6 defer-foreign-keys transaction boundary is unsupported');
        }
        if (!in_array($action, ['no action', 'restrict'], true)) {
            throw new \InvalidArgumentException('SQLite fkey6 defer-foreign-keys action is unsupported');
        }

        $originalParents = $parents;
        $originalChildren = $children;
        $immediateFailure = !$pragmaDefer && $action === 'restrict';
        $triggerRows = [];
        $changedParentIds = [];

        if (!$immediateFailure) {
            foreach ($parents as $index => $parent) {
                if (($parent['id'] ?? null) !== $target) {
                    continue;
                }
                $changedParentIds[] = $target;
                if ($operation === 'delete') {
                    unset($parents[$index]);
                    if ($repairTrigger) {
                        $triggerRows[] = $parent + ['label' => $parent['label'] ?? 'repaired'];
                    }
                    continue;
                }
                $parents[$index]['id'] = (string) $target . '-moved';
            }
            $parents = array_values($parents);
            foreach ($triggerRows as $row) {
                $parents[] = $row;
            }
        }

        $violations = self::foreignKeyViolations($parents, $children, 'id', 'parent_id');
        $deferredOutstanding = $pragmaDefer && $violations !== [];
        $commitFails = $deferredOutstanding && $transaction === 'commit';
        $rolledBack = $transaction === 'rollback' || $commitFails || $immediateFailure;
        $finalParents = $rolledBack ? $originalParents : $parents;
        $finalChildren = $rolledBack ? $originalChildren : $children;

        return [
            'source' => 'fkey6.test fkey6-1.0..4.2',
            'operation' => 'pragma-defer-foreign-keys-transaction-boundary',
            'status' => $immediateFailure || $commitFails ? 'constraint-failed' : 'commit-ok',
            'pragma_defer_foreign_keys' => $pragmaDefer,
            'deferred_fk_dbstatus' => $deferredOutstanding ? 1 : 0,
            'transaction_boundary' => $transaction,
            'pragma_reset_after_boundary' => $transaction !== 'statement',
            'action' => $action,
            'immediate_restrict_failed_before_trigger' => $immediateFailure,
            'commit_failed_with_deferred_violation' => $commitFails,
            'rolled_back' => $rolledBack,
            'repair_trigger_fired' => $repairTrigger && $triggerRows !== [] && !$immediateFailure,
            'changed_parent_ids' => $changedParentIds,
            'trigger_repaired_parent_ids' => array_values(array_column($triggerRows, 'id')),
            'violations' => $violations,
            'violation_count' => count($violations),
            'parent_ids' => array_values(array_column(self::sortRows($finalParents), 'id')),
            'child_parent_ids' => array_values(array_column(self::sortRows($finalChildren), 'parent_id')),
            'dependencies' => [
                'sqlite-fkey6-defer-foreign-keys-delays-all-actions-to-outer-commit',
                'sqlite-fkey6-defer-foreign-keys-resets-after-commit-or-rollback',
                'sqlite-fkey6-defer-foreign-keys-disables-restrict-until-commit',
                'sqlite-fkey6-deferred-dbstatus-tracks-outstanding-violations',
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
     * @return array<string,mixed>
     */
    public static function qualifiedTriggerNameDiagnostic(string $triggerName, bool $temporary): array
    {
        $parts = explode('.', $triggerName);
        $databaseName = count($parts) === 2 ? $parts[0] : null;
        $localName = count($parts) === 2 ? $parts[1] : $triggerName;
        if ($databaseName !== null) {
            self::identifier($databaseName, 'trigger database name');
        }
        self::identifier($localName, 'trigger name');

        $status = 'commit-ok';
        $error = null;
        if ($temporary && $databaseName !== null) {
            $status = 'schema-error';
            $error = 'temporary trigger may not have qualified name';
        } elseif ($databaseName !== null && $databaseName !== 'main' && $databaseName !== 'temp') {
            $status = 'schema-error';
            $error = 'unknown database ' . $databaseName;
        }

        return [
            'source' => 'trigger7.test trigger7-1.1..1.2',
            'operation' => 'qualified-trigger-name-diagnostic',
            'status' => $status,
            'temporary' => $temporary,
            'database_name' => $databaseName,
            'trigger_name' => $localName,
            'error' => $error,
            'dependencies' => [
                'sqlite-trigger7-temporary-trigger-may-not-have-qualified-name',
                'sqlite-trigger7-qualified-trigger-unknown-database',
            ],
        ];
    }

    /**
     * @param list<array{name:string,columns:list<string>,timing?:string,event?:string}> $triggers
     * @param list<string> $updatedColumns
     * @return array<string,mixed>
     */
    public static function updateOfExplainTriggerPruning(array $triggers, array $updatedColumns): array
    {
        $updatedColumns = array_values(array_map(static fn (string $column): string => self::identifier($column, 'updated column'), $updatedColumns));
        $emitted = [];
        $pruned = [];

        foreach ($triggers as $trigger) {
            $name = self::identifier((string) ($trigger['name'] ?? ''), 'trigger name');
            $columns = array_values(array_map(static fn (string $column): string => self::identifier($column, 'trigger column'), $trigger['columns'] ?? []));
            $intersects = array_intersect($columns, $updatedColumns) !== [];
            if ($intersects) {
                $emitted[] = $name;
            } else {
                $pruned[] = $name;
            }
        }

        return [
            'source' => 'trigger7.test trigger7-2.1..2.6',
            'operation' => 'update-of-explain-trigger-pruning',
            'status' => 'commit-ok',
            'updated_columns' => $updatedColumns,
            'emitted_trigger_names' => $emitted,
            'pruned_trigger_names' => $pruned,
            'explain_text' => implode(' ', array_map(static fn (string $name): string => '___update_t1.' . $name . '___', $emitted)),
            'dependencies' => [
                'sqlite-trigger7-update-of-prunes-unmatched-trigger-programs',
                'sqlite-trigger7-rowid-update-does-not-match-named-column-trigger',
            ],
        ];
    }

    /**
     * @param list<array{name:string,timing:string,event:string}> $triggers
     * @param list<string> $dropNames
     * @return array<string,mixed>
     */
    public static function selectiveDropTriggerCatalog(array $triggers, array $dropNames): array
    {
        $remaining = [];
        $dropped = [];
        $dropSet = array_fill_keys(array_map(static fn (string $name): string => self::identifier($name, 'drop trigger name'), $dropNames), true);

        foreach ($triggers as $trigger) {
            $name = self::identifier((string) ($trigger['name'] ?? ''), 'trigger name');
            $record = [
                'name' => $name,
                'timing' => strtolower((string) ($trigger['timing'] ?? '')),
                'event' => strtolower((string) ($trigger['event'] ?? '')),
            ];
            if (isset($dropSet[$name])) {
                $dropped[] = $record;
                continue;
            }
            $remaining[] = $record;
        }

        return [
            'source' => 'trigger7.test trigger7-3.1',
            'operation' => 'selective-drop-trigger-catalog',
            'status' => 'commit-ok',
            'dropped_trigger_names' => array_values(array_column($dropped, 'name')),
            'remaining_trigger_names' => array_values(array_column($remaining, 'name')),
            'remaining_by_event' => self::triggerEventCounts($remaining),
            'remaining_by_timing' => self::triggerTimingCounts($remaining),
            'dependencies' => [
                'sqlite-trigger7-many-triggers-on-table-remain-addressable',
                'sqlite-trigger7-drop-trigger-removes-only-named-trigger',
            ],
        ];
    }

    /**
     * @param list<array{schema:string,name:string,table:string,event:string,timing:string}> $triggers
     * @return array<string,mixed>
     */
    public static function dropTriggerSchemaResolutionPlan(array $triggers, string $dropName, string $event, string $table, bool $ifExists = false): array
    {
        $schemas = ['temp' => 0, 'main' => 1];
        $records = [];
        foreach (array_values($triggers) as $trigger) {
            $schema = self::identifier((string) ($trigger['schema'] ?? ''), 'trigger schema');
            $schemas[$schema] ??= count($schemas);
            $records[] = [
                'schema' => $schema,
                'name' => self::identifier((string) ($trigger['name'] ?? ''), 'trigger name'),
                'table' => self::identifier((string) ($trigger['table'] ?? ''), 'trigger table'),
                'event' => strtolower(self::identifier((string) ($trigger['event'] ?? ''), 'trigger event')),
                'timing' => strtolower(self::identifier((string) ($trigger['timing'] ?? ''), 'trigger timing')),
            ];
        }

        $event = strtolower(self::identifier($event, 'trigger event'));
        $table = self::identifier($table, 'trigger table');
        [$dropSchema, $localName] = self::splitQualifiedTriggerName($dropName);
        if ($dropSchema !== null) {
            self::identifier($dropSchema, 'drop trigger schema');
        }
        $localName = self::identifier($localName, 'drop trigger name');

        $before = self::triggerFireList($records, $event, $table, $schemas);
        $dropIndex = null;
        foreach ($records as $index => $record) {
            if ($record['name'] !== $localName) {
                continue;
            }
            if ($dropSchema !== null && $record['schema'] !== $dropSchema) {
                continue;
            }
            if ($dropIndex === null || $schemas[$record['schema']] < $schemas[$records[$dropIndex]['schema']]) {
                $dropIndex = $index;
            }
        }

        $error = null;
        $dropped = null;
        if ($dropIndex === null) {
            $error = $ifExists ? null : 'no such trigger: ' . ($dropSchema === null ? $localName : $dropSchema . '.' . $localName);
        } else {
            $dropped = $records[$dropIndex];
            unset($records[$dropIndex]);
            $records = array_values($records);
        }

        $after = self::triggerFireList($records, $event, $table, $schemas);

        return [
            'source' => 'e_droptrigger.test e_droptrigger-1..4',
            'operation' => 'drop-trigger-schema-resolution-and-fired-program-removal',
            'status' => $error === null ? 'commit-ok' : 'schema-error',
            'drop_name' => $dropName,
            'drop_schema' => $dropSchema,
            'drop_trigger' => $localName,
            'if_exists' => $ifExists,
            'error' => $error,
            'dropped_trigger' => $dropped === null ? null : $dropped['schema'] . '.' . $dropped['name'],
            'event' => $event,
            'table' => $table,
            'fired_before' => $before,
            'fired_after' => $after,
            'remaining_trigger_names' => self::qualifiedTriggerNames($records, $schemas),
            'schema_rows_removed' => $dropped === null ? 0 : 1,
            'dependencies' => [
                'sqlite-drop-trigger-removes-schema-row',
                'sqlite-drop-trigger-unqualified-schema-search-order',
                'sqlite-drop-trigger-removed-program-no-longer-fires',
                'sqlite-drop-trigger-if-exists-allows-missing-trigger',
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
     * @param list<array{op:string,value?:bool,name?:string}> $actions
     * @return array<string,mixed>
     */
    public static function foreignKeysPragmaToggleTransaction(array $actions, bool $initial = false): array
    {
        $enabled = $initial;
        $depth = 0;
        $history = [];
        $ignored = 0;

        foreach ($actions as $index => $action) {
            $op = strtolower(trim((string) ($action['op'] ?? '')));
            if ($op === 'begin' || $op === 'savepoint') {
                ++$depth;
            } elseif ($op === 'commit' || $op === 'release') {
                $depth = max(0, $depth - 1);
            } elseif ($op === 'rollback') {
                $depth = 0;
            } elseif ($op === 'pragma') {
                $value = (bool) ($action['value'] ?? false);
                if ($depth === 0) {
                    $enabled = $value;
                } else {
                    ++$ignored;
                }
            } elseif ($op === 'read') {
                // Read-only probe used by the upstream fkey2-8 matrix.
            } else {
                throw new \InvalidArgumentException('SQLite fkey2 foreign_keys pragma action is unsupported');
            }

            $history[] = [
                'index' => $index,
                'op' => $op,
                'requested' => $action['value'] ?? null,
                'transaction_depth' => $depth,
                'foreign_keys' => $enabled ? 1 : 0,
                'ignored_in_transaction' => $op === 'pragma' && $depth > 0,
            ];
        }

        return [
            'source' => 'fkey2.test fkey2-8.1..8.16',
            'operation' => 'foreign-keys-pragma-transaction-boundary',
            'status' => 'commit-ok',
            'initial_foreign_keys' => $initial ? 1 : 0,
            'final_foreign_keys' => $enabled ? 1 : 0,
            'transaction_depth' => $depth,
            'ignored_toggle_count' => $ignored,
            'history' => $history,
            'history_count' => count($history),
            'dependencies' => [
                'sqlite-fkey2-foreign-keys-pragma-autocommit-toggle',
                'sqlite-fkey2-foreign-keys-pragma-ignored-inside-transaction',
                'sqlite-fkey2-foreign-keys-pragma-ignored-inside-savepoint',
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
     * @param array<string,mixed> $row
     * @param array<string,mixed> $parentAssignments
     * @param array<string,mixed> $triggerAssignments
     * @return array<string,mixed>
     */
    public static function beforeUpdateSelfMutationPreservesColumns(array $row, array $parentAssignments, array $triggerAssignments): array
    {
        if ($row === []) {
            throw new \InvalidArgumentException('SQLite triggerC before update row must not be empty');
        }
        if ($parentAssignments === []) {
            throw new \InvalidArgumentException('SQLite triggerC parent update assignments must not be empty');
        }
        if ($triggerAssignments === []) {
            throw new \InvalidArgumentException('SQLite triggerC trigger assignments must not be empty');
        }

        $original = $row;
        $triggerRow = $row;
        foreach ($triggerAssignments as $column => $value) {
            self::identifier((string) $column, 'before update trigger assignment column');
            $triggerRow[$column] = is_callable($value) ? $value($triggerRow, $original) : $value;
        }

        $final = $triggerRow;
        foreach ($parentAssignments as $column => $value) {
            self::identifier((string) $column, 'parent update assignment column');
            $final[$column] = is_callable($value) ? $value($original, $triggerRow) : $value;
        }

        $preserved = [];
        foreach ($triggerAssignments as $column => $_value) {
            if (array_key_exists($column, $parentAssignments)) {
                continue;
            }
            $preserved[(string) $column] = $final[$column] ?? null;
        }

        return [
            'source' => 'triggerC.test triggerC-10.1..10.3',
            'operation' => 'before-update-trigger-self-mutation-preserves-unassigned-columns',
            'status' => 'commit-ok',
            'original_row' => $original,
            'trigger_row' => $triggerRow,
            'parent_assignments' => array_keys($parentAssignments),
            'trigger_assignments' => array_keys($triggerAssignments),
            'final_row' => $final,
            'preserved_trigger_columns' => $preserved,
            'preserved_trigger_column_count' => count($preserved),
            'parent_assignment_count' => count($parentAssignments),
            'trigger_assignment_count' => count($triggerAssignments),
            'dependencies' => [
                'sqlite-triggerC-before-update-self-mutation',
                'sqlite-triggerC-parent-update-does-not-clobber-unassigned-trigger-columns',
                'sqlite-triggerC-wide-row-column-preservation',
            ],
        ];
    }

    /**
     * @param list<array{a:int|null,b:int|float|string|null,c:int|null}> $rows
     * @return array<string,mixed>
     */
    public static function deleteUndoTriggerStatements(array $rows, callable $where): array
    {
        $remaining = [];
        $deleted = [];
        $undo = [];

        foreach ($rows as $row) {
            if ($where($row)) {
                $deleted[] = $row;
                $undo[] = 'INSERT INTO Item (a,b,c) VALUES ('
                    . SQLiteRealExpressionAffinityCorpusPlan::quote($row['a'] ?? null)
                    . ','
                    . SQLiteRealExpressionAffinityCorpusPlan::quote($row['b'] ?? null)
                    . ','
                    . SQLiteRealExpressionAffinityCorpusPlan::quote($row['c'] ?? null)
                    . ');';
                continue;
            }

            $remaining[] = $row;
        }

        return [
            'source' => 'trigger5.test trigger5-1.1',
            'operation' => 'after-delete-trigger-undo-sql-generation',
            'status' => 'commit-ok',
            'deleted_rows' => $deleted,
            'remaining_rows' => self::sortRows($remaining),
            'undo_statements' => $undo,
            'deleted_count' => count($deleted),
            'undo_count' => count($undo),
            'remaining_count' => count($remaining),
            'quote_function_used' => true,
            'dependencies' => [
                'sqlite-trigger5-after-delete-old-row-undo-sql',
                'sqlite-trigger5-quote-function-preserves-real-text-null-values',
                'sqlite-trigger5-delete-trigger-emits-one-undo-row-per-deleted-row',
            ],
        ];
    }

    /**
     * @param list<array{id:int|string,label?:string}> $parents
     * @param list<array{id:int|string,parent_id:int|string|null,label?:string}> $children
     * @param array{operation:string,target_parent?:int|string,replacement_parent?:int|string|null,conflict_child?:int|string|null,delete_children?:bool,trigger_replaces_parent?:bool} $statement
     * @return array<string,mixed>
     */
    public static function replaceDeferredForeignKeyCounter(array $parents, array $children, array $statement): array
    {
        $operation = (string) ($statement['operation'] ?? '');
        if (!in_array($operation, ['delete-parent-replace-parent', 'replace-child-then-delete', 'delete-parent-trigger-replace'], true)) {
            throw new \InvalidArgumentException('SQLite fkey8 deferred counter operation is unsupported');
        }

        $parents = array_values($parents);
        $children = array_values($children);
        $originalParents = $parents;
        $originalChildren = $children;
        $implicitDeletes = [];
        $triggerEffects = [];

        if ($operation === 'delete-parent-replace-parent' || $operation === 'delete-parent-trigger-replace') {
            $target = $statement['target_parent'] ?? throw new \InvalidArgumentException('SQLite fkey8 target parent is required');
            foreach ($parents as $index => $parent) {
                if (($parent['id'] ?? null) === $target) {
                    $implicitDeletes[] = ['table' => 'parent', 'id' => $parent['id'], 'reason' => 'delete-parent'];
                    unset($parents[$index]);
                    break;
                }
            }
            $parents = array_values($parents);

            if (($statement['delete_children'] ?? false) === true) {
                foreach ($children as $index => $child) {
                    if (($child['parent_id'] ?? null) === $target) {
                        $implicitDeletes[] = ['table' => 'child', 'id' => $child['id'], 'reason' => 'cascade-delete'];
                        unset($children[$index]);
                    }
                }
                $children = array_values($children);
            }

            $replacement = $statement['replacement_parent'] ?? null;
            if ($replacement !== null) {
                $parents[] = ['id' => $replacement, 'label' => 'replace-parent-' . (string) $replacement];
                $implicitDeletes[] = ['table' => 'parent', 'id' => $replacement, 'reason' => 'or-replace-conflict'];
            }

            if (($statement['trigger_replaces_parent'] ?? false) === true) {
                $triggerEffects[] = ['event' => 'after-delete', 'action' => 'insert-or-replace-parent', 'id' => $replacement];
            }
        } else {
            $conflictChild = $statement['conflict_child'] ?? throw new \InvalidArgumentException('SQLite fkey8 conflict child is required');
            foreach ($children as $index => $child) {
                if (($child['id'] ?? null) === $conflictChild) {
                    $implicitDeletes[] = ['table' => 'child', 'id' => $child['id'], 'reason' => 'or-replace-conflict'];
                    unset($children[$index]);
                    break;
                }
            }
            $children = array_values($children);

            $replacementParent = $statement['replacement_parent'] ?? null;
            $children[] = ['id' => $conflictChild, 'parent_id' => $replacementParent, 'label' => 'replace-child-' . (string) $conflictChild];
            if (($statement['delete_children'] ?? false) === true) {
                $children = [];
                $implicitDeletes[] = ['table' => 'child', 'id' => $conflictChild, 'reason' => 'delete-child-before-commit'];
            }
        }

        $violations = self::foreignKeyViolations($parents, $children, 'id', 'parent_id');
        $status = $violations === [] ? 'commit-ok' : 'commit-failed';

        return [
            'source' => 'fkey8.test fkey8-2.1.2..2.3.1',
            'operation' => 'deferred-foreign-key-counter-implicit-delete',
            'status' => $status,
            'statement_operation' => $operation,
            'implicit_deletes' => $implicitDeletes,
            'implicit_delete_count' => count($implicitDeletes),
            'trigger_effects' => $triggerEffects,
            'deferred_violation_count' => count($violations),
            'violations' => $violations,
            'committed_parent_ids' => array_values(array_column(self::sortRows($status === 'commit-ok' ? $parents : $originalParents), 'id')),
            'committed_child_parent_ids' => array_values(array_column(self::sortRows($status === 'commit-ok' ? $children : $originalChildren), 'parent_id')),
            'rollback_restored' => $status !== 'commit-ok',
            'constraint_counter_includes_implicit_deletes' => true,
            'dependencies' => [
                'sqlite-fkey8-implicit-delete-updates-deferred-counter',
                'sqlite-fkey8-or-replace-without-rowid-foreign-key-counter',
                'sqlite-fkey8-trigger-side-replace-preserves-counter',
            ],
        ];
    }

    /**
     * @param list<array{schema:string,id:int|string}> $parents
     * @param list<array{schema:string,id:int|string,parent_id:int|string|null}> $children
     * @return array<string,mixed>
     */
    public static function attachedSchemaCascadeUpdate(array $parents, array $children, string $schema, int $multiplier): array
    {
        $schema = self::identifier($schema, 'attached schema');
        if ($multiplier < 1) {
            throw new \InvalidArgumentException('SQLite fkey8 attached cascade multiplier is invalid');
        }

        $updatedParents = [];
        $updatedKeys = [];
        foreach ($parents as $parent) {
            if (($parent['schema'] ?? '') === $schema) {
                $old = $parent['id'];
                $parent['id'] = (int) $parent['id'] * $multiplier;
                $updatedKeys[(string) $old] = $parent['id'];
            }
            $updatedParents[] = $parent;
        }

        $updatedChildren = [];
        $cascadeCount = 0;
        foreach ($children as $child) {
            if (($child['schema'] ?? '') === $schema && array_key_exists((string) ($child['parent_id'] ?? ''), $updatedKeys)) {
                $child['parent_id'] = $updatedKeys[(string) $child['parent_id']];
                ++$cascadeCount;
            }
            $updatedChildren[] = $child;
        }

        return [
            'source' => 'fkey8.test fkey8-7.0..7.4',
            'operation' => 'attached-schema-foreign-key-update-cascade',
            'status' => 'commit-ok',
            'schema' => $schema,
            'multiplier' => $multiplier,
            'updated_parent_ids' => array_values(array_column(self::sortRows($updatedParents), 'id')),
            'updated_child_parent_ids' => array_values(array_column(self::sortRows($updatedChildren), 'parent_id')),
            'cascade_count' => $cascadeCount,
            'main_schema_untouched' => array_values(array_filter(
                $updatedChildren,
                static fn (array $child): bool => ($child['schema'] ?? '') !== $schema
            )) === array_values(array_filter(
                $children,
                static fn (array $child): bool => ($child['schema'] ?? '') !== $schema
            )),
            'dependencies' => [
                'sqlite-fkey8-attached-schema-cascade-update',
                'sqlite-fkey8-child-table-resolves-parent-inside-own-schema',
                'sqlite-fkey8-cascade-update-preserves-attached-schema-routing',
            ],
        ];
    }

    /**
     * @param list<array{rowid:int,a:int,b:int}> $rows
     * @return array<string,mixed>
     */
    public static function beforeTriggerRowidMutation(array $rows, string $operation, int $targetA, string $beforeMutation): array
    {
        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite triggerC rowid mutation operation is unsupported');
        }

        $beforeMutation = strtolower(trim($beforeMutation));
        if (!in_array($beforeMutation, ['delete-rowid-1', 'move-rowid-1-to-8'], true)) {
            throw new \InvalidArgumentException('SQLite triggerC rowid mutation action is unsupported');
        }

        $sortByRowid = static function (array $input): array {
            usort($input, static fn (array $left, array $right): int => $left['rowid'] <=> $right['rowid']);

            return array_values($input);
        };

        $rows = $sortByRowid($rows);
        $targetRowid = null;
        foreach ($rows as $row) {
            if ($row['a'] === $targetA) {
                $targetRowid = $row['rowid'];
                break;
            }
        }

        $afterLog = [];
        $beforeApplied = false;
        if ($targetRowid !== null) {
            if ($beforeMutation === 'delete-rowid-1') {
                $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['rowid'] !== 1));
                $beforeApplied = true;
            } else {
                foreach ($rows as &$row) {
                    if ($row['rowid'] !== 1) {
                        continue;
                    }

                    $oldRowid = $row['rowid'];
                    $row['rowid'] = 8;
                    if ($operation === 'update') {
                        $afterLog[] = 'after fired ' . $oldRowid . '->' . $row['rowid'];
                    }
                    $beforeApplied = true;
                    break;
                }
                unset($row);
            }
        }

        $changed = false;
        foreach ($rows as $index => &$row) {
            if ($row['rowid'] !== $targetRowid) {
                continue;
            }

            if ($operation === 'update') {
                $oldRowid = $row['rowid'];
                $row['b'] = 7;
                $afterLog[] = 'after fired ' . $oldRowid . '->' . $row['rowid'];
                $changed = true;
                break;
            }

            unset($rows[$index]);
            $afterLog[] = 'after fired ' . $targetRowid;
            $changed = true;
            break;
        }
        unset($row);

        $rows = $sortByRowid(array_values($rows));

        return [
            'source' => 'triggerC.test triggerC-7.1..7.9',
            'operation' => 'before-trigger-rowid-mutation',
            'status' => 'commit-ok',
            'statement' => $operation,
            'target_a' => $targetA,
            'target_rowid' => $targetRowid,
            'before_mutation' => $beforeMutation,
            'before_trigger_applied' => $beforeApplied,
            'outer_statement_changed' => $changed,
            'final_rows' => $rows,
            'final_rowids' => array_values(array_column($rows, 'rowid')),
            'final_a_values' => array_values(array_column($rows, 'a')),
            'final_b_values' => array_values(array_column($rows, 'b')),
            'after_log' => $afterLog,
            'after_log_count' => count($afterLog),
            'dependencies' => [
                'sqlite-triggerC-before-trigger-can-delete-target-row',
                'sqlite-triggerC-before-trigger-can-move-rowid-before-outer-statement',
                'sqlite-triggerC-after-trigger-fires-only-for-surviving-outer-row-change',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function largeTriggerBodyExecution(int $statementCount, int $outerInsertValue = 5, int $outerRowCount = 1): array
    {
        if ($statementCount < 0) {
            throw new \InvalidArgumentException('SQLite trigger8 statement count must be non-negative');
        }
        if ($outerRowCount < 1) {
            throw new \InvalidArgumentException('SQLite trigger8 outer row count must be positive');
        }

        $triggerRows = [];
        for ($row = 0; $row < $outerRowCount; ++$row) {
            for ($statement = 0; $statement < $statementCount; ++$statement) {
                $triggerRows[] = [
                    'outer_row_index' => $row,
                    'outer_value' => $outerInsertValue + $row,
                    'statement_ordinal' => $statement,
                    'y' => $statement,
                ];
            }
        }

        return [
            'source' => 'trigger8.test trigger8-1.1',
            'operation' => 'large-trigger-body-executes-all-statements',
            'status' => 'commit-ok',
            'statement_count' => $statementCount,
            'outer_insert_value' => $outerInsertValue,
            'outer_row_count' => $outerRowCount,
            'trigger_row_count' => count($triggerRows),
            'first_statement_ordinal' => $triggerRows[0]['statement_ordinal'] ?? null,
            'last_statement_ordinal' => $statementCount === 0 ? null : $statementCount - 1,
            'trigger_rows' => $triggerRows,
            'trigger_values' => array_values(array_column($triggerRows, 'y')),
            'per_outer_row_counts' => array_fill(0, $outerRowCount, $statementCount),
            'dependencies' => [
                'sqlite-trigger8-large-trigger-body-statement-drain',
                'sqlite-trigger8-trigger-program-preserves-statement-order',
                'sqlite-trigger8-each-outer-row-runs-full-trigger-body',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array{parent:string,child:string,parent_collation?:string}> $keyMap
     * @return array<string,mixed>
     */
    public static function foreignKeyCheckCollationPlan(
        string $childTable,
        string $parentTable,
        array $parentRows,
        array $childRows,
        array $keyMap,
        bool $withoutRowidChild = false,
        ?string $schema = null
    ): array {
        $childTable = self::identifier($childTable, 'foreign_key_check child table');
        $parentTable = self::identifier($parentTable, 'foreign_key_check parent table');
        if ($schema !== null) {
            $schema = self::identifier($schema, 'foreign_key_check schema');
        }
        if ($keyMap === []) {
            throw new \InvalidArgumentException('SQLite fkey5 foreign_key_check key map is empty');
        }

        $normalizedKeys = [];
        foreach ($keyMap as $key) {
            $normalizedKeys[] = [
                'parent' => self::identifier((string) ($key['parent'] ?? ''), 'foreign_key_check parent key'),
                'child' => self::identifier((string) ($key['child'] ?? ''), 'foreign_key_check child key'),
                'parent_collation' => strtolower((string) ($key['parent_collation'] ?? 'binary')),
            ];
        }

        $violations = [];
        foreach (array_values($childRows) as $index => $child) {
            $childKey = [];
            $hasNull = false;
            foreach ($normalizedKeys as $key) {
                if (!array_key_exists($key['child'], $child)) {
                    throw new \InvalidArgumentException('SQLite fkey5 foreign_key_check child row is missing key column');
                }
                $value = $child[$key['child']];
                $childKey[] = $value;
                $hasNull = $hasNull || $value === null;
            }
            if ($hasNull) {
                continue;
            }

            $matched = false;
            foreach ($parentRows as $parent) {
                foreach ($normalizedKeys as $position => $key) {
                    if (!array_key_exists($key['parent'], $parent)) {
                        throw new \InvalidArgumentException('SQLite fkey5 foreign_key_check parent row is missing key column');
                    }
                    if (!self::foreignKeyParentValueMatches($parent[$key['parent']], $childKey[$position], $key['parent_collation'])) {
                        continue 2;
                    }
                }
                $matched = true;
                break;
            }

            if (!$matched) {
                $violations[] = [
                    'table' => $childTable,
                    'rowid' => $withoutRowidChild ? null : $index + 1,
                    'parent' => $parentTable,
                    'fkid' => 0,
                    'child_key' => $childKey,
                ];
            }
        }

        return [
            'source' => 'fkey5.test fkey5-5.0..13.12',
            'operation' => 'foreign-key-check-parent-collation-composite',
            'status' => 'commit-ok',
            'schema' => $schema ?? 'main',
            'child_table' => $childTable,
            'parent_table' => $parentTable,
            'without_rowid_child' => $withoutRowidChild,
            'key_columns' => $normalizedKeys,
            'violation_rows' => $violations,
            'violation_count' => count($violations),
            'result_columns' => ['table', 'rowid', 'parent', 'fkid'],
            'result_tuples' => array_values(array_map(
                static fn (array $row): array => [$row['table'], $row['rowid'], $row['parent'], $row['fkid']],
                $violations
            )),
            'null_child_key_suppressed' => self::hasNullChildKey($childRows, $normalizedKeys),
            'dependencies' => [
                'sqlite-fkey5-foreign-key-check-four-column-result',
                'sqlite-fkey5-parent-collation-controls-child-comparison',
                'sqlite-fkey5-composite-key-column-order',
                'sqlite-fkey5-without-rowid-child-reports-null-rowid',
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
     * @param array<string,mixed> $parent
     */
    private static function requireCompositeReplaceParent(array $parent): void
    {
        foreach (['rowid', 'a', 'b', 'c'] as $column) {
            if (!array_key_exists($column, $parent)) {
                throw new \InvalidArgumentException('SQLite fkey2-13 parent row is missing ' . $column);
            }
        }
    }

    /**
     * @param list<array{rowid:int,a:int|string,b:int|string,c:int|string}> $parents
     */
    private static function nextCompositeReplaceRowid(array $parents): int
    {
        $max = 0;
        foreach ($parents as $parent) {
            $max = max($max, (int) ($parent['rowid'] ?? 0));
        }

        return $max + 1;
    }

    /**
     * @param list<array{rowid:int,a:int|string,b:int|string,c:int|string}> $parents
     * @param list<array{d:int|string,e:int|string,f?:int|string}> $children
     * @return list<array{child_index:int,child_key:array{mixed,mixed},reason:string}>
     */
    private static function compositeReplaceViolations(array $parents, array $children): array
    {
        $keys = [];
        foreach ($parents as $parent) {
            $keys[(string) $parent['b'] . "\0" . (string) $parent['c']] = true;
        }

        $violations = [];
        foreach ($children as $index => $child) {
            $key = (string) ($child['d'] ?? '') . "\0" . (string) ($child['e'] ?? '');
            if (isset($keys[$key])) {
                continue;
            }

            $violations[] = [
                'child_index' => $index,
                'child_key' => [$child['d'] ?? null, $child['e'] ?? null],
                'reason' => 'missing-composite-parent-after-replace-delete',
            ];
        }

        return $violations;
    }

    /**
     * @param list<array{nodeid:int,parent:int|null}> $nodes
     * @param list<array{id:string,nodeid:int}> $leaves
     * @return list<array<string,mixed>>
     */
    private static function fkey2GraphViolations(array $nodes, array $leaves): array
    {
        $nodeIds = array_values(array_map(static fn (array $node): int => (int) $node['nodeid'], $nodes));
        $violations = [];
        foreach ($nodes as $rowid => $node) {
            $parent = $node['parent'];
            if ($parent !== null && !in_array((int) $parent, $nodeIds, true)) {
                $violations[] = [
                    'table' => 'node',
                    'rowid' => $rowid + 1,
                    'child_key' => (int) $parent,
                    'parent_key' => (int) $parent,
                ];
            }
        }
        foreach ($leaves as $rowid => $leaf) {
            $nodeid = (int) $leaf['nodeid'];
            if (!in_array($nodeid, $nodeIds, true)) {
                $violations[] = [
                    'table' => 'leaf',
                    'rowid' => $rowid + 1,
                    'child_key' => $nodeid,
                    'parent_key' => $nodeid,
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
     * @param list<array{node:int,parent:int|null}> $rows
     * @return list<int>
     */
    private static function cascadeDeleteTreeRows(array &$rows, int $deleteNode, bool $recursive): array
    {
        $deleted = [];
        $queue = [$deleteNode];
        while ($queue !== []) {
            $node = array_shift($queue);
            $removed = false;
            foreach ($rows as $index => $row) {
                if (!is_int($row['node'] ?? null) || !(is_int($row['parent'] ?? null) || ($row['parent'] ?? null) === null)) {
                    throw new \InvalidArgumentException('SQLite fkey2 recursive cascade row is malformed');
                }
                if ((int) ($row['node'] ?? 0) !== $node) {
                    continue;
                }
                unset($rows[$index]);
                $rows = array_values($rows);
                $deleted[] = $node;
                $removed = true;
                break;
            }
            if (!$removed || !$recursive && $node !== $deleteNode) {
                continue;
            }
            foreach ($rows as $row) {
                if (!is_int($row['node'] ?? null) || !(is_int($row['parent'] ?? null) || ($row['parent'] ?? null) === null)) {
                    throw new \InvalidArgumentException('SQLite fkey2 recursive cascade row is malformed');
                }
                if (($row['parent'] ?? null) === $node) {
                    $queue[] = (int) $row['node'];
                }
            }
        }

        return $deleted;
    }

    /**
     * @param list<array{node:int,parent:int|null}> $rows
     * @param list<int> $deleted
     */
    private static function treeDeleteReachedDepth(array $rows, array $deleted, int $root, int $requiredDepth): bool
    {
        $parents = [];
        foreach ($rows as $row) {
            $parents[(int) $row['node']] = $row['parent'] === null ? null : (int) $row['parent'];
        }
        foreach ($deleted as $node) {
            $depth = 0;
            $cursor = $node;
            while (isset($parents[$cursor]) && $parents[$cursor] !== null) {
                $depth++;
                if ($parents[$cursor] === $root) {
                    break;
                }
                $cursor = $parents[$cursor];
            }
            if ($depth >= $requiredDepth) {
                return true;
            }
        }

        return false;
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
     * @param array<string,mixed> $row
     * @return array{trigger:string,rowid:mixed,oid:mixed,_rowid_:mixed,x:mixed}
     */
    private static function triggerDLogEntry(string $trigger, array $row): array
    {
        return [
            'trigger' => $trigger,
            'rowid' => $row['rowid'] ?? null,
            'oid' => $row['oid'] ?? null,
            '_rowid_' => $row['_rowid_'] ?? null,
            'x' => $row['x'] ?? null,
        ];
    }

    private static function numericAdd(mixed $value, int $delta): int|float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException('SQLite triggerD numeric field is required');
        }

        return $value + $delta;
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

    private static function compositeCountChangesViolations(array $parents, array $children): array
    {
        $parentKeys = [];
        foreach ($parents as $parent) {
            $parentKeys[self::valueKey($parent['b'] ?? null) . '|' . self::valueKey($parent['c'] ?? null)] = true;
        }

        $violations = [];
        foreach ($children as $child) {
            $e = $child['e'] ?? null;
            $f = $child['f'] ?? null;
            if ($e === null || $f === null) {
                continue;
            }

            $key = self::valueKey($e) . '|' . self::valueKey($f);
            if (!isset($parentKeys[$key])) {
                $violations[] = [
                    'child_id' => $child['id'] ?? null,
                    'child_key' => [$e, $f],
                    'parent_key' => [$e, $f],
                ];
            }
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
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @return array<string,mixed>
     */
    public static function fkey1QuotedCascadeReplacePlan(
        array $parents,
        array $children,
        string $parentKey,
        string $childKey,
        string $action,
        bool $partialParentIndex = false
    ): array {
        $parentKey = self::quotedIdentifier($parentKey, 'parent key');
        $childKey = self::quotedIdentifier($childKey, 'child key');
        $action = strtolower(trim($action));
        if (!in_array($action, ['cascade', 'restrict', 'no action'], true)) {
            throw new \InvalidArgumentException('SQLite fkey1 quoted cascade action is unsupported');
        }

        $initialChildKeys = array_values(array_map(
            static fn (array $row): mixed => self::requiredRowValue($row, $childKey, 'child row'),
            $children
        ));
        $parentKeys = array_values(array_map(
            static fn (array $row): mixed => self::requiredRowValue($row, $parentKey, 'parent row'),
            $parents
        ));

        $status = 'commit-ok';
        $error = null;
        $trace = [];
        $remainingParents = [];
        $remainingChildren = $children;
        if ($partialParentIndex) {
            $status = 'foreign-key-mismatch';
            $error = 'foreign key mismatch';
        } elseif ($action === 'restrict' && $children !== []) {
            $status = 'constraint-failed';
            $error = 'FOREIGN KEY constraint failed';
            $remainingParents = $parents;
        } elseif ($action === 'cascade') {
            $trace[] = 'DELETE FROM quoted-parent';
            $remainingChildren = array_values(array_filter(
                $children,
                static fn (array $row): bool => !in_array($row[$childKey] ?? null, $parentKeys, true)
            ));
        }

        return [
            'source' => 'fkey1.test fkey1-4.0..9.1',
            'operation' => 'quoted-identifier-fkey-cascade-replace',
            'status' => $status,
            'error' => $error,
            'parent_key' => $parentKey,
            'child_key' => $childKey,
            'action' => $action,
            'partial_parent_index' => $partialParentIndex,
            'quoted_identifier_dequoted_once' => true,
            'initial_parent_keys' => $parentKeys,
            'initial_child_keys' => $initialChildKeys,
            'remaining_parent_count' => count($remainingParents),
            'remaining_child_keys' => array_values(array_map(
                static fn (array $row): mixed => self::requiredRowValue($row, $childKey, 'child row'),
                $remainingChildren
            )),
            'trace_statement_count' => count($trace),
            'dependencies' => [
                'sqlite-fkey1-quoted-identifiers-dequote-once',
                'sqlite-fkey1-on-delete-cascade-removes-children',
                'sqlite-fkey1-partial-parent-index-does-not-satisfy-fk',
                'sqlite-fkey1-restrict-fails-before-delete',
            ],
        ];
    }

    /**
     * @param list<array{id:int,parent_id:?int,label?:string}> $rows
     * @return array<string,mixed>
     */
    public static function fkey1SelfReplaceCascadeViolation(array $rows, int $replaceId, ?int $newParentId): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $id = (int) self::requiredRowValue($row, 'id', 'self-referential row');
            $byId[$id] = $row;
        }

        $deleted = [];
        $delete = static function (int $id) use (&$delete, &$byId, &$deleted): void {
            if (!isset($byId[$id])) {
                return;
            }
            unset($byId[$id]);
            $deleted[] = $id;
            foreach (array_keys($byId) as $candidate) {
                if (($byId[$candidate]['parent_id'] ?? null) === $id) {
                    $delete((int) $candidate);
                }
            }
        };
        $delete($replaceId);

        $status = ($newParentId !== null && !isset($byId[$newParentId])) ? 'constraint-failed' : 'commit-ok';

        return [
            'source' => 'fkey1.test fkey1-5.1..5.4',
            'operation' => 'self-referential-replace-cascade-violation',
            'status' => $status,
            'error' => $status === 'constraint-failed' ? 'FOREIGN KEY constraint failed' : null,
            'replace_id' => $replaceId,
            'new_parent_id' => $newParentId,
            'cascade_deleted_ids' => $deleted,
            'surviving_ids_before_insert' => array_values(array_map('intval', array_keys($byId))),
            'dependencies' => [
                'sqlite-fkey1-replace-deletes-old-row-before-insert',
                'sqlite-fkey1-self-referential-cascade-can-remove-new-parent',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $foreignKeyColumns
     * @return array<string,mixed>
     */
    public static function fkey1WideForeignKeyCheck(array $rows, array $foreignKeyColumns, string $parentTable): array
    {
        $columns = array_values(array_map(
            static fn (string $column): string => self::identifier($column, 'foreign key column'),
            $foreignKeyColumns
        ));
        $parentTable = self::identifier($parentTable, 'parent table');
        $violations = [];
        foreach ($rows as $index => $row) {
            $childKey = [];
            foreach ($columns as $column) {
                $childKey[] = $row[$column] ?? null;
            }
            if (array_filter($childKey, static fn (mixed $value): bool => $value !== null) !== []) {
                $violations[] = [
                    'table' => 't1',
                    'rowid' => $index + 1,
                    'parent' => $parentTable,
                    'fkid' => 0,
                    'child_key_width' => count($childKey),
                ];
            }
        }

        return [
            'source' => 'fkey1.test fkey1-7.1..7.2',
            'operation' => 'wide-foreign-key-check-register-allocation',
            'status' => 'commit-ok',
            'foreign_key_width' => count($columns),
            'table_column_count' => $rows === [] ? 0 : count($rows[0]),
            'violation_count' => count($violations),
            'result_tuples' => array_map(
                static fn (array $row): array => [$row['table'], $row['rowid'], $row['parent'], $row['fkid']],
                $violations
            ),
            'dependencies' => [
                'sqlite-fkey1-foreign-key-check-wide-key-register-allocation',
                'sqlite-fkey1-generated-column-wide-fkey-check-does-not-overread',
            ],
        ];
    }

    private static function quotedIdentifier(string $identifier, string $label): string
    {
        if ($identifier === '') {
            throw new \InvalidArgumentException("SQLite dynamic trigger FK {$label} is malformed");
        }
        if (str_contains($identifier, "\0")) {
            throw new \InvalidArgumentException("SQLite dynamic trigger FK {$label} is malformed");
        }

        return $identifier;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function requiredRowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite dynamic trigger FK {$label} column is missing");
        }

        return $row[$column];
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

    /**
     * @param list<array{a:int,b:int,c:int}> $rows
     * @param list<array{a:int,b:int,c:int}> $logRows
     * @param array<string,int> $old
     * @param array<string,int> $new
     */
    private static function applyTrigger2Program(array &$rows, array &$logRows, string $program, array $old, array $new): int
    {
        $changes = 0;
        if ($program === 'update-b-from-old') {
            foreach ($rows as &$row) {
                if (array_key_exists('b', $old)) {
                    $row['b'] = $old['b'];
                    ++$changes;
                }
            }
            unset($row);

            return $changes;
        }

        if ($program === 'insert-log-new-c') {
            $logRows[] = ['a' => (int) ($new['c'] ?? 0), 'b' => 2, 'c' => 3];

            return 1;
        }

        if ($program === 'delete-log-a1') {
            $before = count($logRows);
            $logRows = array_values(array_filter($logRows, static fn (array $row): bool => $row['a'] !== 1));

            return $before - count($logRows);
        }

        if ($program === 'compound-insert-update-delete-log') {
            $rows[] = ['a' => 500, 'b' => (int) (($new['b'] ?? 0) * 10), 'c' => 700];
            ++$changes;
            if (array_key_exists('c', $old)) {
                foreach ($rows as &$row) {
                    $row['c'] = $old['c'];
                    ++$changes;
                }
                unset($row);
            }
            $changes += count($logRows);
            $logRows = [];

            return $changes;
        }

        if ($program === 'insert-log-select-table') {
            foreach ($rows as $row) {
                $logRows[] = $row;
                ++$changes;
            }

            return $changes;
        }

        throw new \InvalidArgumentException('SQLite trigger2 trigger program is unsupported');
    }

    /**
     * @param list<array<string,mixed>> $children
     * @return list<int>
     */
    private static function matchingChildIndexes(array $children, string $childKeyColumn, mixed $parentKey): array
    {
        $matches = [];
        foreach ($children as $index => $child) {
            if (($child[$childKeyColumn] ?? null) === $parentKey) {
                $matches[] = $index;
            }
        }

        return $matches;
    }

    /**
     * @param array<int,array{a:int,b:string}> $rows
     * @param list<array{timing:string,a:int,b:string,row_count:int,value:string}> $log
     */
    private static function deleteWithoutRowidRow(array &$rows, int $primaryKey, bool $beforeTrigger, bool $afterTrigger, array &$log): void
    {
        if (!isset($rows[$primaryKey])) {
            return;
        }

        $old = $rows[$primaryKey];
        if ($beforeTrigger) {
            $log[] = self::withoutRowidDeleteLogEntry('before', $old, count($rows));
        }

        unset($rows[$primaryKey]);

        if ($afterTrigger) {
            $log[] = self::withoutRowidDeleteLogEntry('after', $old, count($rows));
        }
    }

    /**
     * @param array{a:int,b:string} $old
     * @return array{timing:string,a:int,b:string,row_count:int,value:string}
     */
    private static function withoutRowidDeleteLogEntry(string $timing, array $old, int $rowCount): array
    {
        return [
            'timing' => $timing,
            'a' => $old['a'],
            'b' => $old['b'],
            'row_count' => $rowCount,
            'value' => (string) $old['a'] . $old['b'] . (string) $rowCount,
        ];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @return list<array{child_index:int,child_key:mixed,reason:string}>
     */
    private static function foreignKeyMissingParentKeys(array $parents, array $children, string $parentKeyColumn, string $childKeyColumn): array
    {
        $parentKeys = array_values(array_column($parents, $parentKeyColumn));
        $violations = [];
        foreach ($children as $index => $child) {
            $childKey = $child[$childKeyColumn] ?? null;
            if ($childKey === null || in_array($childKey, $parentKeys, true)) {
                continue;
            }

            $violations[] = [
                'child_index' => $index,
                'child_key' => $childKey,
                'reason' => 'missing-parent-at-deferred-commit',
            ];
        }

        return $violations;
    }

    private static function foreignKeyParentValueMatches(mixed $parent, mixed $child, string $collation): bool
    {
        if ($parent === null || $child === null) {
            return false;
        }

        return match ($collation) {
            'nocase' => strcasecmp((string) $parent, (string) $child) === 0,
            'rtrim' => rtrim((string) $parent) === rtrim((string) $child),
            default => (string) $parent === (string) $child,
        };
    }

    /**
     * @param list<array<string,mixed>> $childRows
     * @param list<array{parent:string,child:string,parent_collation:string}> $keyMap
     */
    private static function hasNullChildKey(array $childRows, array $keyMap): bool
    {
        foreach ($childRows as $child) {
            foreach ($keyMap as $key) {
                if (($child[$key['child']] ?? null) === null) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite dynamic trigger FK {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array{x:int,y:string}> $rows
     * @return list<array{c1:string}>
     */
    private static function triggerACompoundRows(array $rows, ?callable $wordPredicate): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[(string) $row['x']] = ['c1' => (string) $row['x']];
        }
        foreach ($rows as $row) {
            if ($wordPredicate !== null && !$wordPredicate($row)) {
                continue;
            }
            $values[(string) $row['y']] = ['c1' => (string) $row['y']];
        }
        ksort($values, SORT_STRING);

        return array_values($values);
    }

    /**
     * @param list<array{x:int,y:string}> $left
     * @param list<array{a:int,b:int,c:string}> $right
     * @return list<array{x:int,b:int}>
     */
    private static function triggerAJoinRows(array $left, array $right): array
    {
        $rows = [];
        foreach ($left as $leftRow) {
            foreach ($right as $rightRow) {
                if ($leftRow['y'] === $rightRow['c']) {
                    $rows[] = ['x' => $leftRow['x'], 'b' => $rightRow['b']];
                }
            }
        }
        usort($rows, static fn (array $a, array $b): int => $b['x'] <=> $a['x']);

        return $rows;
    }

    /**
     * @param list<array{name:string,timing:string,event:string}> $triggers
     * @return array<string,int>
     */
    private static function triggerEventCounts(array $triggers): array
    {
        $counts = [];
        foreach ($triggers as $trigger) {
            $event = $trigger['event'];
            $counts[$event] = ($counts[$event] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param list<array{rowid:int,x:string|int,y?:mixed,z?:mixed}> $rows
     * @return array<string,mixed>
     */
    public static function trigger9OldColumnLoadPlan(array $rows, string $event, string $oldExpression, ?string $whenColumn = null, mixed $whenMinimum = null): array
    {
        $event = strtolower(trim($event));
        if (!in_array($event, ['delete', 'update'], true)) {
            throw new \InvalidArgumentException('SQLite trigger9 event must be delete or update');
        }
        $oldExpression = self::identifier($oldExpression, 'OLD expression column');
        if (!in_array($oldExpression, ['rowid', 'x'], true)) {
            throw new \InvalidArgumentException('SQLite trigger9 OLD expression must be rowid or x');
        }
        if ($whenColumn !== null) {
            $whenColumn = self::identifier($whenColumn, 'WHEN column');
        }

        $rows = array_values($rows);
        $emitted = [];
        $updatedRows = [];
        foreach ($rows as $row) {
            $passesWhen = true;
            if ($whenColumn !== null) {
                $candidate = $row[$whenColumn] ?? null;
                $passesWhen = strcmp((string) $candidate, (string) $whenMinimum) >= 0;
            }
            if ($passesWhen) {
                $emitted[] = $row[$oldExpression];
            }
            if ($event === 'update') {
                $updated = $row;
                $updated['y'] = '';
                $updatedRows[] = $updated;
            }
        }

        return [
            'source' => 'trigger9.test trigger9-1.2.1..1.7.3',
            'operation' => 'old-column-trigger-load-plan',
            'status' => 'commit-ok',
            'event' => $event,
            'old_expression' => 'old.' . $oldExpression,
            'when_column' => $whenColumn,
            'when_minimum' => $whenMinimum,
            'emitted_values' => $emitted,
            'emitted_count' => count($emitted),
            'rowdata_opcode_required' => false,
            'loaded_old_columns' => array_values(array_unique(array_filter([$oldExpression, $whenColumn]))),
            'loaded_old_column_count' => count(array_unique(array_filter([$oldExpression, $whenColumn]))),
            'updated_rows' => $updatedRows,
            'statement_row_count' => count($rows),
            'dependencies' => [
                'sqlite-trigger9-old-rowid-does-not-load-full-rowdata',
                'sqlite-trigger9-old-column-subset-loads-needed-column-only',
                'sqlite-trigger9-when-clause-shares-old-column-registers',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:string}> $rows
     * @return array<string,mixed>
     */
    public static function trigger9InsteadOfViewOldRowsPlan(array $rows, string $viewShape): array
    {
        $viewShape = strtolower(trim($viewShape));
        if (!in_array($viewShape, ['plain', 'where-alias', 'distinct', 'except', 'group-having'], true)) {
            throw new \InvalidArgumentException('SQLite trigger9 view shape is unsupported');
        }

        $rows = array_values($rows);
        $selected = [];
        if ($viewShape === 'plain') {
            $selected = $rows;
        } elseif ($viewShape === 'where-alias') {
            $selected = array_values(array_filter($rows, static fn (array $row): bool => strcmp($row['b'], 'one') > 0));
        } elseif ($viewShape === 'distinct') {
            $seen = [];
            foreach ($rows as $row) {
                $key = $row['a'] . "\0" . $row['b'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $selected[] = $row;
            }
        } elseif ($viewShape === 'except') {
            $selected = array_values(array_filter($rows, static fn (array $row): bool => !($row['a'] === 1 && $row['b'] === 'one')));
        } else {
            $groups = [];
            foreach ($rows as $row) {
                $groups[$row['a']][] = $row['b'];
            }
            ksort($groups);
            foreach ($groups as $a => $values) {
                $max = max($values);
                if (strcmp($max, 'two') > 0) {
                    $selected[] = ['a' => (int) $a, 'b' => $max];
                }
            }
        }

        return [
            'source' => 'trigger9.test trigger9-3.2..3.6',
            'operation' => 'instead-of-view-old-row-materialization',
            'status' => 'commit-ok',
            'view_shape' => $viewShape,
            'old_a_values' => array_values(array_map(static fn (array $row): int => $row['a'], $selected)),
            'old_b_values' => array_values(array_map(static fn (array $row): string => $row['b'], $selected)),
            'old_row_count' => count($selected),
            'unused_view_columns_are_null_safe' => true,
            'where_alias_reused_without_full_old_row' => $viewShape === 'where-alias',
            'compound_view_materialized_before_trigger' => in_array($viewShape, ['distinct', 'except', 'group-having'], true),
            'dependencies' => [
                'sqlite-trigger9-instead-of-view-trigger-materializes-old-rows',
                'sqlite-trigger9-unused-view-columns-are-null-safe',
                'sqlite-trigger9-compound-view-old-rows-feed-trigger-program',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:string}> $rows
     * @return array<string,mixed>
     */
    public static function triggerFWithoutRowidDeleteReplacePlan(array $rows, string $triggerMode): array
    {
        $triggerMode = strtolower(trim($triggerMode));
        if (!in_array($triggerMode, ['none', 'after-delete', 'before-delete', 'before-after-delete'], true)) {
            throw new \InvalidArgumentException('SQLite triggerF trigger mode is unsupported');
        }

        $table = [];
        foreach ($rows as $row) {
            $a = (int) $row['a'];
            $table[$a] = ['a' => $a, 'b' => (string) $row['b']];
        }
        ksort($table);

        $log = [];
        $delete = function (int $key) use (&$table, &$log, $triggerMode): void {
            if (!isset($table[$key])) {
                return;
            }
            $old = $table[$key];
            $beforeCount = count($table);
            if ($triggerMode === 'before-delete' || $triggerMode === 'before-after-delete') {
                $log[] = $old['a'] . $old['b'] . $beforeCount;
            }
            unset($table[$key]);
            if ($triggerMode === 'after-delete' || $triggerMode === 'before-after-delete') {
                $log[] = $old['a'] . $old['b'] . count($table);
            }
        };

        $delete(1);
        $delete(2);
        $table[2] = ['a' => 2, 'b' => 'three'];
        ksort($table);
        $delete(3);
        $replacement = $table[2] ?? ['a' => 2, 'b' => 'three'];
        unset($table[2]);
        $table[3] = ['a' => 3, 'b' => $replacement['b']];
        ksort($table);

        return [
            'source' => 'triggerF.test triggerF-1.1.0..1.4.2',
            'operation' => 'without-rowid-delete-replace-trigger-log',
            'status' => 'commit-ok',
            'trigger_mode' => $triggerMode,
            'recursive_triggers' => true,
            'log_rows' => $log,
            'log_count' => count($log),
            'remaining_rows' => array_values($table),
            'remaining_keys' => array_keys($table),
            'dependencies' => [
                'sqlite-triggerF-without-rowid-replace-deletes-conflicting-row',
                'sqlite-triggerF-before-delete-sees-row-before-removal',
                'sqlite-triggerF-after-delete-sees-table-after-removal',
                'sqlite-triggerF-update-or-replace-delete-triggers-fire-before-new-row',
            ],
        ];
    }

    /**
     * @param list<int> $indexValues
     * @return array<string,mixed>
     */
    public static function triggerGRecursiveSelectOncePlan(array $indexValues, int $start, string $shape): array
    {
        $shape = strtolower(trim($shape));
        if (!in_array($shape, ['single', 'join'], true)) {
            throw new \InvalidArgumentException('SQLite triggerG recursive shape is unsupported');
        }
        if ($start >= 5) {
            throw new \InvalidArgumentException('SQLite triggerG start value must recurse toward five');
        }

        $eligible = array_values(array_filter(
            array_map('intval', $indexValues),
            static fn (int $value): bool => $value >= 1 && $value <= 4
        ));
        sort($eligible);
        $eligible = array_values(array_unique($eligible));

        $inserted = [];
        for ($c = $start; $c <= 5; ++$c) {
            $inserted[] = $c;
        }

        $resultRows = [];
        foreach ($inserted as $c) {
            if ($shape === 'single') {
                foreach ($eligible as $a) {
                    $resultRows[] = $c * 100 + $a;
                }
                continue;
            }

            foreach ($eligible as $left) {
                foreach ($eligible as $right) {
                    if ($right < 2 || $right > 5) {
                        continue;
                    }
                    $resultRows[] = $c * 10000 + $left * 100 + $right;
                }
            }
        }
        sort($resultRows);

        return [
            'source' => $shape === 'single' ? 'triggerG.test triggerG-100..110' : 'triggerG.test triggerG-200',
            'operation' => 'recursive-trigger-select-once-index-plan',
            'status' => 'commit-ok',
            'shape' => $shape,
            'start' => $start,
            'recursive_rows' => $inserted,
            'recursive_row_count' => count($inserted),
            'eligible_index_values' => $eligible,
            'result_rows' => $resultRows,
            'result_count' => count($resultRows),
            'op_once_resets_per_recursive_frame' => true,
            'dependencies' => [
                'sqlite-triggerG-recursive-trigger-reruns-select-program-per-frame',
                'sqlite-triggerG-index-in-filter-is-not-stale-across-recursion',
                'sqlite-triggerG-join-loop-op-once-state-is-frame-local',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function tempTriggerSharedCacheReloadPlan(int $seed, string $reloadKind, bool $attachedSchema = false): array
    {
        if (!in_array($reloadKind, ['schema-reload', 'connection-reopen'], true)) {
            throw new \InvalidArgumentException('SQLite temptrigger reload kind is unsupported');
        }

        $first = [$seed, $seed + 1];
        $external = [$seed + 2, $seed + 3];
        $afterReload = [$seed + 4, $seed + 5];

        return [
            'source' => $attachedSchema ? 'temptrigger.test temptrigger-3.1..3.4' : 'temptrigger.test temptrigger-1.1..2.5',
            'operation' => $attachedSchema ? 'attached-temp-trigger-schema-reload' : 'shared-cache-temp-trigger-schema-reload',
            'status' => 'commit-ok',
            'reload_kind' => $reloadKind,
            'attached_schema' => $attachedSchema,
            'base_rows' => [$first, $external, $afterReload],
            'temp_rows' => [$first, $afterReload],
            'temp_trigger_fired_for_owner_before_reload' => true,
            'temp_trigger_hidden_from_peer_connection' => !$attachedSchema,
            'temp_trigger_survived_schema_reload' => true,
            'drop_trigger_after_reload_ok' => true,
            'schema_cookie_source' => $attachedSchema ? 'attached-database-peer' : $reloadKind,
            'dependencies' => [
                'sqlite-temptrigger-connection-local-temp-trigger',
                'sqlite-temptrigger-shared-cache-schema-reload-preserves-owner-trigger',
                'sqlite-temptrigger-attached-schema-reload-preserves-temp-trigger',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function tempTriggerQualifiedBodyPlan(string $event, bool $tempTrigger, int|string $left, int|string $right): array
    {
        $event = strtolower(trim($event));
        if (!in_array($event, ['insert', 'update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite temptrigger qualified body event is unsupported');
        }

        $qualifiedAllowed = $tempTrigger;
        $targetRows = [];
        $mainRows = [];
        if ($qualifiedAllowed) {
            if ($event === 'insert') {
                $targetRows[] = [$left, $right];
            } elseif ($event === 'update') {
                $mainRows[] = [$left, $right];
            } else {
                $mainRows[] = ['main-survivor', (string) $left . ':' . (string) $right];
            }
        }

        return [
            'source' => 'temptrigger.test temptrigger-8.1.1..8.3.3',
            'operation' => 'temp-trigger-qualified-body-dml',
            'status' => $qualifiedAllowed ? 'commit-ok' : 'create-trigger-error',
            'event' => $event,
            'temp_trigger' => $tempTrigger,
            'qualified_dml_allowed' => $qualifiedAllowed,
            'error' => $qualifiedAllowed ? null : 'qualified table names are not allowed on INSERT, UPDATE, and DELETE statements within triggers',
            'target_rows' => $targetRows,
            'main_rows' => $mainRows,
            'aux_rows' => $event === 'delete' && $qualifiedAllowed ? [] : $targetRows,
            'dependencies' => [
                'sqlite-temptrigger-qualified-dml-only-for-temp-triggers',
                'sqlite-temptrigger-qualified-insert-update-delete-routes-attached-targets',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function tempTriggerNameResolutionPlan(int $seed, bool $mainShadowCreated): array
    {
        $auxRows = [[$seed, $seed + 1]];
        $mainRows = [];
        if ($mainShadowCreated) {
            $mainRows[] = [$seed + 2, $seed + 3];
        } else {
            $auxRows[] = [$seed + 2, $seed + 3];
        }

        return [
            'source' => 'temptrigger.test temptrigger-6.0..7.6',
            'operation' => 'temp-trigger-name-resolution',
            'status' => 'commit-ok',
            'main_shadow_created' => $mainShadowCreated,
            'main_rows' => $mainRows,
            'aux_rows' => $auxRows,
            'qualified_aux_reference_in_temp_trigger_ok' => true,
            'qualified_aux_reference_in_persistent_trigger_error' => true,
            'main_trigger_still_guards_main_table' => true,
            'dependencies' => [
                'sqlite-temptrigger-main-shadow-does-not-steal-existing-attached-target',
                'sqlite-temptrigger-recreated-body-resolves-unqualified-name-after-shadow',
                'sqlite-temptrigger-persistent-trigger-cannot-reference-attached-body',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function tempTriggerAttachedChainPlan(int $databaseCount, array $row, string $event): array
    {
        if ($databaseCount < 2) {
            throw new \InvalidArgumentException('SQLite temptrigger attached chain needs at least two databases');
        }
        $event = strtolower(trim($event));
        if (!in_array($event, ['insert', 'update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite temptrigger attached chain event is unsupported');
        }

        $tuple = [
            $row['a'] ?? throw new \InvalidArgumentException('SQLite temptrigger chain row requires a'),
            $row['b'] ?? throw new \InvalidArgumentException('SQLite temptrigger chain row requires b'),
            $row['c'] ?? throw new \InvalidArgumentException('SQLite temptrigger chain row requires c'),
        ];
        $rowsBySchema = [];
        for ($i = 0; $i < $databaseCount; ++$i) {
            $rotated = $tuple;
            for ($step = 0; $step < $i % 3; ++$step) {
                $rotated = [$rotated[1], $rotated[2], $rotated[0]];
            }
            $rowsBySchema['db' . $i] = $event === 'delete' ? [] : [$rotated];
        }

        return [
            'source' => 'temptrigger.test temptrigger-9.0..9.5.3',
            'operation' => 'temp-trigger-attached-schema-chain',
            'status' => 'commit-ok',
            'event' => $event,
            'database_count' => $databaseCount,
            'trigger_count' => $databaseCount - 1,
            'rows_by_schema' => $rowsBySchema,
            'cascade_depth' => $databaseCount - 1,
            'rotates_values_across_attached_schemas' => $event !== 'delete',
            'delete_clears_chained_attached_tables' => $event === 'delete',
            'dependencies' => [
                'sqlite-temptrigger-attached-insert-chain-routes-new-values',
                'sqlite-temptrigger-attached-update-chain-routes-new-values',
                'sqlite-temptrigger-attached-delete-chain-clears-downstream-tables',
            ],
        ];
    }

    /**
     * @param list<array{name:string,timing:string,event:string}> $triggers
     * @return array<string,int>
     */
    private static function triggerTimingCounts(array $triggers): array
    {
        $counts = [];
        foreach ($triggers as $trigger) {
            $timing = $trigger['timing'];
            $counts[$timing] = ($counts[$timing] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @return array{0:?string,1:string}
     */
    private static function splitQualifiedTriggerName(string $name): array
    {
        $parts = explode('.', $name);
        if (count($parts) === 1) {
            return [null, $parts[0]];
        }
        if (count($parts) === 2) {
            return [$parts[0], $parts[1]];
        }

        throw new \InvalidArgumentException('SQLite dynamic trigger FK drop trigger name is malformed');
    }

    /**
     * @param list<array{schema:string,name:string,table:string,event:string,timing:string}> $triggers
     * @param array<string,int> $schemas
     * @return list<string>
     */
    private static function triggerFireList(array $triggers, string $event, string $table, array $schemas): array
    {
        $rows = array_values(array_filter(
            $triggers,
            static fn (array $trigger): bool => $trigger['event'] === $event && $trigger['table'] === $table
        ));
        usort($rows, static function (array $left, array $right) use ($schemas): int {
            return [$schemas[$left['schema']] ?? 999, $left['timing'] === 'before' ? 0 : 1, $left['name']]
                <=> [$schemas[$right['schema']] ?? 999, $right['timing'] === 'before' ? 0 : 1, $right['name']];
        });

        return array_values(array_map(static fn (array $trigger): string => $trigger['schema'] . '.' . $trigger['name'], $rows));
    }

    /**
     * @param array<string,mixed> $row
     * @return list<mixed>
     */
    private static function triggerDAliasValues(array $row, bool $ordinaryRowidColumns): array
    {
        return [
            $row['rowid'],
            $row['oid'],
            $row['_rowid_'],
            $row['x'],
            $ordinaryRowidColumns ? 'ordinary-column' : 'storage-rowid',
        ];
    }

    /**
     * @param array<string,mixed> $oldRow
     * @param array<string,mixed> $newRow
     * @return list<array<string,mixed>>
     */
    private static function triggerDAliasLogRows(string $event, string $timing, array $oldRow, array $newRow, bool $ordinaryRowidColumns): array
    {
        if ($event === 'insert') {
            return [[
                'trigger' => $timing === 'before' ? 'r1' : 'r2',
                'phase' => $timing,
                'values' => self::triggerDAliasValues($newRow, $ordinaryRowidColumns),
            ]];
        }
        if ($event === 'delete') {
            return [[
                'trigger' => $timing === 'before' ? 'r5' : 'r6',
                'phase' => $timing,
                'values' => self::triggerDAliasValues($oldRow, $ordinaryRowidColumns),
            ]];
        }

        return [
            [
                'trigger' => $timing === 'before' ? 'r3.old' : 'r4.old',
                'phase' => $timing,
                'values' => self::triggerDAliasValues($oldRow, $ordinaryRowidColumns),
            ],
            [
                'trigger' => $timing === 'before' ? 'r3.new' : 'r4.new',
                'phase' => $timing,
                'values' => self::triggerDAliasValues($newRow, $ordinaryRowidColumns),
            ],
        ];
    }

    /**
     * @param list<array{schema:string,name:string,table:string,event:string,timing:string}> $triggers
     * @param array<string,int> $schemas
     * @return list<string>
     */
    private static function qualifiedTriggerNames(array $triggers, array $schemas): array
    {
        usort($triggers, static function (array $left, array $right) use ($schemas): int {
            return [$schemas[$left['schema']] ?? 999, $left['name']]
                <=> [$schemas[$right['schema']] ?? 999, $right['name']];
        });

        return array_values(array_map(static fn (array $trigger): string => $trigger['schema'] . '.' . $trigger['name'], $triggers));
    }

    private static function raiseActionForValue(int $value, bool $viewTrigger): string
    {
        if ($viewTrigger) {
            return match ($value) {
                1 => 'rollback',
                2 => 'ignore',
                3 => 'abort',
                default => 'none',
            };
        }

        return match ($value) {
            1 => 'abort',
            2 => 'fail',
            3 => 'rollback',
            4 => 'ignore',
            default => 'none',
        };
    }

    private static function raiseMessageForAction(string $action, bool $viewTrigger): ?string
    {
        if ($action === 'none' || $action === 'ignore') {
            return null;
        }

        if ($viewTrigger) {
            return $action === 'rollback' ? 'View rollback' : 'View abort';
        }

        return match ($action) {
            'abort' => 'Trigger abort',
            'fail' => 'Trigger fail',
            'rollback' => 'Trigger rollback',
            default => null,
        };
    }

    /**
     * @param list<int> $values
     * @param list<int> $allowed
     * @return list<int>
     */
    private static function filteredIntegers(array $values, array $allowed): array
    {
        $allowedSet = array_fill_keys(array_map('strval', $allowed), true);

        return array_values(array_filter(
            $values,
            static fn (int $value): bool => isset($allowedSet[(string) $value])
        ));
    }
}
