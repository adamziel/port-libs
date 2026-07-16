<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

/**
 * @param array<string,mixed> $array
 */
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
    'real upstream trigger2 view insert subset cites generated column regression' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test trigger2-10.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE VIEW v2(a,b,c,d) AS SELECT * FROM t1'));
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO v2(a,d) VALUES(11,14)'));
        $t->true(is_string($source) && str_contains($source, 'SELECT * FROM t1;'));
    },
];

$columns = ['a', 'b', 'c', 'd'];

for ($i = 1; $i <= 120; ++$i) {
    $a = 10 + $i;
    $d = 40 + ($i * 3);
    $middle = $i % 4 === 0 ? ['b' => 'b' . $i] : [];
    $tail = $i % 6 === 0 ? ['c' => 'c' . $i] : [];
    $firstInsert = ['a' => $a, 'd' => $d] + $middle + $tail;
    $secondInsert = $i % 5 === 0
        ? ['b' => 'only-b-' . $i]
        : ['a' => $a + 1000, 'b' => 'b2-' . $i, 'c' => null, 'd' => $d + 1000];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewInsertColumnSubset(
        $columns,
        [$firstInsert, $secondInsert]
    );
    $case = 'real upstream trigger2-10 view insert subset dynamic ' . $i;
    $firstExpected = [
        'a' => $a,
        'b' => $middle['b'] ?? null,
        'c' => $tail['c'] ?? null,
        'd' => $d,
    ];
    $secondExpected = [
        'a' => $secondInsert['a'] ?? null,
        'b' => $secondInsert['b'] ?? null,
        'c' => $secondInsert['c'] ?? null,
        'd' => $secondInsert['d'] ?? null,
    ];

    foreach ([
        'source' => 'trigger2.test trigger2-10.1',
        'operation' => 'instead-of-view-insert-column-subset',
        'status' => 'commit-ok',
        'view_columns' => $columns,
        'insert_count' => 2,
        'base_rows.0' => $firstExpected,
        'base_rows.1' => $secondExpected,
        'trigger_rows.0.new_row' => $firstExpected,
        'trigger_rows.1.new_row' => $secondExpected,
        'dependencies.0' => 'sqlite-trigger2-instead-of-insert-view-column-subset',
        'dependencies.1' => 'sqlite-trigger2-omitted-view-columns-are-null-in-new-row',
        'dependencies.2' => 'sqlite-trigger2-trigger-body-uses-new-column-values-for-base-insert',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' omitted columns are tracked in trigger row'] = static function (TestRunner $t) use ($plan, $middle, $tail): void {
        $omitted = $plan()['trigger_rows'][0]['omitted_columns'];
        $t->same(!array_key_exists('b', $middle), in_array('b', $omitted, true));
        $t->same(!array_key_exists('c', $tail), in_array('c', $omitted, true));
    };

    $tests[$case . ' first and last row aliases are stable'] = static function (TestRunner $t) use ($plan, $firstExpected, $secondExpected): void {
        $actual = $plan();
        $t->same($firstExpected, $actual['first_base_row']);
        $t->same($secondExpected, $actual['last_base_row']);
    };
}

$tests['real upstream trigger2 view insert subset rejects empty column list'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewInsertColumnSubset([], [['a' => 1]])
    );
};

$tests['real upstream trigger2 view insert subset rejects malformed column'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewInsertColumnSubset(['a', 'bad-name'], [['a' => 1]])
    );
};

return $tests;
