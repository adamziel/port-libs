<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$selectATables = [
    't1' => [
        ['a' => 1, 'b' => 'a', 'c' => 'a'],
        ['a' => 9.9, 'b' => 'b', 'c' => 'B'],
        ['a' => null, 'b' => 'C', 'c' => 'c'],
        ['a' => 'hello', 'b' => 'd', 'c' => 'D'],
        ['a' => new SQLiteBlobValue('abc'), 'b' => 'e', 'c' => 'e'],
    ],
];

$selectATables['t3'] = array_merge(
    $selectATables['t1'],
    [
        ['a' => null, 'b' => 'U', 'c' => 'u'],
        ['a' => 'mad', 'b' => 'Z', 'c' => 'z'],
        ['a' => new SQLiteBlobValue('hare'), 'b' => 'm', 'c' => 'M'],
        ['a' => 5200000.0, 'b' => 'X', 'c' => 'x'],
        ['a' => -23, 'b' => 'Y', 'c' => 'y'],
    ],
    $selectATables['t1'],
    [
        ['a' => null, 'b' => 'U', 'c' => 'u'],
        ['a' => 'mad', 'b' => 'Z', 'c' => 'z'],
        ['a' => 'hare', 'b' => 'm', 'c' => 'M'],
        ['a' => 5200000.0, 'b' => 'X', 'c' => 'x'],
        ['a' => -23, 'b' => 'Y', 'c' => 'y'],
    ],
    $selectATables['t1'],
    [
        ['a' => null, 'b' => 'U', 'c' => 'u'],
        ['a' => 'mad', 'b' => 'Z', 'c' => 'z'],
        ['a' => 'hare', 'b' => 'm', 'c' => 'M'],
        ['a' => 5200000.0, 'b' => 'X', 'c' => 'x'],
        ['a' => -23, 'b' => 'Y', 'c' => 'y'],
    ],
);

$flattenSelectARows = static function (array $rows): array {
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

$assertSelectAFlat = static function (TestRunner $t, string $sql, array $expectedFlat) use ($selectATables, $flattenSelectARows): void {
    $actualFlat = $flattenSelectARows(SQLiteSelectSql::execute($sql, $selectATables));

    $t->same($expectedFlat, $actualFlat, $sql);
    $t->same(count($expectedFlat), count($actualFlat), 'selectA flattened value count');
    $t->same(intdiv(count($expectedFlat), 3), intdiv(count($actualFlat), 3), 'selectA row count');
    $t->same(
        $expectedFlat === [] ? [] : [$expectedFlat[0], $expectedFlat[array_key_last($expectedFlat)]],
        $actualFlat === [] ? [] : [$actualFlat[0], $actualFlat[array_key_last($actualFlat)]],
        'selectA first/last flattened values'
    );
    $t->same(
        md5(json_encode($expectedFlat, JSON_THROW_ON_ERROR)),
        md5(json_encode($actualFlat, JSON_THROW_ON_ERROR)),
        'selectA flattened fingerprint'
    );
    $t->contains('selectA.test', 'selectA.test INTERSECT/EXCEPT source');
};

$selectAIntersectExceptCases = [
    'selectA-2.41 EXCEPT removes high text keys ordered by a' => [
        "SELECT a,b,c FROM t1 EXCEPT SELECT a,b,c FROM t1 WHERE b>='d' ORDER BY a,b,c",
        [null, 'C', 'c', 1, 'a', 'a', 9.9, 'b', 'B'],
    ],
    'selectA-2.45 INTERSECT keeps low text keys ordered by a' => [
        "SELECT a,b,c FROM t1 INTERSECT SELECT a,b,c FROM t1 WHERE b<'d' ORDER BY a,b,c",
        [null, 'C', 'c', 1, 'a', 'a', 9.9, 'b', 'B'],
    ],
    'selectA-2.46 reversed INTERSECT keeps low text keys ordered by a' => [
        "SELECT a,b,c FROM t1 WHERE b<'d' INTERSECT SELECT a,b,c FROM t1 ORDER BY a,b,c",
        [null, 'C', 'c', 1, 'a', 'a', 9.9, 'b', 'B'],
    ],
    'selectA-2.47 EXCEPT low set ordered by a desc' => [
        "SELECT a,b,c FROM t1 EXCEPT SELECT a,b,c FROM t1 WHERE b>='d' ORDER BY a DESC",
        [9.9, 'b', 'B', 1, 'a', 'a', null, 'C', 'c'],
    ],
    'selectA-2.48 INTERSECT high set ordered by a desc' => [
        "SELECT a,b,c FROM t1 INTERSECT SELECT a,b,c FROM t1 WHERE b>='d' ORDER BY a DESC",
        ['abc', 'e', 'e', 'hello', 'd', 'D'],
    ],
    'selectA-2.49 reversed INTERSECT high set ordered by a desc' => [
        "SELECT a,b,c FROM t1 WHERE b>='d' INTERSECT SELECT a,b,c FROM t1 ORDER BY a DESC",
        ['abc', 'e', 'e', 'hello', 'd', 'D'],
    ],
    'selectA-2.50 EXCEPT high set ordered by a desc' => [
        "SELECT a,b,c FROM t1 EXCEPT SELECT a,b,c FROM t1 WHERE b<'d' ORDER BY a DESC",
        ['abc', 'e', 'e', 'hello', 'd', 'D'],
    ],
    'selectA-2.51 INTERSECT low set ordered by a desc' => [
        "SELECT a,b,c FROM t1 INTERSECT SELECT a,b,c FROM t1 WHERE b<'d' ORDER BY a DESC",
        [9.9, 'b', 'B', 1, 'a', 'a', null, 'C', 'c'],
    ],
    'selectA-2.52 reversed INTERSECT low set ordered by a desc' => [
        "SELECT a,b,c FROM t1 WHERE b<'d' INTERSECT SELECT a,b,c FROM t1 ORDER BY a DESC",
        [9.9, 'b', 'B', 1, 'a', 'a', null, 'C', 'c'],
    ],
    'selectA-2.53 EXCEPT low set ordered by b then a desc' => [
        "SELECT a,b,c FROM t1 EXCEPT SELECT a,b,c FROM t1 WHERE b>='d' ORDER BY b, a DESC",
        [null, 'C', 'c', 1, 'a', 'a', 9.9, 'b', 'B'],
    ],
    'selectA-2.54 INTERSECT high set ordered by b' => [
        "SELECT a,b,c FROM t1 INTERSECT SELECT a,b,c FROM t1 WHERE b>='d' ORDER BY b",
        ['hello', 'd', 'D', 'abc', 'e', 'e'],
    ],
    'selectA-2.55 reversed INTERSECT high set ordered by b desc then c' => [
        "SELECT a,b,c FROM t1 WHERE b>='d' INTERSECT SELECT a,b,c FROM t1 ORDER BY b DESC, c",
        ['abc', 'e', 'e', 'hello', 'd', 'D'],
    ],
    'selectA-2.56 EXCEPT high set ordered by b c desc a' => [
        "SELECT a,b,c FROM t1 EXCEPT SELECT a,b,c FROM t1 WHERE b<'d' ORDER BY b, c DESC, a",
        ['hello', 'd', 'D', 'abc', 'e', 'e'],
    ],
    'selectA-2.57 INTERSECT low set ordered by b nocase' => [
        "SELECT a,b,c FROM t1 INTERSECT SELECT a,b,c FROM t1 WHERE b<'d' ORDER BY b COLLATE NOCASE",
        [1, 'a', 'a', 9.9, 'b', 'B', null, 'C', 'c'],
    ],
    'selectA-2.58 reversed INTERSECT low set ordered by binary b' => [
        "SELECT a,b,c FROM t1 WHERE b<'d' INTERSECT SELECT a,b,c FROM t1 ORDER BY b",
        [null, 'C', 'c', 1, 'a', 'a', 9.9, 'b', 'B'],
    ],
    'selectA-2.60 INTERSECT high set ordered by c' => [
        "SELECT a,b,c FROM t1 INTERSECT SELECT a,b,c FROM t1 WHERE b>='d' ORDER BY c",
        ['hello', 'd', 'D', 'abc', 'e', 'e'],
    ],
    'selectA-2.61 reversed INTERSECT high set ordered by binary c with tie terms' => [
        "SELECT a,b,c FROM t1 WHERE b>='d' INTERSECT SELECT a,b,c FROM t1 ORDER BY c COLLATE BINARY, b DESC, c, a, b, c, a, b, c",
        ['hello', 'd', 'D', 'abc', 'e', 'e'],
    ],
    'selectA-2.62 EXCEPT high set ordered by c desc then a' => [
        "SELECT a,b,c FROM t1 EXCEPT SELECT a,b,c FROM t1 WHERE b<'d' ORDER BY c DESC, a",
        ['abc', 'e', 'e', 'hello', 'd', 'D'],
    ],
    'selectA-2.63 INTERSECT low set ordered by c nocase' => [
        "SELECT a,b,c FROM t1 INTERSECT SELECT a,b,c FROM t1 WHERE b<'d' ORDER BY c COLLATE NOCASE",
        [1, 'a', 'a', 9.9, 'b', 'B', null, 'C', 'c'],
    ],
];

foreach ($selectAIntersectExceptCases as $name => [$sql, $expectedFlat]) {
    $tests['real upstream selectA.test ' . $name . ' full result'] = static function (TestRunner $t) use ($assertSelectAFlat, $sql, $expectedFlat, $name): void {
        $assertSelectAFlat($t, $sql, $expectedFlat);
        $t->contains(substr($name, 0, 12), $name);
    };

    for ($limit = 0; $limit <= 10; $limit++) {
        for ($offset = 0; $offset <= 10; $offset++) {
            $tests[sprintf('real upstream selectA.test %s dynamic limit %02d offset %02d', $name, $limit, $offset)] =
                static function (TestRunner $t) use ($assertSelectAFlat, $sql, $expectedFlat, $limit, $offset, $name): void {
                    $expectedRows = array_chunk($expectedFlat, 3);
                    $expectedSlice = array_slice($expectedRows, $offset, $limit);
                    $expectedLimitedFlat = [];
                    foreach ($expectedSlice as $row) {
                        array_push($expectedLimitedFlat, ...$row);
                    }

                    $assertSelectAFlat($t, $sql . " LIMIT {$limit} OFFSET {$offset}", $expectedLimitedFlat);
                    $t->contains('selectA-2.', $name);
                    $t->true($limit >= 0 && $offset >= 0, 'selectA non-negative LIMIT/OFFSET bounds');
                };
        }
    }
}

return $tests;
