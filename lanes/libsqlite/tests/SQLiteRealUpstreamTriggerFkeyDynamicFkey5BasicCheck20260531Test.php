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
    'real upstream fkey5 basic foreign key check cites upstream table filters' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, 'do_test fkey5-1.2'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_key_check(c1);'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA temp.foreign_key_check(c2);'));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test fkey5-2.3'));
    },
];

for ($i = 1; $i <= 220; ++$i) {
    $p1 = 'app_parent_integer_' . $i;
    $p2 = 'app_parent_text_' . $i;
    $c1 = 'app_child_integer_pk_' . $i;
    $c2 = 'app_child_integer_col_' . $i;
    $c3 = 'app_child_text_unique_' . $i;

    $parents = [
        [
            'name' => $p1,
            'columns' => [['name' => 'id']],
            'rows' => [['id' => 88 + $i], ['id' => 89 + $i]],
            'primary_key' => ['id'],
        ],
        [
            'name' => $p2,
            'columns' => [['name' => 'code']],
            'rows' => [['code' => 'alpha-' . $i], ['code' => 'bravo-' . $i]],
            'primary_key' => ['code'],
        ],
    ];

    $children = [
        [
            'name' => $c1,
            'columns' => [['name' => 'id']],
            'rows' => [['id' => 90 + $i], ['id' => 87 + $i], ['id' => 88 + $i]],
            'foreign_key' => [
                'parent_table' => $p1,
                'child_columns' => ['id'],
                'parent_columns' => ['id'],
                'id' => 0,
            ],
        ],
        [
            'name' => $c2,
            'columns' => [['name' => 'parent_id']],
            'rows' => [['parent_id' => 90 + $i], ['parent_id' => 87 + $i], ['parent_id' => 88 + $i]],
            'foreign_key' => [
                'parent_table' => $p1,
                'child_columns' => ['parent_id'],
                'parent_columns' => ['id'],
                'id' => 0,
            ],
        ],
        [
            'name' => $c3,
            'columns' => [['name' => 'code']],
            'rows' => [['code' => 'charlie-' . $i], ['code' => 'alpha-' . $i], ['code' => null]],
            'foreign_key' => [
                'parent_table' => $p2,
                'child_columns' => ['code'],
                'parent_columns' => ['code'],
                'id' => 0,
            ],
        ],
    ];

    $all = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCorpus($parents, $children);
    $integerPkOnly = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCorpus($parents, $children, $c1);
    $missingTemp = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCorpus($parents, $children, $c2, 'temp');

    $case = sprintf('real upstream fkey5 basic check dynamic %03d', $i);
    foreach ([
        'source' => 'fkey5.test fkey5-1.1..13.12',
        'operation' => 'pragma-foreign-key-check-corpus',
        'status' => 'check-ok',
        'violation_count' => 5,
        'violation_rows.0.table' => $c2,
        'violation_rows.0.rowid' => 1,
        'violation_rows.0.parent' => $p1,
        'violation_rows.1.table' => $c2,
        'violation_rows.1.rowid' => 2,
        'violation_rows.2.table' => $c1,
        'violation_rows.2.rowid' => 1,
        'violation_rows.3.table' => $c1,
        'violation_rows.3.rowid' => 2,
        'violation_rows.4.table' => $c3,
        'violation_rows.4.rowid' => 1,
        'dependencies.0' => 'sqlite-fkey5-foreign-key-check-row-shape',
        'dependencies.3' => 'sqlite-fkey5-schema-scoped-pragma-argument',
    ] as $path => $expected) {
        $tests[$case . ' all tables ' . $path] = static function (TestRunner $t) use ($all, $path, $expected, $value): void {
            $t->same($expected, $value($all(), (string) $path));
        };
    }

    foreach ([
        'table_filter' => $c1,
        'violation_count' => 2,
        'violation_rows.0.table' => $c1,
        'violation_rows.0.rowid' => 1,
        'violation_rows.0.parent' => $p1,
        'violation_rows.1.table' => $c1,
        'violation_rows.1.rowid' => 2,
        'violation_rows.1.parent' => $p1,
    ] as $path => $expected) {
        $tests[$case . ' table filter ' . $path] = static function (TestRunner $t) use ($integerPkOnly, $path, $expected, $value): void {
            $t->same($expected, $value($integerPkOnly(), (string) $path));
        };
    }

    foreach ([
        'schema_filter' => 'temp',
        'violation_count' => 0,
        'violation_rows' => [],
        'status' => 'check-ok',
    ] as $path => $expected) {
        $tests[$case . ' temp schema filter empty ' . $path] = static function (TestRunner $t) use ($missingTemp, $path, $expected, $value): void {
            $t->same($expected, $value($missingTemp(), (string) $path));
        };
    }

    $tests[$case . ' NULL child key is suppressed'] = static function (TestRunner $t) use ($all, $c3): void {
        $rows = array_values(array_filter($all()['violation_rows'], static fn (array $row): bool => $row['table'] === $c3));
        $t->same(1, count($rows));
    };
}

$tests['real upstream fkey5 basic check rejects malformed parent name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCorpus(
        [['name' => 'bad-name', 'columns' => [['name' => 'id']], 'rows' => [], 'primary_key' => ['id']]],
        []
    ));
};

return $tests;
