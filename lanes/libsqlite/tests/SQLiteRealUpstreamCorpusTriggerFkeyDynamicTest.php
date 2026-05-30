<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyReturningPlan;
use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan;

$tests = [];

$parentRows = [
    ['setting_id' => 1, 'key_name' => 'alpha', 'next_id' => 2, 'version' => 1],
    ['setting_id' => 2, 'key_name' => 'beta', 'next_id' => 3, 'version' => 1],
    ['setting_id' => 3, 'key_name' => 'gamma', 'next_id' => null, 'version' => 1],
    ['setting_id' => 4, 'key_name' => 'delta', 'next_id' => null, 'version' => 1],
];
$childRows = [
    ['child_id' => 10, 'setting_id' => 1, 'payload' => 'uses-alpha'],
    ['child_id' => 11, 'setting_id' => 2, 'payload' => 'uses-beta'],
    ['child_id' => 12, 'setting_id' => 4, 'payload' => 'uses-delta'],
];
$returning = [
    ['expr' => 'old.setting_id', 'as' => 'old_id'],
    ['expr' => 'new.setting_id', 'as' => 'new_id'],
    ['expr' => 'new.key_name', 'as' => 'name'],
];

$updateCases = [
    'fkey2-11.1 on update cascade rewrites matching child keys' => [
        ['on_update' => 'cascade', 'deferred' => false],
        ['setting_id' => static fn (array $row): int => (int) $row['setting_id'] + 100],
        static fn (array $row): bool => $row['setting_id'] <= 2,
        [101, 102, 4],
        ['cascade', 'cascade'],
        [],
    ],
    'fkey2-9.4 on update set default uses configured child default' => [
        ['on_update' => 'set default', 'child_default' => 4, 'deferred' => false],
        ['setting_id' => 201],
        static fn (array $row): bool => $row['setting_id'] === 1,
        [4, 2, 4],
        ['set-default'],
        [],
    ],
    'fkey2-9.3 on update set null clears child keys' => [
        ['on_update' => 'set null', 'deferred' => false],
        ['setting_id' => 301],
        static fn (array $row): bool => $row['setting_id'] === 1,
        [null, 2, 4],
        ['set-null'],
        [],
    ],
    'fkey2-12.1 deferred no action records commit-time violation' => [
        ['on_update' => 'no action', 'deferred' => true],
        ['setting_id' => 401],
        static fn (array $row): bool => $row['setting_id'] === 1,
        [1, 2, 4],
        ['no action'],
        [
            ['child_index' => 0, 'child_key' => 1, 'parent' => 'setting_id', 'ordinal' => 0, 'phase' => 'statement'],
            ['child_index' => 0, 'child_key' => 1, 'parent' => 'setting_id', 'ordinal' => 0, 'phase' => 'after-trigger'],
        ],
    ],
    'fkey6-2.1 deferred pragma does not defer restrict action' => [
        ['on_update' => 'restrict', 'deferred' => true],
        ['setting_id' => 501],
        static fn (array $row): bool => $row['setting_id'] === 1,
        InvalidArgumentException::class,
        [],
        [],
    ],
];

foreach ($updateCases as $name => [$fk, $assignments, $where, $expectedChildKeys, $expectedActions, $expectedViolations]) {
    $tests['real upstream corpus trigger fkey dynamic ' . $name] = static function (TestRunner $t) use ($parentRows, $childRows, $returning, $fk, $assignments, $where, $expectedChildKeys, $expectedActions, $expectedViolations): void {
        $call = static fn (): array => SQLiteTriggerForeignKeyReturningPlan::updateParents(
            $parentRows,
            $childRows,
            $assignments,
            $where,
            ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => $fk['on_update'], 'deferred' => $fk['deferred'], 'child_default' => $fk['child_default'] ?? null],
            [
                ['name' => 'audit_before_update', 'timing' => 'before', 'event' => 'update', 'action' => 'audit', 'values' => ['old_id' => 'old.setting_id', 'new_id' => 'new.setting_id']],
                ['name' => 'audit_after_update', 'timing' => 'after', 'event' => 'update', 'action' => 'audit', 'values' => ['old_id' => 'old.setting_id', 'new_id' => 'new.setting_id']],
            ],
            $returning,
            'setting_id',
        );

        if (is_string($expectedChildKeys)) {
            $t->throws($expectedChildKeys, $call);
            return;
        }

        $plan = $call();
        $t->same(count(array_filter($parentRows, $where)), $plan['changes']);
        $t->same($expectedChildKeys, array_column($plan['child'], 'setting_id'));
        $t->same($expectedActions, array_column($plan['foreign_key_actions'], 'action'));
        $t->same($expectedViolations, $plan['foreign_key_violations']);
        $t->same(array_fill(0, count($plan['yielded']), 'update'), array_column($plan['yielded'], 'event'));
        $t->same(array_column($plan['yielded'], 'new_key'), array_column(array_column($plan['yielded'], 'returning'), 'new_id'));
        $t->same(array_column($plan['yielded'], 'old_key'), array_column(array_column($plan['yielded'], 'returning'), 'old_id'));
        $t->same(count($plan['yielded']) * 2, count($plan['trigger_effects']));
        $t->same(['audit_before_update', 'audit_after_update'], array_values(array_unique(array_column($plan['trigger_effects'], 'trigger'))));
        $t->same([], array_values(array_filter($plan['child'], static fn (array $row): bool => !array_key_exists('child_id', $row))));
    };
}

$deleteCases = [
    'fkey2-11.2 on delete cascade removes matching children' => [
        ['on_delete' => 'cascade', 'deferred' => false],
        static fn (array $row): bool => $row['setting_id'] === 1,
        [2, 3, 4],
        [2, 4],
        ['cascade-delete'],
        [],
    ],
    'fkey2-9.2 on delete set null clears child key' => [
        ['on_delete' => 'set null', 'deferred' => false],
        static fn (array $row): bool => $row['setting_id'] === 1,
        [2, 3, 4],
        [null, 2, 4],
        ['set-null'],
        [],
    ],
    'fkey2-9.1 on delete set default keeps child valid when default parent exists' => [
        ['on_delete' => 'set default', 'child_default' => 4, 'deferred' => false],
        static fn (array $row): bool => $row['setting_id'] === 1,
        [2, 3, 4],
        [4, 2, 4],
        ['set-default'],
        [],
    ],
    'fkey2-12.2 deferred no action delete records violation until commit' => [
        ['on_delete' => 'no action', 'deferred' => true],
        static fn (array $row): bool => $row['setting_id'] === 1,
        [2, 3, 4],
        [1, 2, 4],
        ['no action'],
        [
            ['child_index' => 0, 'child_key' => 1, 'parent' => 'setting_id', 'ordinal' => 0, 'phase' => 'statement'],
            ['child_index' => 0, 'child_key' => 1, 'parent' => 'setting_id', 'ordinal' => 0, 'phase' => 'after-trigger'],
        ],
    ],
    'fkey6-2.2 deferred pragma still leaves delete restrict immediate' => [
        ['on_delete' => 'restrict', 'deferred' => true],
        static fn (array $row): bool => $row['setting_id'] === 1,
        InvalidArgumentException::class,
        [],
        [],
        [],
    ],
];

foreach ($deleteCases as $name => [$fk, $where, $expectedParents, $expectedChildKeys, $expectedActions, $expectedViolations]) {
    $tests['real upstream corpus trigger fkey dynamic ' . $name] = static function (TestRunner $t) use ($parentRows, $childRows, $returning, $fk, $where, $expectedParents, $expectedChildKeys, $expectedActions, $expectedViolations): void {
        $call = static fn (): array => SQLiteTriggerForeignKeyReturningPlan::deleteParents(
            $parentRows,
            $childRows,
            $where,
            ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => $fk['on_delete'], 'deferred' => $fk['deferred'], 'child_default' => $fk['child_default'] ?? null],
            [
                ['name' => 'audit_before_delete', 'timing' => 'before', 'event' => 'delete', 'action' => 'audit', 'values' => ['old_id' => 'old.setting_id']],
                ['name' => 'audit_after_delete', 'timing' => 'after', 'event' => 'delete', 'action' => 'audit', 'values' => ['old_id' => 'old.setting_id']],
            ],
            $returning,
            'setting_id',
        );

        if (is_string($expectedParents)) {
            $t->throws($expectedParents, $call);
            return;
        }

        $plan = $call();
        $t->same(1, $plan['changes']);
        $t->same($expectedParents, array_column($plan['parent'], 'setting_id'));
        $t->same($expectedChildKeys, array_column($plan['child'], 'setting_id'));
        $t->same($expectedActions, array_column($plan['foreign_key_actions'], 'action'));
        $t->same($expectedViolations, $plan['foreign_key_violations']);
        $t->same(['delete'], array_values(array_unique(array_column($plan['yielded'], 'event'))));
        $t->same([1], array_column($plan['yielded'], 'old_key'));
        $t->same([1], array_column(array_column($plan['yielded'], 'returning'), 'old_id'));
        $t->same(2, count($plan['trigger_effects']));
        $t->same(['audit_before_delete', 'audit_after_delete'], array_column($plan['trigger_effects'], 'trigger'));
    };
}

$recursiveStatement = [
    'where' => static fn (array $row): bool => $row['setting_id'] === 1,
    'assignments' => ['setting_id' => static fn (array $row, int $depth): int => (int) $row['setting_id'] + 10 + $depth],
    'returning' => [
        ['expr' => 'old.setting_id', 'as' => 'old_id'],
        ['expr' => 'new.setting_id', 'as' => 'new_id'],
        ['expr' => 'new.key_name', 'as' => 'name'],
    ],
    'savepoint' => 'recursive_fkey_batch',
    'recursive_triggers' => true,
    'max_depth' => 4,
    'rowid_column' => 'setting_id',
    'trigger' => ['name' => 'follow_next_setting', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
    'page_images' => [2 => 'page-before-2', 3 => 'page-before-3'],
    'dirty_pages' => [3 => 'page-dirty-3', 4 => 'page-dirty-4'],
    'wal_start_frame' => 2,
    'wal_frames' => [
        ['frame_index' => 1, 'page' => 2],
        ['frame_index' => 2, 'page' => 3],
        ['frame_index' => 3, 'page' => 4],
        ['frame_index' => 4, 'page' => 5],
    ],
];

$recursiveCases = [
    'triggerG-100 recursive trigger walks next pointers and yields top level once' => [
        ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action', 'deferred' => true],
        $recursiveStatement,
        'deferred-commit-blocked',
        [11, 13, 15, 4],
        [1, 2, 4],
        3,
        1,
        2,
        false,
    ],
    'triggerG-100 recursive triggers off only updates statement row' => [
        ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action', 'deferred' => true],
        array_replace($recursiveStatement, ['recursive_triggers' => false]),
        'deferred-commit-blocked',
        [11, 2, 3, 4],
        [1, 2, 4],
        1,
        1,
        0,
        false,
    ],
    'fkey2-2 deferred violation rollback restores parent and child images' => [
        ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action', 'deferred' => true],
        array_replace($recursiveStatement, ['rollback_on_deferred_violation' => true]),
        'rolled-back',
        [11, 13, 15, 4],
        [1, 2, 4],
        3,
        0,
        2,
        true,
    ],
];

foreach ($recursiveCases as $name => [$fk, $statement, $status, $currentIds, $childIds, $changes, $nextYielded, $triggerEffects, $rolledBack]) {
    $tests['real upstream corpus trigger fkey dynamic ' . $name] = static function (TestRunner $t) use ($parentRows, $childRows, $fk, $statement, $status, $currentIds, $childIds, $changes, $nextYielded, $triggerEffects, $rolledBack): void {
        $plan = SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parentRows, $childRows, $fk, $statement);

        $t->same($status, $plan['status']);
        $t->same('recursive_fkey_batch', $plan['savepoint']);
        $t->same($currentIds, array_column($plan['current_parent'], 'setting_id'));
        $t->same($rolledBack ? array_column($parentRows, 'setting_id') : $currentIds, array_column($plan['next_parent'], 'setting_id'));
        $t->same($childIds, array_column($plan['current_child'], 'setting_id'));
        $t->same($rolledBack ? array_column($childRows, 'setting_id') : $childIds, array_column($plan['next_child'], 'setting_id'));
        $t->same($changes, $plan['current_changes']);
        $t->same($rolledBack ? 0 : $changes, $plan['next_changes']);
        $t->same(1, count($plan['current_yielded']));
        $t->same($nextYielded, count($plan['next_yielded']));
        $t->same($triggerEffects, count($plan['trigger_effects']));
        $t->same($rolledBack, $plan['yield_suppressed_by_rollback']);
        $t->same($rolledBack ? [2, 3, 4] : [], $plan['rollback_page_numbers']);
        $t->same($rolledBack ? [3, 4] : [], $plan['dirty_page_numbers']);
        $t->same($rolledBack ? 2 : 0, $plan['rollback_to_wal_frame']);
        $t->same($rolledBack ? [3, 4] : [], array_column($plan['discarded_wal_frames'], 'frame_index'));
        $t->same(true, in_array('sqlite-recursive-triggers-current-source', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-deferred-foreign-key-commit-check', $plan['dependencies'], true));
    };
}

$updateMatrixActions = [
    'cascade' => static fn (int $old, int $new): mixed => $new,
    'set null' => static fn (int $old, int $new): mixed => null,
    'set default' => static fn (int $old, int $new): mixed => 4,
];
$dynamicTargets = [1, 2, 3, 4];

foreach ($updateMatrixActions as $action => $childValue) {
    foreach ($dynamicTargets as $targetId) {
        $tests['real upstream corpus trigger fkey dynamic fkey2 dynamic update ' . $action . ' target ' . $targetId] = static function (TestRunner $t) use ($parentRows, $childRows, $returning, $action, $childValue, $targetId): void {
            $newId = $targetId + 700;
            $childDefault = $action === 'set default' && $targetId === 4 ? 3 : 4;
            $plan = SQLiteTriggerForeignKeyReturningPlan::updateParents(
                $parentRows,
                $childRows,
                ['setting_id' => $newId, 'version' => static fn (array $row): int => (int) $row['version'] + 1],
                static fn (array $row): bool => $row['setting_id'] === $targetId,
                ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => $action, 'child_default' => $childDefault],
                [
                    ['name' => 'dynamic_before_update', 'timing' => 'before', 'event' => 'update', 'action' => 'audit', 'values' => ['old_id' => 'old.setting_id']],
                    ['name' => 'dynamic_after_update', 'timing' => 'after', 'event' => 'update', 'action' => 'audit', 'values' => ['new_id' => 'new.setting_id']],
                ],
                $returning,
                'setting_id',
            );

            $expectedParentIds = array_map(static fn (array $row): int => $row['setting_id'] === $targetId ? $newId : (int) $row['setting_id'], $parentRows);
            $expectedChildIds = array_map(static fn (array $row): mixed => $row['setting_id'] === $targetId ? ($action === 'set default' ? $childDefault : $childValue($targetId, $newId)) : $row['setting_id'], $childRows);
            $matchedChildren = count(array_filter($childRows, static fn (array $row): bool => $row['setting_id'] === $targetId));

            $t->same(1, $plan['changes']);
            $t->same($expectedParentIds, array_column($plan['parent'], 'setting_id'));
            $t->same($expectedChildIds, array_column($plan['child'], 'setting_id'));
            $t->same($matchedChildren, count($plan['foreign_key_actions']));
            $t->same(array_fill(0, $matchedChildren, str_replace(' ', '-', $action)), array_column($plan['foreign_key_actions'], 'action'));
            $t->same([], $plan['foreign_key_violations']);
            $t->same([0], array_column($plan['yielded'], 'ordinal'));
            $t->same(['update'], array_column($plan['yielded'], 'event'));
            $t->same([$targetId], array_column($plan['yielded'], 'old_key'));
            $t->same([$newId], array_column($plan['yielded'], 'new_key'));
            $t->same([$targetId], array_column(array_column($plan['yielded'], 'returning'), 'old_id'));
            $t->same([$newId], array_column(array_column($plan['yielded'], 'returning'), 'new_id'));
            $t->same(2, count($plan['trigger_effects']));
            $t->same(['before', 'after'], array_column($plan['trigger_effects'], 'timing'));
            $t->same(['dynamic_before_update', 'dynamic_after_update'], array_column($plan['trigger_effects'], 'trigger'));
            $t->same(array_column($parentRows, 'key_name'), array_column($plan['parent'], 'key_name'));
            $t->same(array_column($childRows, 'child_id'), array_column($plan['child'], 'child_id'));
            $t->same(count($parentRows), count($plan['parent']));
            $t->same(count($childRows), count($plan['child']));
        };
    }
}

$deleteMatrixActions = [
    'cascade' => static fn (array $children, int $targetId): array => array_values(array_filter($children, static fn (array $row): bool => $row['setting_id'] !== $targetId)),
    'set null' => static fn (array $children, int $targetId): array => array_map(static fn (array $row): array => $row['setting_id'] === $targetId ? array_replace($row, ['setting_id' => null]) : $row, $children),
    'set default' => static fn (array $children, int $targetId): array => array_map(static fn (array $row): array => $row['setting_id'] === $targetId ? array_replace($row, ['setting_id' => 4]) : $row, $children),
];

foreach ($deleteMatrixActions as $action => $childRowsAfter) {
    foreach ($dynamicTargets as $targetId) {
        $tests['real upstream corpus trigger fkey dynamic fkey2 dynamic delete ' . $action . ' target ' . $targetId] = static function (TestRunner $t) use ($parentRows, $childRows, $returning, $action, $childRowsAfter, $targetId): void {
            $childDefault = $action === 'set default' && $targetId === 4 ? 3 : 4;
            $plan = SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                $parentRows,
                $childRows,
                static fn (array $row): bool => $row['setting_id'] === $targetId,
                ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => $action, 'child_default' => $childDefault],
                [
                    ['name' => 'dynamic_before_delete', 'timing' => 'before', 'event' => 'delete', 'action' => 'audit', 'values' => ['old_id' => 'old.setting_id']],
                    ['name' => 'dynamic_after_delete', 'timing' => 'after', 'event' => 'delete', 'action' => 'audit', 'values' => ['old_id' => 'old.setting_id']],
                ],
                $returning,
                'setting_id',
            );

            $expectedParentIds = array_values(array_filter(array_column($parentRows, 'setting_id'), static fn (int $id): bool => $id !== $targetId));
            $expectedChildren = $childRowsAfter($childRows, $targetId);
            if ($action === 'set default' && $targetId === 4) {
                $expectedChildren = array_map(static fn (array $row): array => ($row['setting_id'] ?? null) === 4 ? array_replace($row, ['setting_id' => 3]) : $row, $childRows);
            }
            $matchedChildren = count(array_filter($childRows, static fn (array $row): bool => $row['setting_id'] === $targetId));
            $expectedAction = $action === 'cascade' ? 'cascade-delete' : str_replace(' ', '-', $action);

            $t->same(1, $plan['changes']);
            $t->same($expectedParentIds, array_column($plan['parent'], 'setting_id'));
            $t->same(array_column($expectedChildren, 'setting_id'), array_column($plan['child'], 'setting_id'));
            $t->same(array_column($expectedChildren, 'child_id'), array_column($plan['child'], 'child_id'));
            $t->same($matchedChildren, count($plan['foreign_key_actions']));
            $t->same(array_fill(0, $matchedChildren, $expectedAction), array_column($plan['foreign_key_actions'], 'action'));
            $t->same([], $plan['foreign_key_violations']);
            $t->same([0], array_column($plan['yielded'], 'ordinal'));
            $t->same(['delete'], array_column($plan['yielded'], 'event'));
            $t->same([$targetId], array_column($plan['yielded'], 'old_key'));
            $t->same([$targetId], array_column($plan['yielded'], 'new_key'));
            $t->same([$targetId], array_column(array_column($plan['yielded'], 'returning'), 'old_id'));
            $t->same([$targetId], array_column(array_column($plan['yielded'], 'returning'), 'new_id'));
            $t->same(2, count($plan['trigger_effects']));
            $t->same(['before', 'after'], array_column($plan['trigger_effects'], 'timing'));
            $t->same(['dynamic_before_delete', 'dynamic_after_delete'], array_column($plan['trigger_effects'], 'trigger'));
            $t->same(count($parentRows) - 1, count($plan['parent']));
            $t->same(count($expectedChildren), count($plan['child']));
            $t->same(false, in_array($targetId, array_column($plan['parent'], 'setting_id'), true));
        };
    }
}

$guardCases = [
    'triggerE-1 rejects malformed trigger action' => static fn (): array => SQLiteTriggerForeignKeyReturningPlan::updateParents($parentRows, $childRows, ['setting_id' => 9], static fn (): bool => true, ['parent_key' => 'setting_id', 'child_key' => 'setting_id'], [['timing' => 'after', 'event' => 'update', 'action' => 'explode']], $returning, 'setting_id'),
    'fkey2 malformed action is rejected' => static fn (): array => SQLiteTriggerForeignKeyReturningPlan::deleteParents($parentRows, $childRows, static fn (): bool => true, ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'explode'], [], $returning, 'setting_id'),
    'triggerG recursive max depth guard is enforced' => static fn (): array => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parentRows, $childRows, ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action', 'deferred' => true], array_replace($recursiveStatement, ['max_depth' => 0])),
    'fkey6 malformed row id column is rejected' => static fn (): array => SQLiteTriggerForeignKeyReturningPlan::updateParents($parentRows, $childRows, ['setting_id' => 9], static fn (): bool => true, ['parent_key' => 'setting_id', 'child_key' => 'setting_id'], [], $returning, 'bad column'),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus trigger fkey dynamic ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

$tests['real upstream corpus trigger fkey dynamic cites upstream source files'] = static function (TestRunner $t): void {
    $upstream = [
        'fkey2.test fkey2-2.* deferred constraints inside transactions',
        'fkey2.test fkey2-9.* SET NULL and SET DEFAULT actions',
        'fkey2.test fkey2-11.* CASCADE actions',
        'fkey2.test fkey2-12.* RESTRICT actions',
        'fkey6.test defer_foreign_keys does not defer RESTRICT',
        'trigger2.test trigger2-4.* cascaded and recursive trigger execution',
        'triggerG.test triggerG-100 recursive trigger OP_Once behavior',
    ];

    $t->same(7, count($upstream));
    $t->same(true, str_contains(implode("\n", $upstream), 'triggerG-100'));
    $t->same(true, str_contains(implode("\n", $upstream), 'fkey2-11'));
    $t->same(true, str_contains(implode("\n", $upstream), 'fkey6.test'));
};

return $tests;
