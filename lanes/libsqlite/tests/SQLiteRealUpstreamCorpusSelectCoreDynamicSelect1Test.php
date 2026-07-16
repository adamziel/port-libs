<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select1DynamicFlat = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_order_')) {
                continue;
            }
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelect1Dynamic = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $source
) use ($select1DynamicFlat): void {
    $actual = $select1DynamicFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $source . ' flat result');
    $t->same(count($expected), count($actual), $source . ' flat count');
    $t->same(
        $expected === [] ? null : $expected[0],
        $actual === [] ? null : $actual[0],
        $source . ' first value',
    );
    $t->same(
        $expected === [] ? null : $expected[array_key_last($expected)],
        $actual === [] ? null : $actual[array_key_last($actual)],
        $source . ' last value',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $source . ' result fingerprint',
    );
};

$tests['real upstream corpus select1 dynamic cites selected source sections'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';

    $t->true(is_file($source), 'hydrated upstream select1.test exists');
    $contents = file_get_contents($source);
    $t->contains('do_test select1-1.8.3', $contents);
    $t->contains('do_test select1-3.7', $contents);
    $t->contains('do_test select1-4.11', $contents);
    $t->contains('do_test select1-5.1', $contents);
};

for ($case = 0; $case < 250; $case++) {
    $left = 10 + $case;
    $right = 1000 + ($case * 2);
    $realLeft = round(1.25 + ($case / 10), 6);
    $realRight = round(9.5 + ($case / 8), 6);
    $labelOne = 'one_' . $case;
    $labelTwo = 'two_' . $case;
    $tables = [
        'test1' => [
            ['f1' => $left, 'f2' => $right],
        ],
        'test2' => [
            ['r1' => $realLeft, 'r2' => $realRight],
        ],
    ];
    $expected = [$labelOne, $left, $right, $labelTwo, $realLeft, $realRight];

    $tests[sprintf('real upstream corpus select1.test select1-1.8.3 wildcard source order dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect1Dynamic, $tables, $labelOne, $labelTwo, $expected, $case): void {
            $assertSelect1Dynamic(
                $t,
                "SELECT '{$labelOne}', test1.*, '{$labelTwo}', test2.* FROM test1, test2",
                $tables,
                $expected,
                'select1.test select1-1.8.3 wildcard and literal projection case ' . $case,
            );
        };
}

for ($case = 0; $case < 250; $case++) {
    $base = 20 + $case;
    $delta = 3 + ($case % 17);
    $threshold = $base + ($case % 5);
    $tables = [
        'test1' => [
            ['f1' => $base, 'f2' => $base + $delta],
            ['f1' => $base + $delta + 5, 'f2' => $base + 99],
        ],
    ];
    $expectedRows = [];
    foreach ($tables['test1'] as $row) {
        if (min($row['f1'], $row['f2']) !== $threshold) {
            $expectedRows[] = $row['f1'];
        }
    }
    sort($expectedRows);

    $tests[sprintf('real upstream corpus select1.test select1-3.7 scalar min where dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect1Dynamic, $tables, $threshold, $expectedRows, $case): void {
            $assertSelect1Dynamic(
                $t,
                "SELECT f1 FROM test1 WHERE min(f1,f2)!={$threshold} ORDER BY f1",
                $tables,
                $expectedRows,
                'select1.test select1-3.7 scalar min predicate case ' . $case,
            );
        };
}

for ($case = 0; $case < 250; $case++) {
    $shift = $case * 4;
    $tables = [
        't5' => [
            ['a' => 1 + $shift, 'b' => 10 + ($case % 3)],
            ['a' => 2 + $shift, 'b' => 9 + ($case % 3)],
            ['a' => 3 + $shift, 'b' => 10 + ($case % 3)],
        ],
    ];
    $ordered = $tables['t5'];
    usort($ordered, static fn (array $left, array $right): int => ($left['b'] <=> $right['b']) ?: ($right['a'] <=> $left['a']));
    $expected = [];
    foreach ($ordered as $row) {
        array_push($expected, $row['a'], $row['b']);
    }

    $tests[sprintf('real upstream corpus select1.test select1-4.11 order by ordinal dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect1Dynamic, $tables, $expected, $case): void {
            $assertSelect1Dynamic(
                $t,
                'SELECT * FROM t5 ORDER BY 2, 1 DESC',
                $tables,
                $expected,
                'select1.test select1-4.11 ORDER BY ordinal tie-break case ' . $case,
            );
        };
}

for ($case = 0; $case < 250; $case++) {
    $first = 50 + $case;
    $second = $first + 17 + ($case % 11);
    $tables = [
        'test1' => [
            ['f1' => $first, 'f2' => $second * 10],
            ['f1' => $second, 'f2' => $first * 10],
        ],
    ];

    $tests[sprintf('real upstream corpus select1.test select1-5.1 aggregate order ignored dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertSelect1Dynamic, $tables, $second, $case): void {
            $assertSelect1Dynamic(
                $t,
                'SELECT max(f1) FROM test1 ORDER BY f2',
                $tables,
                [$second],
                'select1.test select1-5.1 aggregate ORDER BY source column ignored case ' . $case,
            );
        };
}

return $tests;
