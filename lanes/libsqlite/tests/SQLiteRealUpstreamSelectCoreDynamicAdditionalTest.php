<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select2Tables = static function (): array {
    $tbl1 = [];
    for ($i = 0; $i <= 30; $i++) {
        $tbl1[] = ['f1' => $i % 9, 'f2' => $i % 10];
    }

    $tbl2 = [];
    for ($i = 1; $i <= 30000; $i++) {
        $tbl2[] = ['f1' => $i, 'f2' => $i * 2, 'f3' => $i * 3];
    }

    return [
        'tbl1' => $tbl1,
        'tbl2' => $tbl2,
        'aa' => [['a' => 1], ['a' => 3]],
        'bb' => [['b' => 2], ['b' => 4], ['b' => 0]],
    ];
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectLogTables = static function (): array {
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
        't2' => [
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 1, 'b' => 4, 'c' => 5],
            ['a' => 6, 'b' => 4, 'c' => 7],
        ],
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

$select2Cases = [
    'select2.test select2-1.1 distinct outer f1 values' => ['SELECT DISTINCT f1 FROM tbl1 ORDER BY f1', [0, 1, 2, 3, 4, 5, 6, 7, 8]],
    'select2.test select2-1.1 nested f2 values for f1 0' => ['SELECT f2 FROM tbl1 WHERE f1=0 ORDER BY f2', [0, 7, 8, 9]],
    'select2.test select2-1.1 nested f2 values for f1 1' => ['SELECT f2 FROM tbl1 WHERE f1=1 ORDER BY f2', [0, 1, 8, 9]],
    'select2.test select2-1.1 nested f2 values for f1 2' => ['SELECT f2 FROM tbl1 WHERE f1=2 ORDER BY f2', [0, 1, 2, 9]],
    'select2.test select2-1.1 nested f2 values for f1 3' => ['SELECT f2 FROM tbl1 WHERE f1=3 ORDER BY f2', [0, 1, 2, 3]],
    'select2.test select2-1.1 nested f2 values for f1 4' => ['SELECT f2 FROM tbl1 WHERE f1=4 ORDER BY f2', [2, 3, 4]],
    'select2.test select2-1.1 nested f2 values for f1 5' => ['SELECT f2 FROM tbl1 WHERE f1=5 ORDER BY f2', [3, 4, 5]],
    'select2.test select2-1.1 nested f2 values for f1 6' => ['SELECT f2 FROM tbl1 WHERE f1=6 ORDER BY f2', [4, 5, 6]],
    'select2.test select2-1.1 nested f2 values for f1 7' => ['SELECT f2 FROM tbl1 WHERE f1=7 ORDER BY f2', [5, 6, 7]],
    'select2.test select2-1.1 nested f2 values for f1 8' => ['SELECT f2 FROM tbl1 WHERE f1=8 ORDER BY f2', [6, 7, 8]],
    'select2.test select2-1.2 distinct bounded f1 predicate' => ['SELECT DISTINCT f1 FROM tbl1 WHERE f1>3 AND f1<5', [4]],
    'select2.test select2-2.1 large table count' => ['SELECT count(*) FROM tbl2', [30000]],
    'select2.test select2-2.2 large table residual greater-than count' => ['SELECT count(*) FROM tbl2 WHERE f2>1000', [29500]],
    'select2.test select2-3.1 commuted equality finds row' => ['SELECT f1 FROM tbl2 WHERE 1000=f2', [500]],
    'select2.test select2-3.2c direct equality finds same row' => ['SELECT f1 FROM tbl2 WHERE f2=1000', [500]],
    'select2.test select2-4.2 cross join truthy column' => ['SELECT * FROM aa CROSS JOIN bb WHERE b', [1, 2, 1, 4, 3, 2, 3, 4]],
    'select2.test select2-4.3 cross join not truthy column' => ['SELECT * FROM aa CROSS JOIN bb WHERE NOT b', [1, 0, 3, 0]],
    'select2.test select2-4.4 min function truthy predicate' => ['SELECT * FROM aa, bb WHERE min(a,b)', [1, 2, 1, 4, 3, 2, 3, 4]],
    'select2.test select2-4.5 not min function predicate' => ['SELECT * FROM aa, bb WHERE NOT min(a,b)', [1, 0, 3, 0]],
];

foreach ([1, 10, 100, 500, 1000, 5000, 10000, 20000, 40000, 59000] as $threshold) {
    $select2Cases["select2.test select2-2.2 dynamic f2 greater than {$threshold}"] = [
        "SELECT count(*) FROM tbl2 WHERE f2>{$threshold}",
        [max(0, 30000 - intdiv($threshold, 2))],
    ];
}

foreach ([2, 4, 6, 20, 200, 1000, 2000, 12000, 40000, 60000] as $value) {
    $expected = $value % 2 === 0 && $value >= 2 && $value <= 60000 ? [$value / 2] : [];
    $select2Cases["select2.test select2-3.2c dynamic f2 equality {$value}"] = [
        "SELECT f1 FROM tbl2 WHERE f2={$value}",
        $expected,
    ];
    $select2Cases["select2.test select2-3.1 dynamic commuted f2 equality {$value}"] = [
        "SELECT f1 FROM tbl2 WHERE {$value}=f2",
        $expected,
    ];
}

foreach ([[1, 10], [11, 20], [101, 200], [501, 600], [1001, 1100], [5001, 5100], [10001, 10100], [20001, 20200], [29801, 30000], [30001, 30100]] as [$lo, $hi]) {
    $select2Cases["select2.test select2-2.2 dynamic f1 closed range {$lo} {$hi}"] = [
        "SELECT count(*) FROM tbl2 WHERE f1>={$lo} AND f1<={$hi}",
        [max(0, min(30000, $hi) - max(1, $lo) + 1)],
    ];
}

foreach ([[2, 20], [200, 400], [1000, 1200], [2000, 2400], [10000, 10400], [20000, 20400], [40000, 40400], [59000, 60000]] as [$lo, $hi]) {
    $select2Cases["select2.test select2-2.2 dynamic f2 closed range {$lo} {$hi}"] = [
        "SELECT count(*) FROM tbl2 WHERE f2>={$lo} AND f2<={$hi}",
        [max(0, intdiv(min(60000, $hi), 2) - intdiv(max(2, $lo) - 1, 2))],
    ];
}

foreach ([[3, 30], [300, 600], [1500, 1800], [3000, 3600], [15000, 15600], [30000, 30600], [87000, 90000]] as [$lo, $hi]) {
    $select2Cases["select2.test select2-2.2 dynamic f3 closed range {$lo} {$hi}"] = [
        "SELECT count(*) FROM tbl2 WHERE f3>={$lo} AND f3<={$hi}",
        [max(0, intdiv(min(90000, $hi), 3) - intdiv(max(3, $lo) - 1, 3))],
    ];
}

foreach ([1, 7, 31, 63, 127, 511, 1023, 2047, 8191, 16383, 24575, 32767, 49151, 65535, 81919, 89999] as $threshold) {
    $select2Cases["select2.test select2-2.2 dynamic f3 greater than {$threshold}"] = [
        "SELECT count(*) FROM tbl2 WHERE f3>{$threshold}",
        [max(0, 30000 - intdiv($threshold, 3))],
    ];
}

foreach ($select2Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($select2Tables, $sql, $expected, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $select2Tables()), $flatValues);
    };
}

$select4Cases = [
    'select4.test select4-1.1c union all ordered rows' => ['SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log=3 ORDER BY log', [0, 1, 2, 3, 4, 5, 5, 6, 7, 8]],
    'select4.test select4-1.1e union all ordered descending rows' => ['SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log=3 ORDER BY log DESC', [8, 7, 6, 5, 5, 4, 3, 2, 1, 0]],
    'select4.test select4-1.1f union all without final order' => ['SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log=2', [0, 1, 2, 3, 4, 5, 3, 4]],
    'select4.test select4-2.1 union distinct ordered rows' => ['SELECT DISTINCT log FROM t1 UNION SELECT n FROM t1 WHERE log=3 ORDER BY log', [0, 1, 2, 3, 4, 5, 6, 7, 8]],
    'select4.test select4-3.1.1 except ordered rows' => ['SELECT DISTINCT log FROM t1 EXCEPT SELECT n FROM t1 WHERE log=3 ORDER BY log', [0, 1, 2, 3, 4]],
    'select4.test select4-3.1.3 except ordered descending rows' => ['SELECT DISTINCT log FROM t1 EXCEPT SELECT n FROM t1 WHERE log=3 ORDER BY log DESC', [4, 3, 2, 1, 0]],
    'select4.test select4-4.1.1 intersect ordered row' => ['SELECT DISTINCT log FROM t1 INTERSECT SELECT n FROM t1 WHERE log=3 ORDER BY log', [5]],
    'select4.test select4-4.1.2 union-all intersect precedence rows' => ['SELECT DISTINCT log FROM t1 UNION ALL SELECT 6 INTERSECT SELECT n FROM t1 WHERE log=3 ORDER BY log', [5, 6]],
    'select4.test select4-5.2i compound order by second then first' => ['SELECT DISTINCT 1, log FROM t1 UNION ALL SELECT 2, n FROM t1 WHERE log=3 ORDER BY 2, 1', [1, 0, 1, 1, 1, 2, 1, 3, 1, 4, 1, 5, 2, 5, 2, 6, 2, 7, 2, 8]],
    'select4.test select4-5.2j compound order by first then second descending' => ['SELECT DISTINCT 1, log FROM t1 UNION ALL SELECT 2, n FROM t1 WHERE log=3 ORDER BY 1, 2 DESC', [1, 5, 1, 4, 1, 3, 1, 2, 1, 1, 1, 0, 2, 8, 2, 7, 2, 6, 2, 5]],
    'select4.test select4-5.4 chained union all ordered rows' => ['SELECT log FROM t1 WHERE n=2 UNION ALL SELECT log FROM t1 WHERE n=3 UNION ALL SELECT log FROM t1 WHERE n=4 UNION ALL SELECT log FROM t1 WHERE n=5 ORDER BY log', [1, 2, 2, 3]],
    'select4.test select4-6.1 group union ordered by alias' => ['SELECT log, count(*) as cnt FROM t1 GROUP BY log UNION SELECT log, n FROM t1 WHERE n=7 ORDER BY cnt, log', [0, 1, 1, 1, 2, 2, 3, 4, 3, 7, 4, 8, 5, 15]],
    'select4.test select4-6.3 nulls indistinct for union' => ['SELECT NULL UNION SELECT NULL UNION SELECT 1 UNION SELECT 2 AS x ORDER BY x', [null, 1, 2]],
    'select4.test select4-6.3.1 nulls preserved for union all' => ['SELECT NULL UNION ALL SELECT NULL UNION ALL SELECT 1 UNION ALL SELECT 2 AS x ORDER BY x', [null, null, 1, 2]],
    'select4.test select4-6.7 null except null gives empty rowset' => ['SELECT NULL EXCEPT SELECT NULL', []],
];

foreach ($select4Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($selectLogTables, $sql, $expected, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $selectLogTables()), $flatValues);
    };
}

$select5Cases = [
    'select5.test select5-1.0 distinct group source y values' => ['SELECT DISTINCT y FROM t1 ORDER BY y', [5, 6, 7, 8, 9, 10]],
    'select5.test select5-1.1 grouped count ordered by group' => ['SELECT y, count(*) FROM t1 GROUP BY y ORDER BY y', [5, 15, 6, 8, 7, 4, 8, 2, 9, 1, 10, 1]],
    'select5.test select5-1.2 grouped count ordered by aggregate' => ['SELECT y, count(*) FROM t1 GROUP BY y ORDER BY count(*), y', [9, 1, 10, 1, 8, 2, 7, 4, 6, 8, 5, 15]],
    'select5.test select5-1.3 aggregate first column order' => ['SELECT count(*), y FROM t1 GROUP BY y ORDER BY count(*), y', [1, 9, 1, 10, 2, 8, 4, 7, 8, 6, 15, 5]],
    'select5.test select5-2.3 having aggregate predicate' => ['SELECT y, count(*) FROM t1 GROUP BY y HAVING count(*)<3 ORDER BY y', [8, 2, 9, 1, 10, 1]],
    'select5.test select5-3.1 grouped aggregate with having column' => ['SELECT x, count(*), avg(y) FROM t1 GROUP BY x HAVING x<4 ORDER BY x', [1, 1, 5.0, 2, 1, 5.0, 3, 1, 5.0]],
    'select5.test select5-4.1 empty avg aggregate is null' => ['SELECT avg(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.2 empty count aggregate is zero' => ['SELECT count(x) FROM t1 WHERE x>100', [0]],
    'select5.test select5-4.3 empty min aggregate is null' => ['SELECT min(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.4 empty max aggregate is null' => ['SELECT max(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.5 empty sum aggregate is null' => ['SELECT sum(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-6.1 nulls group equal for one column' => ['SELECT count(x), y FROM t3 GROUP BY y ORDER BY 1', [1, 4, 2, null]],
    'select5.test select5-6.2 nulls group equal for composite key' => ['SELECT max(x), count(x), y, z FROM t4 GROUP BY y, z ORDER BY 1', [1, 1, 2, null, 2, 1, 3, null, 3, 1, null, 5, 4, 2, null, 6, 5, 2, null, null, 6, 1, 7, 8]],
    'select5.test select5-7.2 group order by count alias' => ['SELECT count(*), count(x) as cnt FROM t4 GROUP BY y ORDER BY cnt', [1, 1, 1, 1, 1, 1, 5, 5]],
    'select5.test select5-8.1 join grouped count by equality' => ['SELECT a, count(b) FROM t8a, t8b WHERE b=t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test select5-8.4 join grouped count star by equality' => ['SELECT a, count(*) FROM t8a, t8b WHERE b=t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test select5-8.5 join grouped count by inequality' => ['SELECT a, count(b) FROM t8a, t8b WHERE b<x GROUP BY a ORDER BY a', ['one', 6, 'two', 3]],
    'select5.test select5-8.6 join grouped count ordered by aggregate' => ['SELECT a, count(b) FROM t8a, t8b WHERE b=t8b.rowid GROUP BY a ORDER BY 2', ['two', 1, 'one', 2]],
    'select5.test select5-8.7 cross join grouped count non-null column' => ['SELECT a, count(b) FROM t8a, t8b GROUP BY a ORDER BY 2', ['two', 3, 'one', 6]],
    'select5.test select5-8.8 cross join grouped count star' => ['SELECT a, count(*) FROM t8a, t8b GROUP BY a ORDER BY 2', ['two', 3, 'one', 9]],
];

foreach ($select5Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($select5Tables, $sql, $expected, $assertFlatValues, $flatValues): void {
        $assertFlatValues($t, $expected, SQLiteSelectSql::execute($sql, $select5Tables()), $flatValues);
    };
}

return $tests;
