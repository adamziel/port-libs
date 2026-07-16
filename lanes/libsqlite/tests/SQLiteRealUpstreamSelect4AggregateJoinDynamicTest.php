<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select4AggregateJoinFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @return array{0:array<string,list<array<string,mixed>>>,1:list<int>}
 */
$select4AggregateJoinFixture = static function (int $seed, int $threshold): array {
    $rows = [];
    $base = $seed * 1000;
    for ($x = 1; $x <= 100; $x++) {
        $rows[] = [
            'a' => $x % 10,
            'b' => intdiv($x, 10),
            'c' => $base + $x,
            'd' => 'dyn' . $seed . '-' . $x,
        ];
    }

    $expectedByA = [];
    foreach ($rows as $row) {
        if ($row['a'] < $threshold) {
            continue;
        }
        $a = (int) $row['a'];
        if (!isset($expectedByA[$a]) || $row['b'] > $expectedByA[$a]['b']) {
            $expectedByA[$a] = $row;
        }
    }
    ksort($expectedByA);

    return [
        ['stream_items' => $rows],
        array_map(static fn (array $row): int => (int) $row['c'], array_values($expectedByA)),
    ];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<int> $expected
 */
$select4AggregateJoinAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($select4AggregateJoinFlat): void {
    $actual = $select4AggregateJoinFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' row count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' first and last row',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
};

$tests['real upstream select4.test select4-16 aggregate subquery joins cite source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test';

    $t->true(is_file($source), 'hydrated upstream select4.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream select4.test is readable');
    $t->contains('do_execsql_test select4-16.1', $text);
    $t->contains('do_execsql_test select4-16.2', $text);
    $t->contains('do_execsql_test select4-16.3', $text);
    $t->contains('Use a co-routine for subqueries if the', $text);
};

$tests['real upstream select4.test select4-16 canonical aggregate subquery join rows'] =
    static function (TestRunner $t) use ($select4AggregateJoinFixture, $select4AggregateJoinAssert): void {
        [$tables, $expected] = $select4AggregateJoinFixture(0, 5);
        $sql = 'SELECT t3.c FROM '
            . '(SELECT a,max(b) AS m FROM stream_items WHERE a>=5 GROUP BY a) AS t2 '
            . 'JOIN stream_items AS t3 '
            . 'WHERE t2.a=t3.a AND t2.m=t3.b '
            . 'ORDER BY t3.a';

        $select4AggregateJoinAssert($t, $sql, $tables, $expected, 'select4-16.1 canonical');
        $t->same([95, 96, 97, 98, 99], $expected, 'canonical upstream select4-16 result shape');
    };

$joinOperators = [
    'JOIN',
    'CROSS JOIN',
    'LEFT JOIN',
];

for ($seed = 0; $seed < 1200; $seed++) {
    $threshold = $seed % 11;
    $joinOperator = $joinOperators[$seed % count($joinOperators)];
    [$tables, $expected] = $select4AggregateJoinFixture($seed + 1, $threshold);
    $sql = 'SELECT t3.c FROM '
        . "(SELECT a,max(b) AS m FROM stream_items WHERE a>={$threshold} GROUP BY a) AS t2 "
        . "{$joinOperator} stream_items AS t3 "
        . 'WHERE t2.a=t3.a AND t2.m=t3.b '
        . 'ORDER BY t3.a';

    $tests[sprintf('real upstream select4.test select4-16 dynamic aggregate subquery %s seed %04d', strtolower(str_replace(' ', '-', $joinOperator)), $seed)] =
        static function (TestRunner $t) use ($select4AggregateJoinAssert, $sql, $tables, $expected, $joinOperator, $threshold): void {
            $select4AggregateJoinAssert($t, $sql, $tables, $expected, 'select4-16 ' . $joinOperator);
            $t->true($threshold >= 0 && $threshold <= 10, 'threshold stays inside generated a-domain');
        };
}

return $tests;
