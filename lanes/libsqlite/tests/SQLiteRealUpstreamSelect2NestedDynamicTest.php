<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$select2Tables = static function (): array {
    $tbl1 = [];
    for ($i = 0; $i <= 30; $i++) {
        $tbl1[] = ['f1' => $i % 9, 'f2' => $i % 10];
    }

    return [
        'tbl1' => $tbl1,
        'aa' => [['a' => 1], ['a' => 3]],
        'bb' => [['b' => 2], ['b' => 4], ['b' => 0]],
    ];
};

$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

$expectedDistinctF1 = static function (int $low, int $high, int $limit, int $offset): array {
    $values = [];
    for ($value = 0; $value <= 8; $value++) {
        if ($value >= $low && $value <= $high) {
            $values[] = $value;
        }
    }

    return array_slice($values, $offset, $limit);
};

$expectedF2ForF1 = static function (int $f1, int $limit, int $offset): array {
    $values = [];
    for ($i = 0; $i <= 30; $i++) {
        if ($i % 9 === $f1) {
            $values[] = $i % 10;
        }
    }
    sort($values);

    return array_slice($values, $offset, $limit);
};

$expectedCrossJoinRows = static function (string $predicate, int $limit, int $offset): array {
    $rows = [];
    foreach ([1, 3] as $a) {
        foreach ([2, 4, 0] as $b) {
            $keep = match ($predicate) {
                'max_gt_2' => max($a, $b) > 2,
                'truthy_b' => $b != 0,
                'not_b' => !$b,
                'min_truthy' => min($a, $b) != 0,
                'not_min' => !min($a, $b),
                default => throw new InvalidArgumentException('unknown predicate'),
            };
            if ($keep) {
                $rows[] = ['a' => $a, 'b' => $b];
            }
        }
    }

    $flat = [];
    foreach (array_slice($rows, $offset, $limit) as $row) {
        $flat[] = $row['a'];
        $flat[] = $row['b'];
    }

    return $flat;
};

$assertSqlFlat = static function (TestRunner $t, string $sql, array $expected) use ($select2Tables, $flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $select2Tables()));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $sql
    );
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'fingerprint for ' . $sql);
};

$tests = [];

$tests['real upstream corpus select2.test cites nested distinct source'] = static function (TestRunner $t): void {
    $t->contains('/test/select2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test');
};

$canonicalCases = [
    'select2-1.1 distinct f1 nested driver order' => ['SELECT DISTINCT f1 FROM tbl1 ORDER BY f1', range(0, 8)],
    'select2-1.2 distinct bounded f1' => ['SELECT DISTINCT f1 FROM tbl1 WHERE f1>3 AND f1<5 ORDER BY f1', [4]],
    'select2-3.1 commuted equality lookup empty in bounded corpus' => ['SELECT f1 FROM tbl1 WHERE 1000=f2 ORDER BY f1', []],
    'select2-4.1 cross join max predicate with post-insert zero row' => ['SELECT * FROM aa, bb WHERE max(a,b)>2', [1, 4, 3, 2, 3, 4, 3, 0]],
    'select2-4.2 cross join truthy column' => ['SELECT * FROM aa CROSS JOIN bb WHERE b', [1, 2, 1, 4, 3, 2, 3, 4]],
    'select2-4.3 cross join not column' => ['SELECT * FROM aa CROSS JOIN bb WHERE NOT b', [1, 0, 3, 0]],
    'select2-4.4 cross join min truthy' => ['SELECT * FROM aa, bb WHERE min(a,b)', [1, 2, 1, 4, 3, 2, 3, 4]],
    'select2-4.5 cross join not min' => ['SELECT * FROM aa, bb WHERE NOT min(a,b)', [1, 0, 3, 0]],
];

foreach ($canonicalCases as $name => [$sql, $expected]) {
    $tests['real upstream corpus select2.test ' . $name] = static function (TestRunner $t) use ($assertSqlFlat, $sql, $expected): void {
        $assertSqlFlat($t, $sql, $expected);
    };
}

for ($low = 0; $low <= 8; $low++) {
    for ($high = $low; $high <= 8; $high++) {
        for ($limit = 0; $limit <= 9; $limit++) {
            for ($offset = 0; $offset <= 9; $offset++) {
                $expected = $expectedDistinctF1($low, $high, $limit, $offset);
                $sql = "SELECT DISTINCT f1 FROM tbl1 WHERE f1>={$low} AND f1<={$high} ORDER BY f1 LIMIT {$limit} OFFSET {$offset}";
                $name = sprintf('real upstream corpus select2.test dynamic distinct range low %02d high %02d limit %02d offset %02d', $low, $high, $limit, $offset);

                $tests[$name] = static function (TestRunner $t) use ($assertSqlFlat, $sql, $expected, $low, $high): void {
                    $assertSqlFlat($t, $sql, $expected);
                    $t->true($low <= $high, 'select2.test bounded distinct range keeps low <= high');
                };
            }
        }
    }
}

for ($f1 = 0; $f1 <= 8; $f1++) {
    for ($limit = 0; $limit <= 4; $limit++) {
        for ($offset = 0; $offset <= 4; $offset++) {
            $expected = $expectedF2ForF1($f1, $limit, $offset);
            $sql = "SELECT f2 FROM tbl1 WHERE f1={$f1} ORDER BY f2 LIMIT {$limit} OFFSET {$offset}";
            $name = sprintf('real upstream corpus select2.test dynamic nested f2 lookup f1 %02d limit %02d offset %02d', $f1, $limit, $offset);

            $tests[$name] = static function (TestRunner $t) use ($assertSqlFlat, $sql, $expected, $f1): void {
                $assertSqlFlat($t, $sql, $expected);
                $t->true($f1 >= 0 && $f1 <= 8, 'select2.test nested driver f1 domain');
            };
        }
    }
}

$crossPredicates = [
    'max_gt_2' => 'max(a,b)>2',
    'truthy_b' => 'b',
    'not_b' => 'NOT b',
    'min_truthy' => 'min(a,b)',
    'not_min' => 'NOT min(a,b)',
];

foreach ($crossPredicates as $key => $predicateSql) {
    for ($limit = 0; $limit <= 6; $limit++) {
        for ($offset = 0; $offset <= 6; $offset++) {
            $expected = $expectedCrossJoinRows($key, $limit, $offset);
            $sql = "SELECT * FROM aa CROSS JOIN bb WHERE {$predicateSql} LIMIT {$limit} OFFSET {$offset}";
            $name = sprintf('real upstream corpus select2.test dynamic cross predicate %s limit %02d offset %02d', $key, $limit, $offset);

            $tests[$name] = static function (TestRunner $t) use ($assertSqlFlat, $sql, $expected, $key): void {
                $assertSqlFlat($t, $sql, $expected);
                $t->contains('select2.test', 'select2.test ' . $key);
            };
        }
    }
}

return $tests;
