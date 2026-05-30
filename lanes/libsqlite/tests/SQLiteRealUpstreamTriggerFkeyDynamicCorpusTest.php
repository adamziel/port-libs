<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$baseTree = static function (int $depth, int $branch = 1): array {
    $rows = [['id' => 1, 'parent_id' => null, 'label' => 'root']];
    for ($i = 2; $i <= $depth; $i++) {
        $rows[] = ['id' => $i, 'parent_id' => $i - 1, 'label' => 'node-' . $i];
    }
    for ($i = 0; $i < $branch; $i++) {
        $rows[] = ['id' => 100 + $i, 'parent_id' => 2, 'label' => 'branch-' . $i];
    }

    return $rows;
};

$cascadeCountFrom = static function (array $rows, int $root): int {
    $deleted = [];
    $queue = [$root];
    while ($queue !== []) {
        $parent = array_shift($queue);
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id === $parent && !isset($deleted[$id])) {
                $deleted[$id] = true;
            }
            if (($row['parent_id'] ?? null) === $parent && !isset($deleted[$id])) {
                $deleted[$id] = true;
                $queue[] = $id;
            }
        }
    }

    return count($deleted);
};

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$tests = [];

for ($depth = 3; $depth <= 22; $depth++) {
    $rows = $baseTree($depth, ($depth % 4) + 1);
    $replaceId = 2 + ($depth % max(1, $depth - 2));
    $incomingParent = $replaceId + 1;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::replaceSelfReferencingRow(
        $rows,
        ['id' => $replaceId, 'parent_id' => $incomingParent, 'label' => 'replacement-' . $depth],
        true,
        true
    );
    $cascadeCount = $cascadeCountFrom($rows, $replaceId);
    $case = 'fkey1-5 replace cascade depth ' . $depth;
    $expectations = [
        'source' => 'fkey1.test fkey1-5.1..5.4',
        'operation' => 'insert-or-replace-self-referencing-cascade',
        'status' => 'rolled-back',
        'deferred' => true,
        'rollback_on_violation' => true,
        'incoming_id' => $replaceId,
        'incoming_parent_id' => $incomingParent,
        'cascade_delete_count' => $cascadeCount,
        'violation_count' => 1,
        'violations.0.id' => $replaceId,
        'violations.0.parent_id' => $incomingParent,
        'boundary' => 'replace-delete-cascade-rolled-back',
        'committed_rows.0.id' => 1,
        'committed_rows.1.id' => 2,
        'dependencies.0' => 'sqlite-fkey1-replace-cascade-parent-delete',
    ];
    foreach ($expectations as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' deletes replaced row before insert'] = static function (TestRunner $t) use ($plan, $replaceId): void {
        $t->same($replaceId, $plan()['deleted_ids'][0]);
    };
    $tests[$case . ' attempted row includes replacement'] = static function (TestRunner $t) use ($plan, $replaceId): void {
        $t->true(in_array($replaceId, array_column($plan()['attempted_rows'], 'id'), true));
    };
}

for ($depth = 3; $depth <= 17; $depth++) {
    $rows = $baseTree($depth, 0);
    $replaceId = $depth;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::replaceSelfReferencingRow(
        $rows,
        ['id' => $replaceId, 'parent_id' => $replaceId - 1, 'label' => 'replacement-valid-' . $depth],
        true,
        true
    );
    $case = 'fkey1-5 replace cascade valid commit depth ' . $depth;
    foreach ([
        'status' => 'commit-ok',
        'violation_count' => 0,
        'boundary' => 'replace-cascade-committed',
        'cascade_delete_count' => 1,
        'committed_rows.' . ($depth - 1) . '.id' => $replaceId,
        'dependencies.1' => 'sqlite-deferred-foreign-key-commit-check',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($id = 1; $id <= 40; $id++) {
    $rows = [
        ['id' => 1, 'kind' => 'keep', 'bucket' => 'a'],
        ['id' => 2, 'kind' => 'remove', 'bucket' => 'b'],
        ['id' => 3, 'kind' => 'side', 'bucket' => 'b'],
        ['id' => 4, 'kind' => 'side', 'bucket' => 'c'],
        ['id' => 5, 'kind' => 'keep', 'bucket' => 'c'],
    ];
    $whereValue = $id % 2 === 0 ? 'remove' : 'keep';
    $deleteValue = $id % 3 === 0 ? 'c' : 'b';
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deleteWithAfterTrigger(
        $rows,
        'kind',
        $whereValue,
        [
            'name' => 'audit_delete_' . $id,
            'event' => 'delete',
            'match_column' => 'kind',
            'match_value' => $whereValue,
            'delete_column' => 'bucket',
            'delete_value' => $deleteValue,
        ]
    );
    $outerDeleted = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['kind'] === $whereValue), 'id'));
    $triggerDeleted = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['kind'] !== $whereValue && $row['bucket'] === $deleteValue), 'id'));
    $case = 'trigger1-1.10 after delete trigger preserves outer delete ' . $id;
    foreach ([
        'source' => 'trigger1.test trigger1-1.10',
        'operation' => 'delete-statement-with-after-delete-trigger',
        'status' => 'commit-ok',
        'trigger' => 'audit_delete_' . $id,
        'outer_deleted_ids' => $outerDeleted,
        'trigger_deleted_ids' => $triggerDeleted,
        'outer_delete_count' => count($outerDeleted),
        'trigger_delete_count' => count($triggerDeleted),
        'total_changes' => count($outerDeleted) + count($triggerDeleted),
        'statement_delete_preserved' => true,
        'dependencies.0' => 'sqlite-trigger1-delete-trigger-does-not-corrupt-outer-delete',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($id = 1; $id <= 50; $id++) {
    $rows = [
        ['id' => 1, 'kind' => 'update', 'bucket' => 'a', 'label' => 'alpha'],
        ['id' => 2, 'kind' => 'keep', 'bucket' => 'b', 'label' => 'bravo'],
        ['id' => 3, 'kind' => 'update', 'bucket' => 'b', 'label' => 'charlie'],
        ['id' => 4, 'kind' => 'side', 'bucket' => 'c', 'label' => 'delta'],
        ['id' => 5, 'kind' => 'keep', 'bucket' => 'c', 'label' => 'echo'],
        ['id' => 6, 'kind' => 'side', 'bucket' => 'd', 'label' => 'foxtrot'],
    ];
    $whereValue = $id % 2 === 0 ? 'update' : 'keep';
    $deleteValue = match ($id % 4) {
        0 => 'c',
        1 => 'b',
        2 => 'd',
        default => 'a',
    };
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::updateWithAfterTrigger(
        $rows,
        'kind',
        $whereValue,
        [
            'label' => static fn (array $old): string => 'x-' . $old['label'],
            'bucket' => static fn (array $old): string => (string) $old['bucket'],
        ],
        [
            'name' => 'audit_update_' . $id,
            'event' => 'update',
            'match_column' => 'kind',
            'match_value' => $whereValue,
            'delete_column' => 'bucket',
            'delete_value' => $deleteValue,
        ]
    );
    $outerUpdated = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['kind'] === $whereValue), 'id'));
    $triggerRows = $rows;
    $triggerDeleted = [];
    foreach ($outerUpdated as $updatedId) {
        foreach ($triggerRows as $index => $row) {
            if (($row['bucket'] ?? null) === $deleteValue && ($row['id'] ?? null) !== $updatedId) {
                $triggerDeleted[] = $row['id'];
                unset($triggerRows[$index]);
            }
        }
        $triggerRows = array_values($triggerRows);
    }
    $remaining = array_values(array_diff(array_column($rows, 'id'), $triggerDeleted));
    sort($remaining);
    $case = 'trigger1-1.11 after update trigger preserves outer update ' . $id;
    foreach ([
        'source' => 'trigger1.test trigger1-1.11',
        'operation' => 'update-statement-with-after-update-trigger',
        'status' => 'commit-ok',
        'trigger' => 'audit_update_' . $id,
        'outer_updated_ids' => $outerUpdated,
        'trigger_deleted_ids' => $triggerDeleted,
        'remaining_ids' => $remaining,
        'outer_update_count' => count($outerUpdated),
        'trigger_delete_count' => count($triggerDeleted),
        'total_changes' => count($outerUpdated) + count($triggerDeleted),
        'statement_update_preserved' => true,
        'dependencies.0' => 'sqlite-trigger1-update-trigger-does-not-corrupt-outer-update',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' updated labels keep outer rows'] = static function (TestRunner $t) use ($plan): void {
        foreach ($plan()['updated_rows'] as $row) {
            $t->same(0, strpos((string) $row['label'], 'x-'));
        }
    };
}

for ($i = 1; $i <= 26; $i++) {
    $objects = [
        ['name' => 'main_items', 'object_type' => 'table'],
        ['name' => 'temp_items', 'object_type' => 'table', 'temp' => true],
    ];
    $actions = [
        ['op' => 'create-trigger', 'name' => 'main_items_ai_' . $i, 'target' => 'main_items'],
        ['op' => 'begin'],
        ['op' => 'create-trigger', 'name' => 'rolled_back_' . $i, 'target' => 'main_items'],
        ['op' => 'rollback'],
        ['op' => 'create-trigger', 'name' => 'temp_items_ai_' . $i, 'target' => 'temp_items', 'temp' => true],
        ['op' => 'drop-trigger', 'name' => 'missing_' . $i, 'if_exists' => true],
        ['op' => 'drop-table', 'name' => $i % 2 === 0 ? 'main_items' : 'temp_items'],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::schemaLifecycle($objects, $actions);
    $case = 'trigger1 schema create drop rollback temp ' . $i;
    $mainTriggerExpected = $i % 2 === 0 ? [] : ['main_items_ai_' . $i];
    $tempTriggerExpected = $i % 2 === 0 ? ['temp_items_ai_' . $i] : [];
    foreach ([
        'source' => 'trigger1.test trigger1-1.2..1.8',
        'operation' => 'trigger-schema-lifecycle',
        'status' => 'ok',
        'error_count' => 0,
        'main_trigger_names' => $mainTriggerExpected,
        'temp_trigger_names' => $tempTriggerExpected,
        'snapshots.3.main' => ['main_items', 'main_items_ai_' . $i],
        'snapshots.4.temp' => ['temp_items', 'temp_items_ai_' . $i],
        'dependencies.0' => 'sqlite-trigger1-create-drop-rollback',
        'dependencies.1' => 'sqlite-trigger1-temp-trigger-hidden-from-main-schema',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 42; $i++) {
    $rows = [
        ['id' => 1, 'parent_id' => null, 'label' => 'root'],
        ['id' => 2, 'parent_id' => 1, 'label' => 'left'],
        ['id' => 3, 'parent_id' => 1, 'label' => 'right'],
        ['id' => 4, 'parent_id' => 2, 'label' => 'left-left'],
        ['id' => 5, 'parent_id' => 2, 'label' => 'left-right'],
        ['id' => 6, 'parent_id' => 3, 'label' => 'right-left'],
        ['id' => 7, 'parent_id' => 3, 'label' => 'right-right'],
    ];
    for ($extra = 0; $extra < ($i % 5); $extra++) {
        $rows[] = ['id' => 20 + $extra, 'parent_id' => 4 + $extra, 'label' => 'extra-' . $extra];
    }
    $root = ($i % 3) + 1;
    $recursive = $i % 2 === 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::recursiveCascadeVsTrigger($rows, $root, $recursive);
    $case = 'fkey2-4 recursive fk cascade ignores recursive trigger pragma ' . $i;
    $fkRemaining = array_column($plan()['fk_remaining_ids'] === [] ? [] : array_map(static fn (int $id): array => ['id' => $id], $plan()['fk_remaining_ids']), 'id');
    unset($fkRemaining);
    foreach ([
        'source' => 'fkey2.test fkey2-4.1..4.4',
        'operation' => 'recursive-foreign-key-actions-ignore-recursive-trigger-pragma',
        'status' => 'commit-ok',
        'recursive_triggers' => $recursive,
        'delete_root' => $root,
        'fk_cascade_ignores_recursive_trigger_pragma' => true,
        'dependencies.0' => 'sqlite-fkey2-recursive-cascade-actions-ignore-recursive-triggers',
        'dependencies.1' => 'sqlite-trigger-recursion-pragma-only-controls-trigger-programs',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' fk cascade deletes root'] = static function (TestRunner $t) use ($plan, $root): void {
        $t->same(false, in_array($root, $plan()['fk_remaining_ids'], true));
    };
    $tests[$case . ' fk cascade reaches deeper than trigger when disabled'] = static function (TestRunner $t) use ($plan, $recursive): void {
        $actual = $plan();
        $t->same($recursive || count($actual['fk_remaining_ids']) <= count($actual['trigger_remaining_ids']), true);
    };
    $tests[$case . ' fk delete count covers at least trigger delete count'] = static function (TestRunner $t) use ($plan): void {
        $actual = $plan();
        $t->same(true, $actual['fk_delete_count'] >= $actual['trigger_delete_count']);
    };
    $tests[$case . ' recursive trigger on matches fk depth'] = static function (TestRunner $t) use ($plan, $recursive): void {
        $actual = $plan();
        $t->same($recursive ? $actual['fk_remaining_ids'] : true, $recursive ? $actual['trigger_remaining_ids'] : true);
    };
}

for ($i = 1; $i <= 34; $i++) {
    $parents = ['A', 'B'];
    if ($i % 3 === 0) {
        $parents[] = 'C';
    }
    $children = ['a', 'b'];
    if ($i % 4 === 0) {
        $children[] = 'c';
    }
    $repairedParents = array_values(array_filter($parents, static function (string $parent) use ($children): bool {
        foreach ($children as $child) {
            if (strcasecmp($child, $parent) === 0) {
                return true;
            }
        }

        return false;
    }));
    $restrict = $i % 2 === 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::restrictReinsertAfterDeleteTrigger($parents, $children, $restrict);
    $case = 'fkey2-12.2 after delete trigger reinsert restrict interaction ' . $i;
    foreach ([
        'source' => 'fkey2.test fkey2-12.2.1..12.2.4',
        'operation' => 'after-delete-trigger-reinsert-versus-restrict',
        'status' => $restrict ? 'constraint-failed' : 'commit-ok',
        'restrict' => $restrict,
        'parent_rows' => $restrict ? $parents : $repairedParents,
        'child_rows' => $children,
        'violation' => $restrict ? 'FOREIGN KEY constraint failed' : null,
        'nocase_lookup' => true,
        'dependencies.0' => 'sqlite-fkey2-restrict-prevents-after-delete-repair-trigger',
        'dependencies.1' => 'sqlite-trigger-when-exists-uses-parent-collation',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' non restrict trigger repairs all child parents'] = static function (TestRunner $t) use ($plan, $restrict, $repairedParents): void {
        $t->same($restrict ? [] : $repairedParents, $restrict ? [] : $plan()['trigger_reinserted']);
    };
}

for ($i = 1; $i <= 36; $i++) {
    $old = $i % 2 === 0 ? 'yes' : 'alpha';
    $new = $i % 2 === 0 ? 'possibly' : 'omega';
    $other = $i % 3 === 0 ? [['c34' => 'spare', 'c35' => 'row']] : [];
    $parents = array_merge([['c34' => $old, 'c35' => 'no']], $other);
    $children = array_merge([['c39' => $old, 'c38' => 'no']], array_map(static fn (array $row): array => ['c39' => $row['c34'], 'c38' => $row['c35']], $other));
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::compositeCascadeColumnMapping($parents, $children, $old, $new);
    $case = 'fkey2-12.3 composite cascade swapped reference columns ' . $i;
    foreach ([
        'source' => 'fkey2.test fkey2-12.3.1..12.3.5',
        'operation' => 'composite-foreign-key-cascade-swapped-column-mapping',
        'status' => 'commit-ok',
        'selected_child_pairs.0' => ['no', $new],
        'updated_parent_key.from' => $old,
        'updated_parent_key.to' => $new,
        'child_reference_mapping.c39' => 'c34',
        'child_reference_mapping.c38' => 'c35',
        'dependencies.0' => 'sqlite-fkey2-composite-cascade-column-order',
        'dependencies.1' => 'sqlite-composite-primary-key-reference-default-column-list',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' unchanged child c38 keeps parent second column'] = static function (TestRunner $t) use ($plan): void {
        $t->same('no', $plan()['child_rows'][0]['c38']);
    };
    $tests[$case . ' child c39 follows parent c34'] = static function (TestRunner $t) use ($plan, $new): void {
        $t->same($new, $plan()['child_rows'][0]['c39']);
    };
}

$tests['trigger1 schema duplicate trigger records upstream error'] = static function (TestRunner $t): void {
    $plan = SQLiteDynamicTriggerForeignKeyPlan::schemaLifecycle(
        [['name' => 'items', 'object_type' => 'table']],
        [
            ['op' => 'create-trigger', 'name' => 'items_ai', 'target' => 'items'],
            ['op' => 'create-trigger', 'name' => 'items_ai', 'target' => 'items'],
        ]
    );
    $t->same('error-recorded', $plan['status']);
    $t->same('trigger items_ai already exists', $plan['errors'][0]['error']);
};

$tests['trigger1 schema missing trigger records upstream error'] = static function (TestRunner $t): void {
    $plan = SQLiteDynamicTriggerForeignKeyPlan::schemaLifecycle(
        [['name' => 'items', 'object_type' => 'table']],
        [['op' => 'drop-trigger', 'name' => 'missing_trigger']]
    );
    $t->same('error-recorded', $plan['status']);
    $t->same('no such trigger: missing_trigger', $plan['errors'][0]['error']);
};

$tests['trigger1 schema missing table records upstream main error'] = static function (TestRunner $t): void {
    $plan = SQLiteDynamicTriggerForeignKeyPlan::schemaLifecycle(
        [],
        [['op' => 'create-trigger', 'name' => 'items_ai', 'target' => 'items']]
    );
    $t->same('error-recorded', $plan['status']);
    $t->same('no such table: main.items', $plan['errors'][0]['error']);
};

return $tests;
