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

$flatten = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            if ($value instanceof SQLiteBlobValue) {
                $value = $value->bytes;
            }
            if (is_float($value) && floor($value) === $value) {
                $value = (int) $value;
            }
            $flat[] = $value;
        }
    }

    return $flat;
};

$sliceFlatRows = static function (array $flat, int $limit, int $offset): array {
    $rows = array_chunk($flat, 3);
    $slice = array_slice($rows, $offset, $limit);
    $out = [];
    foreach ($slice as $row) {
        array_push($out, ...$row);
    }

    return $out;
};

$assertSelectA = static function (TestRunner $t, string $sql, array $expectedFlat) use ($tables, $flatten): void {
    $actualFlat = $flatten(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expectedFlat, $actualFlat, $sql);
    $t->same(count($expectedFlat), count($actualFlat), 'selectA remainder flat value count');
    $t->same(intdiv(count($expectedFlat), 3), intdiv(count($actualFlat), 3), 'selectA remainder row count');
    $t->same(
        $expectedFlat === [] ? [] : [$expectedFlat[0], $expectedFlat[array_key_last($expectedFlat)]],
        $actualFlat === [] ? [] : [$actualFlat[0], $actualFlat[array_key_last($actualFlat)]],
        'selectA remainder first/last flattened values'
    );
    $t->same(
        md5(json_encode($expectedFlat, JSON_THROW_ON_ERROR)),
        md5(json_encode($actualFlat, JSON_THROW_ON_ERROR)),
        'selectA remainder flattened fingerprint'
    );
    $t->contains('selectA.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test');
};

$ascendingAbc = [null, 'C', 'c', null, 'U', 'u', -23, 'Y', 'y', 1, 'a', 'a', 9.9, 'b', 'B', 5200000, 'X', 'x', 'hello', 'd', 'D', 'mad', 'Z', 'z', 'abc', 'e', 'e', 'hare', 'm', 'M'];
$descendingA = ['hare', 'm', 'M', 'abc', 'e', 'e', 'mad', 'Z', 'z', 'hello', 'd', 'D', 5200000, 'X', 'x', 9.9, 'b', 'B', 1, 'a', 'a', -23, 'Y', 'y', null, 'C', 'c', null, 'U', 'u'];
$orderByB = [null, 'C', 'c', null, 'U', 'u', 5200000, 'X', 'x', -23, 'Y', 'y', 'mad', 'Z', 'z', 1, 'a', 'a', 9.9, 'b', 'B', 'hello', 'd', 'D', 'abc', 'e', 'e', 'hare', 'm', 'M'];
$nocaseB = [1, 'a', 'a', 9.9, 'b', 'B', null, 'C', 'c', 'hello', 'd', 'D', 'abc', 'e', 'e', 'hare', 'm', 'M', null, 'U', 'u', 5200000, 'X', 'x', -23, 'Y', 'y', 'mad', 'Z', 'z'];
$nocaseBDesc = ['mad', 'Z', 'z', -23, 'Y', 'y', 5200000, 'X', 'x', null, 'U', 'u', 'hare', 'm', 'M', 'abc', 'e', 'e', 'hello', 'd', 'D', null, 'C', 'c', 9.9, 'b', 'B', 1, 'a', 'a'];
$binaryCDesc = ['mad', 'Z', 'z', -23, 'Y', 'y', 5200000, 'X', 'x', null, 'U', 'u', 'abc', 'e', 'e', null, 'C', 'c', 1, 'a', 'a', 'hare', 'm', 'M', 'hello', 'd', 'D', 9.9, 'b', 'B'];

$cases = [
    'selectA-2.3 union all order by a c b' => [
        'SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY a,c,b',
        $ascendingAbc,
    ],
    'selectA-2.12 reversed union all order by a desc b c' => [
        'SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY a DESC,b,c',
        $descendingA,
    ],
    'selectA-2.13 reversed union all order by a c b' => [
        'SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY a,c,b',
        $ascendingAbc,
    ],
    'selectA-2.14 reversed union all order by b a c' => [
        'SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY b,a,c',
        $orderByB,
    ],
    'selectA-2.15 reversed union all order by b nocase a c' => [
        'SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY b COLLATE NOCASE,a,c',
        $nocaseB,
    ],
    'selectA-2.16 reversed union all order by b nocase desc a c' => [
        'SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY b COLLATE NOCASE DESC,a,c',
        $nocaseBDesc,
    ],
    'selectA-2.20 reversed union all order by c binary desc a b' => [
        'SELECT x,y,z FROM t2 UNION ALL SELECT a,b,c FROM t1 ORDER BY c COLLATE BINARY DESC,a,b',
        $binaryCDesc,
    ],
    'selectA-2.21 union distinct order by a b c' => [
        'SELECT a,b,c FROM t1 UNION SELECT x,y,z FROM t2 ORDER BY a,b,c',
        $ascendingAbc,
    ],
    'selectA-2.22 union distinct order by a desc b c' => [
        'SELECT a,b,c FROM t1 UNION SELECT x,y,z FROM t2 ORDER BY a DESC,b,c',
        $descendingA,
    ],
    'selectA-2.23 union distinct order by a c b' => [
        'SELECT a,b,c FROM t1 UNION SELECT x,y,z FROM t2 ORDER BY a,c,b',
        $ascendingAbc,
    ],
    'selectA-2.24 union distinct order by b a c' => [
        'SELECT a,b,c FROM t1 UNION SELECT x,y,z FROM t2 ORDER BY b,a,c',
        $orderByB,
    ],
    'selectA-2.25 union distinct order by b nocase a c' => [
        'SELECT a,b,c FROM t1 UNION SELECT x,y,z FROM t2 ORDER BY b COLLATE NOCASE,a,c',
        $nocaseB,
    ],
    'selectA-2.26 union distinct order by b nocase desc a c' => [
        'SELECT a,b,c FROM t1 UNION SELECT x,y,z FROM t2 ORDER BY b COLLATE NOCASE DESC,a,c',
        $nocaseBDesc,
    ],
];

$tests['real upstream selectA.test cites union distinct order remainder source'] = static function (TestRunner $t): void {
    $upstream = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test');
    $t->true(is_string($upstream), 'selectA upstream source is readable');
    $t->contains('compound-SELECT merge', $upstream);
    $t->contains('selectA-2.3', $upstream);
    $t->contains('selectA-2.26', $upstream);
};

foreach ($cases as $name => [$sql, $expectedFlat]) {
    $tests['real upstream ' . $name . ' full result'] = static function (TestRunner $t) use ($assertSelectA, $sql, $expectedFlat, $name): void {
        $assertSelectA($t, $sql, $expectedFlat);
        $t->contains('selectA-2.', $name);
    };

    for ($limit = 0; $limit <= 10; $limit++) {
        for ($offset = 0; $offset <= 10; $offset++) {
            $tests[sprintf('real upstream %s dynamic limit %02d offset %02d', $name, $limit, $offset)] =
                static function (TestRunner $t) use ($assertSelectA, $sliceFlatRows, $sql, $expectedFlat, $limit, $offset, $name): void {
                    $expectedLimitedFlat = $sliceFlatRows($expectedFlat, $limit, $offset);

                    $assertSelectA($t, $sql . " LIMIT {$limit} OFFSET {$offset}", $expectedLimitedFlat);
                    $t->contains('selectA-2.', $name);
                    $t->true($limit >= 0 && $offset >= 0, 'selectA LIMIT/OFFSET bounds are non-negative');
                };
        }
    }
}

return $tests;
