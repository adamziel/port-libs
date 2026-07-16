<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

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

$tests = [
    'real upstream e_fkey child lookup plan cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test');

        $t->true(is_string($source));
        $t->contains('do_detail_test e_fkey-25.2', $source);
        $t->contains('SELECT rowid FROM track WHERE trackartist = ?', $source);
        $t->contains('EVIDENCE-OF: R-23302-30956', $source);
        $t->contains('EVIDENCE-OF: R-47936-10044', $source);
        $t->contains('CREATE INDEX childi ON child(a, b)', $source);
        $t->contains('CREATE UNIQUE INDEX childi ON child(b, a)', $source);
        $t->contains('do_detail_test e_fkey-27.3', $source);
        $t->contains('SEARCH track USING COVERING INDEX trackindex (trackartist=?)', $source);
    },
];

for ($i = 1; $i <= 90; ++$i) {
    $base = $i * 1000;
    $parents = [
        ['rowid' => $i * 10 + 5, 'entity_id' => $base + 5, 'label' => 'parent-five-' . $i],
        ['rowid' => $i * 10 + 6, 'entity_id' => $base + 6, 'label' => 'parent-six-' . $i],
        ['rowid' => $i * 10 + 7, 'entity_id' => $base + 7, 'label' => 'parent-seven-' . $i],
    ];
    $children = [
        ['rowid' => $i * 100 + 1, 'item_id' => $i * 100 + 11, 'entity_ref' => $base + 5],
        ['rowid' => $i * 100 + 2, 'item_id' => $i * 100 + 12, 'entity_ref' => $base + 6],
        ['rowid' => $i * 100 + 3, 'item_id' => $i * 100 + 13, 'entity_ref' => null],
        ['rowid' => $i * 100 + 4, 'item_id' => $i * 100 + 14, 'entity_ref' => $base + 99],
    ];

    foreach ([
        'blocked-five' => [$base + 5, $i * 10 + 5, [$i * 100 + 1], 'constraint-failed', 'FOREIGN KEY constraint failed', 0, true],
        'blocked-six' => [$base + 6, $i * 10 + 6, [$i * 100 + 2], 'constraint-failed', 'FOREIGN KEY constraint failed', 0, true],
        'unreferenced-seven' => [$base + 7, $i * 10 + 7, [], 'commit-ok', null, 1, false],
    ] as $label => [$target, $parentRowid, $matchedChildRowids, $status, $error, $changes, $blocked]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyChildLookupPlan($parents, $children, [
            'parent_table' => 'parent_entity',
            'child_table' => 'dependent_item',
            'parent_key_columns' => ['entity_id'],
            'child_key_columns' => ['entity_ref'],
            'target_parent_key' => $target,
        ]);

        foreach ([
            'source' => 'e_fkey.test e_fkey-25.1..27.4',
            'operation' => 'foreign-key-parent-mutation-child-lookup-plan',
            'parent_table' => 'parent_entity',
            'child_table' => 'dependent_item',
            'parent_key_columns.0' => 'entity_id',
            'child_key_columns.0' => 'entity_ref',
            'target_parent_key.entity_id' => $target,
            'child_lookup_sql' => 'SELECT rowid FROM dependent_item WHERE entity_ref = ?',
            'parent_delete_child_lookup_equivalence' => 'SELECT rowid FROM <child-table> WHERE <child-key> = :parent_key_value',
            'delete_eqp.0' => 'SCAN parent_entity',
            'delete_eqp.1' => 'SCAN dependent_item',
            'update_eqp.0' => 'SCAN parent_entity',
            'update_eqp.1' => 'SCAN dependent_item',
            'update_eqp.2' => 'SCAN dependent_item',
            'insert_parent_eqp' => [],
            'parent_insert_runs_child_lookup' => false,
            'parent_update_plans_old_and_new_child_lookup' => true,
            'delete_child_lookup_count' => 1,
            'update_child_lookup_count' => 2,
            'child_lookup_detail' => 'SCAN dependent_item',
            'child_lookup_uses_index' => false,
            'child_lookup_avoids_linear_scan' => false,
            'child_lookup_index_name' => null,
            'child_lookup_index_columns' => null,
            'child_lookup_index_unique' => false,
            'child_lookup_index_covering' => false,
            'child_lookup_index_terms' => [],
            'matched_parent_rowids.0' => $parentRowid,
            'matched_child_row_count' => count($matchedChildRowids),
            'null_child_key_rowids.0' => $i * 100 + 3,
            'null_child_key_short_circuit_count' => 1,
            'delete_status' => $status,
            'delete_error' => $error,
            'delete_changes' => $changes,
            'foreign_key_violation_if_child_lookup_returns_any_row' => $blocked,
            'upstream_cases.0' => 'e_fkey-25.2',
            'upstream_cases.4' => 'e_fkey-25.7',
            'dependencies.0' => 'sqlite-efkey-parent-delete-runs-child-rowid-lookup',
            'dependencies.1' => 'sqlite-efkey-child-lookup-row-blocks-parent-delete',
            'dependencies.2' => 'sqlite-efkey-parent-update-plans-old-and-new-child-lookups',
            'dependencies.3' => 'sqlite-efkey-child-key-index-avoids-linear-scan',
            'dependencies.4' => 'sqlite-efkey-null-child-key-does-not-match-parent-lookup',
        ] as $path => $expected) {
            $tests[sprintf('real upstream e_fkey 25 child lookup %03d %s %s', $i, $label, $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }

        $tests[sprintf('real upstream e_fkey 25 child lookup %03d %s rowids', $i, $label)] = static function (TestRunner $t) use ($plan, $matchedChildRowids): void {
            $t->same($matchedChildRowids, $plan()['matched_child_rowids']);
        };
    }
}

for ($i = 1; $i <= 90; ++$i) {
    $oldX = $i * 20 + 1;
    $oldY = $i * 20 + 2;
    $newX = $i * 20 + 101;
    $newY = $i * 20 + 102;
    $parents = [
        ['rowid' => $i * 10 + 1, 'x' => $oldX, 'y' => $oldY],
        ['rowid' => $i * 10 + 2, 'x' => $oldX + 1, 'y' => $oldY + 1],
    ];
    $children = [
        ['rowid' => $i * 100 + 7, 'parent_a' => $oldX, 'parent_b' => $oldY],
        ['rowid' => $i * 100 + 8, 'parent_a' => $oldX + 1, 'parent_b' => null],
    ];

    foreach ([
        'scan' => [null, 'SCAN child_record', false, false, null, null, false, false, []],
        'covering-ab' => [
            ['name' => 'child_ref_ab', 'columns' => ['parent_a', 'parent_b']],
            'SEARCH child_record USING COVERING INDEX child_ref_ab (parent_a=? AND parent_b=?)',
            true,
            true,
            'child_ref_ab',
            ['parent_a', 'parent_b'],
            false,
            true,
            ['parent_a=?', 'parent_b=?'],
        ],
        'covering-ba' => [
            ['name' => 'child_ref_ba', 'columns' => ['parent_b', 'parent_a'], 'unique' => true],
            'SEARCH child_record USING COVERING INDEX child_ref_ba (parent_b=? AND parent_a=?)',
            true,
            true,
            'child_ref_ba',
            ['parent_b', 'parent_a'],
            true,
            true,
            ['parent_b=?', 'parent_a=?'],
        ],
    ] as $label => [$childIndex, $detail, $usesIndex, $avoidsScan, $indexName, $indexColumns, $unique, $covering, $terms]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyChildLookupPlan($parents, $children, [
            'parent_table' => 'parent_record',
            'child_table' => 'child_record',
            'parent_key_columns' => ['x', 'y'],
            'child_key_columns' => ['parent_a', 'parent_b'],
            'target_parent_key' => [$oldX, $oldY],
            'new_parent_key' => ['x' => $newX, 'y' => $newY],
            'child_index' => $childIndex,
        ]);

        foreach ([
            'source' => 'e_fkey.test e_fkey-25.1..27.4',
            'operation' => 'foreign-key-parent-mutation-child-lookup-plan',
            'parent_table' => 'parent_record',
            'child_table' => 'child_record',
            'parent_key_columns.0' => 'x',
            'parent_key_columns.1' => 'y',
            'child_key_columns.0' => 'parent_a',
            'child_key_columns.1' => 'parent_b',
            'target_parent_key.x' => $oldX,
            'target_parent_key.y' => $oldY,
            'new_parent_key.x' => $newX,
            'new_parent_key.y' => $newY,
            'child_lookup_sql' => 'SELECT rowid FROM child_record WHERE parent_a = ? AND parent_b = ?',
            'delete_eqp.0' => 'SCAN parent_record',
            'delete_eqp.1' => $detail,
            'update_eqp.0' => 'SCAN parent_record',
            'update_eqp.1' => $detail,
            'update_eqp.2' => $detail,
            'delete_child_lookup_count' => 1,
            'update_child_lookup_count' => 2,
            'child_lookup_detail' => $detail,
            'child_lookup_uses_index' => $usesIndex,
            'child_lookup_avoids_linear_scan' => $avoidsScan,
            'child_lookup_index_name' => $indexName,
            'child_lookup_index_unique' => $unique,
            'child_lookup_index_covering' => $covering,
            'matched_parent_rowids.0' => $i * 10 + 1,
            'matched_child_rowids.0' => $i * 100 + 7,
            'matched_child_rows.0.child_key.parent_a' => $oldX,
            'matched_child_rows.0.child_key.parent_b' => $oldY,
            'matched_child_rows.0.parent_key.x' => $oldX,
            'matched_child_rows.0.parent_key.y' => $oldY,
            'matched_child_row_count' => 1,
            'null_child_key_rowids.0' => $i * 100 + 8,
            'delete_status' => 'constraint-failed',
            'delete_error' => 'FOREIGN KEY constraint failed',
            'delete_changes' => 0,
            'parent_update_plans_old_and_new_child_lookup' => true,
            'upstream_cases.5' => 'e_fkey-26.2.1',
            'upstream_cases.9' => 'e_fkey-26.4.1',
            'upstream_cases.11' => 'e_fkey-27.3',
            'upstream_cases.12' => 'e_fkey-27.4',
            'dependencies.2' => 'sqlite-efkey-parent-update-plans-old-and-new-child-lookups',
            'dependencies.3' => 'sqlite-efkey-child-key-index-avoids-linear-scan',
        ] as $path => $expected) {
            $tests[sprintf('real upstream e_fkey 26 27 child index lookup %03d %s %s', $i, $label, $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }

        $tests[sprintf('real upstream e_fkey 26 27 child index lookup %03d %s columns', $i, $label)] = static function (TestRunner $t) use ($plan, $indexColumns): void {
            $t->same($indexColumns, $plan()['child_lookup_index_columns']);
        };
        $tests[sprintf('real upstream e_fkey 26 27 child index lookup %03d %s terms', $i, $label)] = static function (TestRunner $t) use ($plan, $terms): void {
            $t->same($terms, $plan()['child_lookup_index_terms']);
        };
    }
}

$tests['real upstream e_fkey child lookup rejects empty parent rows'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyChildLookupPlan([], []));
$tests['real upstream e_fkey child lookup rejects key arity mismatch'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyChildLookupPlan([['a' => 1]], [], [
    'parent_key_columns' => ['a'],
    'child_key_columns' => ['b', 'c'],
]));
$tests['real upstream e_fkey child lookup rejects scalar composite target'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyChildLookupPlan([['a' => 1, 'b' => 2]], [], [
    'parent_key_columns' => ['a', 'b'],
    'child_key_columns' => ['c', 'd'],
    'target_parent_key' => 1,
]));
$tests['real upstream e_fkey child lookup rejects malformed index'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyChildLookupPlan([['a' => 1]], [], [
    'parent_key_columns' => ['a'],
    'child_key_columns' => ['b'],
    'child_index' => ['columns' => ['b']],
]));
$tests['real upstream e_fkey child lookup rejects missing child column'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyChildLookupPlan([['a' => 1]], [['rowid' => 1]], [
    'parent_key_columns' => ['a'],
    'child_key_columns' => ['b'],
]));

return $tests;
