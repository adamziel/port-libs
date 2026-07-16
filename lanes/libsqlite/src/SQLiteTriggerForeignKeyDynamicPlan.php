<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerForeignKeyDynamicPlan
{
    /**
     * @return array<string,mixed>
     */
    public static function immediateInsertRepairScenario(bool $withRepairTrigger = true): array
    {
        $king = [];
        $prince = [];
        $events = [];

        $row = ['c' => 1, 'd' => 2];
        $prince[] = $row;
        $events[] = ['step' => 'insert-child', 'table' => 'prince', 'key' => 1];

        if ($withRepairTrigger && !self::containsKey($king, 'a', $row['c'])) {
            $king[] = ['a' => $row['c'], 'b' => null];
            $events[] = ['step' => 'after-insert-trigger', 'trigger' => 'kt', 'inserted_parent' => $row['c']];
        }

        $violations = self::childViolations($prince, $king, 'c', 'a');
        if ($violations !== []) {
            $prince = [];
            $events[] = ['step' => 'statement-rollback', 'reason' => 'immediate-foreign-key'];
        }

        return [
            'status' => $violations === [] ? 'statement-ok' : 'constraint-failed',
            'king' => $king,
            'prince' => $prince,
            'events' => $events,
            'violations' => $violations,
            'changes' => count($prince) + count($king),
            'upstream' => 'e_fkey.test e_fkey-31.2/e_fkey-31.3',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function deferredTransactionScenario(bool $repairFive = true, bool $repairTen = true): array
    {
        $parents = [];
        $children = [];
        $events = [];

        foreach ([5, 10] as $key) {
            $children[] = ['c' => $key];
            $events[] = ['step' => 'insert-deferred-child', 'key' => $key];
        }

        $firstViolations = self::childViolations($children, $parents, 'c', 'k');
        if ($repairTen) {
            $parents[] = ['k' => 10];
            $events[] = ['step' => 'repair-parent', 'key' => 10];
        }
        $secondViolations = self::childViolations($children, $parents, 'c', 'k');
        if ($repairFive) {
            $parents[] = ['k' => 5];
            $events[] = ['step' => 'repair-parent', 'key' => 5];
        }
        $finalViolations = self::childViolations($children, $parents, 'c', 'k');

        return [
            'commit_attempts' => [
                ['status' => $firstViolations === [] ? 'commit-ok' : 'commit-blocked', 'violations' => $firstViolations],
                ['status' => $secondViolations === [] ? 'commit-ok' : 'commit-blocked', 'violations' => $secondViolations],
                ['status' => $finalViolations === [] ? 'commit-ok' : 'commit-blocked', 'violations' => $finalViolations],
            ],
            'parents' => self::sortRows($parents, 'k'),
            'children' => $children,
            'events' => $events,
            'upstream' => 'e_fkey.test e_fkey-32.1..32.9',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function deferredSavepointScenario(bool $fixNestedViolation = true): array
    {
        $rows = [
            ['a' => 1, 'b' => 1],
            ['a' => 2, 'b' => 2],
            ['a' => 3, 'b' => 3],
        ];
        $events = [
            ['step' => 'begin'],
            ['step' => 'savepoint', 'name' => 'one', 'kind' => 'nested'],
        ];

        $rows[] = ['a' => 4, 'b' => 5];
        $events[] = ['step' => 'insert', 'row' => ['a' => 4, 'b' => 5]];
        $events[] = ['step' => 'release', 'name' => 'one', 'status' => 'ok-nested-deferred-open'];

        $firstViolations = self::selfReferenceViolations($rows);
        if ($fixNestedViolation) {
            foreach ($rows as &$row) {
                if ($row['a'] === 4) {
                    $row['a'] = 5;
                    break;
                }
            }
            unset($row);
            $events[] = ['step' => 'repair-self-reference', 'old_a' => 4, 'new_a' => 5];
        }
        $finalViolations = self::selfReferenceViolations($rows);

        return [
            'release_status' => 'ok',
            'commit_attempts' => [
                ['status' => $firstViolations === [] ? 'commit-ok' : 'commit-blocked', 'violations' => $firstViolations],
                ['status' => $finalViolations === [] ? 'commit-ok' : 'commit-blocked', 'violations' => $finalViolations],
            ],
            'rows' => self::sortRows($rows, 'a'),
            'events' => $events,
            'upstream' => 'e_fkey.test e_fkey-36.1..36.4',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function transactionSavepointScenario(bool $rollbackOuter = false): array
    {
        $rows = [
            ['a' => 1, 'b' => 1],
            ['a' => 2, 'b' => 2],
            ['a' => 3, 'b' => 3],
        ];
        $snapshot = $rows;
        $events = [
            ['step' => 'savepoint', 'name' => 'one', 'kind' => 'transaction'],
            ['step' => 'savepoint', 'name' => 'two', 'kind' => 'nested'],
        ];
        $rows[] = ['a' => 6, 'b' => 7];
        $events[] = ['step' => 'insert', 'row' => ['a' => 6, 'b' => 7]];
        $events[] = ['step' => 'release', 'name' => 'two', 'status' => 'ok'];

        $violations = self::selfReferenceViolations($rows);
        $releaseOne = $violations === [] ? 'release-ok' : 'release-blocked';
        $events[] = ['step' => 'release', 'name' => 'one', 'status' => $releaseOne];

        if ($rollbackOuter) {
            $rows = $snapshot;
            $events[] = ['step' => 'rollback-to', 'name' => 'one'];
            $events[] = ['step' => 'release', 'name' => 'one', 'status' => 'release-ok'];
            $violations = [];
        }

        return [
            'release_status' => $releaseOne,
            'rows' => self::sortRows($rows, 'a'),
            'violations' => $violations,
            'events' => $events,
            'upstream' => 'e_fkey.test e_fkey-37.1..37.6',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function parentUpdateOrderScenario(int|string $newParentKey): array
    {
        $parent = [['x' => 1]];
        $child = [['a' => 1]];
        $events = [];

        $old = $parent[0]['x'];
        $beforeInserted = self::sqliteSubtract($newParentKey, $old);
        $parent[] = ['x' => $beforeInserted];
        $events[] = ['step' => 'before-trigger', 'inserted_parent' => $beforeInserted];

        $parent[0]['x'] = $newParentKey;
        $events[] = ['step' => 'update-parent-row', 'old' => $old, 'new' => $newParentKey];

        $default = self::maxColumn($parent, 'x');
        $child[0]['a'] = $default;
        $events[] = ['step' => 'foreign-key-set-default', 'child_value' => $default];

        $afterInserted = self::sqliteAdd($newParentKey, $old);
        $parent[] = ['x' => $afterInserted];
        $events[] = ['step' => 'after-trigger', 'inserted_parent' => $afterInserted];

        return [
            'parent' => $parent,
            'child' => $child,
            'events' => $events,
            'event_order' => array_column($events, 'step'),
            'upstream' => 'e_fkey.test e_fkey-51.1..51.3',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function cascadeIgnoresRecursiveTriggerPragmaScenario(bool $recursiveTriggers): array
    {
        $foreignKeyRows = [
            ['node' => 1, 'parent' => null],
            ['node' => 2, 'parent' => 1],
            ['node' => 3, 'parent' => 1],
            ['node' => 4, 'parent' => 2],
            ['node' => 5, 'parent' => 2],
            ['node' => 6, 'parent' => 3],
            ['node' => 7, 'parent' => 3],
        ];
        $triggerRows = $foreignKeyRows;
        $events = [];

        $foreignKeyRows = self::deleteCascadeTree($foreignKeyRows, 1, $events, 'foreign-key-cascade', true);
        $triggerRows = self::deleteCascadeTree($triggerRows, 1, $events, 'after-delete-trigger', $recursiveTriggers);

        return [
            'recursive_triggers' => $recursiveTriggers,
            'foreign_key_remaining_nodes' => array_column($foreignKeyRows, 'node'),
            'trigger_remaining_nodes' => array_column($triggerRows, 'node'),
            'foreign_key_delete_count' => 7 - count($foreignKeyRows),
            'trigger_delete_count' => 7 - count($triggerRows),
            'events' => $events,
            'upstream' => 'fkey2.test fkey2-4.1..4.4',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function deleteTriggerRepairRestrictScenario(string $foreignKeyAction = 'no action'): array
    {
        $parents = [['x' => 'A'], ['x' => 'B']];
        $children = [['y' => 'a'], ['y' => 'b']];
        $events = [];
        $action = strtolower(trim($foreignKeyAction));

        if (!in_array($action, ['no action', 'restrict'], true)) {
            throw new \InvalidArgumentException('SQLite trigger foreign key dynamic delete action is unsupported');
        }

        if ($action === 'restrict' && self::matchingChildRows($children, 'y', $parents) !== []) {
            return [
                'status' => 'constraint-failed',
                'parents' => $parents,
                'children' => $children,
                'events' => [['step' => 'delete-blocked-before-trigger', 'action' => 'restrict']],
                'violations' => self::matchingChildRows($children, 'y', $parents),
                'upstream' => 'fkey2.test fkey2-12.2.1..12.2.4',
            ];
        }

        $deleted = $parents;
        $parents = [];
        foreach ($deleted as $old) {
            if (self::hasNocaseChild($children, 'y', (string) $old['x'])) {
                $parents[] = $old;
                $events[] = ['step' => 'after-delete-trigger-reinsert', 'value' => $old['x']];
            }
        }

        return [
            'status' => 'statement-ok',
            'parents' => $parents,
            'children' => $children,
            'events' => $events,
            'violations' => self::nocaseChildViolations($children, $parents, 'y', 'x'),
            'upstream' => 'fkey2.test fkey2-12.2.1..12.2.4',
        ];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $parents
     * @return list<array<string,mixed>>
     */
    private static function childViolations(array $children, array $parents, string $childKey, string $parentKey): array
    {
        $violations = [];
        foreach ($children as $rowid => $child) {
            $value = $child[$childKey] ?? null;
            if ($value === null || self::containsKey($parents, $parentKey, $value)) {
                continue;
            }
            $violations[] = ['rowid' => $rowid + 1, 'child_key' => $value, 'parent_key' => $value];
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function selfReferenceViolations(array $rows): array
    {
        $violations = [];
        foreach ($rows as $rowid => $row) {
            if (!self::containsKey($rows, 'a', $row['b'])) {
                $violations[] = ['rowid' => $rowid + 1, 'child_key' => $row['b'], 'parent_key' => $row['b']];
            }
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $parents
     * @return list<array<string,mixed>>
     */
    private static function nocaseChildViolations(array $children, array $parents, string $childKey, string $parentKey): array
    {
        $violations = [];
        foreach ($children as $rowid => $child) {
            $value = $child[$childKey] ?? null;
            if ($value === null) {
                continue;
            }
            foreach ($parents as $parent) {
                if (self::sqliteNocaseEquals((string) $value, (string) ($parent[$parentKey] ?? ''))) {
                    continue 2;
                }
            }
            $violations[] = ['rowid' => $rowid + 1, 'child_key' => $value, 'parent_key' => $value];
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function containsKey(array $rows, string $column, mixed $value): bool
    {
        foreach ($rows as $row) {
            if (($row[$column] ?? null) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $events
     * @return list<array<string,mixed>>
     */
    private static function deleteCascadeTree(array $rows, int $node, array &$events, string $source, bool $recursive): array
    {
        $children = [];
        foreach ($rows as $row) {
            if (($row['parent'] ?? null) === $node) {
                $children[] = (int) $row['node'];
            }
        }

        $rows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => (int) $row['node'] !== $node
        ));
        $events[] = ['step' => 'delete-row', 'source' => $source, 'node' => $node];

        if ($recursive) {
            foreach ($children as $child) {
                $rows = self::deleteCascadeTree($rows, $child, $events, $source, true);
            }
        } elseif ($source === 'after-delete-trigger') {
            foreach ($children as $child) {
                $rows = array_values(array_filter(
                    $rows,
                    static fn (array $row): bool => (int) $row['node'] !== $child
                ));
                $events[] = ['step' => 'delete-row', 'source' => $source, 'node' => $child];
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $parents
     * @return list<array<string,mixed>>
     */
    private static function matchingChildRows(array $children, string $childKey, array $parents): array
    {
        $matches = [];
        foreach ($children as $rowid => $child) {
            foreach ($parents as $parent) {
                if (self::sqliteNocaseEquals((string) ($child[$childKey] ?? ''), (string) ($parent['x'] ?? ''))) {
                    $matches[] = ['rowid' => $rowid + 1, 'child_key' => $child[$childKey], 'parent_key' => $parent['x']];
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * @param list<array<string,mixed>> $children
     */
    private static function hasNocaseChild(array $children, string $childKey, string $parentValue): bool
    {
        foreach ($children as $child) {
            if (self::sqliteNocaseEquals((string) ($child[$childKey] ?? ''), $parentValue)) {
                return true;
            }
        }

        return false;
    }

    private static function sqliteNocaseEquals(string $left, string $right): bool
    {
        return strcasecmp($left, $right) === 0;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function sortRows(array $rows, string $column): array
    {
        usort($rows, static fn (array $left, array $right): int => ($left[$column] <=> $right[$column]));

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function maxColumn(array $rows, string $column): mixed
    {
        $values = array_column($rows, $column);

        return max($values);
    }

    private static function sqliteAdd(int|string $left, int|string $right): int|string
    {
        return is_int($left) && is_int($right) ? $left + $right : (int) $left + (int) $right;
    }

    private static function sqliteSubtract(int|string $left, int|string $right): int|string
    {
        return is_int($left) && is_int($right) ? $left - $right : (int) $left - (int) $right;
    }
}
