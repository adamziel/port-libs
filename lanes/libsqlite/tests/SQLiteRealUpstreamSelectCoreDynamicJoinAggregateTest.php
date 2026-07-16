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
    $actualFlat = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expectedFlat, $actualFlat, $sql);
    $t->same(count($expectedFlat), count($actualFlat), 'flat value count for ' . $sql);
    $t->same($expectedFlat === [] ? [] : [$expectedFlat[0], $expectedFlat[array_key_last($expectedFlat)]], $actualFlat === [] ? [] : [$actualFlat[0], $actualFlat[array_key_last($actualFlat)]], 'first/last values for ' . $sql);
    foreach ($expectedFlat as $index => $expectedValue) {
        $t->same($expectedValue, $actualFlat[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
    $t->same(md5(json_encode($expectedFlat, JSON_THROW_ON_ERROR)), md5(json_encode($actualFlat, JSON_THROW_ON_ERROR)), 'flat value fingerprint for ' . $sql);
    $t->true(str_starts_with(strtolower(ltrim($sql)), 'select'), 'query is a SELECT statement');
};

$addCases = static function (array &$tests, string $prefix, array $cases, array $tables) use ($assertSelect): void {
    foreach ($cases as $name => [$sql, $expected]) {
        $tests[$prefix . ' ' . $name] = static function (TestRunner $t) use ($sql, $tables, $expected, $assertSelect, $name): void {
            $assertSelect($t, $sql, $tables, $expected);
            $t->contains(substr($name, 0, strpos($name, ' ') ?: strlen($name)), $name);
        };
    }
};

$selectDocTables = [
    't1' => [
        ['a' => 'a', 'b' => 'one'],
        ['a' => 'b', 'b' => 'two'],
        ['a' => 'c', 'b' => 'three'],
    ],
    't2' => [
        ['a' => 'a', 'b' => 'I'],
        ['a' => 'b', 'b' => 'II'],
        ['a' => 'c', 'b' => 'III'],
    ],
    't3' => [
        ['a' => 'a', 'c' => 1],
        ['a' => 'b', 'c' => 2],
    ],
    't4' => [
        ['a' => 'a', 'c' => null],
        ['a' => 'b', 'c' => 2],
    ],
];

$joinCases = [];
foreach ([',' => 'comma', 'JOIN' => 'plain join', 'INNER JOIN' => 'inner join', 'CROSS JOIN' => 'cross join'] as $joinOperator => $label) {
    $joinCases["e_select.test e_select-0.1.1 {$label} on constraint"] = [
        "SELECT count(*) FROM t1 {$joinOperator} t2 ON (t1.a=t2.a)",
        [3],
    ];
    if ($joinOperator !== ',') {
        $joinCases["e_select.test e_select-0.1.2 {$label} using constraint"] = [
            "SELECT count(*) FROM t1 {$joinOperator} t2 USING (a)",
            [3],
        ];
    }
    $joinCases["e_select.test e_select-0.1.3 {$label} unconstrained cross product"] = [
        "SELECT count(*) FROM t1 {$joinOperator} t2",
        [9],
    ];
}
$joinCases['e_select.test e_select-0.2.0100.1 from projection concat'] = [
    'SELECT a, b, a||b FROM t1 ORDER BY a',
    ['a', 'one', 'aone', 'b', 'two', 'btwo', 'c', 'three', 'cthree'],
];
$joinCases['e_select.test e_select-0.2.0110.1 where projection concat'] = [
    "SELECT a, b, a||b FROM t1 WHERE a!='x' ORDER BY a",
    ['a', 'one', 'aone', 'b', 'two', 'btwo', 'c', 'three', 'cthree'],
];
$joinCases['e_select.test e_select-0.2.0110.2 empty where projection concat'] = [
    "SELECT a, b, a||b FROM t1 WHERE a=='x' ORDER BY a",
    [],
];
$joinCases['e_select.test e_select-0.2.1110.1 distinct where projection concat'] = [
    "SELECT DISTINCT a, b, a||b FROM t1 WHERE a!='x' ORDER BY a",
    ['a', 'one', 'aone', 'b', 'two', 'btwo', 'c', 'three', 'cthree'],
];
$joinCases['e_select.test e_select-0.2.2110.0 select all empty where projection'] = [
    "SELECT ALL a, b, a||b FROM t1 WHERE a=='x' ORDER BY a",
    [],
];
$joinCases['e_select.test e_select-0.2.0111.1 grouped filtered aggregate'] = [
    "SELECT count(*), max(a) FROM t1 WHERE a='a' GROUP BY b ORDER BY b",
    [1, 'a'],
];
$joinCases['e_select.test e_select-0.2.1111.1 distinct grouped filtered aggregate'] = [
    "SELECT DISTINCT count(*), max(a) FROM t1 WHERE a<'c' GROUP BY b ORDER BY b",
    [1, 'a', 1, 'b'],
];
$joinCases['e_select.test e_select-0.2.2111.1 select all grouped filtered aggregate'] = [
    "SELECT ALL count(*), max(a) FROM t1 WHERE b>'one' GROUP BY b ORDER BY b",
    [1, 'c', 1, 'b'],
];

$tests['real upstream corpus select core dynamic e_select join and select-core group'] = static function (TestRunner $t) use ($joinCases, $selectDocTables, $assertSelect): void {
    foreach ($joinCases as $name => [$sql, $expected]) {
        $assertSelect($t, $sql, $selectDocTables, $expected);
        $t->contains('e_select.test', $name);
    }
};
$addCases($tests, 'real upstream corpus select core dynamic', $joinCases, $selectDocTables);

$select5Rows = [];
for ($i = 1; $i < 32; $i++) {
    $j = 0;
    while ((1 << $j) < $i) {
        $j++;
    }
    $select5Rows[] = ['x' => 32 - $i, 'y' => 10 - $j];
}
$select5Tables = [
    't1' => $select5Rows,
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
    'app_metrics' => [
        ['tenant_id' => 1, 'kind' => 'read', 'value' => 4],
        ['tenant_id' => 1, 'kind' => 'write', 'value' => 6],
        ['tenant_id' => 2, 'kind' => 'read', 'value' => 3],
        ['tenant_id' => 2, 'kind' => 'write', 'value' => null],
    ],
];

$select5Cases = [
    'select5.test select5-1.0 distinct y ordered' => ['SELECT DISTINCT y FROM t1 ORDER BY y', [5, 6, 7, 8, 9, 10]],
    'select5.test select5-1.1 grouped count ordered by y' => ['SELECT y, count(*) FROM t1 GROUP BY y ORDER BY y', [5, 15, 6, 8, 7, 4, 8, 2, 9, 1, 10, 1]],
    'select5.test select5-1.2 grouped count ordered by aggregate' => ['SELECT y, count(*) FROM t1 GROUP BY y ORDER BY count(*), y', [9, 1, 10, 1, 8, 2, 7, 4, 6, 8, 5, 15]],
    'select5.test select5-1.3 aggregate first ordered by aggregate' => ['SELECT count(*), y FROM t1 GROUP BY y ORDER BY count(*), y', [1, 9, 1, 10, 2, 8, 4, 7, 8, 6, 15, 5]],
    'select5.test select5-2.3 grouped having count less than three' => ['SELECT y, count(*) FROM t1 GROUP BY y HAVING count(*)<3 ORDER BY y', [8, 2, 9, 1, 10, 1]],
    'select5.test select5-3.1 grouped by x with avg and having' => ['SELECT x, count(*), avg(y) FROM t1 GROUP BY x HAVING x<4 ORDER BY x', [1, 1, 5.0, 2, 1, 5.0, 3, 1, 5.0]],
    'select5.test select5-4.1 empty avg aggregate' => ['SELECT avg(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.2 empty count aggregate' => ['SELECT count(x) FROM t1 WHERE x>100', [0]],
    'select5.test select5-4.3 empty min aggregate' => ['SELECT min(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.4 empty max aggregate' => ['SELECT max(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-4.5 empty sum aggregate' => ['SELECT sum(x) FROM t1 WHERE x>100', [null]],
    'select5.test select5-6.1 nulls group together' => ['SELECT count(x), y FROM t3 GROUP BY y ORDER BY 1', [1, 4, 2, null]],
    'select5.test select5-6.2 null composite groups' => ['SELECT max(x), count(x), y, z FROM t4 GROUP BY y, z ORDER BY 1', [1, 1, 2, null, 2, 1, 3, null, 3, 1, null, 5, 4, 2, null, 6, 5, 2, null, null, 6, 1, 7, 8]],
    'select5.test select5-7.2 grouped counts ordered by alias' => ['SELECT count(*), count(x) as cnt FROM t4 GROUP BY y ORDER BY cnt', [1, 1, 1, 1, 1, 1, 5, 5]],
    'select5.test select5-8.1 join grouped count by text key' => ['SELECT a, count(b) FROM t8a, t8b WHERE b=t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test select5-8.2 join grouped count by unary rowid' => ['SELECT a, count(b) FROM t8a, t8b WHERE b=+t8b.rowid GROUP BY a ORDER BY a', ['one', 2, 'two', 1]],
    'select5.test application grouped count sum by tenant' => ['SELECT tenant_id, count(value), sum(value) FROM app_metrics GROUP BY tenant_id ORDER BY tenant_id', [1, 2, 10, 2, 1, 3]],
    'select5.test application having over aggregate sum' => ['SELECT tenant_id, sum(value) FROM app_metrics GROUP BY tenant_id HAVING sum(value)>=3 ORDER BY sum(value)', [2, 3, 1, 10]],
];

foreach ([5, 6, 7, 8, 9, 10] as $y) {
    $count = 0;
    $min = null;
    $max = null;
    foreach ($select5Rows as $row) {
        if ($row['y'] !== $y) {
            continue;
        }
        $count++;
        $min = $min === null ? $row['x'] : min($min, $row['x']);
        $max = $max === null ? $row['x'] : max($max, $row['x']);
    }
    $select5Cases["select5.test select5-1 dynamic y {$y} filtered aggregate"] = [
        "SELECT count(*), min(x), max(x) FROM t1 WHERE y={$y}",
        [$count, $min, $max],
    ];
    $select5Cases["select5.test select5-1 dynamic y {$y} ordered x slice"] = [
        "SELECT x, y FROM t1 WHERE y={$y} ORDER BY x LIMIT 3",
        array_merge(...array_map(
            static fn (array $row): array => [$row['x'], $row['y']],
            array_slice((static function (array $rows): array {
                usort($rows, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

                return $rows;
            })(array_values(array_filter($select5Rows, static fn (array $row): bool => $row['y'] === $y))), 0, 3),
        )),
    ];
}

$tests['real upstream corpus select core dynamic select5 aggregate group and null batch'] = static function (TestRunner $t) use ($select5Cases, $select5Tables, $assertSelect): void {
    foreach ($select5Cases as $name => [$sql, $expected]) {
        $assertSelect($t, $sql, $select5Tables, $expected);
        $t->contains('select5.test', $name);
    }
};
$addCases($tests, 'real upstream corpus select core dynamic', $select5Cases, $select5Tables);

return $tests;
