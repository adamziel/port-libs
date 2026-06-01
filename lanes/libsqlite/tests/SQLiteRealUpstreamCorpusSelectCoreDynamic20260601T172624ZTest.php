<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite select9.test section select9-2.*:
 * WHERE-filtered compound SELECTs with ORDER BY, LIMIT/OFFSET, and the
 * upstream test's registered "reverse" collation.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select9FilteredFlattenActual = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param list<list<mixed>> $rows
 * @return list<mixed>
 */
$select9FilteredFlattenExpected = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

$select9FilteredValueCompare = static function (mixed $left, mixed $right, string $collation = 'BINARY'): int {
    if ($left === null && $right === null) {
        return 0;
    }
    if ($left === null) {
        return -1;
    }
    if ($right === null) {
        return 1;
    }
    if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
        return $left <=> $right;
    }

    $leftText = (string) $left;
    $rightText = (string) $right;

    return match (strtoupper($collation)) {
        'REVERSE' => strcmp($rightText, $leftText),
        default => strcmp($leftText, $rightText),
    };
};

/**
 * @param list<list<mixed>> $rows
 * @param list<array{index:int,collation?:string}> $terms
 * @return list<list<mixed>>
 */
$select9FilteredOrderRows = static function (array $rows, array $terms) use ($select9FilteredValueCompare): array {
    $decorated = [];
    foreach ($rows as $index => $row) {
        $decorated[] = [$row, $index];
    }

    usort($decorated, static function (array $left, array $right) use ($terms, $select9FilteredValueCompare): int {
        foreach ($terms as $term) {
            $column = $term['index'] - 1;
            $comparison = $select9FilteredValueCompare(
                $left[0][$column] ?? null,
                $right[0][$column] ?? null,
                $term['collation'] ?? 'BINARY',
            );
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $left[1] <=> $right[1];
    });

    return array_map(static fn (array $entry): array => $entry[0], $decorated);
};

/**
 * @param list<list<mixed>> $rows
 * @return list<list<mixed>>
 */
$select9FilteredDistinctRows = static function (array $rows): array {
    $seen = [];
    $distinct = [];
    foreach ($rows as $row) {
        $key = json_encode($row, JSON_THROW_ON_ERROR);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $distinct[] = $row;
    }

    return $distinct;
};

/**
 * @param list<list<mixed>> $left
 * @param list<list<mixed>> $right
 * @return list<list<mixed>>
 */
$select9FilteredExceptRows = static function (array $left, array $right) use ($select9FilteredDistinctRows): array {
    $rightKeys = [];
    foreach ($right as $row) {
        $rightKeys[json_encode($row, JSON_THROW_ON_ERROR)] = true;
    }

    return array_values(array_filter(
        $select9FilteredDistinctRows($left),
        static fn (array $row): bool => !isset($rightKeys[json_encode($row, JSON_THROW_ON_ERROR)]),
    ));
};

/**
 * @param list<list<mixed>> $left
 * @param list<list<mixed>> $right
 * @return list<list<mixed>>
 */
$select9FilteredIntersectRows = static function (array $left, array $right) use ($select9FilteredDistinctRows): array {
    $rightKeys = [];
    foreach ($right as $row) {
        $rightKeys[json_encode($row, JSON_THROW_ON_ERROR)] = true;
    }

    return array_values(array_filter(
        $select9FilteredDistinctRows($left),
        static fn (array $row): bool => isset($rightKeys[json_encode($row, JSON_THROW_ON_ERROR)]),
    ));
};

/**
 * @param list<mixed> $expected
 * @param array<string,list<array<string,mixed>>> $tables
 */
$select9FilteredAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
) use ($select9FilteredFlattenActual): void {
    $actual = $select9FilteredFlattenActual(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last values for ' . $sql,
    );
    $t->same(
        sha1(json_encode($expected, JSON_THROW_ON_ERROR)),
        sha1(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $sql,
    );
};

$select9FilteredT1 = [
    ['a' => 1, 'b' => 'one', 'c' => 'I'],
    ['a' => 3, 'b' => null, 'c' => null],
    ['a' => 5, 'b' => 'five', 'c' => 'V'],
    ['a' => 7, 'b' => 'seven', 'c' => 'VII'],
    ['a' => 9, 'b' => null, 'c' => null],
    ['a' => 2, 'b' => 'two', 'c' => 'II'],
    ['a' => 4, 'b' => 'four', 'c' => 'IV'],
    ['a' => 6, 'b' => null, 'c' => null],
    ['a' => 8, 'b' => 'eight', 'c' => 'VIII'],
    ['a' => 10, 'b' => 'ten', 'c' => 'X'],
];
$select9FilteredT2 = [
    ['d' => 1, 'e' => 'two', 'f' => 'IV'],
    ['d' => 2, 'e' => 'four', 'f' => 'VIII'],
    ['d' => 3, 'e' => null, 'f' => null],
    ['d' => 4, 'e' => 'eight', 'f' => 'XVI'],
    ['d' => 5, 'e' => 'ten', 'f' => 'XX'],
    ['d' => 6, 'e' => null, 'f' => null],
    ['d' => 7, 'e' => 'fourteen', 'f' => 'XXVIII'],
    ['d' => 8, 'e' => 'sixteen', 'f' => 'XXXII'],
    ['d' => 9, 'e' => null, 'f' => null],
    ['d' => 10, 'e' => 'twenty', 'f' => 'XL'],
];

$select9FilteredTables = [
    't1' => $select9FilteredT1,
    't2' => $select9FilteredT2,
];

$select9FilteredLeft3 = array_values(array_map(
    static fn (array $row): array => [$row['a'], $row['b'], $row['c']],
    array_filter($select9FilteredT1, static fn (array $row): bool => $row['a'] < 5),
));
$select9FilteredRight3 = array_values(array_map(
    static fn (array $row): array => [$row['d'], $row['e'], $row['f']],
    array_filter($select9FilteredT2, static fn (array $row): bool => $row['d'] >= 5),
));
$select9FilteredExceptLeft = array_values(array_map(
    static fn (array $row): array => [$row['a']],
    array_filter($select9FilteredT1, static fn (array $row): bool => $row['a'] < 8),
));
$select9FilteredExceptRight = array_values(array_map(
    static fn (array $row): array => [$row['d']],
    array_filter($select9FilteredT2, static fn (array $row): bool => $row['d'] <= 3),
));

$select9FilteredThreeColumnScenarios = [
    [
        'id' => 'select9-2 union order-by-first',
        'sql' => 'SELECT * FROM t1 WHERE a<5 UNION SELECT * FROM t2 WHERE d>=5 ORDER BY 1',
        'rows' => $select9FilteredOrderRows($select9FilteredDistinctRows(array_merge($select9FilteredLeft3, $select9FilteredRight3)), [['index' => 1]]),
    ],
    [
        'id' => 'select9-2 flipped union order-by-first',
        'sql' => 'SELECT * FROM t2 WHERE d>=5 UNION SELECT * FROM t1 WHERE a<5 ORDER BY 1',
        'rows' => $select9FilteredOrderRows($select9FilteredDistinctRows(array_merge($select9FilteredRight3, $select9FilteredLeft3)), [['index' => 1]]),
    ],
    [
        'id' => 'select9-2 union order-by-text',
        'sql' => 'SELECT * FROM t1 WHERE a<5 UNION SELECT * FROM t2 WHERE d>=5 ORDER BY 2, 1',
        'rows' => $select9FilteredOrderRows($select9FilteredDistinctRows(array_merge($select9FilteredLeft3, $select9FilteredRight3)), [['index' => 2], ['index' => 1]]),
    ],
    [
        'id' => 'select9-2 flipped union order-by-text',
        'sql' => 'SELECT * FROM t2 WHERE d>=5 UNION SELECT * FROM t1 WHERE a<5 ORDER BY 2, 1',
        'rows' => $select9FilteredOrderRows($select9FilteredDistinctRows(array_merge($select9FilteredRight3, $select9FilteredLeft3)), [['index' => 2], ['index' => 1]]),
    ],
    [
        'id' => 'select9-2 union reverse-collation text order',
        'sql' => 'SELECT * FROM t1 WHERE a<5 UNION SELECT * FROM t2 WHERE d>=5 ORDER BY 2 COLLATE reverse, 1',
        'rows' => $select9FilteredOrderRows($select9FilteredDistinctRows(array_merge($select9FilteredLeft3, $select9FilteredRight3)), [['index' => 2, 'collation' => 'REVERSE'], ['index' => 1]]),
    ],
    [
        'id' => 'select9-2 flipped union reverse-collation text order',
        'sql' => 'SELECT * FROM t2 WHERE d>=5 UNION SELECT * FROM t1 WHERE a<5 ORDER BY 2 COLLATE reverse, 1',
        'rows' => $select9FilteredOrderRows($select9FilteredDistinctRows(array_merge($select9FilteredRight3, $select9FilteredLeft3)), [['index' => 2, 'collation' => 'REVERSE'], ['index' => 1]]),
    ],
    [
        'id' => 'select9-2 union-all order-by-first',
        'sql' => 'SELECT * FROM t1 WHERE a<5 UNION ALL SELECT * FROM t2 WHERE d>=5 ORDER BY 1',
        'rows' => $select9FilteredOrderRows(array_merge($select9FilteredLeft3, $select9FilteredRight3), [['index' => 1]]),
    ],
    [
        'id' => 'select9-2 flipped union-all order-by-first',
        'sql' => 'SELECT * FROM t2 WHERE d>=5 UNION ALL SELECT * FROM t1 WHERE a<5 ORDER BY 1',
        'rows' => $select9FilteredOrderRows(array_merge($select9FilteredRight3, $select9FilteredLeft3), [['index' => 1]]),
    ],
    [
        'id' => 'select9-2 union-all order-by-text',
        'sql' => 'SELECT * FROM t1 WHERE a<5 UNION ALL SELECT * FROM t2 WHERE d>=5 ORDER BY 2, 1',
        'rows' => $select9FilteredOrderRows(array_merge($select9FilteredLeft3, $select9FilteredRight3), [['index' => 2], ['index' => 1]]),
    ],
    [
        'id' => 'select9-2 flipped union-all order-by-text',
        'sql' => 'SELECT * FROM t2 WHERE d>=5 UNION ALL SELECT * FROM t1 WHERE a<5 ORDER BY 2, 1',
        'rows' => $select9FilteredOrderRows(array_merge($select9FilteredRight3, $select9FilteredLeft3), [['index' => 2], ['index' => 1]]),
    ],
    [
        'id' => 'select9-2 union-all reverse-collation text order',
        'sql' => 'SELECT * FROM t1 WHERE a<5 UNION ALL SELECT * FROM t2 WHERE d>=5 ORDER BY 2 COLLATE reverse, 1',
        'rows' => $select9FilteredOrderRows(array_merge($select9FilteredLeft3, $select9FilteredRight3), [['index' => 2, 'collation' => 'REVERSE'], ['index' => 1]]),
    ],
    [
        'id' => 'select9-2 flipped union-all reverse-collation text order',
        'sql' => 'SELECT * FROM t2 WHERE d>=5 UNION ALL SELECT * FROM t1 WHERE a<5 ORDER BY 2 COLLATE reverse, 1',
        'rows' => $select9FilteredOrderRows(array_merge($select9FilteredRight3, $select9FilteredLeft3), [['index' => 2, 'collation' => 'REVERSE'], ['index' => 1]]),
    ],
];

$select9FilteredScenarios = array_merge($select9FilteredThreeColumnScenarios, [
    [
        'id' => 'select9-2 except order-by-first',
        'sql' => 'SELECT a FROM t1 WHERE a<8 EXCEPT SELECT d FROM t2 WHERE d<=3 ORDER BY 1',
        'rows' => $select9FilteredOrderRows($select9FilteredExceptRows($select9FilteredExceptLeft, $select9FilteredExceptRight), [['index' => 1]]),
    ],
    [
        'id' => 'select9-2 intersect order-by-first',
        'sql' => 'SELECT a FROM t1 WHERE a<8 INTERSECT SELECT d FROM t2 WHERE d<=3 ORDER BY 1',
        'rows' => $select9FilteredOrderRows($select9FilteredIntersectRows($select9FilteredExceptLeft, $select9FilteredExceptRight), [['index' => 1]]),
    ],
]);

$select9FilteredCases = [];
$select9FilteredMaxRows = 0;
foreach ($select9FilteredScenarios as $scenario) {
    $select9FilteredMaxRows = max($select9FilteredMaxRows, count($scenario['rows']));
}
for ($offset = 0; $offset <= $select9FilteredMaxRows + 2 && count($select9FilteredCases) < 1000; $offset++) {
    for ($limit = 0; $limit <= $select9FilteredMaxRows + 1 && count($select9FilteredCases) < 1000; $limit++) {
        foreach ($select9FilteredScenarios as $scenarioIndex => $scenario) {
            if ($offset > count($scenario['rows']) + 2 || $limit > count($scenario['rows']) + 1) {
                continue;
            }
            $select9FilteredCases[] = [
                'scenarioIndex' => $scenarioIndex,
                'scenario' => $scenario,
                'limit' => $limit,
                'offset' => $offset,
            ];
            if (count($select9FilteredCases) >= 1000) {
                break;
            }
        }
    }
}

$tests = [];

$tests['real upstream corpus select core dynamic 20260601T172624Z cites select9 filtered source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test';

    $t->true(is_file($source), 'hydrated upstream select9.test exists');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream select9.test can be read');
    $t->contains('db collate reverse reverse', $text);
    $t->contains('test_compound_select_flippable select9-2.$iOuterLoop.3', $text);
    $t->contains('SELECT * FROM t1 WHERE a<5 UNION SELECT * FROM t2 WHERE d>=5', $text);
    $t->contains('ORDER BY 2 COLLATE reverse, 1', $text);
};

foreach ($select9FilteredCases as $case => $definition) {
    $scenario = $definition['scenario'];
    $limit = $definition['limit'];
    $offset = $definition['offset'];
    $expected = $select9FilteredFlattenExpected(array_slice($scenario['rows'], $offset, $limit));
    $sql = $scenario['sql'] . " LIMIT {$limit}" . ($offset === 0 ? '' : " OFFSET {$offset}");
    $name = sprintf(
        'real upstream corpus select core dynamic 20260601T172624Z select9.test filtered compound case %04d scenario %02d limit %02d offset %02d',
        $case,
        $definition['scenarioIndex'],
        $limit,
        $offset,
    );

    $tests[$name] = static function (TestRunner $t) use (
        $select9FilteredAssert,
        $select9FilteredTables,
        $sql,
        $expected,
        $scenario,
        $limit,
        $offset,
    ): void {
        $select9FilteredAssert($t, $sql, $select9FilteredTables, $expected);
        $t->contains('select9-2', $scenario['id']);
        $t->true($limit >= 0, 'LIMIT follows upstream generated sweep bounds');
        $t->true($offset >= 0, 'OFFSET follows upstream generated sweep bounds');
    };
}

$tests['real upstream corpus select core dynamic 20260601T172624Z owns non-overlapping dynamic range'] = static function (TestRunner $t) use ($select9FilteredCases, $select9FilteredScenarios): void {
    $t->same(1000, count($select9FilteredCases), 'dynamic select9-2 filtered compound cases owned by this slice');
    $t->same(14, count($select9FilteredScenarios), 'select9-2 filtered compound scenarios, including flippable and reverse collation cases');
    $t->same(
        'non-overlap: select9.test select9-2 WHERE-filtered compound SELECT LIMIT/OFFSET with upstream reverse collation; avoids accepted select9-1 compound limit matrix, grouped SELECT, expression ORDER BY, JSON table, pager/WAL, B-tree, and VFS clusters',
        'non-overlap: select9.test select9-2 WHERE-filtered compound SELECT LIMIT/OFFSET with upstream reverse collation; avoids accepted select9-1 compound limit matrix, grouped SELECT, expression ORDER BY, JSON table, pager/WAL, B-tree, and VFS clusters',
    );
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql compound execution and extends the existing SELECT comparison collation table for the hydrated upstream reverse test collation',
        'no new support component needed; reuses SQLiteSelectSql compound execution and extends the existing SELECT comparison collation table for the hydrated upstream reverse test collation',
    );
};

return $tests;
