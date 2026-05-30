<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    't1' => [
        ['a' => 1, 'b' => 'a', 'c' => 'a'],
        ['a' => 9.9, 'b' => 'b', 'c' => 'B'],
        ['a' => null, 'b' => 'C', 'c' => 'c'],
        ['a' => 'hello', 'b' => 'd', 'c' => 'D'],
        ['a' => new SQLiteBlobValue('abc'), 'b' => 'e', 'c' => 'e'],
    ],
    't2' => [
        ['x' => null, 'y' => 'U', 'z' => 'u'],
        ['x' => 'mad', 'y' => 'Z', 'z' => 'z'],
        ['x' => new SQLiteBlobValue('hare'), 'y' => 'm', 'z' => 'M'],
        ['x' => 5200000.0, 'y' => 'X', 'z' => 'x'],
        ['x' => -23, 'y' => 'Y', 'z' => 'y'],
    ],
];

$normalizeValue = static function (mixed $value): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return $value->bytes;
    }
    if (is_float($value) && floor($value) === $value) {
        return (int) $value;
    }

    return $value;
};

$flatten = static function (array $rows) use ($normalizeValue): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $normalizeValue($value);
        }
    }

    return $flat;
};

$rowsFromFlat = static function (array $flat): array {
    $rows = [];
    for ($i = 0; $i < count($flat); $i += 3) {
        $rows[] = ['a' => $flat[$i], 'b' => $flat[$i + 1], 'c' => $flat[$i + 2]];
    }

    return $rows;
};

$abcAsc = [null, 'C', 'c', null, 'U', 'u', -23, 'Y', 'y', 1, 'a', 'a', 9.9, 'b', 'B', 5200000, 'X', 'x', 'hello', 'd', 'D', 'mad', 'Z', 'z', 'abc', 'e', 'e', 'hare', 'm', 'M'];
$aDesc = ['hare', 'm', 'M', 'abc', 'e', 'e', 'mad', 'Z', 'z', 'hello', 'd', 'D', 5200000, 'X', 'x', 9.9, 'b', 'B', 1, 'a', 'a', -23, 'Y', 'y', null, 'C', 'c', null, 'U', 'u'];
$bac = [null, 'C', 'c', null, 'U', 'u', 5200000, 'X', 'x', -23, 'Y', 'y', 'mad', 'Z', 'z', 1, 'a', 'a', 9.9, 'b', 'B', 'hello', 'd', 'D', 'abc', 'e', 'e', 'hare', 'm', 'M'];
$nocaseB = [1, 'a', 'a', 9.9, 'b', 'B', null, 'C', 'c', 'hello', 'd', 'D', 'abc', 'e', 'e', 'hare', 'm', 'M', null, 'U', 'u', 5200000, 'X', 'x', -23, 'Y', 'y', 'mad', 'Z', 'z'];
$nocaseBDesc = ['mad', 'Z', 'z', -23, 'Y', 'y', 5200000, 'X', 'x', null, 'U', 'u', 'hare', 'm', 'M', 'abc', 'e', 'e', 'hello', 'd', 'D', null, 'C', 'c', 9.9, 'b', 'B', 1, 'a', 'a'];
$cDesc = ['mad', 'Z', 'z', -23, 'Y', 'y', 5200000, 'X', 'x', null, 'U', 'u', 'hare', 'm', 'M', 'abc', 'e', 'e', 'hello', 'd', 'D', null, 'C', 'c', 9.9, 'b', 'B', 1, 'a', 'a'];
$cBinaryDesc = ['mad', 'Z', 'z', -23, 'Y', 'y', 5200000, 'X', 'x', null, 'U', 'u', 'abc', 'e', 'e', null, 'C', 'c', 1, 'a', 'a', 'hare', 'm', 'M', 'hello', 'd', 'D', 9.9, 'b', 'B'];

$cases = [
    'selectA-2.1 union all order by a,b,c' => ['SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY a,b,c', $abcAsc],
    'selectA-2.1.1 union all qualified projection order by a,b,c' => ['SELECT t1.a, t1.b, t1.c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY a,b,c', $abcAsc],
    'selectA-2.1.2 union all qualified order terms' => ['SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY t1.a,t1.b,t1.c', $abcAsc],
    'selectA-2.2 union all order by a desc' => ['SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY a DESC,b,c', $aDesc],
    'selectA-2.3 union all order by a,c,b' => ['SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY a,c,b', $abcAsc],
    'selectA-2.4 union all order by b,a,c' => ['SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY b,a,c', $bac],
    'selectA-2.5 union all order by b nocase' => ['SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY b COLLATE NOCASE,a,c', $nocaseB],
    'selectA-2.6 union all order by b nocase desc' => ['SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY b COLLATE NOCASE DESC,a,c', $nocaseBDesc],
    'selectA-2.10 union all order by c binary desc' => ['SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY c COLLATE BINARY DESC,a,b', $cBinaryDesc],
    'selectA-2.11 reversed union all order by a,b,c' => ['SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY a,b,c', $abcAsc],
    'selectA-2.12 reversed union all order by a desc' => ['SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY a DESC,b,c', $aDesc],
    'selectA-2.13 reversed union all order by a,c,b' => ['SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY a,c,b', $abcAsc],
    'selectA-2.14 reversed union all order by b,a,c' => ['SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY b,a,c', $bac],
    'selectA-2.15 reversed union all order by b nocase' => ['SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY b COLLATE NOCASE,a,c', $nocaseB],
    'selectA-2.16 reversed union all order by b nocase desc' => ['SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY b COLLATE NOCASE DESC,a,c', $nocaseBDesc],
    'selectA-2.20 reversed union all order by c binary desc' => ['SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY c COLLATE BINARY DESC,a,b', $cBinaryDesc],
];

foreach ($cases as $name => [$sql, $flatExpected]) {
    $expectedRows = $rowsFromFlat($flatExpected);
    $tests['real upstream selectA.test ' . $name . ' full result'] = static function (TestRunner $t) use ($sql, $tables, $flatExpected, $flatten): void {
        $actual = $flatten(SQLiteSelectSql::execute($sql, $tables));

        $t->same($flatExpected, $actual, $sql);
        $t->same(30, count($actual), 'selectA compound full flattened width');
        $t->contains('selectA.test', 'selectA.test compound merge source');
        $t->contains('ORDER BY', $sql);
    };

    for ($offset = 0; $offset <= 10; $offset++) {
        for ($limit = 0; $limit <= 10; $limit++) {
            $limitedSql = $sql . ' LIMIT ' . $limit;
            if ($offset > 0) {
                $limitedSql .= ' OFFSET ' . $offset;
            }
            $expected = $flatten(array_slice($expectedRows, $offset, $limit));
            $tests[sprintf('real upstream selectA.test %s limit %02d offset %02d', $name, $limit, $offset)] = static function (TestRunner $t) use ($limitedSql, $tables, $expected, $flatten, $offset, $limit): void {
                $actual = $flatten(SQLiteSelectSql::execute($limitedSql, $tables));

                $t->same($expected, $actual, $limitedSql);
                $t->same(count($expected), count($actual), 'flattened LIMIT/OFFSET width');
                $t->same(max(0, min($limit, 10 - $offset)), intdiv(count($actual), 3), 'selectA bounded slice size');
                $t->true($offset >= 0 && $limit >= 0, 'selectA non-negative LIMIT/OFFSET bounds');
            };
        }
    }
}

return $tests;
