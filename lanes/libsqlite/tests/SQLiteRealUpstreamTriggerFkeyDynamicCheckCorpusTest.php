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
    'real upstream fkey5 foreign key check cites base result rows' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_key_check;'));
        $t->true(is_string($source) && str_contains($source, 'EVIDENCE-OF: R-45728-08709'));
        $t->true(is_string($source) && str_contains($source, 'EVIDENCE-OF: R-00471-55166'));
    },
    'real upstream fkey5 foreign key check cites collation matrix' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, "INSERT INTO c19 VALUES('alpha','abc')"));
        $t->true(is_string($source) && str_contains($source, "INSERT INTO c21 VALUES('alpha','abc    ')"));
        $t->true(is_string($source) && str_contains($source, 'do_test fkey5-8.7'));
    },
    'real upstream fkey5 foreign key check cites without rowid and vtab args' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, 'WITHOUT ROWID'));
        $t->true(is_string($source) && str_contains($source, "SELECT *, 'x' FROM pragma_foreign_key_check('t1','aux')"));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 13.12'));
    },
];

for ($i = 1; $i <= 180; ++$i) {
    $parents = [
        ['b' => 'Alpha-' . $i, 'c' => 'abc'],
        ['b' => 'beta-' . $i, 'c' => 'def    '],
    ];

    $binaryChild = [
        ['x' => 'alpha-' . $i, 'y' => 'abc'],
        ['x' => 'Alpha-' . $i, 'y' => 'abc'],
        ['x' => null, 'y' => 'abc'],
    ];
    $binaryPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCollationPlan(
        'c19',
        'p5',
        $parents,
        $binaryChild,
        [
            ['parent' => 'b', 'child' => 'x', 'parent_collation' => 'binary'],
            ['parent' => 'c', 'child' => 'y', 'parent_collation' => 'binary'],
        ]
    );

    foreach ([
        'source' => 'fkey5.test fkey5-5.0..13.12',
        'operation' => 'foreign-key-check-parent-collation-composite',
        'child_table' => 'c19',
        'parent_table' => 'p5',
        'without_rowid_child' => false,
        'violation_count' => 1,
        'result_columns' => ['table', 'rowid', 'parent', 'fkid'],
        'result_tuples.0' => ['c19', 1, 'p5', 0],
        'violation_rows.0.child_key' => ['alpha-' . $i, 'abc'],
        'null_child_key_suppressed' => true,
        'dependencies.0' => 'sqlite-fkey5-foreign-key-check-four-column-result',
    ] as $path => $expected) {
        $tests['fkey5-8.0 binary composite check dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($binaryPlan, $path, $expected, $value): void {
            $t->same($expected, $value($binaryPlan(), (string) $path));
        };
    }

    $nocaseRtrimChild = [
        ['x' => 'alpha-' . $i, 'y' => 'abc    '],
        ['x' => 'BETA-' . $i, 'y' => 'def'],
        ['x' => 'gamma-' . $i, 'y' => 'zzz'],
    ];
    $nocaseRtrimPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCollationPlan(
        'c21',
        'p6',
        $parents,
        $nocaseRtrimChild,
        [
            ['parent' => 'b', 'child' => 'x', 'parent_collation' => 'nocase'],
            ['parent' => 'c', 'child' => 'y', 'parent_collation' => 'rtrim'],
        ],
        false,
        'main'
    );

    foreach ([
        'schema' => 'main',
        'child_table' => 'c21',
        'parent_table' => 'p6',
        'violation_count' => 1,
        'result_tuples.0' => ['c21', 3, 'p6', 0],
        'violation_rows.0.child_key' => ['gamma-' . $i, 'zzz'],
        'key_columns.0.parent_collation' => 'nocase',
        'key_columns.1.parent_collation' => 'rtrim',
        'dependencies.1' => 'sqlite-fkey5-parent-collation-controls-child-comparison',
        'dependencies.2' => 'sqlite-fkey5-composite-key-column-order',
    ] as $path => $expected) {
        $tests['fkey5-8.4 nocase rtrim composite check dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($nocaseRtrimPlan, $path, $expected, $value): void {
            $t->same($expected, $value($nocaseRtrimPlan(), (string) $path));
        };
    }

    $withoutRowidPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCollationPlan(
        'c30',
        'p30',
        [['id' => $i]],
        [
            ['master' => $i, 'line' => 999],
            ['master' => $i + 1000, 'line' => 45],
        ],
        [['parent' => 'id', 'child' => 'master', 'parent_collation' => 'binary']],
        true,
        'aux'
    );

    foreach ([
        'schema' => 'aux',
        'child_table' => 'c30',
        'parent_table' => 'p30',
        'without_rowid_child' => true,
        'violation_count' => 1,
        'result_tuples.0' => ['c30', null, 'p30', 0],
        'violation_rows.0.rowid' => null,
        'violation_rows.0.child_key' => [$i + 1000],
        'dependencies.3' => 'sqlite-fkey5-without-rowid-child-reports-null-rowid',
    ] as $path => $expected) {
        $tests['fkey5-10.3 without rowid check dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($withoutRowidPlan, $path, $expected, $value): void {
            $t->same($expected, $value($withoutRowidPlan(), (string) $path));
        };
    }

    $tests['fkey5 dynamic ' . $i . ' rejects malformed child column'] = static function (TestRunner $t) use ($parents, $binaryChild): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCollationPlan(
            'c19',
            'p5',
            $parents,
            $binaryChild,
            [['parent' => 'b', 'child' => 'bad-key']]
        ));
    };
}

return $tests;
