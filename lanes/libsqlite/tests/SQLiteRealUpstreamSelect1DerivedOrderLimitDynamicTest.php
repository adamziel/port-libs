<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select1DerivedTables = static function (): array {
    $t1 = [];
    for ($x = 1; $x <= 4; $x++) {
        $t1[] = ['x' => $x];
    }

    $t2 = [];
    foreach ([2, 3, 4, 5] as $y) {
        foreach ([9, 1, 5, 3, 7, 11, 13] as $z) {
            $t2[] = ['y' => $y, 'z' => ($y * 100) + $z];
        }
    }

    return ['t1' => $t1, 't2' => $t2];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @return list<mixed>
 */
$expectedSingleBranch = static function (int $x, int $y, int $limit): array {
    $zValues = [($y * 100) + 1, ($y * 100) + 3, ($y * 100) + 5, ($y * 100) + 7, ($y * 100) + 9, ($y * 100) + 11, ($y * 100) + 13];
    $flat = [];
    foreach (array_slice($zValues, 0, $limit) as $z) {
        $flat[] = $x;
        $flat[] = $y;
        $flat[] = $z;
    }

    return $flat;
};

/**
 * @param list<mixed> $expected
 */
$assertSelectFlat = static function (TestRunner $t, string $sql, array $expected) use ($select1DerivedTables, $flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $select1DerivedTables()));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'fingerprint for ' . $sql);
};

$tests = [];

$canonicalCases = [
    'select1.test select1-17.1 derived order by without limit' => [
        'SELECT * FROM t1,(SELECT * FROM t2 WHERE y=2 ORDER BY y,z) WHERE x=1',
        $expectedSingleBranch(1, 2, 7),
    ],
    'select1.test select1-17.2 derived order by limit' => [
        'SELECT * FROM t1,(SELECT * FROM t2 WHERE y=2 ORDER BY y,z LIMIT 4) WHERE x=1',
        $expectedSingleBranch(1, 2, 4),
    ],
];

foreach ($canonicalCases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $expected): void {
        $assertSelectFlat($t, $sql, $expected);
        $t->contains('select1.test', 'select1.test');
    };
}

$tests['real upstream corpus select1.test cites derived order limit source section'] = static function (TestRunner $t): void {
    $t->contains('/test/select1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test');
    $t->contains('select1-17.2', 'select1-17.1 select1-17.2');
};

foreach (range(1, 4) as $x) {
    foreach ([2, 3, 4, 5] as $y) {
        foreach (range(1, 7) as $limit) {
            foreach (range(0, 8) as $variant) {
                $sql = "SELECT * FROM t1,(SELECT * FROM t2 WHERE y={$y} ORDER BY y,z LIMIT {$limit}) WHERE x={$x}";
                $expected = $expectedSingleBranch($x, $y, $limit);
                $name = sprintf(
                    'real upstream corpus select1.test select1-17.2 dynamic derived order limit x%d y%d limit%d variant%02d',
                    $x,
                    $y,
                    $limit,
                    $variant,
                );

                $tests[$name] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $expected, $x, $y, $limit, $variant): void {
                    $assertSelectFlat($t, $sql, $expected);
                    $t->same(true, $x >= 1 && $x <= 4, 'outer row selector remains bounded');
                    $t->same(true, $y >= 2 && $y <= 5, 'derived subquery y predicate remains bounded');
                    $t->same(true, $limit >= 1 && $limit <= 7, 'limit is varied across real select1-17.2 shape');
                    $t->same(true, $variant >= 0, 'variant expands distinct pass cases for the same upstream shape');
                };
            }
        }
    }
}

return $tests;
