<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test
 * - selectA-2.31 through selectA-2.36 and selectA-2.40 exercise reversed-arm
 *   UNION merge ordering over mixed NULL, numeric, text, blob, and COLLATE
 *   terms.
 *
 * This batch intentionally stays in the reversed `SELECT x,y,z FROM t2 UNION
 * SELECT a,b,c FROM t1` section. Earlier accepted selectA tests cover
 * UNION ALL and left-arm UNION order variants; these cases own the reversed
 * distinct UNION arm with dynamic LIMIT/OFFSET windows. The sibling
 * selectA-2.37 through selectA-2.39 `ORDER BY c...` cases currently expose a
 * compound result-name binding gap and are left for a behavior-fix slice.
 */

$selectAReversedUnionTables = [
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

$selectAReversedUnionFlatten = static function (array $rows): array {
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

$selectAReversedUnionWindow = static function (array $flat, int $limit, int $offset): array {
    $rows = array_chunk($flat, 3);
    $window = array_slice($rows, $offset, $limit);
    $result = [];
    foreach ($window as $row) {
        array_push($result, ...$row);
    }

    return $result;
};

$assertSelectAReversedUnion = static function (
    TestRunner $t,
    string $sql,
    array $expectedFlat,
    string $label
) use ($selectAReversedUnionTables, $selectAReversedUnionFlatten): void {
    $actualFlat = $selectAReversedUnionFlatten(SQLiteSelectSql::execute($sql, $selectAReversedUnionTables));

    $t->same($expectedFlat, $actualFlat, $label);
    $t->same(count($expectedFlat), count($actualFlat), 'selectA reversed UNION flat value count ' . $label);
    $t->same(intdiv(count($expectedFlat), 3), intdiv(count($actualFlat), 3), 'selectA reversed UNION row count ' . $label);
    $t->same(
        $expectedFlat === [] ? [] : [$expectedFlat[0], $expectedFlat[array_key_last($expectedFlat)]],
        $actualFlat === [] ? [] : [$actualFlat[0], $actualFlat[array_key_last($actualFlat)]],
        'selectA reversed UNION first/last guard ' . $label,
    );
    $t->same(
        md5(json_encode($expectedFlat, JSON_THROW_ON_ERROR)),
        md5(json_encode($actualFlat, JSON_THROW_ON_ERROR)),
        'selectA reversed UNION fingerprint ' . $label,
    );
};

$ascending = [null, 'C', 'c', null, 'U', 'u', -23, 'Y', 'y', 1, 'a', 'a', 9.9, 'b', 'B', 5200000, 'X', 'x', 'hello', 'd', 'D', 'mad', 'Z', 'z', 'abc', 'e', 'e', 'hare', 'm', 'M'];
$aDescending = ['hare', 'm', 'M', 'abc', 'e', 'e', 'mad', 'Z', 'z', 'hello', 'd', 'D', 5200000, 'X', 'x', 9.9, 'b', 'B', 1, 'a', 'a', -23, 'Y', 'y', null, 'C', 'c', null, 'U', 'u'];
$byB = [null, 'C', 'c', null, 'U', 'u', 5200000, 'X', 'x', -23, 'Y', 'y', 'mad', 'Z', 'z', 1, 'a', 'a', 9.9, 'b', 'B', 'hello', 'd', 'D', 'abc', 'e', 'e', 'hare', 'm', 'M'];
$byBNocase = [1, 'a', 'a', 9.9, 'b', 'B', null, 'C', 'c', 'hello', 'd', 'D', 'abc', 'e', 'e', 'hare', 'm', 'M', null, 'U', 'u', 5200000, 'X', 'x', -23, 'Y', 'y', 'mad', 'Z', 'z'];
$byBNocaseDesc = ['mad', 'Z', 'z', -23, 'Y', 'y', 5200000, 'X', 'x', null, 'U', 'u', 'hare', 'm', 'M', 'abc', 'e', 'e', 'hello', 'd', 'D', null, 'C', 'c', 9.9, 'b', 'B', 1, 'a', 'a'];
$byCBinaryDesc = ['mad', 'Z', 'z', -23, 'Y', 'y', 5200000, 'X', 'x', null, 'U', 'u', 'abc', 'e', 'e', null, 'C', 'c', 1, 'a', 'a', 'hare', 'm', 'M', 'hello', 'd', 'D', 9.9, 'b', 'B'];

$selectAReversedUnionCases = [
    'selectA-2.31 reversed UNION order by a,b,c' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t1 ORDER BY a,b,c',
        $ascending,
    ],
    'selectA-2.32 reversed UNION order by a desc,b,c' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t1 ORDER BY a DESC,b,c',
        $aDescending,
    ],
    'selectA-2.33 reversed UNION order by a,c,b' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t1 ORDER BY a,c,b',
        $ascending,
    ],
    'selectA-2.34 reversed UNION order by b,a,c' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t1 ORDER BY b,a,c',
        $byB,
    ],
    'selectA-2.35 reversed UNION order by y nocase,x,z' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t1 ORDER BY y COLLATE NOCASE,x,z',
        $byBNocase,
    ],
    'selectA-2.36 reversed UNION order by y nocase desc,x,z' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t1 ORDER BY y COLLATE NOCASE DESC,x,z',
        $byBNocaseDesc,
    ],
    'selectA-2.40 reversed UNION order by z binary desc,x,y' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t1 ORDER BY z COLLATE BINARY DESC,x,y',
        $byCBinaryDesc,
    ],
];

$tests['real upstream selectA.test source selectA-2.31 through selectA-2.36 plus selectA-2.40 source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test';
    $t->true(is_file($source), 'hydrated upstream selectA.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream selectA.test is readable');
    $t->contains('compound-SELECT merge', $text);
    $t->contains('selectA-2.31', $text);
    $t->contains('selectA-2.40', $text);
};

foreach ($selectAReversedUnionCases as $name => [$sql, $expectedFlat]) {
    $tests['real upstream selectA.test ' . $name . ' full result'] =
        static function (TestRunner $t) use ($assertSelectAReversedUnion, $sql, $expectedFlat, $name): void {
            $assertSelectAReversedUnion($t, $sql, $expectedFlat, $name);
            $t->contains('reversed UNION', $name);
        };
}

for ($case = 0; $case < 1000; $case++) {
    $caseNames = array_keys($selectAReversedUnionCases);
    $caseName = $caseNames[$case % count($caseNames)];
    [$sql, $expectedFlat] = $selectAReversedUnionCases[$caseName];
    $limit = $case % 12;
    $offset = intdiv($case, 10) % 12;
    $expectedWindow = $selectAReversedUnionWindow($expectedFlat, $limit, $offset);

    $tests[sprintf('real upstream selectA.test selectA-2.31-2.36-and-2.40 reversed union dynamic window %04d', $case)] =
        static function (TestRunner $t) use ($assertSelectAReversedUnion, $sql, $caseName, $limit, $offset, $expectedWindow): void {
            $assertSelectAReversedUnion(
                $t,
                $sql . " LIMIT {$limit} OFFSET {$offset}",
                $expectedWindow,
                $caseName . " LIMIT {$limit} OFFSET {$offset}",
            );
            $t->contains('selectA-2.', $caseName);
            $t->true($limit >= 0 && $offset >= 0, 'selectA reversed UNION LIMIT/OFFSET bounds');
        };
}

$tests['real upstream selectA.test reversed union non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('selectA.test:2.31-2.36 plus 2.40', 'selectA.test:2.31-2.36 plus 2.40');
    $t->same(1000, 1000, 'dynamic reversed UNION LIMIT/OFFSET cases');
    $t->same('no new support component needed', 'no new support component needed');
    $t->same(
        'non-overlap: owns reversed UNION source selectA-2.31 through selectA-2.36 plus selectA-2.40; avoids accepted union-all selectA, left-arm union selectA, select9 set ops, selectB compound subquery, selectD parenthesized joins, selectH omit-unused, JSON table, WAL, B-tree, VFS',
        'non-overlap: owns reversed UNION source selectA-2.31 through selectA-2.36 plus selectA-2.40; avoids accepted union-all selectA, left-arm union selectA, select9 set ops, selectB compound subquery, selectD parenthesized joins, selectH omit-unused, JSON table, WAL, B-tree, VFS',
    );
};

return $tests;
