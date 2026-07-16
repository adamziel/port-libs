<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test
 *
 * This ports select3.test's core GROUP BY behavior into dynamic PHP cases:
 * grouped count output ordered by grouping key and GROUP BY aliases ordered by
 * aggregate count. Multi-aggregate SELECT-list and HAVING alias cases are left
 * to a separate executor-support slice because the current row-array executor
 * intentionally accepts only one aggregate value expression per grouped query.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select3Flat = static function (array $rows): array {
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
$select3Assert = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($select3Flat): void {
    $actual = $select3Flat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' result');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint',
    );
};

/**
 * @return array{application_rows:list<array<string,mixed>>}
 */
$select3Tables = static function (int $seed): array {
    $rows = [];
    $count = 40 + ($seed % 17);
    $offset = $seed % 11;
    for ($id = 1; $id <= $count; $id++) {
        $bucket = 0;
        while ((1 << $bucket) < ($id + $offset)) {
            $bucket++;
        }
        $rows[] = [
            'n' => $id + $offset,
            'log' => $bucket,
            'weight' => (($id * 7) + $seed) % 13,
        ];
    }

    return ['application_rows' => $rows];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return array<int,array{count:int,min_n:int,min_log:int,max_n:int,max_log:int,sum_n:int,sum_log:int,avg_n:float,avg_log:float,sum_weight:int,max_expr:int}>
 */
$select3Stats = static function (array $rows): array {
    $stats = [];
    foreach ($rows as $row) {
        $log = (int) $row['log'];
        if (!isset($stats[$log])) {
            $stats[$log] = [
                'n' => [],
                'log' => [],
                'weight' => [],
            ];
        }
        $stats[$log]['n'][] = (int) $row['n'];
        $stats[$log]['log'][] = $log;
        $stats[$log]['weight'][] = (int) $row['weight'];
    }

    $result = [];
    foreach ($stats as $log => $values) {
        $result[$log] = [
            'count' => count($values['n']),
            'min_n' => min($values['n']),
            'min_log' => min($values['log']),
            'max_n' => max($values['n']),
            'max_log' => max($values['log']),
            'sum_n' => array_sum($values['n']),
            'sum_log' => array_sum($values['log']),
            'avg_n' => array_sum($values['n']) / count($values['n']),
            'avg_log' => array_sum($values['log']) / count($values['log']),
            'sum_weight' => array_sum($values['weight']),
            'max_expr' => max(array_map(static fn (int $n): int => $n + ($log * 2), $values['n'])),
        ];
    }
    ksort($result);

    return $result;
};

$tests = [];

$tests['real upstream select3.test cites aggregate group source sections'] = static function (TestRunner $t): void {
    $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test';

    $t->true(is_file($path), 'hydrated upstream select3.test exists');
    $source = file_get_contents($path);
    $t->true(is_string($source), 'hydrated upstream select3.test is readable');
    $t->contains('do_test select3-1.2', $source);
    $t->contains('do_test select3-2.6', $source);
    $t->contains('do_test select3-4.4', $source);
    $t->contains('do_test select3-5.1', $source);
};

for ($seed = 0; $seed < 500; $seed++) {
    $tables = $select3Tables($seed);
    $rows = $tables['application_rows'];
    $stats = $select3Stats($rows);

    $distinctLogs = array_keys($stats);
    sort($distinctLogs);

    $groupExpected = [];
    foreach ($stats as $log => $stat) {
        array_push($groupExpected, $log, $stat['count']);
    }

    $aliasExpectedRows = [];
    foreach ($stats as $log => $stat) {
        $aliasExpectedRows[] = ['x' => ($log * 2) + 1, 'count' => $stat['count']];
    }
    usort($aliasExpectedRows, static fn (array $left, array $right): int => ($left['count'] <=> $right['count']) ?: ($left['x'] <=> $right['x']));
    $aliasExpected = [];
    foreach ($aliasExpectedRows as $row) {
        array_push($aliasExpected, $row['x'], $row['count']);
    }

    $tests[sprintf('real upstream select3.test grouped count ordered seed %04d', $seed)] = static function (TestRunner $t) use ($select3Assert, $tables, $groupExpected, $seed): void {
        $select3Assert(
            $t,
            'SELECT log, count(*) FROM application_rows GROUP BY log ORDER BY log',
            $tables,
            $groupExpected,
            'select3-2.1 grouped count seed ' . $seed,
        );
    };

    $tests[sprintf('real upstream select3.test alias group order count seed %04d', $seed)] = static function (TestRunner $t) use ($select3Assert, $tables, $aliasExpected, $seed): void {
        $select3Assert(
            $t,
            'SELECT log*2+1 AS x, count(*) AS y FROM application_rows GROUP BY x ORDER BY y, x',
            $tables,
            $aliasExpected,
            'select3-2.7 alias group order seed ' . $seed,
        );
    };
}

return $tests;
