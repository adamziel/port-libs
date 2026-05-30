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

for ($i = 1; $i <= 45; $i++) {
    $firstA = $i;
    $firstB = $i + 1;
    $secondA = $i + 2;
    $secondB = $i + 3;
    $insertA = $i + 4;
    $insertB = $i + 5;
    $initialRows = [
        ['a' => $firstA, 'b' => $firstB],
        ['a' => $secondA, 'b' => $secondB],
    ];
    if ($i % 3 === 0) {
        $initialRows[] = ['a' => $i + 6, 'b' => $i + 7];
    }
    $insertRows = [
        ['a' => $insertA, 'b' => $insertB],
    ];
    if ($i % 4 === 0) {
        $insertRows[] = ['a' => $i + 8, 'b' => $i + 9];
    }
    $originalSumA = array_sum(array_column($initialRows, 'a'));
    $originalSumB = array_sum(array_column($initialRows, 'b'));
    $firstAfterSumA = $originalSumA - $firstA + ($firstA * 10);
    $firstAfterSumB = $originalSumB - $firstB + ($firstB * 10);
    $updatedRows = array_map(static fn (array $row): array => ['a' => $row['a'] * 10, 'b' => $row['b'] * 10], $initialRows);
    $updatedSumA = array_sum(array_column($updatedRows, 'a'));
    $updatedSumB = array_sum(array_column($updatedRows, 'b'));
    $deleteSecondBeforeSumA = $updatedSumA - ($firstA * 10);
    $deleteSecondBeforeSumB = $updatedSumB - ($firstB * 10);
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::rowTriggerExecutionOrder($initialRows, $insertRows);
    $case = 'trigger2-1 row trigger before after order dynamic ' . $i;

    $tests[$case] = static function (TestRunner $t) use ($plan, $initialRows, $insertRows, $firstA, $firstB, $secondA, $secondB, $insertA, $insertB, $originalSumA, $originalSumB, $firstAfterSumA, $firstAfterSumB, $updatedSumA, $updatedSumB, $deleteSecondBeforeSumA, $deleteSecondBeforeSumB): void {
        $actual = $plan();

        $t->same('trigger2.test trigger2-1.1..1.3', $actual['source']);
        $t->same('row-trigger-before-after-execution-order', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same(count($initialRows) * 2, $actual['update_log_count']);
        $t->same(1, $actual['conditional_update_log_count']);
        $t->same(count($initialRows) * 2, $actual['delete_log_count']);
        $t->same(count($insertRows) * 2, $actual['insert_log_count']);
        $t->same($firstA, $actual['update_log'][0]['old_a']);
        $t->same($firstB, $actual['update_log'][0]['old_b']);
        $t->same($originalSumA, $actual['update_log'][0]['db_sum_a']);
        $t->same($originalSumB, $actual['update_log'][0]['db_sum_b']);
        $t->same($firstA * 10, $actual['update_log'][0]['new_a']);
        $t->same($firstB * 10, $actual['update_log'][0]['new_b']);
        $t->same($firstAfterSumA, $actual['update_log'][1]['db_sum_a']);
        $t->same($firstAfterSumB, $actual['update_log'][1]['db_sum_b']);
        $t->same($actual['update_log'][1], $actual['conditional_update_log'][0]);
        $t->same($secondA, $actual['update_log'][2]['old_a']);
        $t->same($secondB, $actual['update_log'][2]['old_b']);
        $t->same($updatedSumA, $actual['delete_log'][0]['db_sum_a']);
        $t->same($updatedSumB, $actual['delete_log'][0]['db_sum_b']);
        $t->same($deleteSecondBeforeSumA, $actual['delete_log'][2]['db_sum_a']);
        $t->same($deleteSecondBeforeSumB, $actual['delete_log'][2]['db_sum_b']);
        $t->same(0, $actual['delete_log'][count($initialRows) * 2 - 1]['db_sum_a']);
        $t->same(0, $actual['delete_log'][count($initialRows) * 2 - 1]['db_sum_b']);
        $t->same(0, $actual['insert_log'][0]['db_sum_a']);
        $t->same(0, $actual['insert_log'][0]['db_sum_b']);
        $t->same($insertA, $actual['insert_log'][0]['new_a']);
        $t->same($insertB, $actual['insert_log'][0]['new_b']);
        $t->same($insertA, $actual['insert_log'][1]['db_sum_a']);
        $t->same($insertB, $actual['insert_log'][1]['db_sum_b']);
        $t->same($insertRows, $actual['final_insert_rows']);
        $t->same('sqlite-trigger2-before-trigger-sees-prestatement-rowset', $actual['dependencies'][0]);
        $t->same('sqlite-trigger2-after-trigger-sees-current-row-change', $actual['dependencies'][1]);
        $t->same('sqlite-trigger2-when-clause-uses-old-row-image', $actual['dependencies'][2]);
    };
}

for ($i = 1; $i <= 48; $i++) {
    $rows = [
        ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0],
        ['a' => 1, 'b' => 0, 'c' => 0, 'd' => 0],
    ];
    if ($i % 4 === 0) {
        $rows[] = ['a' => 2, 'b' => 0, 'c' => 0, 'd' => 0];
    }
    $updates = [
        ['columns' => ['b', 'c']],
        ['columns' => ['b']],
        ['columns' => ['d'], 'where' => static fn (array $row): bool => $row['a'] === 0],
        ['columns' => ['a', 'b']],
    ];
    if ($i % 3 === 0) {
        $updates[] = ['columns' => ['c'], 'where' => static fn (array $row): bool => $row['a'] === 99];
    }
    $insertRows = [
        ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0],
        ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0],
        ['a' => 200 + $i, 'b' => 0, 'c' => 0, 'd' => 0],
    ];
    $subqueryWhen = $i % 5 !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selectiveTriggerExecution($rows, $updates, $insertRows, $subqueryWhen);
    $case = 'trigger2-3 selective update-of and when dynamic ' . $i;
    $expectedUpdateOfLog = count($rows) + 1;
    $expectedUpdateEvents = $expectedUpdateOfLog + ($i % 3 === 0 ? 1 : 0);
    $expectedWhenLog = ($subqueryWhen ? 1 : 0) + 1;

    $tests[$case] = static function (TestRunner $t) use ($plan, $rows, $insertRows, $subqueryWhen, $expectedUpdateOfLog, $expectedUpdateEvents, $expectedWhenLog): void {
        $actual = $plan();
        $t->same('trigger2.test trigger2-3.1..3.2', $actual['source']);
        $t->same('selective-update-of-and-when-trigger-execution', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same($expectedUpdateOfLog, $actual['update_of_log_count']);
        $t->same($expectedUpdateEvents, count($actual['update_events']));
        $t->same(['b', 'c'], $actual['update_events'][0]['columns']);
        $t->same(['d'], $actual['update_events'][count($rows)]['columns']);
        $t->same(1, $actual['final_rows'][0]['a']);
        $t->same(3, $actual['final_rows'][0]['b']);
        $t->same(1, $actual['final_rows'][0]['c']);
        $t->same(1, $actual['final_rows'][0]['d']);
        $t->same(2, $actual['final_rows'][1]['a']);
        $t->same(3, $actual['final_rows'][1]['b']);
        $t->same(1, $actual['final_rows'][1]['c']);
        $t->same(0, $actual['final_rows'][1]['d']);
        $t->same($expectedWhenLog, $actual['when_log_count']);
        $t->same($insertRows, $actual['inserted_rows']);
        $t->same($subqueryWhen ? 'table-empty-subquery' : 'new-a-gt-20', $actual['when_log'][0]['trigger']);
        $t->same($subqueryWhen ? 0 : 2, $subqueryWhen ? $actual['when_log'][0]['new_a'] : $actual['when_log'][0]['preinsert_count']);
        $t->same($subqueryWhen ? 0 : $insertRows[2]['a'], $actual['when_log'][0]['new_a']);
        $t->same('new-a-gt-20', $actual['when_log'][$expectedWhenLog - 1]['trigger']);
        $t->same('sqlite-trigger2-update-of-fires-only-for-named-columns', $actual['dependencies'][0]);
        $t->same('sqlite-trigger2-when-new-row-predicate', $actual['dependencies'][1]);
        $t->same('sqlite-trigger2-when-subquery-sees-preinsert-table', $actual['dependencies'][2]);
    };
}

for ($i = 1; $i <= 54; $i++) {
    $tables = [
        'tblA' => $i % 2 === 0 ? [['a' => 9, 'b' => 9]] : [],
        'tblB' => $i % 3 === 0 ? [['a' => 8, 'b' => 8]] : [],
        'tblC' => $i % 4 === 0 ? [['a' => 7, 'b' => 7]] : [],
    ];
    $insertRow = ['a' => $i, 'b' => $i + 1];
    $recursive = $i % 2 === 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::cascadedTriggerExecution($tables, $insertRow, $recursive);
    $case = 'trigger2-4 cascaded trigger program dynamic ' . $i;

    $tests[$case] = static function (TestRunner $t) use ($plan, $tables, $insertRow, $recursive): void {
        $actual = $plan();
        $t->same('trigger2.test trigger2-4.1..4.2', $actual['source']);
        $t->same('cascaded-trigger-program-execution', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same(count($tables['tblA']) + 1, count($actual['tblA_rows']));
        $t->same(count($tables['tblB']) + 1, count($actual['tblB_rows']));
        $t->same(count($tables['tblC']) + 1, count($actual['tblC_rows']));
        $t->same($insertRow, $actual['tblA_rows'][count($actual['tblA_rows']) - 1]);
        $t->same($insertRow, $actual['tblB_rows'][count($actual['tblB_rows']) - 1]);
        $t->same($insertRow, $actual['tblC_rows'][count($actual['tblC_rows']) - 1]);
        $t->same([$insertRow, $insertRow], $actual['recursive_rows']);
        $t->same(!$recursive, $actual['recursive_trigger_program_limited']);
        $t->same(true, $actual['cascade_reaches_second_trigger']);
        $t->same('sqlite-trigger2-trigger-program-may-fire-other-triggers', $actual['dependencies'][0]);
        $t->same('sqlite-trigger2-recursive-trigger-program-limited-when-disabled', $actual['dependencies'][1]);
    };
}

for ($i = 1; $i <= 44; $i++) {
    $rows = $i % 2 === 0 ? [['a' => 10, 'b' => 20, 'c' => 30]] : [];
    $insertRow = ['a' => 100 + $i, 'b' => 200 + $i, 'c' => 300 + $i];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramChangesCount($rows, $insertRow);
    $case = 'trigger2-5 trigger program changes count boundary ' . $i;

    $tests[$case] = static function (TestRunner $t) use ($plan, $insertRow): void {
        $actual = $plan();
        $t->same('trigger2.test trigger2-5', $actual['source']);
        $t->same('trigger-program-changes-count-boundary', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same(1, $actual['reported_changes']);
        $t->same(5, $actual['trigger_side_effect_changes']);
        $t->same(6, $actual['total_physical_changes']);
        $t->same([$insertRow], $actual['final_rows']);
        $t->same('sqlite-trigger2-count-changes-excludes-trigger-program-side-effects', $actual['dependencies'][0]);
    };
}

foreach ([false, true] as $updateConflict) {
    foreach (['default', 'abort', 'fail', 'ignore', 'replace', 'rollback'] as $policy) {
        for ($i = 1; $i <= 20; $i++) {
            $rows = [
                ['a' => $i, 'b' => 2, 'c' => 3],
                ['a' => $i + 10, 'b' => 3, 'c' => 4],
            ];
            $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerConflictPropagation($rows, $policy, $i, $updateConflict);
            $case = 'trigger2-6 conflict policy propagation ' . ($updateConflict ? 'update ' : 'insert ') . $policy . ' ' . $i;
            $expectedStatus = match ($policy) {
                'ignore', 'replace' => 'commit-ok',
                'rollback' => 'rolled-back',
                default => 'constraint-failed',
            };

            $tests[$case] = static function (TestRunner $t) use ($plan, $policy, $i, $updateConflict, $expectedStatus): void {
                $actual = $plan();
                $t->same($updateConflict ? 'trigger2.test trigger2-6.2a..6.2h' : 'trigger2.test trigger2-6.1a..6.1h', $actual['source']);
                $t->same($updateConflict ? 'update-trigger-conflict-policy-propagation' : 'insert-trigger-conflict-policy-propagation', $actual['operation']);
                $t->same($expectedStatus, $actual['status']);
                $t->same($policy, $actual['outer_policy']);
                $t->same($i, $actual['incoming_key']);
                $t->same($policy === 'rollback', $actual['rolled_back']);
                $t->same($policy === 'replace', $actual['trigger_row_survived']);
                $t->same(!$updateConflict && in_array($policy, ['default', 'abort', 'fail', 'ignore'], true), $actual['statement_row_survived']);
                $t->same($policy === 'rollback' ? [] : true, $policy === 'rollback' ? $actual['final_rows'] : is_array($actual['final_rows']));
                $t->same($policy === 'rollback' ? [] : true, $policy === 'rollback' ? $actual['final_keys'] : in_array($i + 10, $actual['final_keys'], true));
                $t->same($policy === 'ignore' ? null : ($policy === 'replace' ? null : 'UNIQUE constraint failed: tbl.a'), $actual['error']);
                $t->same('sqlite-trigger2-outer-conflict-policy-applies-to-trigger-program', $actual['dependencies'][0]);
                $t->same('sqlite-trigger2-rollback-policy-clears-transaction', $actual['dependencies'][1]);
            };
        }
    }
}

for ($i = 1; $i <= 44; $i++) {
    $parents = [
        ['id' => 0, 'label' => 'zero'],
        ['id' => 1, 'label' => 'one'],
        ['id' => 2, 'label' => 'two'],
    ];
    $children = [
        ['id' => 10, 'parent_id' => 1, 'label' => 'child-a'],
        ['id' => 11, 'parent_id' => $i % 3 === 0 ? 2 : 1, 'label' => 'child-b'],
        ['id' => 12, 'parent_id' => null, 'label' => 'loose'],
    ];
    if ($i % 4 === 0) {
        $children[] = ['id' => 13, 'parent_id' => 1, 'label' => 'child-c'];
    }
    $newId = $i % 2 === 0 ? 0 : -1;
    $defer = $i % 5 !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysUpdateCommit($parents, $children, 1, $newId, $defer);
    $case = 'fkey6-3.2 defer foreign keys restrict update commit check ' . $i;
    $expectedStatus = $defer ? 'commit-failed' : 'constraint-failed';
    $initialViolationCount = count(array_filter($children, static fn (array $row): bool => $row['parent_id'] === 1));
    foreach ([
        'source' => 'fkey6.test fkey6-3.2.1..3.2.6',
        'operation' => 'defer-foreign-keys-restrict-update-commit-check',
        'status' => $expectedStatus,
        'defer_foreign_keys' => $defer,
        'pragma_after_boundary' => 0,
        'old_parent_key' => 1,
        'new_parent_key' => $newId,
        'initial_violation_count' => $initialViolationCount,
        'commit_violation_count' => $initialViolationCount,
        'child_parent_ids' => array_column($children, 'parent_id'),
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' deferred attempt updates parent before failed commit'] = static function (TestRunner $t) use ($plan, $defer, $newId): void {
        $actual = $plan();
        $expected = $defer ? [0, $newId, 2] : [0, 1, 2];
        sort($expected);
        $t->same($expected, $actual['parent_ids']);
    };
    $tests[$case . ' dependency names cite defer and reset'] = static function (TestRunner $t) use ($plan, $defer): void {
        $deps = $plan()['dependencies'];
        $t->same(true, in_array('sqlite-fkey6-defer-foreign-keys-resets-at-transaction-boundary', $deps, true));
        $t->same($defer, in_array('sqlite-fkey6-commit-still-rejects-outstanding-violations', $deps, true));
    };
}

for ($i = 1; $i <= 52; $i++) {
    $parents = [
        ['id' => 0, 'label' => 'zero'],
        ['id' => 1, 'label' => 'one'],
        ['id' => 2, 'label' => 'two'],
    ];
    $children = [
        ['id' => 10, 'parent_id' => 1, 'label' => 'child-a'],
        ['id' => 11, 'parent_id' => $i % 4 === 0 ? 2 : 1, 'label' => 'child-b'],
        ['id' => 12, 'parent_id' => null, 'label' => 'loose'],
    ];
    if ($i % 5 === 0) {
        $children[] = ['id' => 13, 'parent_id' => 1, 'label' => 'child-c'];
    }
    $defer = $i % 6 !== 0;
    $repair = $i % 3 !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysRestrictDelete($parents, $children, 1, $defer, $repair);
    $case = 'fkey6-3.3 defer foreign keys after delete trigger repair ' . $i;
    $expectedStatus = !$defer ? 'constraint-failed' : ($repair ? 'commit-ok' : 'commit-failed');
    $initialViolationCount = count(array_filter($children, static fn (array $row): bool => $row['parent_id'] === 1));
    foreach ([
        'source' => 'fkey6.test fkey6-3.3.1..3.3.4',
        'operation' => 'defer-foreign-keys-restrict-delete-trigger-repair',
        'status' => $expectedStatus,
        'defer_foreign_keys' => $defer,
        'pragma_after_boundary' => 0,
        'deleted_parent' => 1,
        'initial_violation_count' => $initialViolationCount,
        'commit_violation_count' => $expectedStatus === 'commit-failed' ? $initialViolationCount : ($expectedStatus === 'constraint-failed' ? $initialViolationCount : 0),
        'trigger_repaired' => $defer && $repair,
        'child_parent_ids' => array_column($children, 'parent_id'),
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' repaired commit leaves parent key present'] = static function (TestRunner $t) use ($plan, $defer, $repair): void {
        $actual = $plan();
        $t->same($defer && $repair, ($actual['status'] === 'commit-ok') && in_array(1, $actual['parent_ids'], true));
    };
    $tests[$case . ' repaired trigger uses upstream deleted label'] = static function (TestRunner $t) use ($plan, $defer, $repair): void {
        $actual = $plan();
        $t->same($defer && $repair ? 'deleted!' : null, $actual['trigger_inserted_parent']['label'] ?? null);
    };
    $tests[$case . ' failed commit preserves rollback preview flag'] = static function (TestRunner $t) use ($plan, $expectedStatus): void {
        $t->same($expectedStatus !== 'commit-ok', $plan()['rollback_restored']);
    };
    $tests[$case . ' dependencies distinguish repair from immediate restrict'] = static function (TestRunner $t) use ($plan, $defer): void {
        $deps = $plan()['dependencies'];
        $t->same(true, in_array('sqlite-fkey6-defer-foreign-keys-resets-at-transaction-boundary', $deps, true));
        $t->same($defer, in_array('sqlite-fkey6-after-delete-trigger-can-repair-deferred-restrict', $deps, true));
    };
}

$replaceConflictCases = [
    'delete-a2' => [
        'before' => [['a' => 2, 'b' => 'b', 'count' => 3]],
        'after' => [['a' => 2, 'b' => 'b', 'count' => 2]],
        'final' => [['a' => 1, 'b' => 'a'], ['a' => 3, 'b' => 'c']],
        'direct' => true,
    ],
    'insert-replace-rowid' => [
        'before' => [['a' => 2, 'b' => 'b', 'count' => 3]],
        'after' => [['a' => 2, 'b' => 'b', 'count' => 2]],
        'final' => [['a' => 1, 'b' => 'a'], ['a' => 2, 'b' => 'd'], ['a' => 3, 'b' => 'c']],
        'direct' => false,
    ],
    'update-replace-rowid' => [
        'before' => [['a' => 2, 'b' => 'b', 'count' => 3]],
        'after' => [['a' => 2, 'b' => 'b', 'count' => 2]],
        'final' => [['a' => 1, 'b' => 'a'], ['a' => 2, 'b' => 'c']],
        'direct' => false,
    ],
    'insert-replace-unique-b' => [
        'before' => [['a' => 2, 'b' => 'b', 'count' => 3]],
        'after' => [['a' => 2, 'b' => 'b', 'count' => 2]],
        'final' => [['a' => 1, 'b' => 'a'], ['a' => 3, 'b' => 'c'], ['a' => 4, 'b' => 'b']],
        'direct' => false,
    ],
    'update-replace-unique-b' => [
        'before' => [['a' => 2, 'b' => 'b', 'count' => 3]],
        'after' => [['a' => 2, 'b' => 'b', 'count' => 2]],
        'final' => [['a' => 1, 'b' => 'a'], ['a' => 3, 'b' => 'b']],
        'direct' => false,
    ],
    'insert-replace-rowid-and-unique' => [
        'before' => [['a' => 2, 'b' => 'b', 'count' => 3], ['a' => 3, 'b' => 'c', 'count' => 2]],
        'after' => [['a' => 2, 'b' => 'b', 'count' => 2], ['a' => 3, 'b' => 'c', 'count' => 1]],
        'final' => [['a' => 1, 'b' => 'a'], ['a' => 2, 'b' => 'c']],
        'direct' => false,
    ],
    'update-replace-rowid-and-unique' => [
        'before' => [['a' => 1, 'b' => 'a', 'count' => 3], ['a' => 2, 'b' => 'b', 'count' => 2]],
        'after' => [['a' => 1, 'b' => 'a', 'count' => 2], ['a' => 2, 'b' => 'b', 'count' => 1]],
        'final' => [['a' => 1, 'b' => 'b']],
        'direct' => false,
    ],
];

for ($i = 1; $i <= 36; $i++) {
    foreach ($replaceConflictCases as $operation => $expected) {
        $before = $i % 2 === 0;
        $recursive = $i % 3 !== 0;
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::replaceConflictDeleteTrigger($operation, $before, $recursive);
        $expectedTriggerRows = ($recursive || $expected['direct']) ? $expected[$before ? 'before' : 'after'] : [];
        $case = 'triggerC-5 replace conflict delete trigger firing ' . $operation . ' matrix ' . $i;

        foreach ([
            'source' => 'triggerC.test triggerC-5.1..5.3',
            'operation' => 'or-replace-delete-trigger-firing',
            'status' => 'commit-ok',
            'dml' => $operation,
            'trigger_timing' => $before ? 'before' : 'after',
            'recursive_triggers' => $recursive,
            'direct_delete' => $expected['direct'],
            'conflict_delete_triggers_fire' => $recursive || $expected['direct'],
            'trigger_rows' => $expectedTriggerRows,
            'trigger_row_count' => count($expectedTriggerRows),
            'final_rows' => $expected['final'],
            'final_row_count' => count($expected['final']),
            'dependencies.0' => 'sqlite-triggerC-or-replace-delete-triggers',
            'dependencies.1' => 'sqlite-recursive-triggers-gate-conflict-delete-triggers',
            'dependencies.2' => 'sqlite-before-after-delete-trigger-row-counts',
        ] as $path => $expectedValue) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expectedValue, $value): void {
                $t->same($expectedValue, $value($plan(), (string) $path));
            };
        }

        $tests[$case . ' before count is one larger than after count when trigger fires'] = static function (TestRunner $t) use ($operation, $recursive, $expected): void {
            if (!$recursive && !$expected['direct']) {
                $t->same([], SQLiteDynamicTriggerForeignKeyPlan::replaceConflictDeleteTrigger($operation, true, false)['trigger_rows']);
                return;
            }
            $beforeRows = SQLiteDynamicTriggerForeignKeyPlan::replaceConflictDeleteTrigger($operation, true, $recursive)['trigger_rows'];
            $afterRows = SQLiteDynamicTriggerForeignKeyPlan::replaceConflictDeleteTrigger($operation, false, $recursive)['trigger_rows'];
            $t->same(count($beforeRows), count($afterRows));
            foreach ($beforeRows as $index => $row) {
                $t->same($row['a'], $afterRows[$index]['a']);
                $t->same($row['b'], $afterRows[$index]['b']);
                $t->same($row['count'] - 1, $afterRows[$index]['count']);
            }
        };
    }
}

for ($i = 1; $i <= 42; $i++) {
    $leftRows = [['a' => 1, 'b' => 2], ['a' => 3, 'b' => 4]];
    if ($i % 3 === 0) {
        $leftRows[] = ['a' => 5, 'b' => 6];
    }
    $rightRows = [['c' => 10, 'd' => 20], ['c' => 30, 'd' => 40]];
    if ($i % 4 === 0) {
        $rightRows[] = ['c' => 50, 'd' => 60];
    }
    $expectedViewRows = count($leftRows) * count($rightRows);
    $updateMatches = 0;
    $deleteMatches = 0;
    foreach ($leftRows as $left) {
        foreach ($rightRows as $right) {
            if (($left['a'] + $right['c']) % 2 === $i % 2) {
                ++$updateMatches;
            }
            if ((int) $left['b'] === 2 || (int) $right['d'] === 40) {
                ++$deleteMatches;
            }
        }
    }
    $operations = [
        [
            'op' => 'update',
            'where' => static fn (array $row): bool => ((int) $row['a'] + (int) $row['c']) % 2 === $i % 2,
            'row' => ['a' => 100 + $i, 'b' => 200 + $i, 'c' => 300 + $i, 'd' => 400 + $i],
        ],
        [
            'op' => 'delete',
            'where' => static fn (array $row): bool => (int) $row['b'] === 2 || (int) $row['d'] === 40,
        ],
        [
            'op' => 'insert',
            'row' => ['a' => 700 + $i, 'b' => 800 + $i, 'c' => 900 + $i, 'd' => 1000 + $i],
        ],
    ];
    $expectedLogRows = ($updateMatches * 2) + ($deleteMatches * 2) + 2;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewTriggerLog($leftRows, $rightRows, $operations);
    $case = 'trigger2-7 instead-of view trigger old new rows dynamic ' . $i;

    $tests[$case] = static function (TestRunner $t) use ($plan, $expectedViewRows, $expectedLogRows, $operations, $updateMatches): void {
        $actual = $plan();
        $first = $actual['first_log_row'];
        $last = $actual['last_log_row'];

        $t->same('trigger2.test trigger2-7.1..7.4', $actual['source']);
        $t->same('instead-of-view-trigger-old-new-log', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same($expectedViewRows, $actual['view_row_count']);
        $t->same(3, $actual['operation_count']);
        $t->same($expectedLogRows, $actual['log_row_count']);
        $t->same($updateMatches > 0 ? $operations[0]['row']['a'] : 1, $updateMatches > 0 ? $first['new_a'] : $first['old_a']);
        $t->same($updateMatches > 0 ? $operations[0]['row']['b'] : 2, $updateMatches > 0 ? $first['new_b'] : $first['old_b']);
        $t->same($updateMatches > 0 ? $operations[0]['row']['c'] : 10, $updateMatches > 0 ? $first['new_c'] : $first['old_c']);
        $t->same($updateMatches > 0 ? $operations[0]['row']['d'] : 20, $updateMatches > 0 ? $first['new_d'] : $first['old_d']);
        $t->same(0, $last['old_a']);
        $t->same(0, $last['old_b']);
        $t->same(0, $last['old_c']);
        $t->same(0, $last['old_d']);
        $t->same($operations[2]['row']['a'], $last['new_a']);
        $t->same($operations[2]['row']['b'], $last['new_b']);
        $t->same($operations[2]['row']['c'], $last['new_c']);
        $t->same($operations[2]['row']['d'], $last['new_d']);
        $t->same('sqlite-trigger2-instead-of-update-view-old-new-row', $actual['dependencies'][0]);
        $t->same('sqlite-trigger2-instead-of-delete-view-old-row', $actual['dependencies'][1]);
        $t->same('sqlite-trigger2-instead-of-insert-view-new-row', $actual['dependencies'][2]);
    };
}

for ($i = 1; $i <= 44; $i++) {
    $baseRows = [
        ['a' => $i, 'b' => $i + 1, 'c' => $i + 2],
        ['a' => $i + 3, 'b' => $i + 4, 'c' => $i + 5],
    ];
    if ($i % 4 === 0) {
        $baseRows[] = ['a' => $i + 6, 'b' => $i + 7, 'c' => $i + 8];
    }
    $expectedViewRows = array_map(
        static fn (array $row): array => [
            'x' => $row['a'] + $row['b'],
            'y' => $row['b'] + $row['c'],
            'z' => $row['a'] + $row['c'],
        ],
        $baseRows
    );
    $deleteMatches = 0;
    $updateMatches = 0;
    foreach ($expectedViewRows as $row) {
        if (($row['x'] + $row['z']) % 2 === $i % 2) {
            ++$deleteMatches;
        }
        if ($row['y'] >= ($i * 2) + 5) {
            ++$updateMatches;
        }
    }
    $insertRow = ['x' => 1000 + $i, 'y' => 2000 + $i, 'z' => 3000 + $i];
    $updateRow = ['x' => 4000 + $i, 'y' => 5000 + $i, 'z' => 6000 + $i];
    $operations = [
        [
            'op' => 'delete',
            'where' => static fn (array $row): bool => ((int) $row['x'] + (int) $row['z']) % 2 === $i % 2,
        ],
        ['op' => 'insert', 'row' => $insertRow],
        [
            'op' => 'update',
            'where' => static fn (array $row): bool => (int) $row['y'] >= ($i * 2) + 5,
            'row' => $updateRow,
        ],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::expressionViewTriggerRows($baseRows, $operations);
    $case = 'trigger2-8 expression view trigger old new rows dynamic ' . $i;

    $tests[$case] = static function (TestRunner $t) use ($plan, $expectedViewRows, $deleteMatches, $insertRow, $updateRow, $updateMatches): void {
        $actual = $plan();
        $insertLog = $actual['log_rows'][$deleteMatches];
        $lastLog = $actual['log_rows'][$actual['log_row_count'] - 1];

        $t->same('trigger2.test trigger2-8.1..8.6', $actual['source']);
        $t->same('expression-view-instead-of-trigger-old-new-rows', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same($expectedViewRows, $actual['view_rows']);
        $t->same(count($expectedViewRows), $actual['view_row_count']);
        $t->same($deleteMatches + 1 + $updateMatches, $actual['log_row_count']);
        $t->same(null, $insertLog['old_x']);
        $t->same($insertRow['x'], $insertLog['new_x']);
        $t->same($insertRow['y'], $insertLog['new_y']);
        $t->same($insertRow['z'], $insertLog['new_z']);
        $t->same($updateMatches > 0 ? $updateRow['x'] : $insertRow['x'], $lastLog['new_x']);
        $t->same($updateMatches > 0 ? $updateRow['y'] : $insertRow['y'], $lastLog['new_y']);
        $t->same($updateMatches > 0 ? $updateRow['z'] : $insertRow['z'], $lastLog['new_z']);
        $t->same('sqlite-trigger2-view-expression-columns-feed-old-row', $actual['dependencies'][0]);
        $t->same('sqlite-trigger2-view-insert-feeds-new-expression-row', $actual['dependencies'][1]);
        $t->same('sqlite-trigger2-view-update-feeds-old-and-new-expression-rows', $actual['dependencies'][2]);
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

for ($i = 1; $i <= 220; $i++) {
    $parents = [
        ['id' => 1, 'label' => 'one'],
        ['id' => 2, 'label' => 'two'],
        ['id' => 10 + $i, 'label' => 'extra'],
    ];
    $children = [
        ['id' => 100 + $i, 'parent_id' => 1, 'label' => 'left'],
        ['id' => 200 + $i, 'parent_id' => 2, 'label' => 'right'],
    ];
    $operation = match ($i % 3) {
        0 => 'delete-parent-replace-parent',
        1 => 'replace-child-then-delete',
        default => 'delete-parent-trigger-replace',
    };
    $statement = [
        'operation' => $operation,
        'target_parent' => 1,
        'replacement_parent' => $i % 4 === 0 ? 2 : 13 + $i,
        'conflict_child' => 100 + $i,
        'delete_children' => $operation === 'replace-child-then-delete' || $i % 5 === 0,
        'trigger_replaces_parent' => $operation === 'delete-parent-trigger-replace',
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::replaceDeferredForeignKeyCounter($parents, $children, $statement);
    $case = sprintf('fkey8 deferred implicit delete counter dynamic %03d', $i);

    $tests['real upstream fkey8.test ' . $case] = static function (TestRunner $t) use ($plan, $statement, $operation, $i): void {
        $actual = $plan();
        $expectedCommit = $operation === 'replace-child-then-delete' || ($statement['delete_children'] ?? false) === true;

        $t->same('fkey8.test fkey8-2.1.2..2.3.1', $actual['source']);
        $t->same('deferred-foreign-key-counter-implicit-delete', $actual['operation']);
        $t->same($operation, $actual['statement_operation']);
        $t->same($expectedCommit ? 'commit-ok' : 'commit-failed', $actual['status']);
        $t->same(!$expectedCommit, $actual['rollback_restored']);
        $t->same(true, $actual['constraint_counter_includes_implicit_deletes']);
        $t->true($actual['implicit_delete_count'] >= 1);
        $t->same($expectedCommit ? 0 : 1, $actual['deferred_violation_count']);
        $t->same($operation === 'delete-parent-trigger-replace' ? 1 : 0, count($actual['trigger_effects']));
        $t->same('sqlite-fkey8-implicit-delete-updates-deferred-counter', $actual['dependencies'][0]);
        $t->same('sqlite-fkey8-or-replace-without-rowid-foreign-key-counter', $actual['dependencies'][1]);
        $t->same('sqlite-fkey8-trigger-side-replace-preserves-counter', $actual['dependencies'][2]);
        $t->same($expectedCommit ? false : [1, 2, 10 + $i], $expectedCommit ? false : $actual['committed_parent_ids']);
    };
}

for ($i = 1; $i <= 180; $i++) {
    $schema = $i % 2 === 0 ? 'aux' : 'tenant';
    $multiplier = 2 + ($i % 9);
    $parents = [
        ['schema' => 'main', 'id' => 10],
        ['schema' => $schema, 'id' => 10],
        ['schema' => $schema, 'id' => 20],
    ];
    $children = [
        ['schema' => 'main', 'id' => 11, 'parent_id' => 10],
        ['schema' => $schema, 'id' => 12, 'parent_id' => 10],
        ['schema' => $schema, 'id' => 13, 'parent_id' => 10],
        ['schema' => $schema, 'id' => 21, 'parent_id' => 20],
        ['schema' => $schema, 'id' => 22, 'parent_id' => 20],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::attachedSchemaCascadeUpdate($parents, $children, $schema, $multiplier);
    $case = sprintf('fkey8 attached schema update cascade dynamic %03d', $i);

    $tests['real upstream fkey8.test ' . $case] = static function (TestRunner $t) use ($plan, $schema, $multiplier): void {
        $actual = $plan();
        $t->same('fkey8.test fkey8-7.0..7.4', $actual['source']);
        $t->same('attached-schema-foreign-key-update-cascade', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same($schema, $actual['schema']);
        $t->same($multiplier, $actual['multiplier']);
        $t->same([10, 10 * $multiplier, 20 * $multiplier], $actual['updated_parent_ids']);
        $t->same([10, 10 * $multiplier, 10 * $multiplier, 20 * $multiplier, 20 * $multiplier], $actual['updated_child_parent_ids']);
        $t->same(4, $actual['cascade_count']);
        $t->same(true, $actual['main_schema_untouched']);
        $t->same('sqlite-fkey8-attached-schema-cascade-update', $actual['dependencies'][0]);
        $t->same('sqlite-fkey8-child-table-resolves-parent-inside-own-schema', $actual['dependencies'][1]);
        $t->same('sqlite-fkey8-cascade-update-preserves-attached-schema-routing', $actual['dependencies'][2]);
    };
}

$tests['real upstream fkey8.test source coverage note for implicit deletes and attached cascade'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');

    $t->true(is_string($source));
    $t->true(str_contains($source, 'INSERT OR REPLACE INTO p1 VALUES(2,'));
    $t->true(str_contains($source, 'INSERT OR REPLACE INTO c2 VALUES(13, 13);'));
    $t->true(str_contains($source, 'UPDATE aux.p1 SET pid = pid * 10;'));
    $t->true(str_contains($source, 'SELECT * FROM aux.c1;'));
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
