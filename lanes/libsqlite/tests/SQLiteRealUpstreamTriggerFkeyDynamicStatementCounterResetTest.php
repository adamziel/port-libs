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
    'real upstream fkey2 statement counter reset cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-2-test 61 0 "BEGIN"'));
        $t->true(is_string($source) && str_contains($source, 'catchsql          "INSERT INTO node SELECT parent, 3 FROM leaf"'));
        $t->true(is_string($source) && str_contains($source, 'fkey2-2-test 68 0 "COMMIT"           FKV'));
        $t->true(is_string($source) && str_contains($source, 'fkey2-2-test 74 0   "INSERT INTO node(nodeid) SELECT DISTINCT parent FROM leaf"'));
    },
];

for ($i = 1; $i <= 240; ++$i) {
    $base = $i * 100;
    $nodes = [
        ['nodeid' => $base + 1, 'parent' => null],
        ['nodeid' => $base + 2, 'parent' => $base + 1],
    ];
    $leaves = [
        ['id' => 'a-' . $i, 'nodeid' => $base + 1],
        ['id' => 'b-' . $i, 'nodeid' => $base + 2],
        ['id' => 'c-' . $i, 'nodeid' => $base + 1],
    ];

    $blocked = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2StatementRollbackCounterReset($nodes, $leaves, false);
    $repaired = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2StatementRollbackCounterReset($nodes, $leaves, true);
    $case = 'real upstream fkey2 deferred statement rollback counter reset dynamic ' . $i;

    foreach ([
        'source' => 'fkey2.test fkey2-2.61..2.75',
        'operation' => 'deferred-counter-reset-after-statement-rollback',
        'status' => 'commit-blocked',
        'statement_status' => 'rolled-back-on-unique-nodeid',
        'statement_rolled_back' => true,
        'deferred_before_statement' => 3,
        'deferred_after_statement' => 3,
        'counter_reset_after_rollback' => true,
        'first_commit_status' => 'commit-blocked',
        'first_commit_violation_count' => 3,
        'repair_with_distinct_parent_select' => false,
        'final_node_ids' => [],
        'final_leaf_ids' => ['a-' . $i, 'b-' . $i, 'c-' . $i],
        'final_violation_count' => 3,
        'dependencies.0' => 'sqlite-fkey2-statement-transaction-restores-deferred-counter',
        'dependencies.1' => 'sqlite-fkey2-insert-select-unique-failure-rolls-back-statement',
    ] as $path => $expected) {
        $tests[$case . ' blocked state ' . $path] = static function (TestRunner $t) use ($blocked, $path, $expected, $value): void {
            $t->same($expected, $value($blocked(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'statement_status' => 'rolled-back-on-unique-nodeid',
        'statement_rolled_back' => true,
        'deferred_before_statement' => 3,
        'deferred_after_statement' => 3,
        'counter_reset_after_rollback' => true,
        'first_commit_status' => 'commit-blocked',
        'first_commit_violation_count' => 3,
        'repair_with_distinct_parent_select' => true,
        'final_node_ids' => [$base + 1, $base + 2],
        'final_leaf_ids' => ['a-' . $i, 'b-' . $i, 'c-' . $i],
        'final_violation_count' => 0,
        'dependencies.2' => 'sqlite-fkey2-distinct-parent-repair-commits-after-counter-reset',
    ] as $path => $expected) {
        $tests[$case . ' repaired state ' . $path] = static function (TestRunner $t) use ($repaired, $path, $expected, $value): void {
            $t->same($expected, $value($repaired(), (string) $path));
        };
    }

    $tests[$case . ' rolled back statement does not leak attempted node rows'] = static function (TestRunner $t) use ($blocked): void {
        $actual = $blocked();
        $t->same([], $actual['final_node_ids']);
        $t->same([0 => 3], [$actual['deferred_after_statement']]);
        $t->same(true, $actual['counter_reset_after_rollback']);
    };
    $tests[$case . ' distinct repair keeps duplicate leaf references attached to one parent'] = static function (TestRunner $t) use ($repaired, $base): void {
        $actual = $repaired();
        $t->same([$base + 1, $base + 2], $actual['final_node_ids']);
        $t->same([$base + 1, $base + 2, $base + 1], $actual['leaf_nodeids']);
        $t->same(0, $actual['final_violation_count']);
    };
}

return $tests;
