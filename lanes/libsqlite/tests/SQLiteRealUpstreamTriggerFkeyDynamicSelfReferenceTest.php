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
    'real upstream fkey3 self reference cites composite insert block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey3.test');
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 3.1.1'));
        $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY(c, d) REFERENCES t3(a, b)'));
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO t3 VALUES(1, 2, 1, 2)'));
    },
    'real upstream fkey3 self reference cites null primary key block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey3.test');
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 3.3.1'));
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO t5 VALUES(NULL, 1)'));
    },
    'real upstream fkey3 self reference cites reversed unique index block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey3.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE UNIQUE INDEX t6i ON t6(b, a)'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE t8 SET d = 2'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE TestTable SET parent_id=1000 where id=2'));
    },
];

for ($i = 1; $i <= 140; ++$i) {
    $base = $i * 10;
    $rows = [
        ['id' => 1, 'a' => $base + 1, 'b' => 'key-' . $i, 'c' => $base + 1, 'd' => 'key-' . $i],
        ['id' => 2, 'a' => $base + 2, 'b' => 'parent-' . $i, 'c' => $base + 1, 'd' => 'key-' . $i],
    ];
    $validSelf = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferencingCompositeForeignKeyPlan(
        $rows,
        ['mode' => 'insert', 'row' => ['id' => 3, 'a' => $base + 3, 'b' => 'self-' . $i, 'c' => $base + 3, 'd' => 'self-' . $i]]
    );
    $nullShortCircuit = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferencingCompositeForeignKeyPlan(
        $rows,
        ['mode' => 'insert', 'row' => ['id' => 4, 'a' => null, 'b' => 'ignored-' . $i, 'c' => null, 'd' => 'missing-' . $i]]
    );
    $invalidInsert = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferencingCompositeForeignKeyPlan(
        $rows,
        ['mode' => 'insert', 'row' => ['id' => 5, 'a' => null, 'b' => 'child-' . $i, 'c' => $base + 99, 'd' => 'missing-' . $i]]
    );

    $case = 'real upstream fkey3 self referencing insert dynamic ' . $i;
    foreach ([
        'source' => 'fkey3.test fkey3-3.1.1..3.6.5',
        'operation' => 'self-referencing-composite-foreign-key',
        'status' => 'commit-ok',
        'mode' => 'insert',
        'parent_columns' => ['a', 'b'],
        'child_columns' => ['c', 'd'],
        'self_match_count' => 2,
        'violation_count' => 0,
        'statement_rolled_back' => false,
        'dependencies.0' => 'sqlite-fkey3-self-reference-insert-can-match-new-row',
    ] as $path => $expected) {
        $tests[$case . ' self row ' . $path] = static function (TestRunner $t) use ($validSelf, $path, $expected, $value): void {
            $t->same($expected, $value($validSelf(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'null_child_key_short_circuit_count' => 1,
        'violation_count' => 0,
        'committed_rows.2.id' => 4,
        'committed_rows.2.a' => null,
        'dependencies.1' => 'sqlite-fkey3-composite-child-null-short-circuits-check',
    ] as $path => $expected) {
        $tests[$case . ' null child short circuit ' . $path] = static function (TestRunner $t) use ($nullShortCircuit, $path, $expected, $value): void {
            $t->same($expected, $value($nullShortCircuit(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'violation_count' => 1,
        'violations.0.child_key' => [$base + 99, 'missing-' . $i],
        'violations.0.reason' => 'missing-self-referencing-composite-parent',
        'committed_rows.0.id' => 1,
        'committed_rows.1.id' => 2,
        'statement_rolled_back' => true,
        'dependencies.3' => 'sqlite-fkey3-failed-update-or-insert-rolls-back-statement',
    ] as $path => $expected) {
        $tests[$case . ' missing parent rolls back ' . $path] = static function (TestRunner $t) use ($invalidInsert, $path, $expected, $value): void {
            $t->same($expected, $value($invalidInsert(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 140; ++$i) {
    $base = $i * 100;
    $rows = [
        ['id' => 1, 'a' => $base + 1, 'b' => 'a', 'c' => $base + 1, 'd' => 'a', 'payload' => 'root'],
        ['id' => 2, 'a' => $base + 2, 'b' => 'a', 'c' => $base + 1, 'd' => 'a', 'payload' => 'child'],
        ['id' => 3, 'a' => $base + 3, 'b' => 'b', 'c' => $base + 2, 'd' => 'a', 'payload' => 'grandchild'],
    ];
    $validUpdate = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferencingCompositeForeignKeyPlan(
        $rows,
        [
            'mode' => 'update',
            'where' => ['id' => 3],
            'set' => ['c' => $base + 1, 'd' => 'a'],
            'unique_index_columns' => ['b', 'a'],
        ]
    );
    $invalidUpdate = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferencingCompositeForeignKeyPlan(
        $rows,
        [
            'mode' => 'update',
            'where' => ['id' => 3],
            'set' => ['c' => $base + 65, 'd' => 'a'],
            'unique_index_columns' => ['b', 'a'],
        ]
    );
    $validDelete = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferencingCompositeForeignKeyPlan(
        $rows,
        [
            'mode' => 'delete',
            'where' => ['id' => 3],
            'unique_index_columns' => ['b', 'a'],
        ]
    );

    $case = 'real upstream fkey3 reversed index update delete dynamic ' . $i;
    foreach ([
        'status' => 'commit-ok',
        'mode' => 'update',
        'unique_index_columns' => ['b', 'a'],
        'unique_index_column_order_differs' => true,
        'attempted_rows.2.c' => $base + 1,
        'attempted_rows.2.d' => 'a',
        'violation_count' => 0,
        'dependencies.2' => 'sqlite-fkey3-parent-lookup-uses-declared-column-order',
    ] as $path => $expected) {
        $tests[$case . ' declared column order valid update ' . $path] = static function (TestRunner $t) use ($validUpdate, $path, $expected, $value): void {
            $t->same($expected, $value($validUpdate(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'mode' => 'update',
        'affected_count' => 1,
        'violation_count' => 1,
        'violations.0.child_key' => [$base + 65, 'a'],
        'committed_rows.2.c' => $base + 2,
        'statement_rolled_back' => true,
    ] as $path => $expected) {
        $tests[$case . ' declared column order invalid update ' . $path] = static function (TestRunner $t) use ($invalidUpdate, $path, $expected, $value): void {
            $t->same($expected, $value($invalidUpdate(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'mode' => 'delete',
        'affected_count' => 1,
        'attempted_rows.0.id' => 1,
        'attempted_rows.1.id' => 2,
        'violation_count' => 0,
        'statement_rolled_back' => false,
    ] as $path => $expected) {
        $tests[$case . ' leaf delete keeps graph valid ' . $path] = static function (TestRunner $t) use ($validDelete, $path, $expected, $value): void {
            $t->same($expected, $value($validDelete(), (string) $path));
        };
    }
}

$tests['real upstream fkey3 self reference rejects malformed mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::selfReferencingCompositeForeignKeyPlan([], ['mode' => 'merge']));
};

$tests['real upstream fkey3 self reference rejects key width mismatch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::selfReferencingCompositeForeignKeyPlan([], [
        'mode' => 'insert',
        'row' => ['a' => 1, 'b' => 2, 'c' => 1],
        'parent_columns' => ['a', 'b'],
        'child_columns' => ['c'],
    ]));
};

return $tests;
