<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select5Tables = static function (): array {
    $t1 = [];
    for ($i = 1; $i < 32; $i++) {
        $j = 0;
        while ((1 << $j) < $i) {
            $j++;
        }
        $t1[] = ['x' => 32 - $i, 'y' => 10 - $j];
    }

    return [
        't1' => $t1,
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
 * @param list<mixed> $expected
 */
$addSelect5Case = static function (array &$tests, string $name, string $sql, array $expected) use ($select5Tables, $flattenRows): void {
    $tests['real upstream corpus select5.test ' . $name] = static function (TestRunner $t) use ($select5Tables, $flattenRows, $sql, $expected, $name): void {
        $actual = $flattenRows(SQLiteSelectSql::execute($sql, $select5Tables()));

        $t->same($expected, $actual, $sql);
        $t->same(count($expected), count($actual), 'flat value count for ' . $name);
        $t->same(
            $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
            $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
            'first/last value guard for ' . $name,
        );
        $t->same(
            md5(json_encode($expected, JSON_THROW_ON_ERROR)),
            md5(json_encode($actual, JSON_THROW_ON_ERROR)),
            'flat result fingerprint for ' . $name,
        );
        $t->contains('select5-', $name);
        $t->contains('SELECT', $sql);
    };
};

/**
 * @param list<array<string,mixed>> $rows
 * @return array<int,int>
 */
$countByY = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $y = (int) $row['y'];
        $groups[$y] = ($groups[$y] ?? 0) + 1;
    }
    ksort($groups);

    return $groups;
};

/**
 * @param array<int,int> $groups
 * @return list<array{y:int,c:int}>
 */
$groupRows = static function (array $groups): array {
    $rows = [];
    foreach ($groups as $y => $count) {
        $rows[] = ['y' => (int) $y, 'c' => $count];
    }

    return $rows;
};

/**
 * @param list<array{y:int,c:int}> $rows
 * @return list<mixed>
 */
$flatYCountRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        $flat[] = $row['y'];
        $flat[] = $row['c'];
    }

    return $flat;
};

/**
 * @param list<array{y:int,c:int}> $rows
 * @return list<mixed>
 */
$flatCountYRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        $flat[] = $row['c'];
        $flat[] = $row['y'];
    }

    return $flat;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flatSelected = static function (array $rows, array $columns): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($columns as $column) {
            $flat[] = $row[$column];
        }
    }

    return $flat;
};

$tests = [];
$tables = $select5Tables();
$t1Groups = $countByY($tables['t1']);
$canonicalGroupRows = $groupRows($t1Groups);
$byCountThenY = $canonicalGroupRows;
usort($byCountThenY, static fn (array $a, array $b): int => ($a['c'] <=> $b['c']) ?: ($a['y'] <=> $b['y']));

$addSelect5Case(
    $tests,
    'select5-1.0 distinct y ordered',
    'SELECT DISTINCT y FROM t1 ORDER BY y',
    array_keys($t1Groups),
);
$addSelect5Case(
    $tests,
    'select5-1.1 grouped count by y ordered',
    'SELECT y, count(*) FROM t1 GROUP BY y ORDER BY y',
    $flatYCountRows($canonicalGroupRows),
);
$addSelect5Case(
    $tests,
    'select5-1.2 grouped count ordered by aggregate',
    'SELECT y, count(*) FROM t1 GROUP BY y ORDER BY count(*), y',
    $flatYCountRows($byCountThenY),
);
$addSelect5Case(
    $tests,
    'select5-1.3 grouped count projected first',
    'SELECT count(*), y FROM t1 GROUP BY y ORDER BY count(*), y',
    $flatCountYRows($byCountThenY),
);

foreach (range(0, 12) as $minimumCount) {
    foreach (range(0, 8) as $offset) {
        foreach ([1, 2, 3, 4, 5, 6, -1] as $limit) {
            $rows = array_values(array_filter(
                $canonicalGroupRows,
                static fn (array $row): bool => $row['c'] >= $minimumCount,
            ));
            usort($rows, static fn (array $a, array $b): int => ($a['c'] <=> $b['c']) ?: ($a['y'] <=> $b['y']));
            $slice = $limit < 0 ? array_slice($rows, $offset) : array_slice($rows, $offset, $limit);
            $addSelect5Case(
                $tests,
                sprintf('select5-2.3 dynamic having count ge %02d limit %d offset %02d', $minimumCount, $limit, $offset),
                "SELECT y, count(*) FROM t1 GROUP BY y HAVING count(*)>={$minimumCount} ORDER BY count(*), y LIMIT {$limit} OFFSET {$offset}",
                $flatYCountRows($slice),
            );
        }
    }
}

foreach (range(1, 31) as $upperX) {
    $rows = array_values(array_filter(
        $tables['t1'],
        static fn (array $row): bool => $row['x'] <= $upperX,
    ));
    $groups = $groupRows($countByY($rows));
    $groups = array_values(array_filter($groups, static fn (array $row): bool => $row['y'] <= 10));
    $addSelect5Case(
        $tests,
        sprintf('select5-3.1 dynamic x upper bound %02d', $upperX),
        "SELECT y, count(*) FROM t1 WHERE x<={$upperX} GROUP BY y HAVING y<=10 ORDER BY y",
        $flatYCountRows($groups),
    );
}

foreach (range(100, 119) as $threshold) {
    $addSelect5Case(
        $tests,
        sprintf('select5-4.2 empty aggregate count threshold %03d', $threshold),
        "SELECT count(x) FROM t1 WHERE x>{$threshold}",
        [0],
    );
}

$t2GroupRows = [
    ['a' => 1, 'b' => 2, 'c' => 3],
    ['a' => 1, 'b' => 4, 'c' => 5],
    ['a' => 6, 'b' => 4, 'c' => 7],
];
$addSelect5Case($tests, 'select5-5.2 group by a', 'SELECT a FROM t2 GROUP BY a', [1, 6]);
$addSelect5Case($tests, 'select5-5.3 where then group by a', 'SELECT a FROM t2 WHERE a>2 GROUP BY a', [6]);
$addSelect5Case($tests, 'select5-5.4 group by a b', 'SELECT a, b FROM t2 GROUP BY a, b', [1, 2, 1, 4, 6, 4]);
$addSelect5Case($tests, 'select5-5.11 expression group by b times a', 'SELECT max(c), b*a, b, a FROM t2 GROUP BY b*a, b, a', [3, 2, 2, 1, 5, 4, 4, 1, 7, 24, 4, 6]);

foreach (range(0, 49) as $variant) {
    $limit = ($variant % 5) + 1;
    $offset = intdiv($variant, 5) % 3;
    $rows = $t2GroupRows;
    usort($rows, static fn (array $a, array $b): int => ($a['a'] <=> $b['a']) ?: ($a['b'] <=> $b['b']));
    $slice = array_slice($rows, $offset, $limit);
    $addSelect5Case(
        $tests,
        sprintf('select5-5.x dynamic grouped t2 limit %02d offset %02d', $limit, $offset),
        "SELECT a, b FROM t2 GROUP BY a, b ORDER BY a, b LIMIT {$limit} OFFSET {$offset}",
        $flatSelected($slice, ['a', 'b']),
    );
}

$addSelect5Case(
    $tests,
    'select5-6.1 nulls group equal',
    'SELECT count(x), y FROM t3 GROUP BY y ORDER BY 1',
    [1, 4, 2, null],
);
$addSelect5Case(
    $tests,
    'select5-7.2 null group count by y',
    'SELECT count(*), count(x) AS cnt FROM t4 GROUP BY y ORDER BY cnt',
    [1, 1, 1, 1, 1, 1, 5, 5],
);

$t8a = $tables['t8a'];
$t8b = $tables['t8b'];
foreach ([
    ['select5-8.1 rowid equality', 'b=t8b.rowid', true],
    ['select5-8.2 unary rowid equality', 'b=+t8b.rowid', true],
    ['select5-8.5 inequality join', 'b<x', false],
] as [$caseName, $predicate, $equality]) {
    $counts = [];
    foreach ($t8a as $left) {
        foreach ($t8b as $right) {
            $match = $equality
                ? $left['b'] !== null && $left['b'] === $right['rowid']
                : $left['b'] !== null && $left['b'] < $right['x'];
            if ($match) {
                $counts[$left['a']] = ($counts[$left['a']] ?? 0) + 1;
            }
        }
    }
    ksort($counts);
    $expected = [];
    foreach ($counts as $a => $count) {
        $expected[] = $a;
        $expected[] = $count;
    }
    $addSelect5Case(
        $tests,
        $caseName,
        "SELECT a, count(b) FROM t8a, t8b WHERE {$predicate} GROUP BY a ORDER BY a",
        $expected,
    );
}

foreach (range(0, 157) as $variant) {
    $useCountStar = ($variant % 2) === 0;
    $orderByCount = ($variant % 3) === 0;
    $limit = ($variant % 7) + 1;
    $offset = intdiv($variant, 7) % 4;
    $counts = ['one' => $useCountStar ? 9 : 6, 'two' => 3];
    $rows = [
        ['a' => 'one', 'c' => $counts['one']],
        ['a' => 'two', 'c' => $counts['two']],
    ];
    if ($orderByCount) {
        usort($rows, static fn (array $a, array $b): int => ($a['c'] <=> $b['c']) ?: strcmp($a['a'], $b['a']));
        $orderBy = '2';
    } else {
        usort($rows, static fn (array $a, array $b): int => strcmp($a['a'], $b['a']));
        $orderBy = 'a';
    }
    $slice = array_slice($rows, $offset, $limit);
    $expected = [];
    foreach ($slice as $row) {
        $expected[] = $row['a'];
        $expected[] = $row['c'];
    }
    $aggregate = $useCountStar ? 'count(*)' : 'count(b)';
    $addSelect5Case(
        $tests,
        sprintf('select5-8 dynamic cross join aggregate variant %03d', $variant),
        "SELECT a, {$aggregate} FROM t8a, t8b GROUP BY a ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}",
        $expected,
    );
}

return $tests;
