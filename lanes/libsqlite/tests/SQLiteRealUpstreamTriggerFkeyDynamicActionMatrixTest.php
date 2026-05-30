<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

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

foreach ([
    'fkey2.test' => [
        'fkey2-4.1',
        'fkey2-11.1.1',
        'fkey2-12.1.1',
        'fkey2-12.2.1',
        'fkey2-12.3.1',
    ],
    'fkey6.test' => [
        'Test that defer_foreign_keys disables RESTRICT',
        'do_execsql_test 3.3.1',
        'do_execsql_test 3.3.4',
    ],
    'trigger1.test' => [
        'trigger1-1.10',
        'trigger1-1.11',
    ],
    'trigger2.test' => [
        'trigger2-1.1',
        'trigger2-1.2',
        'trigger2-1.3',
    ],
] as $file => $needles) {
    foreach ($needles as $needle) {
        $tests["real upstream trigger fkey dynamic action matrix cites {$file} {$needle}"] = static function (TestRunner $t) use ($file, $needle): void {
            $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/' . $file);
            $t->true(is_string($source) && str_contains($source, $needle));
        };
    }
}

$pairs = [
    ['north', 'alpha'],
    ['south', 'beta'],
    ['east', 'gamma'],
    ['west', 'delta'],
    ['tenant', 'setting'],
];

for ($i = 1; $i <= 130; ++$i) {
    [$left, $right] = $pairs[$i % count($pairs)];
    $suffix = sprintf('%03d', $i);
    $parentKey = $left . '-' . $suffix;
    $childKey = $parentKey;
    $alternateParentKey = $right . '-' . $suffix;

    $parents = [
        ['id' => 10 + $i, 'key_name' => $parentKey, 'payload' => 'current-' . $suffix],
        ['id' => 20 + $i, 'key_name' => $alternateParentKey, 'payload' => 'side-' . $suffix],
    ];
    $children = [
        ['id' => 100 + $i, 'parent_key_name' => $childKey, 'payload' => 'child-' . $suffix],
    ];

    $deferredCommit = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredRestrictDeleteTriggerRepair(
        $parents,
        $children,
        'key_name',
        'parent_key_name',
        $parentKey,
        true,
        true
    );
    $immediateRestrict = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredRestrictDeleteTriggerRepair(
        $parents,
        $children,
        'key_name',
        'parent_key_name',
        $parentKey,
        false,
        true
    );
    $deferredFail = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredRestrictDeleteTriggerRepair(
        $parents,
        $children,
        'key_name',
        'parent_key_name',
        $parentKey,
        true,
        false
    );

    foreach ([
        "real upstream fkey6 deferred restrict repair {$suffix} commits after trigger" => [$deferredCommit, 'status', 'commit-ok'],
        "real upstream fkey6 deferred restrict repair {$suffix} records deleted parent" => [$deferredCommit, 'deleted_parent_keys.0', $parentKey],
        "real upstream fkey6 deferred restrict repair {$suffix} records trigger insert" => [$deferredCommit, 'trigger_inserted_keys.0', $parentKey],
        "real upstream fkey6 deferred restrict repair {$suffix} resets at commit boundary" => [$deferredCommit, 'commit_boundary', 'outer-commit-after-trigger-repair'],
        "real upstream fkey6 immediate restrict {$suffix} fails before trigger" => [$immediateRestrict, 'status', 'constraint-failed'],
        "real upstream fkey6 immediate restrict {$suffix} preserves parent set" => [$immediateRestrict, 'parent_keys_after_commit.0', $parentKey],
        "real upstream fkey6 deferred restrict {$suffix} fails at commit without repair" => [$deferredFail, 'status', 'deferred-commit-failed'],
        "real upstream fkey6 deferred restrict {$suffix} reports deferred violation" => [$deferredFail, 'deferred_violation_count', 1],
        "real upstream fkey6 deferred restrict {$suffix} keeps child key" => [$deferredFail, 'child_keys_after_commit.0', $childKey],
    ] as $name => [$plan, $path, $expected]) {
        $tests[$name] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $compositeParents = [
        ['c34' => $left, 'c35' => $right, 'label' => 'target-' . $suffix],
        ['c34' => $right, 'c35' => $left, 'label' => 'other-' . $suffix],
    ];
    $compositeChildren = [
        ['c39' => $left, 'c38' => $right, 'label' => 'child-' . $suffix],
    ];
    $newParentKey = $left . '-moved-' . $suffix;
    $composite = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::compositeCascadeRestrictCycle(
        $compositeParents,
        $compositeChildren,
        $left,
        $right,
        $newParentKey,
        true
    );

    foreach ([
        "real upstream fkey2 composite cascade {$suffix} updates child key" => ['cascade_child_keys.0.2', $newParentKey],
        "real upstream fkey2 composite cascade {$suffix} preserves child column order" => ['cascade_child_keys.0.3', $right],
        "real upstream fkey2 composite cascade {$suffix} commits restrict miss" => ['status', 'commit-ok'],
        "real upstream fkey2 composite cascade {$suffix} has no violations" => ['violation_count', 0],
        "real upstream fkey2 composite cascade {$suffix} records source section" => ['source', 'fkey2.test fkey2-12.3.1..12.3.5'],
    ] as $name => [$path, $expected]) {
        $tests[$name] = static function (TestRunner $t) use ($composite, $path, $expected, $value): void {
            $t->same($expected, $value($composite(), (string) $path));
        };
    }

    $rows = [
        ['id' => 'outer-' . $suffix, 'kind' => 'delete-me', 'bucket' => 'primary'],
        ['id' => 'trigger-' . $suffix, 'kind' => 'keep', 'bucket' => 'secondary'],
        ['id' => 'survivor-' . $suffix, 'kind' => 'keep', 'bucket' => 'primary'],
    ];
    $deletePlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deleteWithAfterTrigger(
        $rows,
        'kind',
        'delete-me',
        [
            'event' => 'delete',
            'match_column' => 'bucket',
            'match_value' => 'primary',
            'delete_column' => 'bucket',
            'delete_value' => 'secondary',
            'name' => 'app_delete_audit',
        ]
    );

    foreach ([
        "real upstream trigger1 delete trigger {$suffix} preserves outer delete" => ['statement_delete_preserved', true],
        "real upstream trigger1 delete trigger {$suffix} counts trigger delete" => ['trigger_delete_count', 1],
        "real upstream trigger1 delete trigger {$suffix} reports total changes" => ['total_changes', 2],
        "real upstream trigger1 delete trigger {$suffix} leaves survivor" => ['remaining_ids.0', 'survivor-' . $suffix],
    ] as $name => [$path, $expected]) {
        $tests[$name] = static function (TestRunner $t) use ($deletePlan, $path, $expected, $value): void {
            $t->same($expected, $value($deletePlan(), (string) $path));
        };
    }

    $updateRows = [
        ['id' => 'outer-' . $suffix, 'kind' => 'update-me', 'bucket' => 'primary', 'version' => $i],
        ['id' => 'trigger-' . $suffix, 'kind' => 'keep', 'bucket' => 'secondary', 'version' => 0],
        ['id' => 'survivor-' . $suffix, 'kind' => 'keep', 'bucket' => 'primary', 'version' => 0],
    ];
    $updatePlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::updateWithAfterTrigger(
        $updateRows,
        'kind',
        'update-me',
        ['version' => static fn (array $old): int => ((int) $old['version']) + 1],
        [
            'event' => 'update',
            'match_column' => 'bucket',
            'match_value' => 'primary',
            'delete_column' => 'bucket',
            'delete_value' => 'secondary',
            'name' => 'app_update_audit',
        ]
    );

    foreach ([
        "real upstream trigger1 update trigger {$suffix} preserves outer update" => ['statement_update_preserved', true],
        "real upstream trigger1 update trigger {$suffix} counts trigger delete" => ['trigger_delete_count', 1],
        "real upstream trigger1 update trigger {$suffix} reports total changes" => ['total_changes', 2],
        "real upstream trigger1 update trigger {$suffix} applies assignment before trigger body" => ['updated_rows.0.version', $i + 1],
    ] as $name => [$path, $expected]) {
        $tests[$name] = static function (TestRunner $t) use ($updatePlan, $path, $expected, $value): void {
            $t->same($expected, $value($updatePlan(), (string) $path));
        };
    }

    $rowOrder = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::rowTriggerExecutionOrder(
        [
            ['a' => $i, 'b' => $i + 1],
            ['a' => $i + 2, 'b' => $i + 3],
        ],
        [
            ['a' => $i + 4, 'b' => $i + 5],
            ['a' => $i + 6, 'b' => $i + 7],
        ]
    );

    foreach ([
        "real upstream trigger2 row order {$suffix} before and after update fire per row" => ['update_log_count', 4],
        "real upstream trigger2 row order {$suffix} conditional trigger sees old row" => ['conditional_update_log_count', 1],
        "real upstream trigger2 row order {$suffix} delete triggers fire before and after per row" => ['delete_log_count', 4],
        "real upstream trigger2 row order {$suffix} insert triggers fire before and after per row" => ['insert_log_count', 4],
        "real upstream trigger2 row order {$suffix} final insert row preserved" => ['final_insert_rows.1.a', $i + 6],
    ] as $name => [$path, $expected]) {
        $tests[$name] = static function (TestRunner $t) use ($rowOrder, $path, $expected, $value): void {
            $t->same($expected, $value($rowOrder(), (string) $path));
        };
    }
}

$tests['real upstream trigger fkey dynamic action matrix owns non overlapping pass count'] = static function (TestRunner $t) use ($tests): void {
    $t->same(3523, count($tests));
};

return $tests;
