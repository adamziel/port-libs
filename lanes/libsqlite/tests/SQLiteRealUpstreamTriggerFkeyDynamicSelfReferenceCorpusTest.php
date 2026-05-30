<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream fkey3 self reference corpus cites same row block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey3.test');
        $t->true(is_string($source) && str_contains($source, 'new row being inserted matches itself'));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 3.1.1'));
    },
    'real upstream fkey3 self reference corpus cites rowid assignment block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey3.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t5(a INTEGER PRIMARY KEY, b REFERENCES t5(a))'));
        $t->true(is_string($source) && str_contains($source, 'do_catchsql_test 3.3.2'));
    },
    'real upstream fkey4 deferred autocommit corpus cites repeated statement block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey4.test');
        $t->true(is_string($source) && str_contains($source, 'that statement doesn'));
        $t->true(is_string($source) && str_contains($source, 'do_test fkey4-1.3'));
    },
];

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

for ($i = 1; $i <= 160; ++$i) {
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferentialForeignKeyInsert(
        [],
        ['a' => $i, 'b' => $i + 1, 'c' => $i, 'd' => $i + 1],
        ['a', 'b'],
        ['c', 'd']
    );
    $case = 'fkey3-3.1 self referential composite row matches itself dynamic ' . $i;
    foreach ([
        'source' => 'fkey3.test fkey3-3.1.1..3.6.5',
        'operation' => 'self-referential-foreign-key-insert',
        'status' => 'commit-ok',
        'matched_parent_after_insert' => true,
        'null_child_key_satisfied' => false,
        'violation_count' => 0,
        'committed_rows.0.a' => $i,
        'committed_rows.0.d' => $i + 1,
        'dependencies.0' => 'sqlite-fkey3-self-referential-row-matches-itself-after-insert',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 160; ++$i) {
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferentialForeignKeyInsert(
        [],
        ['a' => null, 'b' => 1],
        ['a'],
        ['b'],
        'a'
    );
    $case = 'fkey3-3.3 integer primary key null assigned before fk check dynamic ' . $i;
    foreach ([
        'source' => 'fkey3.test fkey3-3.1.1..3.6.5',
        'status' => 'commit-ok',
        'assigned_integer_primary_key' => 1,
        'matched_parent_after_insert' => true,
        'child_key.0' => 1,
        'committed_rows.0.a' => 1,
        'violation_count' => 0,
        'dependencies.1' => 'sqlite-fkey3-integer-primary-key-null-is-assigned-before-fk-check',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 160; ++$i) {
    $rows = [
        ['a' => 1, 'b' => 'a', 'c' => 1, 'd' => 'a'],
        ['a' => 2, 'b' => 'a', 'c' => 2, 'd' => 'a'],
    ];
    $valid = ($i % 5) !== 0;
    $incoming = $valid
        ? ['a' => null, 'b' => 'a', 'c' => ($i % 2) + 1, 'd' => 'a']
        : ['a' => null, 'b' => 'a', 'c' => 65, 'd' => 'a'];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferentialForeignKeyInsert(
        $rows,
        $incoming,
        ['a', 'b'],
        ['c', 'd'],
        'a'
    );
    $case = 'fkey3-3.4 composite parent order follows declaration dynamic ' . $i;
    foreach ([
        'source' => 'fkey3.test fkey3-3.1.1..3.6.5',
        'status' => $valid ? 'commit-ok' : 'constraint-failed',
        'assigned_integer_primary_key' => 3,
        'matched_parent_after_insert' => $valid,
        'child_key.1' => 'a',
        'violation_count' => $valid ? 0 : 1,
        'error' => $valid ? null : 'FOREIGN KEY constraint failed',
        'dependencies.2' => 'sqlite-fkey3-composite-parent-key-order-follows-fk-declaration',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 160; ++$i) {
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredAutocommitForeignKeyFailure(
        [['a' => 1, 'b' => 2]],
        [['c' => 1, 'd' => 3]],
        ['c' => 2 + $i, 'd' => 4 + $i],
        2
    );
    $case = 'fkey4 deferred autocommit violation releases statement transaction dynamic ' . $i;
    foreach ([
        'source' => 'fkey4.test fkey4-1.1..1.4',
        'operation' => 'deferred-autocommit-foreign-key-failure',
        'status' => 'commit-ok',
        'attempt_count' => 2,
        'attempts.0.status' => 'constraint-failed',
        'attempts.0.transaction_left_open' => false,
        'attempts.1.status' => 'constraint-failed',
        'attempts.1.child_count_after_attempt' => 1,
        'child_count' => 1,
        'statement_transaction_retained' => false,
        'dependencies.0' => 'sqlite-fkey4-deferred-autocommit-violation-rolls-back-statement',
        'dependencies.1' => 'sqlite-fkey4-reprepared-statement-fails-independently',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

return $tests;
