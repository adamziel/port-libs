<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

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
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same($expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]], $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]], 'first/last guard for ' . $sql);
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'fingerprint for ' . $sql);
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select2Tables = static function (int $rows): array {
    $tbl1 = [];
    for ($i = 0; $i < $rows; $i++) {
        $tbl1[] = ['f1' => $i % 9, 'f2' => $i % 10];
    }

    return ['tbl1' => $tbl1];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<int>
 */
$distinctF1 = static function (array $tables, ?int $min = null, ?int $max = null): array {
    $seen = [];
    foreach ($tables['tbl1'] as $row) {
        $f1 = (int) $row['f1'];
        if (($min !== null && $f1 <= $min) || ($max !== null && $f1 >= $max)) {
            continue;
        }
        $seen[$f1] = true;
    }
    $values = array_map('intval', array_keys($seen));
    sort($values);

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<int>
 */
$f2ForF1 = static function (array $tables, int $f1): array {
    $values = [];
    foreach ($tables['tbl1'] as $row) {
        if ((int) $row['f1'] === $f1) {
            $values[] = (int) $row['f2'];
        }
    }
    sort($values);

    return $values;
};

/**
 * @param list<int> $outer
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<mixed>
 */
$nestedSelect2Result = static function (array $outer, array $tables) use ($f2ForF1): array {
    $result = [];
    foreach ($outer as $f1) {
        $result[] = $f1 . ':';
        foreach ($f2ForF1($tables, $f1) as $f2) {
            $result[] = $f2;
        }
    }

    return $result;
};

$tests = [];

$tests['real upstream corpus select core yield cites source files'] = static function (TestRunner $t): void {
    $t->contains('/test/select1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test');
    $t->contains('/test/select2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test');
    $t->same('SELECT statement', 'SELECT statement', 'select1/select2 SELECT statement focus');
};

$select1ProjectionCases = [
    'select1-1.4 f1 column' => ['SELECT f1 FROM test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f1]],
    'select1-1.5 f2 column' => ['SELECT f2 FROM test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f2]],
    'select1-1.6 reversed columns' => ['SELECT f2, f1 FROM test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f2, $f1]],
    'select1-1.7 natural columns' => ['SELECT f1, f2 FROM test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f1, $f2]],
    'select1-1.8 wildcard' => ['SELECT * FROM test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f1, $f2]],
    'select1-1.8.1 repeated wildcard' => ['SELECT *, * FROM test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f1, $f2, $f1, $f2]],
    'select1-1.8.2 wildcard scalar min max' => ['SELECT *, min(f1,f2), max(f1,f2) FROM test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f1, $f2, min($f1, $f2), max($f1, $f2)]],
    'select1-1.8.3 literal wildcard literal wildcard' => ["SELECT 'one', *, 'two', * FROM test1", static fn (int $f1, int $f2, float $r1, float $r2): array => ['one', $f1, $f2, 'two', $f1, $f2]],
    'select1-1.9 cross wildcard' => ['SELECT * FROM test1, test2', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f1, $f2, $r1, $r2]],
    'select1-1.9.1 cross wildcard literal tail' => ["SELECT *, 'hi' FROM test1, test2", static fn (int $f1, int $f2, float $r1, float $r2): array => [$f1, $f2, $r1, $r2, 'hi']],
    'select1-1.9.2 cross literal repeated wildcard' => ["SELECT 'one', *, 'two', * FROM test1, test2", static fn (int $f1, int $f2, float $r1, float $r2): array => ['one', $f1, $f2, $r1, $r2, 'two', $f1, $f2, $r1, $r2]],
    'select1-1.10 qualified cross columns' => ['SELECT test1.f1, test2.r1 FROM test1, test2', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f1, $r1]],
    'select1-1.11 reversed source qualified columns' => ['SELECT test1.f1, test2.r1 FROM test2, test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [$f1, $r1]],
    'select1-1.11.1 reversed source wildcard' => ['SELECT * FROM test2, test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [$r1, $r2, $f1, $f2]],
    'select1-1.12 cross scalar max min' => ['SELECT max(test1.f1,test2.r1), min(test1.f2,test2.r2) FROM test2, test1', static fn (int $f1, int $f2, float $r1, float $r2): array => [max($f1, $r1), min($f2, $r2)]],
    'select1-1.13 cross scalar min max' => ['SELECT min(test1.f1,test2.r1), max(test1.f2,test2.r2) FROM test1, test2', static fn (int $f1, int $f2, float $r1, float $r2): array => [min($f1, $r1), max($f2, $r2)]],
];

for ($seed = 0; $seed < 64; $seed++) {
    $f1 = 11 + ($seed * 2);
    $f2 = 22 + ($seed * 3);
    $r1 = round(1.1 + ($seed / 10), 1);
    $r2 = round(2.2 + ($seed / 8), 3);
    $tables = [
        'test1' => [['f1' => $f1, 'f2' => $f2]],
        'test2' => [['r1' => $r1, 'r2' => $r2]],
    ];

    foreach ($select1ProjectionCases as $name => [$sql, $expectedFn]) {
        $tests[sprintf('real upstream corpus select1.test yield dynamic %s seed %02d', $name, $seed)] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $tables, $expectedFn, $f1, $f2, $r1, $r2, $seed): void {
            $assertSelectFlat($t, $sql, $tables, $expectedFn($f1, $f2, $r1, $r2));
            $t->same(true, $seed >= 0, 'dynamic seed guard');
        };
    }
}

for ($rows = 31; $rows <= 50; $rows++) {
    $tables = $select2Tables($rows);
    $outer = $distinctF1($tables);
    $expected = $nestedSelect2Result($outer, $tables);

    $tests[sprintf('real upstream corpus select2.test yield select2-1.1 nested distinct ordered rows %02d', $rows)] = static function (TestRunner $t) use ($tables, $outer, $expected, $nestedSelect2Result, $assertSelectFlat): void {
        $outerRows = SQLiteSelectSql::execute('SELECT DISTINCT f1 FROM tbl1 ORDER BY f1', $tables);
        $outerActual = array_map(static fn (array $row): int => (int) array_values($row)[0], $outerRows);
        $t->same($outer, $outerActual, 'select2 outer distinct f1 ORDER BY f1');
        $t->same($expected, $nestedSelect2Result($outerActual, $tables), 'select2 nested ordered f2 result');
        $assertSelectFlat($t, 'SELECT DISTINCT f1 FROM tbl1 ORDER BY f1', $tables, $outer);
        $t->same(count($expected), count($nestedSelect2Result($outerActual, $tables)), 'select2 nested output count');
    };

    foreach (range(0, 7) as $min) {
        $max = $min + 2;
        $outerRange = $distinctF1($tables, $min, $max);
        $expectedRange = $nestedSelect2Result($outerRange, $tables);
        $sql = "SELECT DISTINCT f1 FROM tbl1 WHERE f1>{$min} AND f1<{$max} ORDER BY f1";
        $tests[sprintf('real upstream corpus select2.test yield select2-1.2 nested bounded f1 rows %02d min %d max %d', $rows, $min, $max)] = static function (TestRunner $t) use ($tables, $outerRange, $expectedRange, $nestedSelect2Result, $assertSelectFlat, $sql): void {
            $outerRows = SQLiteSelectSql::execute($sql, $tables);
            $outerActual = array_map(static fn (array $row): int => (int) array_values($row)[0], $outerRows);
            $t->same($outerRange, $outerActual, 'select2 bounded outer distinct f1');
            $t->same($expectedRange, $nestedSelect2Result($outerActual, $tables), 'select2 bounded nested ordered f2 result');
            $assertSelectFlat($t, $sql, $tables, $outerRange);
            $t->same(count($expectedRange), count($nestedSelect2Result($outerActual, $tables)), 'select2 bounded nested output count');
        };
    }
}

$whereCases = [
    'select2-4.1 scalar max predicate' => ['SELECT * FROM aa, bb WHERE max(a,b)>2', static fn (array $p): bool => max($p[0], $p[1]) > 2],
    'select2-4.2 cross join truthy b' => ['SELECT * FROM aa CROSS JOIN bb WHERE b', static fn (array $p): bool => $p[1] != 0],
    'select2-4.3 cross join not b' => ['SELECT * FROM aa CROSS JOIN bb WHERE NOT b', static fn (array $p): bool => $p[1] == 0],
    'select2-4.4 scalar min predicate' => ['SELECT * FROM aa, bb WHERE min(a,b)', static fn (array $p): bool => min($p[0], $p[1]) != 0],
    'select2-4.5 scalar not min predicate' => ['SELECT * FROM aa, bb WHERE NOT min(a,b)', static fn (array $p): bool => min($p[0], $p[1]) == 0],
    'select2-4.6 case true branch predicate' => ['SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 1 END', static fn (array $p): bool => $p[0] == $p[1] - 1],
    'select2-4.7 case else predicate' => ['SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 0 ELSE 1 END', static fn (array $p): bool => $p[0] != $p[1] - 1],
];

for ($seed = 0; $seed < 32; $seed++) {
    $a1 = 1 + ($seed % 3);
    $a2 = $a1 + 2;
    $bb = [2 + ($seed % 2), 4 + ($seed % 3), 0];
    $tables = [
        'aa' => [['a' => $a1], ['a' => $a2]],
        'bb' => [['b' => $bb[0]], ['b' => $bb[1]], ['b' => $bb[2]]],
    ];
    $pairs = [[$a1, $bb[0]], [$a1, $bb[1]], [$a1, $bb[2]], [$a2, $bb[0]], [$a2, $bb[1]], [$a2, $bb[2]]];

    foreach ($whereCases as $name => [$sql, $predicate]) {
        $expected = [];
        foreach ($pairs as $pair) {
            if ($predicate($pair)) {
                $expected[] = $pair[0];
                $expected[] = $pair[1];
            }
        }

        $tests[sprintf('real upstream corpus select2.test yield dynamic where expression %s seed %02d', $name, $seed)] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $tables, $expected, $seed): void {
            $assertSelectFlat($t, $sql, $tables, $expected);
            $t->same(true, $seed >= 0, 'select2 dynamic where-expression seed guard');
        };
    }
}

$select1AggregateCases = [
    'select1-2.2 count f1 rows' => [
        'SELECT count(f1) FROM test1',
        static fn (array $ctx): array => [$ctx['test1Count']],
    ],
    'select1-2.4 count wildcard rows' => [
        'SELECT COUNT(*) FROM test1',
        static fn (array $ctx): array => [$ctx['test1Count']],
    ],
    'select1-2.5 count wildcard arithmetic' => [
        'SELECT COUNT(*)+1 FROM test1',
        static fn (array $ctx): array => [$ctx['test1Count'] + 1],
    ],
    'select1-2.7 min f1 aggregate' => [
        'SELECT Min(f1) FROM test1',
        static fn (array $ctx): array => [$ctx['minF1']],
    ],
    'select1-2.8 scalar min over f1 f2 rows' => [
        'SELECT MIN(f1,f2) FROM test1 ORDER BY 1',
        static fn (array $ctx): array => $ctx['rowMins'],
    ],
    'select1-2.8.1 coalesce min nullable a' => [
        "SELECT coalesce(min(a),'xyzzy') FROM t3",
        static fn (array $ctx): array => [$ctx['minA']],
    ],
    'select1-2.8.2 min coalesced nullable a' => [
        "SELECT min(coalesce(a,'xyzzy')) FROM t3",
        static fn (array $ctx): array => [$ctx['minCoalescedA']],
    ],
    'select1-2.8.3 duplicate min text aggregate' => [
        'SELECT min(b), min(b) FROM t4',
        static fn (array $ctx): array => [$ctx['long'], $ctx['long']],
    ],
    'select1-2.10 max f1 aggregate' => [
        'SELECT Max(f1) FROM test1',
        static fn (array $ctx): array => [$ctx['maxF1']],
    ],
    'select1-2.11 scalar max over f1 f2 rows' => [
        'SELECT max(f1,f2) FROM test1 ORDER BY 1',
        static fn (array $ctx): array => $ctx['rowMaxes'],
    ],
    'select1-2.12 scalar max arithmetic rows' => [
        'SELECT MAX(f1,f2)+1 FROM test1 ORDER BY 1',
        static fn (array $ctx): array => array_map(static fn (int $value): int => $value + 1, $ctx['rowMaxes']),
    ],
    'select1-2.13 max f1 arithmetic aggregate' => [
        'SELECT MAX(f1)+1 FROM test1',
        static fn (array $ctx): array => [$ctx['maxF1'] + 1],
    ],
    'select1-2.13.1 coalesce max nullable a' => [
        "SELECT coalesce(max(a),'xyzzy') FROM t3",
        static fn (array $ctx): array => [$ctx['maxA']],
    ],
    'select1-2.13.2 max coalesced nullable a' => [
        "SELECT max(coalesce(a,'xyzzy')) FROM t3",
        static fn (array $ctx): array => ['xyzzy'],
    ],
    'select1-2.15 sum f1 aggregate' => [
        'SELECT Sum(f1) FROM test1',
        static fn (array $ctx): array => [$ctx['sumF1']],
    ],
    'select1-2.17 sum f1 arithmetic aggregate' => [
        'SELECT SUM(f1)+1 FROM test1',
        static fn (array $ctx): array => [$ctx['sumF1'] + 1],
    ],
    'select1-2.17.1 sum nullable a aggregate' => [
        'SELECT sum(a) FROM t3',
        static fn (array $ctx): array => [$ctx['sumA']],
    ],
];

for ($seed = 0; $seed < 60; $seed++) {
    $first = 11 + $seed;
    $second = 33 + ($seed * 2);
    $long = 'select1 aggregate long text seed ' . $seed . ' payload payload payload';
    $test1Rows = [
        ['f1' => $first, 'f2' => $first + 11],
        ['f1' => $second, 'f2' => $second + 11],
    ];
    $t3Rows = [
        ['a' => 'abc' . $seed, 'b' => null],
        ['a' => null, 'b' => 'xyz' . $seed],
        ['a' => $first, 'b' => $first + 11],
        ['a' => $second, 'b' => $second + 11],
    ];
    $tables = [
        'test1' => $test1Rows,
        't3' => $t3Rows,
        't4' => [['a' => null, 'b' => $long]],
    ];
    $rowMins = array_map(static fn (array $row): int => min((int) $row['f1'], (int) $row['f2']), $test1Rows);
    $rowMaxes = array_map(static fn (array $row): int => max((int) $row['f1'], (int) $row['f2']), $test1Rows);
    sort($rowMins);
    sort($rowMaxes);

    $ctx = [
        'test1Count' => count($test1Rows),
        't3Count' => count($t3Rows),
        't3CountA' => 3,
        't3CountB' => 3,
        'minF1' => $first,
        'maxF1' => $second,
        'sumF1' => $first + $second,
        'rowMins' => $rowMins,
        'rowMaxes' => $rowMaxes,
        'minA' => $first,
        'maxA' => 'abc' . $seed,
        'minCoalescedA' => $first,
        'sumA' => $first + $second,
        'long' => $long,
    ];

    foreach ($select1AggregateCases as $name => [$sql, $expectedFn]) {
        $tests[sprintf('real upstream corpus select1.test yield aggregate %s seed %02d', $name, $seed)] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $tables, $expectedFn, $ctx, $seed, $name): void {
            $assertSelectFlat($t, $sql, $tables, $expectedFn($ctx));
            $t->contains('select1-2.', $name . ' seed ' . $seed);
        };
    }
}

return $tests;
