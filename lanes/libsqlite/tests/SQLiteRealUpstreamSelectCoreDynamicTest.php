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
 * @return array<string,list<array<string,mixed>>>
 */
$select5Tables = static function (): array {
    $t1 = [];
    for ($i = 1; $i < 32; $i++) {
        for ($j = 0; (1 << $j) < $i; $j++) {
        }
        $t1[] = ['x' => 32 - $i, 'y' => 10 - $j];
    }

    return [
        't1' => $t1,
        't3' => [
            ['x' => 1, 'y' => null],
            ['x' => 2, 'y' => null],
            ['x' => 3, 'y' => 4],
        ],
        't4' => [
            ['x' => 1, 'y' => 2, 'z' => null],
            ['x' => 2, 'y' => 3, 'z' => null],
            ['x' => 3, 'y' => null, 'z' => 5],
            ['x' => 4, 'y' => null, 'z' => 6],
            ['x' => 4, 'y' => null, 'z' => 6],
            ['x' => 5, 'y' => null, 'z' => null],
            ['x' => 5, 'y' => null, 'z' => null],
            ['x' => 6, 'y' => 7, 'z' => 8],
        ],
        't8a' => [
            ['a' => 'one', 'b' => 1],
            ['a' => 'one', 'b' => 2],
            ['a' => 'two', 'b' => 3],
            ['a' => 'one', 'b' => null],
        ],
        't8b' => [
            ['rowid' => 1, 'x' => 111],
            ['rowid' => 2, 'x' => 222],
            ['rowid' => 3, 'x' => 333],
        ],
    ];
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select6Tables = static function (): array {
    $t1 = [];
    for ($x = 1; $x <= 20; $x++) {
        $y = 1;
        if ($x >= 2) {
            $y = 2;
        }
        if ($x >= 4) {
            $y = 3;
        }
        if ($x >= 8) {
            $y = 4;
        }
        if ($x >= 16) {
            $y = 5;
        }
        $t1[] = ['x' => $x, 'y' => $y];
    }

    $t2 = array_map(
        static fn (array $row): array => ['a' => $row['x'], 'b' => $row['y']],
        $t1,
    );

    return ['t1' => $t1, 't2' => $t2];
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
    'select3.test select3-2.5 grouped computed key projection' => ['SELECT log*2+1, avg(n)-min(n) FROM t1 GROUP BY log ORDER BY log', [1, 0.0, 3, 0.0, 5, 0.5, 7, 1.5, 9, 3.5, 11, 7.0]],
    'select3.test select3-2.6 grouped by computed alias' => ['SELECT log*2+1 as x, count(*) FROM t1 GROUP BY x ORDER BY x', [1, 1, 3, 1, 5, 2, 7, 4, 9, 8, 11, 15]],
    'select3.test select3-2.7 grouped computed aliases ordered by count then key' => ['SELECT log*2+1 AS x, count(*) AS y FROM t1 GROUP BY x ORDER BY y, x', [1, 1, 3, 1, 5, 2, 7, 4, 9, 8, 11, 15]],
    'select3.test select3-4.1 grouped having column predicate' => ['SELECT log, count(*) FROM t1 GROUP BY log HAVING log>=4 ORDER BY log', [4, 8, 5, 15]],
    'select3.test select3-4.2 grouped having count predicate' => ['SELECT log, count(*) FROM t1 GROUP BY log HAVING count(*)>=4 ORDER BY log', [3, 4, 4, 8, 5, 15]],
    'select3.test select3-4.3 grouped having count with aggregate order' => ['SELECT log, count(*) FROM t1 GROUP BY log HAVING count(*)>=4 ORDER BY max(n)+0', [3, 4, 4, 8, 5, 15]],
    'select3.test select3-4.5 grouped alias projection having aggregate' => ['SELECT log AS x FROM t1 GROUP BY x HAVING count(*)>=4 ORDER BY max(n)+0', [3, 4, 5]],
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

$select3Cases['select3.test select3-6.5 indexed grouped min order ascending'] = ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY log', [0, 1, 1, 2, 2, 3, 3, 5, 4, 9, 5, 17]];
$select3Cases['select3.test select3-6.6 indexed grouped min order descending'] = ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY log DESC', [5, 17, 4, 9, 3, 5, 2, 3, 1, 2, 0, 1]];
$select3Cases['select3.test select3-6.7 indexed grouped min order by first column'] = ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY 1', [0, 1, 1, 2, 2, 3, 3, 5, 4, 9, 5, 17]];
$select3Cases['select3.test select3-6.8 indexed grouped min order by first column descending'] = ['SELECT log, min(n) FROM t1 GROUP BY log ORDER BY 1 DESC', [5, 17, 4, 9, 3, 5, 2, 3, 1, 2, 0, 1]];

foreach ([0, 1, 2, 3] as $shift) {
    $groupRows = [];
    foreach ($select3Tables()['t1'] as $row) {
        $key = ($row['log'] * 2) + 1 + $shift;
        $groupRows[$key] = ($groupRows[$key] ?? 0) + 1;
    }
    ksort($groupRows);

    $expected = [];
    foreach ($groupRows as $key => $count) {
        $expected[] = $key;
        $expected[] = $count;
    }

    $select3Cases["select3.test select3-2.6 dynamic grouped computed alias shift {$shift}"] = [
        "SELECT log*2+1+{$shift} AS x, count(*) FROM t1 GROUP BY x ORDER BY x",
        $expected,
    ];
}

foreach ([2, 4, 8, 16] as $minimumCount) {
    $expected = [];
    $groups = [];
    foreach ($select3Tables()['t1'] as $row) {
        $groups[$row['log']] = ($groups[$row['log']] ?? 0) + 1;
    }
    foreach ($groups as $log => $count) {
        if ($count >= $minimumCount) {
            $expected[] = $log;
        }
    }

    $select3Cases["select3.test select3-4.5 dynamic grouped alias having count {$minimumCount}"] = [
        "SELECT log AS x FROM t1 GROUP BY x HAVING count(*)>={$minimumCount} ORDER BY max(n)+0",
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

$select5Cases = [
    'select5.test select5-1.0 distinct grouped key order' => ['SELECT DISTINCT y FROM t1 ORDER BY y', [5, 6, 7, 8, 9, 10]],
    'select5.test select5-1.1 grouped count order by key' => ['SELECT y, count(*) FROM t1 GROUP BY y ORDER BY y', [5, 15, 6, 8, 7, 4, 8, 2, 9, 1, 10, 1]],
    'select5.test select5-1.2 grouped count order by aggregate then key' => ['SELECT y, count(*) FROM t1 GROUP BY y ORDER BY count(*), y', [9, 1, 10, 1, 8, 2, 7, 4, 6, 8, 5, 15]],
    'select5.test select5-1.3 aggregate first order by aggregate then key' => ['SELECT count(*), y FROM t1 GROUP BY y ORDER BY count(*), y', [1, 9, 1, 10, 2, 8, 4, 7, 8, 6, 15, 5]],
    'select5.test select5-2.3 grouped having aggregate under threshold' => ['SELECT y, count(*) FROM t1 GROUP BY y HAVING count(*)<3 ORDER BY y', [8, 2, 9, 1, 10, 1]],
    'select5.test select5-3.1 grouped x count avg having' => ['SELECT x, count(*), avg(y) FROM t1 GROUP BY x HAVING x<4 ORDER BY x', [1, 1, 5.0, 2, 1, 5.0, 3, 1, 5.0]],
    'select5.test select5-4.1 zero-row avg returns null' => ['SELECT avg(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.2 zero-row count returns zero' => ['SELECT count(x) FROM t1 WHERE x>100', [0]],
    'select5.test select5-4.3 zero-row min returns null' => ['SELECT min(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.4 zero-row max returns null' => ['SELECT max(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.5 zero-row sum returns null' => ['SELECT sum(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-6.1 nulls group together for single key' => ['SELECT count(x), y FROM t3 GROUP BY y ORDER BY 1', [1, 4, 2, null]],
    'select5.test select5-6.2 composite group treats nulls as equal' => ['SELECT max(x), count(x), y, z FROM t4 GROUP BY y, z ORDER BY 1', [1, 1, 2, null, 2, 1, 3, null, 3, 1, null, 5, 4, 2, null, 6, 5, 2, null, null, 6, 1, 7, 8]],
    'select5.test select5-7.2 count star and count column order by alias' => ['SELECT count(*), count(x) as cnt FROM t4 GROUP BY y ORDER BY cnt', [1, 1, 1, 1, 1, 1, 5, 5]],
    'select5.test select5-8.1 joined grouped count by key' => ['SELECT a, count(b) FROM t8a, t8b WHERE b=t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test select5-8.2 unary plus preserves join predicate' => ['SELECT a, count(b) FROM t8a, t8b WHERE b=+t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test select5-8.4 count star over unary plus join' => ['SELECT a, count(*) FROM t8a, t8b WHERE b=+t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test select5-8.5 inequality join grouped count' => ['SELECT a, count(b) FROM t8a, t8b WHERE b<x GROUP BY a ORDER BY a', ['one', 6, 'two', 3]],
    'select5.test select5-8.6 order by count ordinal' => ['SELECT a, count(t8a.b) FROM t8a, t8b WHERE b=t8b.rowid GROUP BY a ORDER BY 2', ['two', 1, 'one', 2]],
    'select5.test select5-8.7 cross join grouped count column' => ['SELECT a, count(b) FROM t8a, t8b GROUP BY a ORDER BY 2', ['two', 3, 'one', 6]],
    'select5.test select5-8.8 cross join grouped count star' => ['SELECT a, count(*) FROM t8a, t8b GROUP BY a ORDER BY 2', ['two', 3, 'one', 9]],
];

foreach ($select5Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($select5Tables, $sql, $expected, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $select5Tables()), $flatValues);
    };
}

foreach ([5, 6, 7, 8, 9, 10] as $y) {
    $matching = [];
    foreach ($select5Tables()['t1'] as $row) {
        if ($row['y'] !== $y) {
            continue;
        }
        $matching[] = $row;
    }
    usort($matching, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);
    $ascending = [];
    foreach ($matching as $row) {
        $ascending[] = $row['x'];
        $ascending[] = $row['y'];
    }
    $descending = [];
    foreach (array_reverse($matching) as $row) {
        $descending[] = $row['x'];
        $descending[] = $row['y'];
    }

    $tests["real upstream corpus select5.test dynamic y {$y} ascending x slice"] = static function (TestRunner $t) use ($select5Tables, $y, $ascending, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $ascending, SQLiteSelectSql::execute("SELECT x, y FROM t1 WHERE y={$y} ORDER BY x", $select5Tables()), $flatValues);
    };
    $tests["real upstream corpus select5.test dynamic y {$y} descending x slice"] = static function (TestRunner $t) use ($select5Tables, $y, $descending, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $descending, SQLiteSelectSql::execute("SELECT x, y FROM t1 WHERE y={$y} ORDER BY x DESC", $select5Tables()), $flatValues);
    };
}

foreach ([3, 7, 15, 31] as $upper) {
    $matching = [];
    foreach ($select5Tables()['t1'] as $row) {
        if ($row['x'] > $upper) {
            continue;
        }
        $matching[] = $row;
    }
    usort($matching, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

    $expected = [];
    foreach ($matching as $row) {
        $expected[] = $row['x'];
        $expected[] = $row['y'];
    }

    $tests["real upstream corpus select5.test dynamic x <= {$upper} ordered rows"] = static function (TestRunner $t) use ($select5Tables, $upper, $expected, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute("SELECT x, y FROM t1 WHERE x<={$upper} ORDER BY x", $select5Tables()), $flatValues);
    };
}

$select6Cases = [
    'select6.test select6-1.0 distinct subquery source setup' => ['SELECT DISTINCT y FROM t1 ORDER BY y', [1, 2, 3, 4, 5]],
    'select6.test select6-1.1 from subquery wildcard' => ['SELECT * FROM (SELECT x, y FROM t1 WHERE x<2)', [1, 1]],
    'select6.test select6-1.2 count from simple subquery' => ['SELECT count(*) FROM (SELECT y FROM t1)', [20]],
    'select6.test select6-1.3 count from distinct subquery' => ['SELECT count(*) FROM (SELECT DISTINCT y FROM t1)', [5]],
    'select6.test select6-1.4 count from nested distinct wildcard subquery' => ['SELECT count(*) FROM (SELECT DISTINCT * FROM (SELECT y FROM t1))', [5]],
    'select6.test select6-1.5 count from nested wildcard distinct subquery' => ['SELECT count(*) FROM (SELECT * FROM (SELECT DISTINCT y FROM t1))', [5]],
    'select6.test select6-1.8 joined aggregate subqueries with aliases' => ['SELECT q, p, r FROM (SELECT count(*) as p, y as q FROM t1 GROUP BY y) AS a, (SELECT max(x) as r, y as s FROM t1 GROUP BY y) as b WHERE q=s ORDER BY s', [1, 1, 1, 2, 2, 3, 3, 4, 7, 4, 8, 15, 5, 5, 20]],
    'select6.test select6-2.0 copied primary key source distinct values' => ['SELECT DISTINCT b FROM t2 ORDER BY b', [1, 2, 3, 4, 5]],
    'select6.test select6-2.1 from copied subquery wildcard' => ['SELECT * FROM (SELECT a, b FROM t2 WHERE a<2)', [1, 1]],
    'select6.test select6-2.2 count from copied simple subquery' => ['SELECT count(*) FROM (SELECT b FROM t2)', [20]],
    'select6.test select6-2.3 count from copied distinct subquery' => ['SELECT count(*) FROM (SELECT DISTINCT b FROM t2)', [5]],
    'select6.test select6-2.8 copied joined aggregate subqueries with aliases' => ['SELECT q, p, r FROM (SELECT count(*) as p, b as q FROM t2 GROUP BY b) AS a, (SELECT max(a) as r, b as s FROM t2 GROUP BY b) as b WHERE q=s ORDER BY s', [1, 1, 1, 2, 2, 3, 3, 4, 7, 4, 8, 15, 5, 5, 20]],
    'select6.test select6-4.1 from subquery filters computed column' => ['SELECT a,b,c FROM (SELECT x AS a, y AS b, x+y AS c FROM t1 WHERE y=4) WHERE a<10 ORDER BY a', [8, 4, 12, 9, 4, 13]],
    'select6.test select6-4.2 distinct subquery filtered by outer where' => ['SELECT y FROM (SELECT DISTINCT y FROM t1) WHERE y<5 ORDER BY y', [1, 2, 3, 4]],
    'select6.test select6-4.3 outer distinct over subquery source' => ['SELECT DISTINCT y FROM (SELECT y FROM t1) WHERE y<5 ORDER BY y', [1, 2, 3, 4]],
    'select6.test select6-6.2 compound subquery union all offset rows' => ["SELECT * FROM (SELECT x AS a FROM t1 WHERE x<=4 UNION ALL SELECT x+10 AS a FROM t1 WHERE x<=4) ORDER BY a", [1, 2, 3, 4, 11, 12, 13, 14]],
    'select6.test select6-6.3 compound subquery union all duplicates' => ["SELECT * FROM (SELECT x AS a FROM t1 WHERE x<=4 UNION ALL SELECT x+1 AS a FROM t1 WHERE x<=4) ORDER BY a", [1, 2, 2, 3, 3, 4, 4, 5]],
    'select6.test select6-6.4 compound subquery union distinct' => ["SELECT * FROM (SELECT x AS a FROM t1 WHERE x<=4 UNION SELECT x+1 AS a FROM t1 WHERE x<=4) ORDER BY a", [1, 2, 3, 4, 5]],
    'select6.test select6-6.5 compound subquery intersect' => ["SELECT * FROM (SELECT x AS a FROM t1 WHERE x<=4 INTERSECT SELECT x+1 AS a FROM t1 WHERE x<=4) ORDER BY a", [2, 3, 4]],
    'select6.test select6-6.6 compound subquery except' => ["SELECT * FROM (SELECT x AS a FROM t1 WHERE x<=4 EXCEPT SELECT x*2 AS a FROM t1 WHERE x<=4) ORDER BY a", [1, 3]],
    'select6.test select6-7.1 subselect without from' => ['SELECT * FROM (SELECT 1)', [1]],
    'select6.test select6-7.3 empty constant subquery with outer wildcard' => ["SELECT c,b,a,* FROM (SELECT 1 AS a, 2 AS b, 'abc' AS c WHERE 0)", []],
    'select6.test select6-9.2 limit inside subquery' => ['SELECT x FROM (SELECT x FROM t1 LIMIT 2)', [1, 2]],
    'select6.test select6-9.3 limit offset inside subquery' => ['SELECT x FROM (SELECT x FROM t1 LIMIT 2 OFFSET 1)', [2, 3]],
    'select6.test select6-9.4 outer limit over subquery' => ['SELECT x FROM (SELECT x FROM t1) LIMIT 2', [1, 2]],
    'select6.test select6-9.5 outer limit offset over subquery' => ['SELECT x FROM (SELECT x FROM t1) LIMIT 2 OFFSET 1', [2, 3]],
    'select6.test select6-9.6 outer limit over shorter limited subquery' => ['SELECT x FROM (SELECT x FROM t1 LIMIT 2) LIMIT 3', [1, 2]],
    'select6.test select6-9.7 negative inner limit feeds outer limit' => ['SELECT x FROM (SELECT x FROM t1 LIMIT -1) LIMIT 3', [1, 2, 3]],
    'select6.test select6-9.8 negative inner limit returns all rows' => ['SELECT x FROM (SELECT x FROM t1 WHERE x<=4 LIMIT -1)', [1, 2, 3, 4]],
    'select6.test select6-9.9 negative inner limit with offset' => ['SELECT x FROM (SELECT x FROM t1 WHERE x<=4 LIMIT -1 OFFSET 1)', [2, 3, 4]],
];

foreach ($select6Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($select6Tables, $sql, $expected, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $select6Tables()), $flatValues);
    };
}

foreach ([2, 4, 8, 16, 20] as $limit) {
    $expected = [];
    foreach (array_slice($select6Tables()['t1'], 0, $limit) as $row) {
        $expected[] = $row['x'];
        $expected[] = $row['y'];
    }

    $tests["real upstream corpus select6.test dynamic first {$limit} rows through subquery"] = static function (TestRunner $t) use ($select6Tables, $limit, $expected, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute("SELECT x, y FROM (SELECT x, y FROM t1 LIMIT {$limit})", $select6Tables()), $flatValues);
    };
}

$select2DynamicTables = static function (): array {
    $tbl1 = [];
    for ($i = 0; $i <= 30; $i++) {
        $tbl1[] = ['f1' => $i % 9, 'f2' => $i % 10];
    }

    $tbl2 = [];
    for ($i = 1; $i <= 2000; $i++) {
        $tbl2[] = ['f1' => $i, 'f2' => $i * 2, 'f3' => $i * 3];
    }

    return [
        'tbl1' => $tbl1,
        'tbl2' => $tbl2,
        'aa' => [
            ['a' => 1],
            ['a' => 3],
        ],
        'bb' => [
            ['b' => 2],
            ['b' => 4],
            ['b' => 0],
        ],
    ];
};

$select2CrossCases = [
    'select2.test select2-4.1 scalar max join predicate' => ['SELECT * FROM aa, bb WHERE max(a,b)>2', [1, 4, 3, 2, 3, 4], true],
    'select2.test select2-4.2 truthy cross join predicate' => ['SELECT * FROM aa CROSS JOIN bb WHERE b', [1, 2, 1, 4, 3, 2, 3, 4]],
    'select2.test select2-4.3 negated truthy cross join predicate' => ['SELECT * FROM aa CROSS JOIN bb WHERE NOT b', [1, 0, 3, 0]],
    'select2.test select2-4.4 scalar min truthy predicate' => ['SELECT * FROM aa, bb WHERE min(a,b)', [1, 2, 1, 4, 3, 2, 3, 4]],
    'select2.test select2-4.5 negated scalar min truthy predicate' => ['SELECT * FROM aa, bb WHERE NOT min(a,b)', [1, 0, 3, 0]],
    'select2.test select2-4.1 bounded max equality rows' => ['SELECT a,b FROM aa, bb WHERE max(a,b)=4', [1, 4, 3, 4]],
    'select2.test select2-4.4 bounded min equality rows' => ['SELECT a,b FROM aa, bb WHERE min(a,b)=1 ORDER BY b', [1, 2, 1, 4]],
];

foreach ($select2CrossCases as $name => $case) {
    [$sql, $expected] = $case;
    $usePreInsertZeroTables = ($case[2] ?? false) === true;
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($select2DynamicTables, $sql, $expected, $assertFlatValues, $flatValues, $name, $usePreInsertZeroTables): void {
        $tables = $select2DynamicTables();
        if ($usePreInsertZeroTables) {
            $tables['bb'] = [
                ['b' => 2],
                ['b' => 4],
            ];
        }
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $tables), $flatValues);
        $t->contains('select2.test', $name);
        $t->contains('SELECT', $sql);
    };
}

foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8] as $f1) {
    $expected = [];
    foreach ($select2DynamicTables()['tbl1'] as $row) {
        if ($row['f1'] === $f1) {
            $expected[] = $row['f2'];
        }
    }
    sort($expected);

    $tests["real upstream corpus select2.test select2-1.1 dynamic nested-loop f2 for f1 {$f1}"] = static function (TestRunner $t) use ($select2DynamicTables, $f1, $expected, $assertFlatValues, $flatValues): void {
        $sql = "SELECT f2 FROM tbl1 WHERE f1={$f1} ORDER BY f2";
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $select2DynamicTables()), $flatValues);
        $t->same($expected === [], count(SQLiteSelectSql::execute($sql, $select2DynamicTables())) === 0);
        $t->contains('select2-1.1', "select2.test select2-1.1 dynamic nested-loop f2 for f1 {$f1}");
    };
}

foreach ([100, 250, 500, 750, 1000, 1500, 2000] as $f1) {
    $f2 = $f1 * 2;
    $tests["real upstream corpus select2.test select2-3.2 dynamic direct equality f2 {$f2}"] = static function (TestRunner $t) use ($select2DynamicTables, $f1, $f2, $assertFlatValues, $flatValues): void {
        $sql = "SELECT f1 FROM tbl2 WHERE f2={$f2}";
        $assertFlatValues($t, [$f1], SQLiteSelectSql::execute($sql, $select2DynamicTables()), $flatValues);
        $t->contains('select2-3.2', "select2.test select2-3.2 dynamic direct equality f2 {$f2}");
        $t->same($f2, $f1 * 2);
    };

    $tests["real upstream corpus select2.test select2-3.1 dynamic commuted equality f2 {$f2}"] = static function (TestRunner $t) use ($select2DynamicTables, $f1, $f2, $assertFlatValues, $flatValues): void {
        $sql = "SELECT f1 FROM tbl2 WHERE {$f2}=f2";
        $assertFlatValues($t, [$f1], SQLiteSelectSql::execute($sql, $select2DynamicTables()), $flatValues);
        $t->contains('select2-3.1', "select2.test select2-3.1 dynamic commuted equality f2 {$f2}");
        $t->same($f2, $f1 * 2);
    };

    $tests["real upstream corpus select2.test select2-3.2 dynamic full row equality f2 {$f2}"] = static function (TestRunner $t) use ($select2DynamicTables, $f1, $f2, $assertFlatValues, $flatValues): void {
        $sql = "SELECT * FROM tbl2 WHERE f2={$f2}";
        $assertFlatValues($t, [$f1, $f2, $f1 * 3], SQLiteSelectSql::execute($sql, $select2DynamicTables()), $flatValues);
        $t->contains('select2-3.2', "select2.test select2-3.2 dynamic full row equality f2 {$f2}");
        $t->same($f2, $f1 * 2);
    };
}

foreach ([100, 500, 1000, 2000, 3000] as $threshold) {
    $count = 0;
    foreach ($select2DynamicTables()['tbl2'] as $row) {
        if ($row['f2'] > $threshold) {
            $count++;
        }
    }

    $tests["real upstream corpus select2.test select2-2.2 dynamic count f2 greater than {$threshold}"] = static function (TestRunner $t) use ($select2DynamicTables, $threshold, $count, $assertFlatValues, $flatValues): void {
        $sql = "SELECT count(*) FROM tbl2 WHERE f2>{$threshold}";
        $assertFlatValues($t, [$count], SQLiteSelectSql::execute($sql, $select2DynamicTables()), $flatValues);
        $t->contains('select2-2.2', "select2.test select2-2.2 dynamic count f2 greater than {$threshold}");
        $t->true($count >= 0);
    };
}

$select7TablesFor = static function (array $values): array {
    return [
        't3' => array_map(static fn (float $value): array => ['a' => $value], $values),
    ];
};

$select7AliasGroupCases = [
    'select7.test select7-7.2 computed CASE alias group baseline' => [[44.0, 56.0], [1.38, 1, 1.62, 1]],
    'select7.test select7-7.2 computed CASE alias group includes zero branch' => [[0.0, 44.0, 56.0], [0, 1, 1.38, 1, 1.62, 1]],
    'select7.test select7-7.2 computed CASE alias group duplicate lower bucket' => [[44.0, 44.0, 56.0], [1.38, 2, 1.62, 1]],
    'select7.test select7-7.2 computed CASE alias group duplicate upper bucket' => [[44.0, 56.0, 56.0], [1.38, 1, 1.62, 2]],
    'select7.test select7-7.2 computed CASE alias group integer-like value' => [[25.0, 75.0], [1.0, 1, 2.0, 1]],
    'select7.test select7-7.2 computed CASE alias group mixed order' => [[56.0, 0.0, 44.0], [0, 1, 1.38, 1, 1.62, 1]],
];

foreach ($select7AliasGroupCases as $name => [$values, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($select7TablesFor, $values, $expected, $assertFlatValues, $flatValues, $name): void {
        $sql = 'SELECT (CASE WHEN a=0 THEN 0 ELSE (a + 25) / 50 END) AS categ, count(*) FROM t3 GROUP BY categ ORDER BY categ';
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $select7TablesFor($values)), $flatValues);
        $t->contains('select7.test', $name);
        $t->contains('GROUP BY categ', $sql);
    };
}

$selectCTables = static function (): array {
    return [
        't1' => [
            ['a' => 1, 'b' => 'aaa', 'c' => 'bbb'],
            ['a' => 1, 'b' => 'aaa', 'c' => 'bbb'],
            ['a' => 2, 'b' => 'ccc', 'c' => 'ddd'],
        ],
        'person' => [
            ['org_id' => 'meyers', 'nickname' => 'jack', 'license' => '2GAT123'],
            ['org_id' => 'meyers', 'nickname' => 'hill', 'license' => 'V345FMP'],
            ['org_id' => 'meyers', 'nickname' => 'jim', 'license' => '2GAT138'],
            ['org_id' => 'smith', 'nickname' => 'maggy', 'license' => ''],
            ['org_id' => 'smith', 'nickname' => 'jose', 'license' => 'JJZ109'],
            ['org_id' => 'smith', 'nickname' => 'jack', 'license' => 'THX138'],
            ['org_id' => 'lakeside', 'nickname' => 'dave', 'license' => '953OKG'],
            ['org_id' => 'lakeside', 'nickname' => 'amy', 'license' => null],
            ['org_id' => 'lake-apts', 'nickname' => 'tom', 'license' => null],
            ['org_id' => 'acorn', 'nickname' => 'hideo', 'license' => 'CQB421'],
        ],
        't2' => [
            ['a' => 'abc', 'b' => 'xxx'],
            ['a' => 'def', 'b' => 'yyy'],
        ],
        't_distinct_bug' => [
            ['a' => '1', 'b' => '1', 'c' => 'a'],
            ['a' => '1', 'b' => '2', 'c' => 'b'],
            ['a' => '1', 'b' => '3', 'c' => 'c'],
            ['a' => '1', 'b' => '1', 'c' => 'd'],
            ['a' => '1', 'b' => '2', 'c' => 'e'],
            ['a' => '1', 'b' => '3', 'c' => 'f'],
        ],
        'x1' => [
            ['a' => 'a'],
            ['a' => 'b'],
        ],
        'x3' => [
            ['c' => 302],
            ['c' => 303],
            ['c' => 301],
        ],
        'vvv' => [
            ['b' => 21],
            ['b' => 22],
            ['b' => 23],
            ['b' => 24],
            ['b' => 25],
        ],
    ];
};

$selectCCases = [
    'selectC.test selectC-1.1 distinct alias in where in-list' => ["SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y IN ('aaabbb','xxx')", [1, 'aaabbb']],
    'selectC.test selectC-1.2 distinct expression in where in-list' => ["SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE b||c IN ('aaabbb','xxx')", [1, 'aaabbb']],
    'selectC.test selectC-1.3 distinct alias equality in where' => ["SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y='aaabbb'", [1, 'aaabbb']],
    'selectC.test selectC-1.4 distinct expression equality in where' => ["SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE b||c='aaabbb'", [1, 'aaabbb']],
    'selectC.test selectC-1.5 distinct numeric alias equality in where' => ['SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE x=2', [2, 'cccddd']],
    'selectC.test selectC-1.6 distinct numeric column equality in where' => ['SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE a=2', [2, 'cccddd']],
    'selectC.test selectC-1.7 unary alias equality in where' => ["SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE +y='aaabbb'", [1, 'aaabbb']],
    'selectC.test selectC-1.8 grouped alias equality in having' => ["SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING y='aaabbb'", [1, 'aaabbb']],
    'selectC.test selectC-1.9 grouped expression equality in having' => ["SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING b||c='aaabbb'", [1, 'aaabbb']],
    'selectC.test selectC-1.10 alias where before grouped projection' => ["SELECT a AS x, b||c AS y FROM t1 WHERE y='aaabbb' GROUP BY x, y", [1, 'aaabbb']],
    'selectC.test selectC-1.11 expression where before grouped projection' => ["SELECT a AS x, b||c AS y FROM t1 WHERE b||c='aaabbb' GROUP BY x, y", [1, 'aaabbb']],
    'selectC.test selectC-1.12.1 distinct upper alias order' => ['SELECT DISTINCT upper(b) AS x FROM t1 ORDER BY x', ['AAA', 'CCC']],
    'selectC.test selectC-1.13.1 upper alias group order' => ['SELECT upper(b) AS x FROM t1 GROUP BY x ORDER BY x', ['AAA', 'CCC']],
    'selectC.test selectC-1.14.1 upper alias order descending' => ['SELECT upper(b) AS x FROM t1 ORDER BY x DESC', ['CCC', 'AAA', 'AAA']],
    'selectC.test selectC-4.2 distinct subquery keeps duplicate projected a rows' => ['SELECT a FROM (SELECT DISTINCT a, b FROM t_distinct_bug)', ['1', '1', '1']],
    'selectC.test selectC-5.1 compound view rows preserve left order before right arm' => ['SELECT * FROM (SELECT b FROM vvv UNION ALL SELECT c from x3)', [21, 22, 23, 24, 25, 302, 303, 301]],
    'selectC.test selectC-5.2 cross join materialized compound table' => ['SELECT * FROM x1, (SELECT b FROM vvv UNION ALL SELECT c from x3)', ['a', 21, 'a', 22, 'a', 23, 'a', 24, 'a', 25, 'a', 302, 'a', 303, 'a', 301, 'b', 21, 'b', 22, 'b', 23, 'b', 24, 'b', 25, 'b', 302, 'b', 303, 'b', 301]],
];

foreach ($selectCCases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($selectCTables, $sql, $expected, $assertFlatValues, $flatValues, $name): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $selectCTables()), $flatValues);
        $t->contains('selectC.test', $name);
        $t->contains('SELECT', $sql);
    };
}

for ($seed = 1; $seed <= 180; $seed++) {
    $rows = [];
    $expectedPairs = [];
    $targets = [];
    for ($offset = 0; $offset < 12; $offset++) {
        $a = ($offset % 6) + 1;
        $b = 'k' . (($seed + $offset) % 17);
        $c = 'v' . (($seed * 3 + $offset) % 19);
        $rows[] = ['a' => $a, 'b' => $b, 'c' => $c];
        $y = $b . $c;
        if ($offset % 3 !== 1) {
            $targets[$y] = true;
            $expectedPairs[$a . "\0" . $y] = [$a, $y];
        }
    }
    usort($expectedPairs, static fn (array $left, array $right): int => ($left[0] <=> $right[0]) ?: strcmp($left[1], $right[1]));
    $expected = [];
    foreach ($expectedPairs as $pair) {
        $expected[] = $pair[0];
        $expected[] = $pair[1];
    }
    $quotedTargets = implode(',', array_map(static fn (string $value): string => "'" . $value . "'", array_keys($targets)));

    $tests["real upstream corpus selectC.test selectC-1.1 dynamic alias in-list seed {$seed}"] = static function (TestRunner $t) use ($rows, $quotedTargets, $expected, $assertFlatValues, $flatValues, $seed): void {
        $tables = ['t1' => $rows];
        $sql = "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y IN ({$quotedTargets}) ORDER BY x, y";
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $tables), $flatValues);
        $t->contains('selectC-1.1', "selectC.test selectC-1.1 dynamic alias in-list seed {$seed}");
        $t->true(count($expected) >= 12);
    };

    $tests["real upstream corpus selectC.test selectC-1.8 dynamic grouped alias having seed {$seed}"] = static function (TestRunner $t) use ($rows, $quotedTargets, $expected, $assertFlatValues, $flatValues, $seed): void {
        $tables = ['t1' => $rows];
        $sql = "SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING y IN ({$quotedTargets}) ORDER BY x, y";
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $tables), $flatValues);
        $t->contains('selectC-1.8', "selectC.test selectC-1.8 dynamic grouped alias having seed {$seed}");
        $t->true(count($expected) >= 12);
    };
}

$selectETables = static function (): array {
    return [
        't1' => [
            ['a' => 'abc'],
            ['a' => 'def'],
            ['a' => 'ghi'],
        ],
        't2' => [
            ['a' => 'DEF'],
            ['a' => 'abc'],
        ],
        't3' => [
            ['a' => 'def'],
            ['a' => 'jkl'],
        ],
    ];
};

$selectECases = [
    'selectE.test selectE-1.0 except result ordered nocase without changing except collation' => ['SELECT a FROM t1 EXCEPT SELECT a FROM t2 ORDER BY a COLLATE nocase', ['def', 'ghi']],
    'selectE.test selectE-1.1 except t2 minus t3 ordered nocase' => ['SELECT a FROM t2 EXCEPT SELECT a FROM t3 ORDER BY a COLLATE nocase', ['abc', 'DEF']],
    'selectE.test selectE-1.2 except t2 minus t3 ordered binary' => ['SELECT a FROM t2 EXCEPT SELECT a FROM t3 ORDER BY a COLLATE binary', ['DEF', 'abc']],
    'selectE.test selectE-1.3 except t2 minus t3 default binary order' => ['SELECT a FROM t2 EXCEPT SELECT a FROM t3 ORDER BY a', ['DEF', 'abc']],
];

foreach ($selectECases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($selectETables, $sql, $expected, $assertFlatValues, $flatValues, $name): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $selectETables()), $flatValues);
        $t->contains('selectE.test', $name);
        $t->contains('EXCEPT', $sql);
    };
}

$selectFTablesFor = static function (int $seed): array {
    return [
        't1' => [
            ['a' => 1, 'b' => 'one-' . $seed, 'c' => 'I'],
            ['a' => 2, 'b' => 'one-' . ($seed + 1), 'c' => 'II'],
        ],
        't2' => [
            ['d' => 5 + $seed, 'e' => 'ten-' . $seed, 'f' => 'XX'],
            ['d' => 6 + $seed, 'e' => null, 'f' => null],
        ],
    ];
};

foreach ([0, 1, 2, 3, 4, 5, 6, 7] as $seed) {
    $tests["real upstream corpus selectF.test selectF-2 dynamic wildcard compound order seed {$seed}"] = static function (TestRunner $t) use ($selectFTablesFor, $seed, $assertFlatValues, $flatValues): void {
        $tables = $selectFTablesFor($seed);
        $sql = 'SELECT * FROM t2 UNION ALL SELECT * FROM t1 WHERE a<5 ORDER BY 2, 1';
        $expected = [
            6 + $seed,
            null,
            null,
            1,
            'one-' . $seed,
            'I',
            2,
            'one-' . ($seed + 1),
            'II',
            5 + $seed,
            'ten-' . $seed,
            'XX',
        ];
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $tables), $flatValues);
        $t->contains('selectF.test', "selectF.test selectF-2 dynamic wildcard compound order seed {$seed}");
        $t->contains('ORDER BY 2, 1', $sql);
    };
}

foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $upper) {
    $tests["real upstream corpus selectF.test selectF-2 dynamic wildcard compound filtered upper {$upper}"] = static function (TestRunner $t) use ($upper, $assertFlatValues, $flatValues): void {
        $tables = [
            't1' => [
                ['a' => 1, 'b' => 'one', 'c' => 'I'],
                ['a' => 2, 'b' => 'two', 'c' => 'II'],
                ['a' => 3, 'b' => 'three', 'c' => 'III'],
            ],
            't2' => [
                ['d' => 5, 'e' => 'ten', 'f' => 'XX'],
                ['d' => 6, 'e' => null, 'f' => null],
                ['d' => 7, 'e' => 'seven', 'f' => 'VII'],
            ],
        ];
        $sql = "SELECT * FROM t2 WHERE d<={$upper} UNION ALL SELECT * FROM t1 WHERE a<={$upper} ORDER BY 2, 1";
        $rows = [];
        foreach ($tables['t2'] as $row) {
            if ($row['d'] <= $upper) {
                $rows[] = ['d' => $row['d'], 'e' => $row['e'], 'f' => $row['f']];
            }
        }
        foreach ($tables['t1'] as $row) {
            if ($row['a'] <= $upper) {
                $rows[] = ['d' => $row['a'], 'e' => $row['b'], 'f' => $row['c']];
            }
        }
        usort($rows, static function (array $left, array $right): int {
            $leftKey = $left['e'];
            $rightKey = $right['e'];
            if ($leftKey === null && $rightKey !== null) {
                return -1;
            }
            if ($leftKey !== null && $rightKey === null) {
                return 1;
            }
            $text = $leftKey <=> $rightKey;

            return $text !== 0 ? $text : ($left['d'] <=> $right['d']);
        });
        $expected = $flatValues($rows);
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $tables), $flatValues);
        $t->contains('selectF.test', "selectF.test selectF-2 dynamic wildcard compound filtered upper {$upper}");
        $t->true(count($expected) >= min($upper, 3) * 3);
    };
}

return $tests;
