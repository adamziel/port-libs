<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

$assertSelect = static function (TestRunner $t, string $sql, array $tables, array $expectedFlat): void {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    $t->same($expectedFlat, $flat, $sql);
    $t->same(count($expectedFlat), count($flat), 'flat value count for ' . $sql);
    $t->same($expectedFlat === [] ? [] : [$expectedFlat[0], $expectedFlat[array_key_last($expectedFlat)]], $flat === [] ? [] : [$flat[0], $flat[array_key_last($flat)]], 'first/last values for ' . $sql);
    foreach ($expectedFlat as $index => $expectedValue) {
        $t->same($expectedValue, $flat[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
    $t->same(md5(json_encode($expectedFlat, JSON_THROW_ON_ERROR)), md5(json_encode($flat, JSON_THROW_ON_ERROR)), 'flat value fingerprint for ' . $sql);
    $t->true(str_starts_with(strtolower(ltrim($sql)), 'select'), 'query is a SELECT statement');
};

$projectionTables = [
    'test1' => [
        ['f1' => 11, 'f2' => 22],
    ],
    'test2' => [
        ['r1' => 1.1, 'r2' => 2.2],
    ],
    'app_settings' => [
        ['key_name' => 'alpha', 'key_value' => 'on', 'priority' => 2],
    ],
];

$projectionCases = [
    'select1.test select1-1.4 single integer column' => ['SELECT f1 FROM test1', [11]],
    'select1.test select1-1.5 second integer column' => ['SELECT f2 FROM test1', [22]],
    'select1.test select1-1.6 reversed column order' => ['SELECT f2, f1 FROM test1', [22, 11]],
    'select1.test select1-1.7 declared column order' => ['SELECT f1, f2 FROM test1', [11, 22]],
    'select1.test select1-1.8 star projection' => ['SELECT * FROM test1', [11, 22]],
    'select1.test select1-1.9 cross product star' => ['SELECT * FROM test1, test2', [11, 22, 1.1, 2.2]],
    'select1.test select1-1.9.1 cross product literal tail' => ["SELECT *, 'hi' FROM test1, test2", [11, 22, 1.1, 2.2, 'hi']],
    'select1.test select1-1.10 qualified projection in declared order' => ['SELECT test1.f1, test2.r1 FROM test1, test2', [11, 1.1]],
    'select1.test select1-1.11 qualified projection with reversed FROM' => ['SELECT test1.f1, test2.r1 FROM test2, test1', [11, 1.1]],
    'select1.test select1-1.11.1 reversed FROM star order' => ['SELECT * FROM test2, test1', [1.1, 2.2, 11, 22]],
    'select1.test select1-1.12 scalar max min across joined row' => ['SELECT max(test1.f1,test2.r1), min(test1.f2,test2.r2) FROM test2, test1', [11, 2.2]],
    'select1.test select1-1.13 scalar min max across joined row' => ['SELECT min(test1.f1,test2.r1), max(test1.f2,test2.r2) FROM test1, test2', [1.1, 22]],
    'select1.test select1-1.8 application key projection' => ['SELECT key_name, key_value FROM app_settings', ['alpha', 'on']],
    'select1.test select1-1.8 application star projection' => ['SELECT * FROM app_settings', ['alpha', 'on', 2]],
    'select1.test select1-1.10 application projection preserves key and priority' => ['SELECT key_name, priority FROM app_settings', ['alpha', 2]],
];

$addCases = static function (array &$tests, string $prefix, array $cases, array $tables) use ($assertSelect): void {
    foreach ($cases as $name => [$sql, $expected]) {
        $tests[$prefix . ' ' . $name] = static function (TestRunner $t) use ($sql, $expected, $tables, $assertSelect, $name): void {
            $assertSelect($t, $sql, $tables, $expected);
            $t->contains(substr($name, 0, strpos($name, ' ') ?: strlen($name)), $name);
        };
    }
};

$tests['real upstream corpus select core dynamic select1 projection and join group'] = static function (TestRunner $t) use ($projectionCases, $projectionTables, $assertSelect): void {
    foreach ($projectionCases as $name => [$sql, $expected]) {
        $assertSelect($t, $sql, $projectionTables, $expected);
        $t->contains('select1.test', $name);
    }
};

$addCases($tests, 'real upstream corpus select core dynamic', $projectionCases, $projectionTables);

$test1Rows = [
    ['f1' => 11, 'f2' => 22],
    ['f1' => 33, 'f2' => 44],
];
$t3Rows = [
    ['a' => 'abc', 'b' => null],
    ['a' => null, 'b' => 'xyz'],
    ['a' => 11, 'b' => 22],
    ['a' => 33, 'b' => 44],
];
$aggregateTables = [
    'test1' => $test1Rows,
    't3' => $t3Rows,
    't4' => [
        ['a' => null, 'b' => 'This is a string that is too big to fit inside a NBFS buffer'],
    ],
    'app_metrics' => [
        ['tenant_id' => 1, 'metric' => 'hits', 'value' => 3],
        ['tenant_id' => 1, 'metric' => 'misses', 'value' => 2],
        ['tenant_id' => 2, 'metric' => 'hits', 'value' => 7],
    ],
];

$aggregateCases = [
    'select1.test select1-2.2 count one column' => ['SELECT count(f1) FROM test1', [2]],
    'select1.test select1-2.4 count star' => ['SELECT COUNT(*) FROM test1', [2]],
    'select1.test select1-2.5 count star expression' => ['SELECT COUNT(*)+1 FROM test1', [3]],
    'select1.test select1-2.7 min aggregate' => ['SELECT Min(f1) FROM test1', [11]],
    'select1.test select1-2.8 scalar min per row' => ['SELECT MIN(f1,f2) FROM test1', [11, 33]],
    'select1.test select1-2.10 max aggregate' => ['SELECT Max(f1) FROM test1', [33]],
    'select1.test select1-2.11 scalar max per row' => ['SELECT max(f1,f2) FROM test1', [22, 44]],
    'select1.test select1-2.12 scalar max expression per row' => ['SELECT MAX(f1,f2)+1 FROM test1', [23, 45]],
    'select1.test select1-2.13 max aggregate expression' => ['SELECT MAX(f1)+1 FROM test1', [34]],
    'select1.test select1-2.15 sum aggregate' => ['SELECT Sum(f1) FROM test1', [44]],
    'select1.test select1-2.17 sum aggregate expression' => ['SELECT SUM(f1)+1 FROM test1', [45]],
    'select1.test select1-2.5.2 count nullable column table' => ['SELECT count(*) FROM t4', [1]],
    'select1.test select1-2.15 application metric sum' => ['SELECT sum(value) FROM app_metrics', [12]],
    'select1.test select1-2.10 application metric max' => ['SELECT max(value) FROM app_metrics', [7]],
    'select1.test select1-2.7 application metric min' => ['SELECT min(value) FROM app_metrics', [2]],
];

$tests['real upstream corpus select core dynamic select1 aggregate cases'] = static function (TestRunner $t) use ($aggregateCases, $aggregateTables, $assertSelect): void {
    foreach ($aggregateCases as $name => [$sql, $expected]) {
        $assertSelect($t, $sql, $aggregateTables, $expected);
        $t->contains('select1.test', $name);
    }
};

$addCases($tests, 'real upstream corpus select core dynamic', $aggregateCases, $aggregateTables);

$tbl1 = [];
for ($i = 0; $i <= 30; $i++) {
    $tbl1[] = ['f1' => $i % 9, 'f2' => $i % 10];
}
$tbl2 = [];
for ($i = 1; $i <= 240; $i++) {
    $tbl2[] = ['f1' => $i, 'f2' => $i * 2, 'f3' => $i * 3];
}
$select2Tables = [
    'tbl1' => $tbl1,
    'tbl2' => $tbl2,
];

$select2Cases = [
    'select2.test select2-1.1 distinct f1 ordered' => ['SELECT DISTINCT f1 FROM tbl1 ORDER BY f1', [0, 1, 2, 3, 4, 5, 6, 7, 8]],
    'select2.test select2-1.2 distinct f1 bounded predicate' => ['SELECT DISTINCT f1 FROM tbl1 WHERE f1>3 AND f1<5 ORDER BY f1', [4]],
    'select2.test select2-1.1 nested-loop f2 for f1 zero' => ['SELECT f2 FROM tbl1 WHERE f1=0 ORDER BY f2', [0, 7, 8, 9]],
    'select2.test select2-1.1 nested-loop f2 for f1 one' => ['SELECT f2 FROM tbl1 WHERE f1=1 ORDER BY f2', [0, 1, 8, 9]],
    'select2.test select2-1.1 nested-loop f2 for f1 two' => ['SELECT f2 FROM tbl1 WHERE f1=2 ORDER BY f2', [0, 1, 2, 9]],
    'select2.test select2-1.1 nested-loop f2 for f1 three' => ['SELECT f2 FROM tbl1 WHERE f1=3 ORDER BY f2', [0, 1, 2, 3]],
    'select2.test select2-1.1 nested-loop f2 for f1 four' => ['SELECT f2 FROM tbl1 WHERE f1=4 ORDER BY f2', [2, 3, 4]],
    'select2.test select2-1.1 nested-loop f2 for f1 five' => ['SELECT f2 FROM tbl1 WHERE f1=5 ORDER BY f2', [3, 4, 5]],
    'select2.test select2-1.1 nested-loop f2 for f1 six' => ['SELECT f2 FROM tbl1 WHERE f1=6 ORDER BY f2', [4, 5, 6]],
    'select2.test select2-1.1 nested-loop f2 for f1 seven' => ['SELECT f2 FROM tbl1 WHERE f1=7 ORDER BY f2', [5, 6, 7]],
    'select2.test select2-1.1 nested-loop f2 for f1 eight' => ['SELECT f2 FROM tbl1 WHERE f1=8 ORDER BY f2', [6, 7, 8]],
    'select2.test select2-2.1 count all scaled table' => ['SELECT count(*) FROM tbl2', [240]],
    'select2.test select2-2.2 count predicate scaled table' => ['SELECT count(*) FROM tbl2 WHERE f2>100', [190]],
    'select2.test select2-3.1 commuted equality predicate' => ['SELECT f1 FROM tbl2 WHERE 100=f2', [50]],
    'select2.test select2-3.2c direct equality predicate' => ['SELECT f1 FROM tbl2 WHERE f2=100', [50]],
    'select2.test select2-3.2b equality with order stability' => ['SELECT f1, f3 FROM tbl2 WHERE f2=100', [50, 150]],
];

$tests['real upstream corpus select core dynamic select2 nested predicate cases'] = static function (TestRunner $t) use ($select2Cases, $select2Tables, $assertSelect): void {
    foreach ($select2Cases as $name => [$sql, $expected]) {
        $assertSelect($t, $sql, $select2Tables, $expected);
        $t->contains('select2.test', $name);
    }
};

$addCases($tests, 'real upstream corpus select core dynamic', $select2Cases, $select2Tables);

$t1 = [];
for ($i = 1; $i < 32; $i++) {
    $j = 0;
    while ((1 << $j) < $i) {
        $j++;
    }
    $t1[] = ['n' => $i, 'log' => $j];
}
$select3Tables = ['t1' => $t1];

$select3Cases = [
    'select3.test select3-1.0 distinct logs' => ['SELECT DISTINCT log FROM t1 ORDER BY log', [0, 1, 2, 3, 4, 5]],
    'select3.test select3-1.1 count star' => ['SELECT count(*) FROM t1', [31]],
    'select3.test select3-2.1 group by count' => ['SELECT log, count(*) FROM t1 GROUP BY log ORDER BY log', [0, 1, 1, 1, 2, 2, 3, 4, 4, 8, 5, 15]],
    'select3.test select3-2.2 group by min' => ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY log', [0, 1, 1, 2, 2, 3, 3, 5, 4, 9, 5, 17]],
    'select3.test select3-2.3.1 group by avg' => ['SELECT log, avg(n) FROM t1 GROUP BY log ORDER BY log', [0, 1.0, 1, 2.0, 2, 3.5, 3, 6.5, 4, 12.5, 5, 24.0]],
    'select3.test select3-2.3.2 group by avg expression' => ['SELECT log, avg(n)+1 FROM t1 GROUP BY log ORDER BY log', [0, 2.0, 1, 3.0, 2, 4.5, 3, 7.5, 4, 13.5, 5, 25.0]],
    'select3.test select3-2.4 group by aggregate arithmetic' => ['SELECT log, avg(n)-min(n) FROM t1 GROUP BY log ORDER BY log', [0, 0.0, 1, 0.0, 2, 0.5, 3, 1.5, 4, 3.5, 5, 7.0]],
    'select3.test select3-2.5 group by projection expression' => ['SELECT log*2+1, avg(n)-min(n) FROM t1 GROUP BY log ORDER BY log', [1, 0.0, 3, 0.0, 5, 0.5, 7, 1.5, 9, 3.5, 11, 7.0]],
    'select3.test select3-2.6 group by expression count' => ['SELECT log*2+1, count(*) FROM t1 GROUP BY log ORDER BY log', [1, 1, 3, 1, 5, 2, 7, 4, 9, 8, 11, 15]],
    'select3.test select3-2.2 group by max boundary' => ['SELECT log, max(n) FROM t1 GROUP BY log ORDER BY log', [0, 1, 1, 2, 2, 4, 3, 8, 4, 16, 5, 31]],
    'select3.test select3-2.1 group by sum boundary' => ['SELECT log, sum(n) FROM t1 GROUP BY log ORDER BY log', [0, 1, 1, 2, 2, 7, 3, 26, 4, 100, 5, 360]],
];

$tests['real upstream corpus select core dynamic select3 group aggregate cases'] = static function (TestRunner $t) use ($select3Cases, $select3Tables, $assertSelect): void {
    foreach ($select3Cases as $name => [$sql, $expected]) {
        $assertSelect($t, $sql, $select3Tables, $expected);
        $t->contains('select3.test', $name);
    }
};

$addCases($tests, 'real upstream corpus select core dynamic', $select3Cases, $select3Tables);

$tests['real upstream corpus select core dynamic rejects missing table like select1-1.1'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT * FROM missing_table', []));
};

return $tests;
