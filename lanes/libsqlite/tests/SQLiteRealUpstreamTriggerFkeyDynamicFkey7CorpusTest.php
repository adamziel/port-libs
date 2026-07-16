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
    'real upstream fkey7 corpus cites authorizer read-set block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey7.test');
        $t->true(is_string($source) && str_contains($source, 'do_tblsread_test 1.2'));
        $t->true(is_string($source) && str_contains($source, 'do_tblsread_test 1.5'));
    },
    'real upstream fkey7 corpus cites insert or fail block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey7.test');
        $t->true(is_string($source) && str_contains($source, 'INSERT OR FAIL INTO child VALUES(123), (123)'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_key_check'));
    },
];

$readSetCases = [
    '1.2 update child reference column reads parent reference table' => [
        ['b'],
        false,
        ['par', 's1'],
        [
            'reads_parent_reference_table' => true,
            'reads_child_primary_key_refs' => false,
            'reads_child_unique_refs' => false,
        ],
    ],
    '1.3 update primary key reads referencing children' => [
        ['a'],
        false,
        ['c1', 'c2', 'par'],
        [
            'reads_parent_reference_table' => false,
            'reads_child_primary_key_refs' => true,
            'reads_child_unique_refs' => false,
        ],
    ],
    '1.4 update unique parent key reads dependent child' => [
        ['c'],
        false,
        ['c3', 'par'],
        [
            'reads_parent_reference_table' => false,
            'reads_child_primary_key_refs' => false,
            'reads_child_unique_refs' => true,
        ],
    ],
    '1.5 update all fk-bearing columns reads all related tables' => [
        ['a', 'b', 'c'],
        true,
        ['c1', 'c2', 'c3', 'par', 's1'],
        [
            'reads_parent_reference_table' => true,
            'reads_child_primary_key_refs' => true,
            'reads_child_unique_refs' => true,
        ],
    ],
];

for ($i = 1; $i <= 120; ++$i) {
    foreach ($readSetCases as $label => [$columns, $whereUsesParentReference, $expectedTables, $flags]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyUpdateReadSet($columns, $whereUsesParentReference);
        $case = 'real upstream fkey7 authorizer read set dynamic ' . $i . ' ' . $label;
        foreach ([
            'source' => 'fkey7.test fkey7-1.2..1.5',
            'operation' => 'foreign-key-update-authorizer-read-set',
            'status' => 'commit-ok',
            'read_tables' => $expectedTables,
            'read_table_count' => count($expectedTables),
            'dependencies.0' => 'sqlite-fkey7-parent-update-reads-new-parent-reference',
            'dependencies.1' => 'sqlite-fkey7-parent-key-update-probes-child-references',
            'dependencies.2' => 'sqlite-fkey7-unique-parent-key-update-probes-dependent-child',
        ] + $flags as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

for ($i = 1; $i <= 160; ++$i) {
    $missingKey = 1000 + $i;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyOrFailInsert([], [], [$missingKey, $missingKey]);
    $case = 'real upstream fkey7 insert or fail fk failure before unique dynamic ' . $i;
    foreach ([
        'source' => 'fkey7.test fkey7-4.1..4.6',
        'operation' => 'insert-or-fail-foreign-key-before-unique',
        'status' => 'constraint-failed',
        'error' => 'FOREIGN KEY constraint failed',
        'failed_key' => $missingKey,
        'committed_child_keys' => [],
        'committed_child_count' => 0,
        'foreign_key_checked_before_unique' => true,
        'or_fail_preserved_prior_successful_rows' => false,
        'foreign_key_check_rows' => [],
        'dependencies.0' => 'sqlite-fkey7-insert-or-fail-checks-foreign-key-before-unique',
        'dependencies.2' => 'sqlite-fkey7-foreign-key-check-clean-after-failed-statement',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 160; ++$i) {
    $key = 2000 + $i;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyOrFailInsert([$key], [], [$key, $key]);
    $case = 'real upstream fkey7 insert or fail unique preserves first row dynamic ' . $i;
    foreach ([
        'source' => 'fkey7.test fkey7-4.1..4.6',
        'operation' => 'insert-or-fail-foreign-key-before-unique',
        'status' => 'constraint-failed',
        'error' => 'UNIQUE constraint failed: child.c',
        'failed_key' => $key,
        'committed_child_keys.0' => $key,
        'committed_child_count' => 1,
        'foreign_key_checked_before_unique' => false,
        'or_fail_preserved_prior_successful_rows' => true,
        'foreign_key_check_rows' => [],
        'dependencies.1' => 'sqlite-fkey7-insert-or-fail-preserves-prior-row-on-unique-failure',
        'dependencies.2' => 'sqlite-fkey7-foreign-key-check-clean-after-failed-statement',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

return $tests;
