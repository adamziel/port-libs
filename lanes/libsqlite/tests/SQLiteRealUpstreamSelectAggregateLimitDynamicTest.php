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

$assertSelect = static function (TestRunner $t, string $sql, array $tables, array $expectedFlat) use ($flattenRows): void {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $flat = $flattenRows($rows);

    $t->same($expectedFlat, $flat, $sql);
    $t->same(count($expectedFlat), count($flat), 'flat value count for ' . $sql);
    $t->same(
        $expectedFlat === [] ? [] : [$expectedFlat[0], $expectedFlat[array_key_last($expectedFlat)]],
        $flat === [] ? [] : [$flat[0], $flat[array_key_last($flat)]],
        'first/last values for ' . $sql
    );
    foreach ($expectedFlat as $index => $expectedValue) {
        $t->same($expectedValue, $flat[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
};

$select5T1 = [];
for ($i = 1; $i < 32; $i++) {
    $j = 0;
    while ((1 << $j) < $i) {
        $j++;
    }
    $select5T1[] = ['x' => 32 - $i, 'y' => 10 - $j];
}

$select5Tables = [
    't1' => $select5T1,
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

$select5Cases = [
    'select5.test select5-1.0 distinct y ordered' => ['SELECT DISTINCT y FROM t1 ORDER BY y', [5, 6, 7, 8, 9, 10]],
    'select5.test select5-1.1 group count ordered by y' => ['SELECT y, count(*) FROM t1 GROUP BY y ORDER BY y', [5, 15, 6, 8, 7, 4, 8, 2, 9, 1, 10, 1]],
    'select5.test select5-1.2 group count ordered by aggregate' => ['SELECT y, count(*) FROM t1 GROUP BY y ORDER BY count(*), y', [9, 1, 10, 1, 8, 2, 7, 4, 6, 8, 5, 15]],
    'select5.test select5-1.3 aggregate first ordered by aggregate' => ['SELECT count(*), y FROM t1 GROUP BY y ORDER BY count(*), y', [1, 9, 1, 10, 2, 8, 4, 7, 8, 6, 15, 5]],
    'select5.test select5-2.3 having aggregate less than three' => ['SELECT y, count(*) FROM t1 GROUP BY y HAVING count(*)<3 ORDER BY y', [8, 2, 9, 1, 10, 1]],
    'select5.test select5-3.1 aggregate rehash boundary' => ['SELECT x, count(*), avg(y) FROM t1 GROUP BY x HAVING x<4 ORDER BY x', [1, 1, 5.0, 2, 1, 5.0, 3, 1, 5.0]],
    'select5.test select5-4.1 avg empty rowset' => ['SELECT avg(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.2 count empty rowset' => ['SELECT count(x) FROM t1 WHERE x>100', [0]],
    'select5.test select5-4.3 min empty rowset' => ['SELECT min(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.4 max empty rowset' => ['SELECT max(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.5 sum empty rowset' => ['SELECT sum(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-5.11 expression group by render columns' => ['SELECT max(c), b*a, b, a FROM t2 GROUP BY b*a, b, a', [3, 2, 2, 1, 5, 4, 4, 1, 7, 24, 4, 6]],
    'select5.test select5-6.1 nulls compare equal for group by' => ['SELECT count(x), y FROM t3 GROUP BY y ORDER BY 1', [1, 4, 2, null]],
    'select5.test select5-6.2 composite null group by' => ['SELECT max(x), count(x), y, z FROM t4 GROUP BY y, z ORDER BY 1', [1, 1, 2, null, 2, 1, 3, null, 3, 1, null, 5, 4, 2, null, 6, 5, 2, null, null, 6, 1, 7, 8]],
    'select5.test select5-7.2 count alias order' => ['SELECT count(*), count(x) as cnt FROM t4 GROUP BY y ORDER BY cnt', [1, 1, 1, 1, 1, 1, 5, 5]],
    'select5.test select5-8.1 join rowid group count' => ['SELECT a, count(b) FROM t8a, t8b WHERE b=t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test select5-8.2 unary plus join rowid group count' => ['SELECT a, count(b) FROM t8a, t8b WHERE b=+t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test select5-8.4 join count star' => ['SELECT a, count(*) FROM t8a, t8b WHERE b=+t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test select5-8.5 inequality join grouped count' => ['SELECT a, count(b) FROM t8a, t8b WHERE b<x GROUP BY a ORDER BY a', ['one', 6, 'two', 3]],
    'select5.test select5-8.6 aggregate ordinal order' => ['SELECT a, count(t8a.b) FROM t8a, t8b WHERE b=t8b.rowid GROUP BY a ORDER BY 2', ['two', 1, 'one', 2]],
];

foreach ($select5Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus select aggregate limit dynamic ' . $name] = static function (TestRunner $t) use ($assertSelect, $select5Tables, $sql, $expected, $name): void {
        $assertSelect($t, $sql, $select5Tables, $expected);
        $t->contains('select5.test', $name);
    };
}

foreach ([5, 6, 7, 8, 9, 10] as $y) {
    $matching = array_values(array_filter($select5T1, static fn (array $row): bool => $row['y'] === $y));
    $xs = array_map(static fn (array $row): int => $row['x'], $matching);
    sort($xs);

    $dynamicCases = [
        "select5.test select5-1.1 dynamic y {$y} grouped count" => [
            "SELECT y, count(*) FROM t1 WHERE y={$y} GROUP BY y",
            [$y, count($matching)],
        ],
        "select5.test select5-1.1 dynamic y {$y} min max x" => [
            "SELECT y, min(x), max(x) FROM t1 WHERE y={$y} GROUP BY y",
            [$y, min($xs), max($xs)],
        ],
        "select5.test select5-1.1 dynamic y {$y} ordered x slice" => [
            "SELECT x FROM t1 WHERE y={$y} ORDER BY x",
            $xs,
        ],
        "select5.test select5-2.3 dynamic y {$y} having boundary" => [
            "SELECT y, count(*) FROM t1 WHERE y={$y} GROUP BY y HAVING count(*)<3",
            count($matching) < 3 ? [$y, count($matching)] : [],
        ],
    ];

    foreach ($dynamicCases as $name => [$sql, $expected]) {
        $tests['real upstream corpus select aggregate limit dynamic ' . $name] = static function (TestRunner $t) use ($assertSelect, $select5Tables, $sql, $expected, $name): void {
            $assertSelect($t, $sql, $select5Tables, $expected);
            $t->contains('select5.test', $name);
        };
    }
}

foreach ([1, 2, 3, 4, 8, 16] as $upperX) {
    $matching = array_values(array_filter($select5T1, static fn (array $row): bool => $row['x'] <= $upperX));
    $ys = array_map(static fn (array $row): int => $row['y'], $matching);
    $expected = [];
    $uniqueYs = array_values(array_unique($ys));
    sort($uniqueYs);
    foreach ($uniqueYs as $y) {
        $count = count(array_filter($matching, static fn (array $row): bool => $row['y'] === $y));
        $expected[] = $y;
        $expected[] = $count;
    }

    $name = "select5.test select5-3.1 dynamic x upper {$upperX} grouped counts";
    $tests['real upstream corpus select aggregate limit dynamic ' . $name] = static function (TestRunner $t) use ($assertSelect, $select5Tables, $upperX, $expected, $name): void {
        $assertSelect($t, "SELECT y, count(*) FROM t1 WHERE x<={$upperX} GROUP BY y ORDER BY y", $select5Tables, $expected);
        $t->contains('select5.test', $name);
    };
}

$songs = [
    ['songid' => 1, 'artist' => 'one', 'timesplayed' => 1],
    ['songid' => 2, 'artist' => 'one', 'timesplayed' => 2],
    ['songid' => 3, 'artist' => 'two', 'timesplayed' => 3],
    ['songid' => 4, 'artist' => 'three', 'timesplayed' => 5],
    ['songid' => 5, 'artist' => 'one', 'timesplayed' => 7],
    ['songid' => 6, 'artist' => 'two', 'timesplayed' => 11],
];
$select8Tables = ['songs' => $songs];
$select8BaseSql = 'SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY LOWER(artist)';
$select8Rows = SQLiteSelectSql::execute($select8BaseSql, $select8Tables);
$select8Flat = $flattenRows($select8Rows);

$select8Cases = [
    'select8.test select8-1.0 expression group by baseline' => [$select8BaseSql, $select8Flat],
    'select8.test select8-1.1 expression group by limit one offset one' => [$select8BaseSql . ' LIMIT 1 OFFSET 1', array_slice($select8Flat, 2, 2)],
    'select8.test select8-1.2 expression group by limit two offset one' => [$select8BaseSql . ' LIMIT 2 OFFSET 1', array_slice($select8Flat, 2, 4)],
    'select8.test select8-1.3 expression group by negative limit offset two' => [$select8BaseSql . ' LIMIT -1 OFFSET 2', array_slice($select8Flat, 4)],
];

foreach ($select8Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus select aggregate limit dynamic ' . $name] = static function (TestRunner $t) use ($assertSelect, $select8Tables, $sql, $expected, $name): void {
        $assertSelect($t, $sql, $select8Tables, $expected);
        $t->contains('select8.test', $name);
    };
}

$tests['real upstream corpus select aggregate limit dynamic expression group by rejects missing function'] = static function (TestRunner $t) use ($select8Tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT artist,sum(timesplayed) FROM songs GROUP BY missing_scalar(artist)',
        $select8Tables
    ));
};

return $tests;
