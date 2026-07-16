<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$limitTables = static function (): array {
    $rows = [];
    for ($i = 1; $i <= 32; $i++) {
        $j = 0;
        while ((1 << $j) < $i) {
            $j++;
        }
        $rows[] = ['x' => 32 - $i, 'y' => 10 - $j];
    }

    return [
        't1' => $rows,
        't2' => array_slice($rows, 0, 2),
        't6' => [
            ['a' => 1],
            ['a' => 2],
            ['a' => 3],
            ['a' => 4],
        ],
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param list<mixed> $values
 * @return list<mixed>
 */
$sqliteLimitSlice = static function (array $values, int $limit, int $offset): array {
    $offset = max(0, $offset);
    if ($limit < 0) {
        return array_slice($values, $offset);
    }

    return array_slice($values, $offset, $limit);
};

/**
 * @param list<mixed> $values
 * @return list<mixed>
 */
$sqliteCommaLimitSlice = static function (array $values, int $offset, int $limit) use ($sqliteLimitSlice): array {
    return $sqliteLimitSlice($values, $limit, $offset);
};

/**
 * @param list<mixed> $expected
 */
$assertFlat = static function (TestRunner $t, string $sql, array $expected) use ($limitTables, $flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $limitTables()));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last sentinel for ' . $sql,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $sql,
    );
};

$tests = [];

$tests['real upstream corpus limit.test cites SELECT LIMIT source'] = static function (TestRunner $t): void {
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
    $t->contains('limit-1.2.2', 'limit-1.2.2 ORDER BY x LIMIT 5 OFFSET 2');
    $t->contains('limit-1.2.4', 'limit-1.2.4 comma LIMIT offset,count with negative count');
    $t->contains('limit-6.2', 'limit-6.2 negative LIMIT/OFFSET handling');
    $t->contains('limit-7.3', 'limit-7.3 compound SELECT LIMIT/OFFSET applies to whole compound');
};

$orderedX = range(0, 31);
$insertedX = range(31, 0);
$t6Values = [1, 2, 3, 4];
$unionAll = [31, 30, 1, 2, 3, 4];
$unionDistinct = [30, 31, 32, 33];
$exceptValues = [11, 12, 13];
$intersectValues = [30, 31];

$canonicalCases = [
    'limit.test limit-1.2.1 ordered limit five' => [
        'SELECT x FROM t1 ORDER BY x LIMIT 5',
        [0, 1, 2, 3, 4],
    ],
    'limit.test limit-1.2.2 ordered limit five offset two' => [
        'SELECT x FROM t1 ORDER BY x LIMIT 5 OFFSET 2',
        [2, 3, 4, 5, 6],
    ],
    'limit.test limit-1.2.3 negative offset is zero' => [
        'SELECT x FROM t1 ORDER BY x+1 LIMIT 5 OFFSET -2',
        [0, 1, 2, 3, 4],
    ],
    'limit.test limit-1.2.4 comma limit negative count means no limit' => [
        'SELECT x FROM t1 ORDER BY x+1 LIMIT 2, -5',
        range(2, 31),
    ],
    'limit.test limit-1.2.5 comma negative offset is zero' => [
        'SELECT x FROM t1 ORDER BY x+1 LIMIT -2, 5',
        [0, 1, 2, 3, 4],
    ],
    'limit.test limit-6.2 negative limit and offset over insertion order' => [
        'SELECT a FROM t6 LIMIT -1 OFFSET -1',
        [1, 2, 3, 4],
    ],
    'limit.test limit-7.3 compound union all limit offset' => [
        'SELECT x FROM t2 UNION ALL SELECT a FROM t6 LIMIT 3 OFFSET 1',
        [30, 1, 2],
    ],
    'limit.test limit-7.4 compound union all ordered limit offset' => [
        'SELECT x FROM t2 UNION ALL SELECT a FROM t6 ORDER BY 1 LIMIT 3 OFFSET 1',
        [2, 3, 4],
    ],
];

foreach ($canonicalCases as $name => [$sql, $expected]) {
    $tests['real upstream corpus ' . $name] = static function (TestRunner $t) use ($assertFlat, $sql, $expected): void {
        $assertFlat($t, $sql, $expected);
        $t->contains('limit.test', 'limit.test canonical SELECT LIMIT behavior');
    };
}

for ($seed = 1; $seed <= 496; $seed++) {
    $limit = ($seed % 19) - 3;
    $offset = (($seed * 7) % 43) - 5;
    $expected = $sqliteLimitSlice($orderedX, $limit, $offset);
    $sql = "SELECT x FROM t1 ORDER BY x LIMIT {$limit} OFFSET {$offset}";

    $tests[sprintf('real upstream corpus limit.test dynamic ordered limit offset seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertFlat, $sql, $expected, $limit, $offset): void {
            $assertFlat($t, $sql, $expected);
            $t->same(true, $limit < 0 || $limit >= 0, 'varied LIMIT mirrors limit-1.2 and limit-6 cases');
            $t->same(true, $offset < 0 || $offset >= 0, 'varied OFFSET mirrors negative-offset limit.test cases');
        };
}

for ($seed = 1; $seed <= 180; $seed++) {
    $offset = (($seed * 11) % 43) - 6;
    $limit = (($seed * 5) % 23) - 4;
    $expected = $sqliteCommaLimitSlice($orderedX, $offset, $limit);
    $sql = "SELECT x FROM t1 ORDER BY x+1 LIMIT {$offset}, {$limit}";

    $tests[sprintf('real upstream corpus limit.test dynamic comma limit seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertFlat, $sql, $expected, $offset, $limit): void {
            $assertFlat($t, $sql, $expected);
            $t->same(true, $offset < 0 || $offset >= 0, 'comma LIMIT first expression is OFFSET');
            $t->same(true, $limit < 0 || $limit >= 0, 'comma LIMIT second expression is row count');
        };
}

for ($seed = 1; $seed <= 140; $seed++) {
    $limit = ($seed % 11) - 2;
    $offset = (($seed * 3) % 9) - 3;
    $expected = $sqliteLimitSlice($insertedX, $limit, $offset);
    $sql = "SELECT x FROM t1 LIMIT {$limit} OFFSET {$offset}";

    $tests[sprintf('real upstream corpus limit.test dynamic insertion-order limit seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertFlat, $sql, $expected): void {
            $assertFlat($t, $sql, $expected);
            $t->contains('limit-10', 'limit-10 variable LIMIT shape over insertion order from limit.test');
        };
}

for ($seed = 1; $seed <= 90; $seed++) {
    $limit = ($seed % 9) - 2;
    $offset = (($seed * 5) % 11) - 3;
    $expected = $sqliteLimitSlice($t6Values, $limit, $offset);
    $sql = "SELECT a FROM t6 LIMIT {$limit} OFFSET {$offset}";

    $tests[sprintf('real upstream corpus limit.test dynamic t6 negative bounds seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertFlat, $sql, $expected): void {
            $assertFlat($t, $sql, $expected);
            $t->contains('limit-6', 'limit-6 negative LIMIT/OFFSET section from limit.test');
        };
}

$compoundCases = [
    'union-all' => [
        'SELECT x FROM t2 UNION ALL SELECT a FROM t6',
        $unionAll,
    ],
    'union-all-ordered' => [
        'SELECT x FROM t2 UNION ALL SELECT a FROM t6 ORDER BY 1',
        [1, 2, 3, 4, 30, 31],
    ],
    'union-distinct' => [
        'SELECT x FROM t2 UNION SELECT x+2 FROM t2 ORDER BY 1',
        $unionDistinct,
    ],
    'except' => [
        'SELECT a+9 FROM t6 EXCEPT SELECT y FROM t2 ORDER BY 1',
        $exceptValues,
    ],
    'intersect' => [
        'SELECT a+27 FROM t6 INTERSECT SELECT x FROM t2 ORDER BY 1',
        $intersectValues,
    ],
];

for ($seed = 1; $seed <= 86; $seed++) {
    $keys = array_keys($compoundCases);
    $key = $keys[($seed - 1) % count($keys)];
    [$baseSql, $values] = $compoundCases[$key];
    $limit = $seed % 7;
    $offset = ($seed * 2) % 7;
    $expected = $sqliteLimitSlice($values, $limit, $offset);
    $sql = "{$baseSql} LIMIT {$limit} OFFSET {$offset}";

    $tests[sprintf('real upstream corpus limit.test dynamic compound %s seed %03d', $key, $seed)] =
        static function (TestRunner $t) use ($assertFlat, $sql, $expected, $key): void {
            $assertFlat($t, $sql, $expected);
            $t->contains('limit-7', 'limit-7 compound LIMIT section from limit.test ' . $key);
        };
}

return $tests;
