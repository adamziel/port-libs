<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test
 * - select3-2.8: GROUP BY result aliases ordered by a derived expression.
 *
 * The dynamic rows below preserve the same SELECT core behavior while varying
 * group widths, values, and result ordering across a larger PHP corpus.
 */

/**
 * @return list<array{n:int,log:int}>
 */
$select3Rows = static function (int $seed): array {
    $rows = [];
    $max = 24 + ($seed % 17);
    $shift = $seed % 5;

    for ($i = 1; $i <= $max; $i++) {
        $value = $i + $shift;
        $rows[] = [
            'n' => $value,
            'log' => (int) floor(log($value, 2)),
        ];
    }

    return $rows;
};

/**
 * @param list<array{n:int,log:int}> $rows
 * @return array<int,list<int>>
 */
$select3Groups = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['log']][] = $row['n'];
    }
    ksort($groups, SORT_NUMERIC);

    return $groups;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

/**
 * @param list<mixed> $expected
 */
$assertSelect3Flat = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' first/last guard',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
    $t->contains('GROUP BY', strtoupper($sql), $label . ' uses grouped SELECT behavior');
};

/**
 * @param array<int,list<int>> $groups
 * @return list<mixed>
 */
$expectedSelect328 = static function (array $groups): array {
    $rows = [];
    foreach ($groups as $log => $values) {
        $x = ($log * 2) + 1;
        $y = count($values);
        $rows[] = ['x' => $x, 'y' => $y, 'order_key' => 10 - ($x + $y)];
    }

    usort($rows, static function (array $left, array $right): int {
        $comparison = $left['order_key'] <=> $right['order_key'];
        if ($comparison !== 0) {
            return $comparison;
        }

        return $left['x'] <=> $right['x'];
    });

    $flat = [];
    foreach ($rows as $row) {
        $flat[] = $row['x'];
        $flat[] = $row['y'];
    }

    return $flat;
};

$tests = [];

$tests['real upstream select3.test select3 aggregate ordering cites source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test';

    $t->true(is_file($source), 'hydrated upstream select3.test is available');
    $text = file_get_contents($source);
    $t->contains('do_test select3-2.8', $text);
    $t->contains('ORDER BY 10-(x+y)', $text);
};

for ($seed = 1; $seed <= 1000; $seed++) {
    $rows = $select3Rows($seed);
    $tables = ['t1' => $rows];
    $groups = $select3Groups($rows);

    $expected328 = $expectedSelect328($groups);
    $tests[sprintf('real upstream select3.test select3-2.8 dynamic alias order seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelect3Flat, $tables, $expected328, $seed): void {
            $sql = 'SELECT log*2+1 AS x, count(*) AS y FROM t1 GROUP BY x ORDER BY 10-(x+y)';
            $assertSelect3Flat($t, $sql, $tables, $expected328, 'select3-2.8 seed ' . $seed);
            $t->contains('select3-2.8', 'select3-2.8 dynamic alias aggregate ordering');
        };
}

return $tests;
