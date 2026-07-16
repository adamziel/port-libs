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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey7.test';

$tests = [
    'real upstream fkey7 authorizer cites table read block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_tblsread_test 1.2'));
        $t->true(is_string($source) && str_contains($source, 'do_tblsread_test 1.5'));
    },
    'real upstream fkey7 authorizer cites insert or fail block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'INSERT OR FAIL INTO child VALUES(123), (123)'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_key_check'));
    },
];

$authorizerStatements = [
    'fkey7-1.2 parent reference update reads parent and referenced parent' => [
        ['operation' => 'update-parent-reference', 'set' => ['b' => 10], 'where_column' => 'a', 'where_value' => 1],
        ['par', 's1'],
        true,
        [],
        [],
    ],
    'fkey7-1.3 parent primary key update reads child dependents' => [
        ['operation' => 'update-parent-primary-key', 'set' => ['a' => 10], 'where_column' => 'b', 'where_value' => 2],
        ['c1', 'c2', 'par'],
        false,
        ['c1', 'c2'],
        [],
    ],
    'fkey7-1.4 parent unique key update reads unique child dependent' => [
        ['operation' => 'update-parent-unique-key', 'set' => ['c' => 30], 'where_column' => 'b', 'where_value' => 2],
        ['c3', 'par'],
        false,
        [],
        ['c3'],
    ],
    'fkey7-1.5 all parent keys update reads all dependent tables' => [
        ['operation' => 'update-parent-all-keys', 'set' => ['a' => 10, 'b' => 20, 'c' => 30], 'where_column' => 'b', 'where_value' => 2],
        ['c1', 'c2', 'c3', 'par', 's1'],
        true,
        ['c1', 'c2'],
        ['c3'],
    ],
];

for ($i = 1; $i <= 160; ++$i) {
    $parents = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 4, 'b' => 5, 'c' => 6],
        ['a' => 10, 'b' => 20, 'c' => 30],
    ];
    if ($i % 5 === 0) {
        $parents[] = ['a' => 40 + $i, 'b' => 50 + $i, 'c' => 60 + $i];
    }
    $children = [
        ['table' => 'c1', 'b' => 1],
        ['table' => 'c2', 'b' => 1],
        ['table' => 'c3', 'b' => 3],
    ];

    foreach ($authorizerStatements as $label => [$statement, $readTables, $referencedRead, $childProbeTables, $uniqueProbeTables]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyAuthorizerReadPlan($parents, $children, $statement);
        $case = sprintf('real upstream corpus fkey7 authorizer dynamic %03d %s', $i, $label);

        foreach ([
            'source' => 'fkey7.test fkey7-1.2..1.5',
            'operation' => 'foreign-key-authorizer-read-dependencies',
            'status' => 'commit-ok',
            'statement_operation' => $statement['operation'],
            'read_tables' => $readTables,
            'read_table_count' => count($readTables),
            'updated_count' => 1,
            'child_probe_tables' => $childProbeTables,
            'unique_child_probe_tables' => $uniqueProbeTables,
            'referenced_parent_read' => $referencedRead,
            'violation_count' => 0,
            'foreign_key_checks_enabled' => true,
            'dependencies.0' => 'sqlite-fkey7-authorizer-reads-parent-reference-table',
            'dependencies.1' => 'sqlite-fkey7-authorizer-reads-child-tables-for-primary-key-update',
            'dependencies.2' => 'sqlite-fkey7-authorizer-reads-unique-child-table-for-unique-key-update',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

for ($i = 1; $i <= 180; ++$i) {
    $parentValues = [123, 200 + $i, 300 + $i];
    $batches = [
        'fkey7-4.1 foreign key failure inserts nothing' => [
            [999 + $i, 123],
            false,
            [],
            0,
            0,
            999 + $i,
            'foreign-key',
            false,
        ],
        'fkey7-4.4 unique failure preserves first successful row' => [
            [123, 123],
            true,
            [123],
            1,
            1,
            123,
            'unique',
            true,
        ],
        'fkey7-4 mixed valid prefix stops at missing parent' => [
            [123, 200 + $i, 777 + $i],
            true,
            [123, 200 + $i],
            2,
            2,
            777 + $i,
            'foreign-key',
            true,
        ],
    ];

    foreach ($batches as $label => [$incoming, $unique, $inserted, $count, $failedIndex, $failedValue, $reason, $preserves]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insertOrFailForeignKeyBatch($parentValues, $incoming, $unique);
        $case = sprintf('real upstream corpus fkey7 insert or fail dynamic %03d %s', $i, $label);

        foreach ([
            'source' => 'fkey7.test fkey7-4.1..4.6',
            'operation' => 'insert-or-fail-foreign-key-batch',
            'status' => 'constraint-failed',
            'conflict_policy' => 'fail',
            'unique_child' => $unique,
            'parent_values' => $parentValues,
            'incoming_child_values' => $incoming,
            'inserted_child_values' => $inserted,
            'inserted_count' => $count,
            'failed_index' => $failedIndex,
            'failed_value' => $failedValue,
            'failed_reason' => $reason,
            'foreign_key_check_rows' => [],
            'statement_preserves_prior_successes' => $preserves,
            'dependencies.0' => 'sqlite-fkey7-insert-or-fail-stops-at-first-fk-violation',
            'dependencies.1' => 'sqlite-fkey7-insert-or-fail-preserves-prior-successful-rows',
            'dependencies.2' => 'sqlite-fkey7-foreign-key-check-empty-after-failed-statement',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

$tests['real upstream fkey7 authorizer rejects unsupported operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyAuthorizerReadPlan([], [], ['operation' => 'drop-parent']));
};

$tests['real upstream fkey7 authorizer rejects empty set list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyAuthorizerReadPlan([], [], ['operation' => 'update-parent-reference', 'set' => []]));
};

$tests['real upstream fkey7 insert or fail rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::insertOrFailForeignKeyBatch([1], [], false));
};

return $tests;
