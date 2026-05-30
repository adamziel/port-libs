<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select1Tables = static function (): array {
    return [
        'test1' => [
            ['f1' => 11, 'f2' => 22],
            ['f1' => 33, 'f2' => 44],
        ],
        'test2' => [
            ['r1' => 1.1, 'r2' => 2.2],
        ],
        't3' => [
            ['a' => 'abc', 'b' => null],
            ['a' => null, 'b' => 'xyz'],
            ['a' => 11, 'b' => 22],
            ['a' => 33, 'b' => 44],
        ],
        't4' => [
            ['a' => null, 'b' => 'This is a string that is too big to fit inside a NBFS buffer'],
        ],
        't5' => [
            ['a' => 1, 'b' => 10],
            ['a' => 2, 'b' => 9],
            ['a' => 3, 'b' => 10],
        ],
    ];
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select3Tables = static function (): array {
    $rows = [];
    for ($i = 1; $i < 32; $i++) {
        for ($j = 0; (1 << $j) < $i; $j++) {
        }
        $rows[] = ['n' => $i, 'log' => $j];
    }

    return ['t1' => $rows];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flatValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param list<mixed> $expected
 * @param list<array<string,mixed>> $actualRows
 */
$assertFlatValues = static function (TestRunner $t, array $expected, array $actualRows, callable $flatValues): void {
    $actual = $flatValues($actualRows);
    $t->same(count($expected), count($actual));
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null);
    }
};

$tests = [];

$select1Cases = [
    'select1.test select1-1.4 column f1' => ['SELECT f1 FROM test1 WHERE f1=11', [11]],
    'select1.test select1-1.5 column f2' => ['SELECT f2 FROM test1 WHERE f1=11', [22]],
    'select1.test select1-1.6 reversed columns' => ['SELECT f2, f1 FROM test1 WHERE f1=11', [22, 11]],
    'select1.test select1-1.7 ordered columns' => ['SELECT f1, f2 FROM test1 WHERE f1=11', [11, 22]],
    'select1.test select1-1.8 wildcard projection' => ['SELECT * FROM test1 WHERE f1=11', [11, 22]],
    'select1.test select1-1.8.2 wildcard plus scalar min max' => ['SELECT *, min(f1,f2), max(f1,f2) FROM test1 WHERE f1=11', [11, 22, 11, 22]],
    'select1.test select1-1.9 cross product wildcard' => ['SELECT * FROM test1, test2 WHERE f1=11', [11, 22, 1.1, 2.2]],
    'select1.test select1-1.9.1 wildcard plus literal after join' => ["SELECT *, 'hi' FROM test1, test2 WHERE f1=11", [11, 22, 1.1, 2.2, 'hi']],
    'select1.test select1-1.10 qualified columns' => ['SELECT test1.f1, test2.r1 FROM test1, test2 WHERE f1=11', [11, 1.1]],
    'select1.test select1-1.11 reversed source qualified columns' => ['SELECT test1.f1, test2.r1 FROM test2, test1 WHERE f1=11', [11, 1.1]],
    'select1.test select1-1.11.1 reversed source wildcard' => ['SELECT * FROM test2, test1 WHERE f1=11', [1.1, 2.2, 11, 22]],
    'select1.test select1-1.11.2 self join aliases' => ['SELECT * FROM test1 AS a, test1 AS b WHERE a.f1=11 AND b.f1=11', [11, 22, 11, 22]],
    'select1.test select1-1.12 scalar max min across joined sources' => ['SELECT max(test1.f1,test2.r1), min(test1.f2,test2.r2) FROM test2, test1 WHERE f1=11', [11, 2.2]],
    'select1.test select1-1.13 scalar min max across joined sources' => ['SELECT min(test1.f1,test2.r1), max(test1.f2,test2.r2) FROM test1, test2 WHERE f1=11', [1.1, 22]],
    'select1.test select1-2.0 mixed inserted rows' => ['SELECT * FROM t3', ['abc', null, null, 'xyz', 11, 22, 33, 44]],
    'select1.test select1-2.2 count column aggregate' => ['SELECT count(f1) FROM test1', [2]],
    'select1.test select1-2.4 count wildcard aggregate' => ['SELECT COUNT(*) FROM test1', [2]],
    'select1.test select1-2.5 count wildcard expression' => ['SELECT COUNT(*)+1 FROM test1', [3]],
    'select1.test select1-2.7 min aggregate' => ['SELECT Min(f1) FROM test1', [11]],
    'select1.test select1-2.8 scalar min rows' => ['SELECT MIN(f1,f2) FROM test1 ORDER BY f1', [11, 33]],
    'select1.test select1-2.10 max aggregate' => ['SELECT Max(f1) FROM test1', [33]],
    'select1.test select1-2.11 scalar max rows' => ['SELECT max(f1,f2) FROM test1 ORDER BY f1', [22, 44]],
    'select1.test select1-2.12 scalar max expression rows' => ['SELECT MAX(f1,f2)+1 FROM test1 ORDER BY f1', [23, 45]],
    'select1.test select1-2.13 max aggregate expression' => ['SELECT MAX(f1)+1 FROM test1', [34]],
    'select1.test select1-2.15 sum aggregate' => ['SELECT Sum(f1) FROM test1', [44]],
    'select1.test select1-2.17 sum aggregate expression' => ['SELECT SUM(f1)+1 FROM test1', [45]],
    'select1.test select1-2.17.1 sum mixed affinity column' => ['SELECT sum(a) FROM t3', [44]],
    'select1.test select1-3.1 where less than empty' => ['SELECT f1 FROM test1 WHERE f1<11', []],
    'select1.test select1-3.2 where less than or equal' => ['SELECT f1 FROM test1 WHERE f1<=11', [11]],
    'select1.test select1-3.3 where equal' => ['SELECT f1 FROM test1 WHERE f1=11', [11]],
    'select1.test select1-3.4 where greater than or equal' => ['SELECT f1 FROM test1 WHERE f1>=11 ORDER BY f1', [11, 33]],
    'select1.test select1-3.5 where greater than' => ['SELECT f1 FROM test1 WHERE f1>11', [33]],
    'select1.test select1-3.6 where not equal' => ['SELECT f1 FROM test1 WHERE f1!=11', [33]],
    'select1.test select1-3.7 where scalar min predicate' => ['SELECT f1 FROM test1 WHERE min(f1,f2)!=11', [33]],
    'select1.test select1-3.8 where scalar max predicate' => ['SELECT f1 FROM test1 WHERE max(f1,f2)!=11 ORDER BY f1', [11, 33]],
    'select1.test select1-4.1 order by column' => ['SELECT f1 FROM test1 ORDER BY f1', [11, 33]],
    'select1.test select1-4.2 order by unary expression' => ['SELECT f1 FROM test1 ORDER BY -f1', [33, 11]],
    'select1.test select1-4.3 order by scalar function' => ['SELECT f1 FROM test1 ORDER BY min(f1,f2)', [11, 33]],
    'select1.test select1-4.5 order by numeric constant' => ['SELECT f1 FROM test1 ORDER BY 8.4', [11, 33]],
    'select1.test select1-4.6 order by text constant' => ["SELECT f1 FROM test1 ORDER BY '8.4'", [11, 33]],
    'select1.test select1-4.11 order by column then descending ordinal equivalent' => ['SELECT a,b FROM t5 ORDER BY b, a DESC', [2, 9, 3, 10, 1, 10]],
    'select1.test select1-4.12 order by first column desc' => ['SELECT a,b FROM t5 ORDER BY a DESC, b', [3, 10, 2, 9, 1, 10]],
    'select1.test select1-4.13 order by b desc then first column' => ['SELECT a,b FROM t5 ORDER BY b DESC, a', [1, 10, 3, 10, 2, 9]],
];

foreach ($select1Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($select1Tables, $sql, $expected, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $select1Tables()), $flatValues);
    };
}

$select3Rows = [];
for ($i = 1; $i < 32; $i++) {
    for ($j = 0; (1 << $j) < $i; $j++) {
    }
    $select3Rows[] = $i;
    $select3Rows[] = $j;
}

$select3Cases = [
    'select3.test select3-1.0 source rows and log distribution setup' => ['SELECT n, log FROM t1 ORDER BY n', $select3Rows],
    'select3.test select3-1.0 distinct logs' => ['SELECT DISTINCT log FROM t1 ORDER BY log', [0, 1, 2, 3, 4, 5]],
    'select3.test select3-1.1 count all rows' => ['SELECT count(*) FROM t1', [31]],
    'select3.test select3-2.1 grouped counts' => ['SELECT log, count(*) FROM t1 GROUP BY log ORDER BY log', [0, 1, 1, 1, 2, 2, 3, 4, 4, 8, 5, 15]],
    'select3.test select3-2.2 grouped min rows' => ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY log', [0, 1, 1, 2, 2, 3, 3, 5, 4, 9, 5, 17]],
    'select3.test select3-2.3.1 grouped average rows' => ['SELECT log, avg(n) FROM t1 GROUP BY log ORDER BY log', [0, 1.0, 1, 2.0, 2, 3.5, 3, 6.5, 4, 12.5, 5, 24.0]],
    'select3.test select3-2.3.2 grouped average expression rows' => ['SELECT log, avg(n)+1 FROM t1 GROUP BY log ORDER BY log', [0, 2.0, 1, 3.0, 2, 4.5, 3, 7.5, 4, 13.5, 5, 25.0]],
    'select3.test select3-2.4 grouped average minus min rows' => ['SELECT log, avg(n)-min(n) FROM t1 GROUP BY log ORDER BY log', [0, 0.0, 1, 0.0, 2, 0.5, 3, 1.5, 4, 3.5, 5, 7.0]],
    'select3.test select3-4.1 grouped having column predicate' => ['SELECT log, count(*) FROM t1 GROUP BY log HAVING log>=4 ORDER BY log', [4, 8, 5, 15]],
    'select3.test select3-4.2 grouped having count predicate' => ['SELECT log, count(*) FROM t1 GROUP BY log HAVING count(*)>=4 ORDER BY log', [3, 4, 4, 8, 5, 15]],
    'select3.test select3-4.3 grouped having count with aggregate order' => ['SELECT log, count(*) FROM t1 GROUP BY log HAVING count(*)>=4 ORDER BY max(n)+0', [3, 4, 4, 8, 5, 15]],
    'select3.test select3-6.1 grouped min order ascending' => ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY log', [0, 1, 1, 2, 2, 3, 3, 5, 4, 9, 5, 17]],
    'select3.test select3-6.2 grouped min order descending' => ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY log DESC', [5, 17, 4, 9, 3, 5, 2, 3, 1, 2, 0, 1]],
    'select3.test select3-6.3 grouped min order by first column' => ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY 1', [0, 1, 1, 2, 2, 3, 3, 5, 4, 9, 5, 17]],
    'select3.test select3-6.4 grouped min order by first column descending' => ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY 1 DESC', [5, 17, 4, 9, 3, 5, 2, 3, 1, 2, 0, 1]],
    'select3.test select3-7.1 empty grouped aggregate rowset' => ['SELECT a, sum(b) FROM t2 WHERE b=5 GROUP BY a', []],
];

for ($log = 0; $log <= 5; $log++) {
    $ascending = [];
    $descending = [];
    foreach ($select3Tables()['t1'] as $row) {
        if ($row['log'] !== $log) {
            continue;
        }
        $ascending[] = $row['n'];
        $ascending[] = $row['log'];
        array_unshift($descending, $row['log']);
        array_unshift($descending, $row['n']);
    }

    $select3Cases["select3.test select3-1.0 dynamic log {$log} ascending slice"] = [
        "SELECT n, log FROM t1 WHERE log={$log} ORDER BY n",
        $ascending,
    ];
    $select3Cases["select3.test select3-1.0 dynamic log {$log} descending slice"] = [
        "SELECT n, log FROM t1 WHERE log={$log} ORDER BY n DESC",
        $descending,
    ];
}

foreach ([4, 8, 16, 31] as $limit) {
    $expected = [];
    foreach (array_slice($select3Tables()['t1'], 0, $limit) as $row) {
        $expected[] = $row['n'];
        $expected[] = $row['log'];
    }

    $select3Cases["select3.test select3-1.0 dynamic first {$limit} ordered rows"] = [
        "SELECT n, log FROM t1 WHERE n<={$limit} ORDER BY n",
        $expected,
    ];
}

foreach ($select3Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($select3Tables, $sql, $expected, $assertFlatValues, $flatValues): void {
        $tables = $select3Tables();
        $tables['t2'] = [['a' => 1, 'b' => 2]];
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $tables), $flatValues);
    };
}

return $tests;
