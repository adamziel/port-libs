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
    'real upstream fkey2 vacuum bypass cites section summary' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-6.*: Test that FK processing is automatically disabled when'));
        $t->true(is_string($source) && str_contains($source, 'running VACUUM'));
    },
    'real upstream fkey2 vacuum bypass cites executable corpus row' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-6.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t1(a REFERENCES t2(c), b)'));
        $t->true(is_string($source) && str_contains($source, 'VACUUM;'));
    },
    'real upstream fkey2 vacuum bypass rejects malformed copy order' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey2VacuumForeignKeyBypassPlan(
            [['id' => 1, 'c' => 1]],
            [['id' => 2, 'a' => 1]],
            ['copy_order' => ['child', 'missing']]
        ));
    },
    'real upstream fkey2 vacuum bypass requires parent and child copy phases' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey2VacuumForeignKeyBypassPlan(
            [['id' => 1, 'c' => 1]],
            [['id' => 2, 'a' => 1]],
            ['copy_order' => ['child']]
        ));
    },
];

for ($i = 1; $i <= 80; ++$i) {
    $base = $i * 10;
    $parentKey = $i % 2 === 0 ? 'c' : 'parent_code';
    $childKey = $i % 2 === 0 ? 'a' : 'child_parent_code';
    $parentTable = $i % 3 === 0 ? 'lookup_records' : 'parent_records';
    $childTable = $i % 3 === 0 ? 'dependent_records' : 'child_records';
    $copyOrder = $i % 4 === 0 ? ['parent', 'child'] : ['child', 'parent'];
    $foreignKeys = $i % 5 !== 0;
    $expectedTransient = $copyOrder[0] === 'child' ? 2 : 0;
    $expectedWouldFail = $foreignKeys && $expectedTransient > 0;
    $pageCountBefore = 4 + ($i % 7);
    $pageCountAfter = $pageCountBefore + ($i % 2);

    $parents = [
        ['id' => 1, $parentKey => $base + 1, 'payload' => 'parent-a-' . $i],
        ['id' => 2, $parentKey => $base + 2, 'payload' => 'parent-b-' . $i],
    ];
    $children = [
        ['id' => 10, $childKey => $base + 1, 'payload' => 'child-a-' . $i],
        ['id' => 11, $childKey => $base + 2, 'payload' => 'child-b-' . $i],
        ['id' => 12, $childKey => null, 'payload' => 'child-null-' . $i],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2VacuumForeignKeyBypassPlan(
        $parents,
        $children,
        [
            'parent_table' => $parentTable,
            'child_table' => $childTable,
            'parent_key' => $parentKey,
            'child_key' => $childKey,
            'copy_order' => $copyOrder,
            'foreign_keys' => $foreignKeys,
            'page_count_before' => $pageCountBefore,
            'page_count_after' => $pageCountAfter,
        ]
    );

    $case = 'fkey2-6 vacuum foreign key bypass dynamic ' . $i;
    foreach ([
        'source' => 'fkey2.test fkey2-6.1',
        'operation' => 'vacuum-foreign-key-processing-bypass',
        'status' => 'commit-ok',
        'parent_table' => $parentTable,
        'child_table' => $childTable,
        'parent_key' => $parentKey,
        'child_key' => $childKey,
        'copy_order.0' => $copyOrder[0],
        'copy_order.1' => $copyOrder[1],
        'phase_count' => 2,
        'phases.0.copy' => $copyOrder[0],
        'phases.0.table' => $copyOrder[0] === 'parent' ? $parentTable : $childTable,
        'phases.0.foreign_key_processing_enabled' => false,
        'phases.0.connection_foreign_keys_requested' => $foreignKeys ? 1 : 0,
        'phases.0.transient_violation_count_without_bypass' => $expectedTransient,
        'phases.0.would_fail_with_vacuum_bypass_removed' => $expectedWouldFail,
        'phases.1.copy' => $copyOrder[1],
        'phases.1.foreign_key_processing_enabled' => false,
        'phases.1.transient_violation_count_without_bypass' => 0,
        'foreign_keys_connection_setting' => $foreignKeys ? 1 : 0,
        'vacuum_foreign_key_processing_enabled' => false,
        'foreign_keys_restored_after_vacuum' => $foreignKeys ? 1 : 0,
        'transient_violation_count_without_bypass' => $expectedTransient,
        'would_fail_with_vacuum_bypass_removed' => $expectedWouldFail,
        'before_violation_count' => 0,
        'final_violation_count' => 0,
        'parent_row_count_after' => 2,
        'child_row_count_after' => 3,
        'parent_rows_after.0.id' => 1,
        'parent_rows_after.0.' . $parentKey => $base + 1,
        'parent_rows_after.1.id' => 2,
        'parent_rows_after.1.' . $parentKey => $base + 2,
        'child_rows_after.0.id' => 10,
        'child_rows_after.0.' . $childKey => $base + 1,
        'child_rows_after.1.id' => 11,
        'child_rows_after.1.' . $childKey => $base + 2,
        'child_rows_after.2.' . $childKey => null,
        'parent_key_values.0' => $base + 1,
        'parent_key_values.1' => $base + 2,
        'child_key_values.0' => $base + 1,
        'child_key_values.1' => $base + 2,
        'child_key_values.2' => null,
        'page_count_before' => $pageCountBefore,
        'page_count_after' => $pageCountAfter,
        'database_image_rebuilt' => true,
        'table_content_preserved' => true,
        'dependencies.0' => 'sqlite-fkey2-vacuum-disables-foreign-key-processing',
        'dependencies.1' => 'sqlite-fkey2-vacuum-restores-connection-foreign-key-setting',
        'dependencies.2' => 'sqlite-fkey2-vacuum-preserves-valid-parent-child-image',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

return $tests;
